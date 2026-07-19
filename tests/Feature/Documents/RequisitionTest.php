<?php

namespace Tests\Feature\Documents;

use App\Models\Aircraft;
use App\Models\PartSerial;
use App\Models\Requisition;
use App\Models\User;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(DocumentCounterSeeder::class);
    }

    protected function engineer(): User
    {
        return User::factory()->create()->assignRole('Engineer/Technician');
    }

    protected function approver(): User
    {
        return User::factory()->create()->assignRole('Stores Officer'); // has requisitions.approve
    }

    public function test_creating_a_requisition_reserves_a_number_and_saves_a_draft(): void
    {
        $this->actingAs($this->engineer())->post('/requisitions', [
            'full_description' => 'Main wheel tyre',
            'part_no' => 'TYRE-001',
        ])->assertRedirect();

        $r = Requisition::first();
        $this->assertSame('1002', $r->requisition_no); // seeded next requisition serial
        $this->assertSame('draft', $r->status);
    }

    public function test_submit_moves_a_draft_to_submitted(): void
    {
        $eng = $this->engineer();
        $r = Requisition::factory()->create(['status' => 'draft', 'requested_by_user_id' => $eng->id]);

        $this->actingAs($eng)->post("/requisitions/{$r->id}/submit")->assertRedirect();
        $this->assertSame('submitted', $r->fresh()->status);
    }

    public function test_an_approver_can_approve_a_submitted_requisition(): void
    {
        $r = Requisition::factory()->submitted()->create(['requested_by_user_id' => $this->engineer()->id]);
        $approver = $this->approver();

        $this->actingAs($approver)->post("/requisitions/{$r->id}/approve", ['remarks' => 'OK'])->assertRedirect();

        $r->refresh();
        $this->assertSame('approved', $r->status);
        $this->assertSame($approver->id, $r->approved_by_user_id);
        $this->assertNotNull($r->approved_at);
    }

    public function test_an_approver_cannot_approve_their_own_requisition(): void
    {
        // Give one user both create and approve, then have them raise + self-approve.
        $user = User::factory()->create();
        $user->givePermissionTo(['requisitions.view', 'requisitions.create', 'requisitions.approve']);
        $r = Requisition::factory()->submitted()->create(['requested_by_user_id' => $user->id]);

        $this->actingAs($user)->post("/requisitions/{$r->id}/approve")
            ->assertSessionHasErrors('approval');
        $this->assertSame('submitted', $r->fresh()->status);
    }

    public function test_rejection_requires_remarks_and_sets_status(): void
    {
        $r = Requisition::factory()->submitted()->create(['requested_by_user_id' => $this->engineer()->id]);
        $approver = $this->approver();

        $this->actingAs($approver)->post("/requisitions/{$r->id}/reject")
            ->assertSessionHasErrors('remarks');

        $this->actingAs($approver)->post("/requisitions/{$r->id}/reject", ['remarks' => 'Wrong part'])
            ->assertRedirect();
        $this->assertSame('rejected', $r->fresh()->status);
    }

    public function test_completing_the_removal_section_transitions_the_removed_serial(): void
    {
        $aircraft = Aircraft::factory()->create();
        $serial = PartSerial::factory()->installed($aircraft)->create(['serial_number' => 'OLD-123']);
        $r = Requisition::factory()->issued()->create(['aircraft_id' => $aircraft->id]);

        $this->actingAs($this->engineer())->post("/requisitions/{$r->id}/removal", [
            'serial_no_removed' => 'OLD-123',
            'reason_for_removal' => 'Tyre burst on landing',
            'repair_facility' => 'NCAT Workshop',
            'date_sent' => '2026-07-19',
        ])->assertRedirect();

        $serial->refresh();
        $this->assertSame('at_repair', $serial->status);
        $this->assertNull($serial->current_aircraft_id);
        $this->assertSame($serial->id, $r->fresh()->removed_serial_id);
    }

    public function test_removal_before_issue_is_blocked(): void
    {
        $r = Requisition::factory()->approved()->create();

        $this->actingAs($this->engineer())->post("/requisitions/{$r->id}/removal", [
            'serial_no_removed' => 'X',
            'reason_for_removal' => 'y',
        ])->assertStatus(422);
    }

    public function test_permissions_gate_create_and_approve(): void
    {
        $viewer = User::factory()->create()->assignRole('Viewer'); // view only
        $r = Requisition::factory()->submitted()->create();

        $this->actingAs($viewer)->post('/requisitions', ['full_description' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->post("/requisitions/{$r->id}/approve")->assertForbidden();
    }
}
