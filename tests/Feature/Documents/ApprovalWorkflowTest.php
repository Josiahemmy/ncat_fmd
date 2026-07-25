<?php

namespace Tests\Feature\Documents;

use App\Models\ApprovalLevel;
use App\Models\ApprovalWorkflow;
use App\Models\Requisition;
use App\Models\RequisitionApproval;
use App\Models\User;
use App\Services\Documents\ApprovalService;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The configurable approval engine (spec §12.1). The seeded default is a single
 * level bound to `requisitions.approve`; RequisitionTest covers that path as the
 * backwards-compatibility regression suite. This file covers multi-level chains,
 * per-level gating, and snapshot isolation when an admin edits levels mid-flight.
 */
class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(DocumentCounterSeeder::class);
    }

    protected function service(): ApprovalService
    {
        return app(ApprovalService::class);
    }

    protected function workflow(): ApprovalWorkflow
    {
        return ApprovalWorkflow::where('document_type', 'requisition')->firstOrFail();
    }

    /** Replace the seeded single level with a two-level chain: HOD then Stores. */
    protected function twoLevelWorkflow(): ApprovalWorkflow
    {
        Role::findOrCreate('HOD', 'web');
        $workflow = $this->workflow();
        $workflow->levels()->delete();

        ApprovalLevel::create([
            'approval_workflow_id' => $workflow->id, 'sequence' => 1,
            'name' => 'HOD Approval', 'role_name' => 'HOD',
        ]);
        ApprovalLevel::create([
            'approval_workflow_id' => $workflow->id, 'sequence' => 2,
            'name' => 'Stores Approval', 'permission_name' => 'requisitions.approve',
        ]);

        return $workflow->refresh();
    }

    protected function requester(): User
    {
        return User::factory()->create()->assignRole('Engineer/Technician');
    }

    protected function submitted(?User $requester = null): Requisition
    {
        $requester ??= $this->requester();
        $r = Requisition::factory()->create(['status' => 'draft', 'requested_by_user_id' => $requester->id]);
        $this->actingAs($requester)->post("/requisitions/{$r->id}/submit")->assertRedirect();

        return $r->refresh();
    }

    public function test_the_migration_seeds_a_single_default_level_bound_to_the_approve_permission(): void
    {
        $levels = $this->workflow()->activeLevels()->get();

        $this->assertCount(1, $levels);
        $this->assertSame('Approval', $levels[0]->name);
        $this->assertSame('requisitions.approve', $levels[0]->permission_name);
        $this->assertNull($levels[0]->role_name);
    }

    public function test_submitting_materialises_one_pending_record_per_active_level(): void
    {
        $this->twoLevelWorkflow();
        $r = $this->submitted();

        $records = $r->approvals()->get();
        $this->assertCount(2, $records);
        $this->assertSame(['HOD Approval', 'Stores Approval'], $records->pluck('level_name')->all());
        $this->assertTrue($records->every(fn ($a) => $a->isPending()));
        $this->assertNotNull($r->submitted_at);
    }

    public function test_a_requisition_sits_at_its_lowest_undecided_level(): void
    {
        $this->twoLevelWorkflow();
        $r = $this->submitted();

        $this->assertSame('HOD Approval', $this->service()->pending($r)->level_name);
    }

    public function test_only_a_user_matching_the_pending_levels_binding_can_act(): void
    {
        $this->twoLevelWorkflow();
        $r = $this->submitted();

        $hod = User::factory()->create();
        $hod->assignRole('HOD');
        $storesOfficer = User::factory()->create()->assignRole('Stores Officer');

        // Level 1 is bound to the HOD role, so the stores officer cannot act yet
        // even though they hold requisitions.approve.
        $this->assertTrue($this->service()->canAct($hod, $r));
        $this->assertFalse($this->service()->canAct($storesOfficer, $r));

        $this->actingAs($storesOfficer)->post("/requisitions/{$r->id}/approve")->assertForbidden();
        $this->assertSame('submitted', $r->fresh()->status);
    }

    public function test_approving_advances_to_the_next_level_without_making_the_requisition_issuable(): void
    {
        $this->twoLevelWorkflow();
        $r = $this->submitted();
        $hod = User::factory()->create();
        $hod->assignRole('HOD');

        $this->actingAs($hod)->post("/requisitions/{$r->id}/approve", ['remarks' => 'Fleet needs it'])
            ->assertRedirect();

        $r->refresh();
        $this->assertSame('submitted', $r->status, 'Not issuable until the final level approves.');
        $this->assertSame('Stores Approval', $this->service()->pending($r)->level_name);

        $first = $r->approvals()->where('sequence', 1)->firstOrFail();
        $this->assertSame('approve', $first->decision);
        $this->assertSame($hod->id, $first->decided_by_user_id);
        $this->assertSame('Fleet needs it', $first->remarks);
        $this->assertNotNull($first->decided_at);
    }

    public function test_approving_the_final_level_makes_the_requisition_issuable(): void
    {
        $this->twoLevelWorkflow();
        $r = $this->submitted();
        $hod = User::factory()->create();
        $hod->assignRole('HOD');
        $officer = User::factory()->create()->assignRole('Stores Officer');

        $this->actingAs($hod)->post("/requisitions/{$r->id}/approve")->assertRedirect();
        $this->actingAs($officer)->post("/requisitions/{$r->id}/approve")->assertRedirect();

        $r->refresh();
        $this->assertSame('approved', $r->status);
        $this->assertSame($officer->id, $r->approved_by_user_id, 'The final approver is stamped on the voucher.');
        $this->assertNotNull($r->approved_at);
        $this->assertNull($this->service()->pending($r));
    }

    public function test_rejecting_mid_chain_ends_the_flow_and_requires_a_reason(): void
    {
        $this->twoLevelWorkflow();
        $r = $this->submitted();
        $hod = User::factory()->create();
        $hod->assignRole('HOD');

        $this->actingAs($hod)->post("/requisitions/{$r->id}/reject")->assertSessionHasErrors('remarks');
        $this->assertSame('submitted', $r->fresh()->status);

        $this->actingAs($hod)->post("/requisitions/{$r->id}/reject", ['remarks' => 'Wrong part number'])
            ->assertRedirect();

        $r->refresh();
        $this->assertSame('rejected', $r->status);
        $this->assertSame('Wrong part number', $r->approval_remarks);
        $this->assertNotNull($r->rejected_at);
        $this->assertNull($this->service()->pending($r), 'A rejected chain has no pending level.');

        // The untouched second level stays undecided as part of the trail.
        $this->assertNull($r->approvals()->where('sequence', 2)->value('decision'));
    }

    public function test_the_approver_may_not_be_the_requester_at_any_level(): void
    {
        $this->twoLevelWorkflow();

        // One user holds the HOD role and raises the requisition themselves.
        $requester = User::factory()->create();
        $requester->assignRole(['HOD', 'Engineer/Technician']);
        $r = $this->submitted($requester);

        // The binding is satisfied, so the request reaches the controller and is
        // refused there. This mirrors the pre-migration single-level behaviour,
        // which RequisitionTest asserts against the same `approval` error key.
        $this->assertTrue($this->service()->matchesPendingLevel($requester, $r));
        $this->assertFalse($this->service()->canAct($requester, $r));

        $this->actingAs($requester)->post("/requisitions/{$r->id}/approve")
            ->assertSessionHasErrors('approval');
        $this->assertSame('submitted', $r->fresh()->status);
    }

    public function test_an_in_flight_requisition_keeps_the_chain_it_started_with(): void
    {
        $this->twoLevelWorkflow();
        $r = $this->submitted();
        $hod = User::factory()->create();
        $hod->assignRole('HOD');

        $this->actingAs($hod)->post("/requisitions/{$r->id}/approve")->assertRedirect();

        // Admin now rewrites the workflow: the second level is deactivated, and a
        // brand new third level is appended.
        $workflow = $this->workflow();
        $workflow->levels()->where('sequence', 2)->update(['is_active' => false]);
        ApprovalLevel::create([
            'approval_workflow_id' => $workflow->id, 'sequence' => 3,
            'name' => 'Finance Approval', 'permission_name' => 'requisitions.approve',
        ]);

        // The in-flight document still resolves against its own snapshot.
        $this->assertSame('Stores Approval', $this->service()->pending($r->fresh())->level_name);
        $this->assertSame(2, $r->fresh()->approvals()->count());

        $officer = User::factory()->create()->assignRole('Stores Officer');
        $this->actingAs($officer)->post("/requisitions/{$r->id}/approve")->assertRedirect();
        $this->assertSame('approved', $r->fresh()->status);

        // A new submission picks up the current configuration instead.
        $fresh = $this->submitted();
        $this->assertSame(
            ['HOD Approval', 'Finance Approval'],
            $fresh->approvals()->get()->pluck('level_name')->all()
        );
    }

    public function test_deleting_a_level_does_not_corrupt_an_in_flight_chain(): void
    {
        $this->twoLevelWorkflow();
        $r = $this->submitted();

        $this->workflow()->levels()->where('sequence', 1)->delete();

        $pending = $this->service()->pending($r->fresh());
        $this->assertSame('HOD Approval', $pending->level_name);
        $this->assertSame('HOD', $pending->role_name);
        $this->assertNull($pending->approval_level_id, 'The reference is cleared but the snapshot survives.');
    }

    public function test_re_submitting_a_rejected_requisition_starts_a_new_cycle_and_keeps_the_history(): void
    {
        $this->twoLevelWorkflow();
        $requester = $this->requester();
        $r = $this->submitted($requester);
        $hod = User::factory()->create();
        $hod->assignRole('HOD');

        $this->actingAs($hod)->post("/requisitions/{$r->id}/reject", ['remarks' => 'Fix the part number'])
            ->assertRedirect();

        $this->actingAs($requester)->put("/requisitions/{$r->id}", [
            'full_description' => 'Main wheel tyre', 'part_no' => 'TYRE-002', 'submit' => 1,
        ])->assertRedirect();

        $r->refresh();
        $this->assertSame('submitted', $r->status);
        $this->assertSame(2, $this->service()->pending($r)->cycle);
        $this->assertSame('HOD Approval', $this->service()->pending($r)->level_name);
        $this->assertSame(4, $r->approvals()->count(), 'Cycle 1 stays on the record as history.');
        $this->assertSame('reject', $r->approvals()->where('cycle', 1)->where('sequence', 1)->value('decision'));
    }

    public function test_a_requisition_submitted_outside_the_engine_is_snapshotted_lazily(): void
    {
        // Demo seeds and legacy rows set status directly without hitting submit().
        $r = Requisition::factory()->submitted()->create(['requested_by_user_id' => $this->requester()->id]);
        $this->assertSame(0, $r->approvals()->count());

        $pending = $this->service()->pending($r);

        $this->assertSame('Approval', $pending->level_name);
        $this->assertSame(1, $r->fresh()->approvals()->count());

        // Repeated reads must not stack duplicate records.
        $this->service()->pending($r->fresh());
        $this->assertSame(1, $r->fresh()->approvals()->count());
    }

    public function test_the_siv_picker_only_offers_fully_approved_requisitions(): void
    {
        $this->twoLevelWorkflow();
        $r = $this->submitted();
        $hod = User::factory()->create();
        $hod->assignRole('HOD');
        $officer = User::factory()->create()->assignRole('Stores Officer');

        $this->actingAs($hod)->post("/requisitions/{$r->id}/approve")->assertRedirect();

        // Half-approved: still not offered.
        $this->inertiaProps("/issuing/create", $officer)
            ->assertJsonCount(0, 'props.approvedRequisitions');

        $this->actingAs($officer)->post("/requisitions/{$r->id}/approve")->assertRedirect();

        $this->inertiaProps('/issuing/create', $officer)
            ->assertJsonCount(1, 'props.approvedRequisitions')
            ->assertJsonPath('props.approvedRequisitions.0.requisition_no', $r->requisition_no);
    }

    public function test_legacy_decided_requisitions_are_backfilled_onto_the_default_level(): void
    {
        // Rows created before this phase carry a decision but no chain records;
        // the migration backfill is re-run here against the same code path.
        $approver = User::factory()->create()->assignRole('Stores Officer');
        $legacy = Requisition::factory()->create([
            'status' => 'approved',
            'approved_by_user_id' => $approver->id,
            'approved_at' => now()->subDays(3),
            'approval_remarks' => 'Historic approval',
        ]);

        RequisitionApproval::create([
            'requisition_id' => $legacy->id,
            'approval_level_id' => $this->workflow()->levels()->value('id'),
            'cycle' => 1, 'sequence' => 1, 'level_name' => 'Approval',
            'permission_name' => 'requisitions.approve',
            'decision' => 'approve', 'decided_by_user_id' => $approver->id,
            'decided_at' => now()->subDays(3), 'remarks' => 'Historic approval',
        ]);

        $props = $this->inertiaProps("/requisitions/{$legacy->id}", $approver);

        $props->assertJsonPath('props.approval.trail.0.level_name', 'Approval')
            ->assertJsonPath('props.approval.trail.0.decision', 'approve')
            ->assertJsonPath('props.approval.pending', null);
    }

    public function test_the_sidebar_badge_counts_only_what_waits_on_this_user(): void
    {
        $this->twoLevelWorkflow();
        $hod = User::factory()->create();
        $hod->assignRole('HOD');
        $officer = User::factory()->create()->assignRole('Stores Officer');

        $r = $this->submitted();

        // Level 1 is the HOD's; the stores officer has nothing to do yet.
        $this->assertSame(1, $this->service()->pendingForCount($hod));
        $this->assertSame(0, $this->service()->pendingForCount($officer));

        $this->actingAs($hod)->post("/requisitions/{$r->id}/approve")->assertRedirect();

        $this->assertSame(0, $this->service()->pendingForCount($hod));
        $this->assertSame(1, $this->service()->pendingForCount($officer));

        $this->inertiaProps('/dashboard', $officer)->assertJsonPath('props.badges.approvals', 1);
        $this->inertiaProps('/dashboard', $hod)->assertJsonPath('props.badges.approvals', 0);
    }

    public function test_a_user_is_not_counted_for_a_requisition_they_raised(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole(['Stores Officer', 'Engineer/Technician']);

        $this->submitted($requester);

        $this->assertSame(0, $this->service()->pendingForCount($requester));
    }

    public function test_role_bound_levels_grant_the_approval_surfaces_without_the_old_permission(): void
    {
        $this->twoLevelWorkflow();
        $hod = User::factory()->create();
        $hod->assignRole('HOD');

        $this->assertFalse($hod->can('requisitions.approve'));
        $this->assertTrue($this->service()->canApproveAnyLevel($hod));
    }

    /**
     * Inertia XHR page request: returns the page object as JSON so props can be
     * asserted without depending on a built front-end asset.
     */
    protected function inertiaProps(string $uri, User $as)
    {
        return $this->actingAs($as)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ])->get($uri)->assertOk();
    }
}
