<?php

namespace Tests\Feature\Admin;

use App\Models\ApprovalLevel;
use App\Models\ApprovalWorkflow;
use App\Models\Requisition;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The Administration screen that configures the approval levels (spec §12.1). */
class ApprovalWorkflowAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    protected function admin(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('approvals.manage');

        return $user;
    }

    protected function workflow(): ApprovalWorkflow
    {
        return ApprovalWorkflow::where('document_type', 'requisition')->firstOrFail();
    }

    protected function save(User $as, array $levels)
    {
        return $this->actingAs($as)->put(
            route('admin.approvals.update', $this->workflow()->id),
            ['levels' => $levels],
        );
    }

    /**
     * Inertia XHR page request: the page object comes back as JSON, so props can
     * be asserted without the front-end build the blade root would need.
     */
    protected function page(User $as, string $uri)
    {
        return $this->actingAs($as)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ])->get($uri);
    }

    public function test_the_screen_needs_the_approvals_manage_permission(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Viewer'))
            ->get(route('admin.approvals.index'))->assertForbidden();

        $this->page($this->admin(), route('admin.approvals.index'))->assertOk();
    }

    public function test_the_screen_lists_the_seeded_default_level(): void
    {
        $this->page($this->admin(), route('admin.approvals.index'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Approvals/Index')
            ->assertJsonPath('props.levels.0.name', 'Approval')
            ->assertJsonPath('props.levels.0.binding_type', 'permission')
            ->assertJsonPath('props.levels.0.binding_value', 'requisitions.approve')
            ->assertJsonPath('props.inFlight', 0);
    }

    public function test_saving_adds_renames_and_orders_levels_in_one_pass(): void
    {
        Role::findOrCreate('HOD', 'web');
        $admin = $this->admin();
        $existing = $this->workflow()->levels()->firstOrFail();

        $this->save($admin, [
            ['name' => 'HOD Approval', 'binding_type' => 'role', 'binding_value' => 'HOD', 'is_active' => true],
            ['id' => $existing->id, 'name' => 'Stores Approval', 'binding_type' => 'permission', 'binding_value' => 'requisitions.approve', 'is_active' => true],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $levels = $this->workflow()->levels()->get();

        $this->assertCount(2, $levels);
        $this->assertSame([1, 2], $levels->pluck('sequence')->all());
        $this->assertSame(['HOD Approval', 'Stores Approval'], $levels->pluck('name')->all());
        $this->assertSame('HOD', $levels[0]->role_name);
        $this->assertNull($levels[0]->permission_name);
        // The renamed existing level keeps its identity, so its history still joins.
        $this->assertSame($existing->id, $levels[1]->id);
    }

    public function test_removing_a_level_from_the_list_deletes_it(): void
    {
        Role::findOrCreate('HOD', 'web');
        $admin = $this->admin();
        $workflow = $this->workflow();
        $extra = ApprovalLevel::create([
            'approval_workflow_id' => $workflow->id, 'sequence' => 2,
            'name' => 'HOD Approval', 'role_name' => 'HOD',
        ]);

        $this->save($admin, [[
            'id' => $workflow->levels()->where('sequence', 1)->value('id'),
            'name' => 'Approval', 'binding_type' => 'permission',
            'binding_value' => 'requisitions.approve', 'is_active' => true,
        ]])->assertRedirect();

        $this->assertDatabaseMissing('approval_levels', ['id' => $extra->id]);
        $this->assertSame(1, $workflow->levels()->count());
    }

    public function test_a_level_cannot_be_bound_to_something_that_does_not_exist(): void
    {
        $this->save($this->admin(), [[
            'name' => 'Ghost Approval', 'binding_type' => 'role',
            'binding_value' => 'Not A Real Role', 'is_active' => true,
        ]])->assertSessionHasErrors('levels.0.binding_value');

        $this->assertSame('Approval', $this->workflow()->levels()->value('name'));
    }

    public function test_the_workflow_cannot_be_emptied_or_fully_deactivated(): void
    {
        $admin = $this->admin();

        $this->save($admin, [])->assertSessionHasErrors('levels');

        $this->save($admin, [[
            'name' => 'Approval', 'binding_type' => 'permission',
            'binding_value' => 'requisitions.approve', 'is_active' => false,
        ]])->assertSessionHasErrors('levels');

        $this->assertSame(1, $this->workflow()->activeLevels()->count());
    }

    public function test_the_screen_reports_how_many_requisitions_are_in_flight(): void
    {
        Requisition::factory()->submitted()->count(3)->create();
        Requisition::factory()->approved()->create();

        $this->page($this->admin(), route('admin.approvals.index'))
            ->assertOk()
            ->assertJsonPath('props.inFlight', 3);
    }
}
