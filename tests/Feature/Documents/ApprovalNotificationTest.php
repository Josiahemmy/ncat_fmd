<?php

namespace Tests\Feature\Documents;

use App\Models\ApprovalLevel;
use App\Models\ApprovalWorkflow;
use App\Models\Requisition;
use App\Models\User;
use App\Notifications\RequisitionAwaitingApproval;
use App\Notifications\RequisitionDecided;
use App\Notifications\RequisitionReadyForIssue;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Event notifications on the database channel (spec §12.1). The live-computed
 * stock alerts are a separate path and are not touched here.
 */
class ApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(DocumentCounterSeeder::class);
        $this->requester = User::factory()->create()->assignRole('Engineer/Technician');
    }

    protected function workflow(): ApprovalWorkflow
    {
        return ApprovalWorkflow::where('document_type', 'requisition')->firstOrFail();
    }

    protected function twoLevelWorkflow(): void
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
    }

    protected function submit(): Requisition
    {
        $r = Requisition::factory()->create(['status' => 'draft', 'requested_by_user_id' => $this->requester->id]);
        $this->actingAs($this->requester)->post("/requisitions/{$r->id}/submit")->assertRedirect();

        return $r->refresh();
    }

    protected function unread(User $user, string $type): int
    {
        return $user->fresh()->unreadNotifications->where('type', $type)->count();
    }

    public function test_reaching_a_level_notifies_only_users_who_can_act_on_that_level(): void
    {
        $this->twoLevelWorkflow();

        $hod = User::factory()->create();
        $hod->assignRole('HOD');
        $officer = User::factory()->create()->assignRole('Stores Officer');
        $viewer = User::factory()->create()->assignRole('Viewer');

        $this->submit();

        $this->assertSame(1, $this->unread($hod, RequisitionAwaitingApproval::class));
        $this->assertSame(0, $this->unread($officer, RequisitionAwaitingApproval::class), 'Level 2 is not open yet.');
        $this->assertSame(0, $this->unread($viewer, RequisitionAwaitingApproval::class));
        $this->assertSame(0, $this->unread($this->requester, RequisitionAwaitingApproval::class));
    }

    public function test_approving_a_level_notifies_the_next_levels_actors_and_the_requester(): void
    {
        $this->twoLevelWorkflow();
        $hod = User::factory()->create();
        $hod->assignRole('HOD');
        $officer = User::factory()->create()->assignRole('Stores Officer');

        $r = $this->submit();
        $this->actingAs($hod)->post("/requisitions/{$r->id}/approve")->assertRedirect();

        $this->assertSame(1, $this->unread($officer, RequisitionAwaitingApproval::class));
        $this->assertSame(1, $this->unread($this->requester, RequisitionDecided::class));

        $notice = $this->requester->fresh()->unreadNotifications
            ->where('type', RequisitionDecided::class)->first();
        $this->assertSame('HOD Approval', $notice->data['level_name']);
        $this->assertFalse($notice->data['fully_approved']);
        $this->assertStringContainsString($r->requisition_no, $notice->data['title']);
    }

    public function test_full_approval_notifies_the_requester_and_every_issues_post_holder(): void
    {
        $officer = User::factory()->create()->assignRole('Stores Officer');
        $keeper = User::factory()->create()->assignRole('Storekeeper');   // holds issues.post
        $viewer = User::factory()->create()->assignRole('Viewer');        // does not

        $r = $this->submit();
        $this->actingAs($officer)->post("/requisitions/{$r->id}/approve")->assertRedirect();

        $this->assertSame('approved', $r->fresh()->status);
        $this->assertSame(1, $this->unread($keeper, RequisitionReadyForIssue::class));
        $this->assertSame(1, $this->unread($officer, RequisitionReadyForIssue::class));
        $this->assertSame(0, $this->unread($viewer, RequisitionReadyForIssue::class));

        $notice = $this->requester->fresh()->unreadNotifications
            ->where('type', RequisitionDecided::class)->first();
        $this->assertTrue($notice->data['fully_approved']);
    }

    public function test_rejection_notifies_the_requester_with_the_reason(): void
    {
        $officer = User::factory()->create()->assignRole('Stores Officer');
        $r = $this->submit();

        $this->actingAs($officer)->post("/requisitions/{$r->id}/reject", ['remarks' => 'Wrong stock code'])
            ->assertRedirect();

        $notice = $this->requester->fresh()->unreadNotifications
            ->where('type', RequisitionDecided::class)->first();

        $this->assertSame('reject', $notice->data['decision']);
        $this->assertStringContainsString('Wrong stock code', $notice->data['message']);
        $this->assertSame(0, $this->unread($this->requester, RequisitionReadyForIssue::class));
    }

    public function test_viewing_a_requisition_repeatedly_does_not_duplicate_notifications(): void
    {
        $officer = User::factory()->create()->assignRole('Stores Officer');
        $r = $this->submit();

        $this->assertSame(1, $this->unread($officer, RequisitionAwaitingApproval::class));

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($officer)->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
            ])->get("/requisitions/{$r->id}")->assertOk();
        }

        $this->assertSame(1, $this->unread($officer, RequisitionAwaitingApproval::class));
    }

    public function test_the_bell_serves_event_notifications_and_marking_them_read_clears_them(): void
    {
        $officer = User::factory()->create()->assignRole('Stores Officer');
        $r = $this->submit();

        $page = $this->actingAs($officer)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ])->get('/requisitions')->assertOk();

        $page->assertJsonPath('props.notices.count', 1)
            ->assertJsonPath('props.notices.items.0.type', 'requisition_awaiting_approval')
            ->assertJsonPath('props.notices.items.0.href', route('requisitions.show', $r->id));

        $id = $page->json('props.notices.items.0.id');

        $this->actingAs($officer)->post(route('notifications.read'), ['id' => $id])->assertRedirect();
        $this->assertSame(0, $this->unread($officer, RequisitionAwaitingApproval::class));
    }

    public function test_marking_all_notifications_read_clears_the_bell(): void
    {
        $officer = User::factory()->create()->assignRole('Stores Officer');
        $this->submit();
        $this->submit();

        $this->assertSame(2, $this->unread($officer, RequisitionAwaitingApproval::class));

        $this->actingAs($officer)->post(route('notifications.read'))->assertRedirect();
        $this->assertSame(0, $this->unread($officer, RequisitionAwaitingApproval::class));
    }

    public function test_a_user_cannot_mark_someone_elses_notification_read(): void
    {
        $officer = User::factory()->create()->assignRole('Stores Officer');
        $other = User::factory()->create()->assignRole('Stores Officer');
        $this->submit();

        $id = $officer->fresh()->unreadNotifications->first()->id;

        $this->actingAs($other)->post(route('notifications.read'), ['id' => $id])->assertRedirect();
        $this->assertSame(1, $this->unread($officer, RequisitionAwaitingApproval::class));
    }
}
