<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    /** A user holding the given permission names. */
    protected function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    protected function superAdmin(): User
    {
        return User::factory()->create()->assignRole('Super Admin');
    }

    public function test_unprivileged_user_is_forbidden_from_admin_sections(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/roles')->assertForbidden();
        $this->actingAs($user)->get('/admin/activity')->assertForbidden();
    }

    public function test_permission_grants_access_to_the_matching_section(): void
    {
        $this->actingAs($this->userWith(['users.view']))->get('/admin/users')->assertOk();
        $this->actingAs($this->userWith(['audit.view']))->get('/admin/activity')->assertOk();
    }

    public function test_super_admin_can_reach_every_section(): void
    {
        $admin = $this->superAdmin();

        foreach (['/admin/users', '/admin/roles', '/admin/fleet', '/admin/stores', '/admin/ata-chapters', '/admin/counters', '/admin/activity'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_can_create_a_user_with_a_forced_temp_password(): void
    {
        $admin = $this->userWith(['users.view', 'users.manage']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Aisha Bello',
            'email' => 'aisha@ncatfmd.com.ng',
            'roles' => ['Storekeeper'],
        ])->assertSessionHas('generated_password');

        $user = User::where('email', 'aisha@ncatfmd.com.ng')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->password_change_required);
        $this->assertTrue($user->hasRole('Storekeeper'));
    }

    public function test_creating_a_user_requires_the_manage_permission(): void
    {
        $viewer = $this->userWith(['users.view']);

        $this->actingAs($viewer)->post('/admin/users', [
            'name' => 'X', 'email' => 'x@ncatfmd.com.ng',
        ])->assertForbidden();
    }

    public function test_admin_can_deactivate_a_user_and_reset_password(): void
    {
        $admin = $this->userWith(['users.view', 'users.manage']);
        $target = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->put("/admin/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'is_active' => false,
            'roles' => [],
        ])->assertRedirect();

        $this->assertFalse($target->fresh()->is_active);

        // Reset issues a fresh temp password + re-flags.
        $this->actingAs($admin)->post("/admin/users/{$target->id}/reset-password")
            ->assertSessionHas('generated_password');
        $this->assertTrue($target->fresh()->password_change_required);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->userWith(['users.view', 'users.manage']);

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'is_active' => false,
            'roles' => [],
        ])->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_role_permission_edits_take_effect(): void
    {
        $admin = $this->userWith(['roles.view', 'roles.manage']);
        $role = Role::create(['name' => 'Line Clerk', 'guard_name' => 'web']);

        $this->actingAs($admin)->put("/admin/roles/{$role->id}", [
            'name' => 'Line Clerk',
            'permissions' => ['stock.view', 'parts.view'],
        ])->assertRedirect();

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('stock.view'));
        $this->assertTrue($role->hasPermissionTo('parts.view'));

        // A user with the role now inherits the permission.
        $member = User::factory()->create()->assignRole('Line Clerk');
        $this->assertTrue($member->can('stock.view'));
    }

    public function test_super_admin_role_is_immutable(): void
    {
        $admin = $this->superAdmin();
        $role = Role::findByName('Super Admin', 'web');

        $this->actingAs($admin)->put("/admin/roles/{$role->id}", [
            'name' => 'Hacked', 'permissions' => [],
        ])->assertSessionHas('error');

        $this->assertNotNull(Role::findByName('Super Admin', 'web'));
    }

    public function test_role_with_users_cannot_be_deleted(): void
    {
        $admin = $this->userWith(['roles.view', 'roles.manage']);
        $role = Role::create(['name' => 'Temp Role', 'guard_name' => 'web']);
        User::factory()->create()->assignRole('Temp Role');

        $this->actingAs($admin)->delete("/admin/roles/{$role->id}")->assertSessionHas('error');
        $this->assertNotNull(Role::findByName('Temp Role', 'web'));
    }

    public function test_document_counter_update_is_audit_logged(): void
    {
        $admin = $this->userWith(['counters.view', 'counters.manage']);
        $this->seed(\Database\Seeders\DocumentCounterSeeder::class);
        $counter = \App\Models\DocumentCounter::where('series', 'siv')->first();

        $this->actingAs($admin)->put("/admin/counters/{$counter->id}", [
            'prefix' => '',
            'next_number' => 300,
            'padding' => 4,
            'confirmed' => true,
            'notes' => 'Confirmed with stores.',
        ])->assertRedirect();

        $this->assertSame(300, $counter->fresh()->next_number);
        $this->assertTrue(Activity::where('log_name', 'document_counter')->where('event', 'updated')->exists());
    }
}
