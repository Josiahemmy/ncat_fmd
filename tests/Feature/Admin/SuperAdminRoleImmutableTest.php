<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "The Super Admin role is immutable" was asserted in RolePolicy but nothing
 * covered it, and the policy is not what enforces it: `Gate::before` grants a
 * Super Admin every ability before any policy runs, so the policy clause is
 * skipped for precisely the account that could do the damage. RoleController
 * checks the name instead. These pin that behaviour to the claim.
 */
class SuperAdminRoleImmutableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    protected function superAdmin(): User
    {
        return tap(User::factory()->create())->assignRole('Super Admin');
    }

    public function test_a_super_admin_cannot_rename_the_super_admin_role(): void
    {
        $role = Role::where('name', 'Super Admin')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->put(route('admin.roles.update', $role->id), ['name' => 'Renamed'])
            ->assertSessionHas('error');

        $this->assertSame('Super Admin', $role->refresh()->name);
    }

    public function test_a_super_admin_cannot_delete_the_super_admin_role(): void
    {
        $role = Role::where('name', 'Super Admin')->firstOrFail();

        $this->actingAs($this->superAdmin())
            ->delete(route('admin.roles.destroy', $role->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['name' => 'Super Admin']);
    }

    /** The gate bypass is real, which is why the controller has to do the work. */
    public function test_the_gate_bypass_grants_a_super_admin_the_policy_ability_anyway(): void
    {
        $role = Role::where('name', 'Super Admin')->firstOrFail();

        $this->assertTrue(
            $this->superAdmin()->can('update', $role),
            'Gate::before no longer bypasses the policy; the RolePolicy comment needs revisiting.',
        );
    }
}
