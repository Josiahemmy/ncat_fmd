<?php

namespace Tests\Feature\Documents;

use App\Models\Aircraft;
use App\Models\Part;
use App\Models\PartSerial;
use App\Models\Requisition;
use App\Models\Siv;
use App\Models\Srv;
use App\Models\StockBalance;
use App\Models\Store;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Stock\StockService;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end: the department's paper workflow running digitally, module to
 * module. Two chains from the spec's §7 acceptance list.
 */
class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $engineer;
    protected User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(StoreSeeder::class);
        $this->seed(DocumentCounterSeeder::class);
        $this->engineer = User::factory()->create()->assignRole('Engineer/Technician');
        $this->officer = User::factory()->create()->assignRole('Stores Officer');
    }

    protected function store(string $type): Store
    {
        return Store::where('type', $type)->firstOrFail();
    }

    public function test_work_order_to_requisition_to_siv_issue_to_removal_at_repair(): void
    {
        $aircraft = Aircraft::factory()->create();
        $part = Part::factory()->serialized()->create();
        $stock = app(StockService::class);

        // 1) Engineer raises a work order.
        $this->actingAs($this->engineer)->post('/work-orders', [
            'aircraft_id' => $aircraft->id, 'work_type' => 'snag',
            'title' => 'SNAG: MLG tyre burst', 'raised_by' => 'Albert', 'work_date' => '2026-07-19',
        ])->assertRedirect();
        $wo = WorkOrder::firstOrFail();

        // 2) Engineer raises a requisition against the WO and submits it.
        $this->actingAs($this->engineer)->post('/requisitions', [
            'work_order_id' => $wo->id, 'aircraft_id' => $aircraft->id,
            'full_description' => 'Main wheel tyre', 'part_id' => $part->id, 'submit' => 1,
        ])->assertRedirect();
        $req = Requisition::firstOrFail();
        $this->assertSame('submitted', $req->status);

        // 3) Officer (≠ requester) approves.
        $this->actingAs($this->officer)->post("/requisitions/{$req->id}/approve")->assertRedirect();
        $this->assertSame('approved', $req->fresh()->status);

        // 4) Replacement serial sits in Bonded; the old unit is installed on the aircraft.
        $newSerial = PartSerial::factory()->create(['part_id' => $part->id, 'status' => 'in_store', 'current_store_id' => $this->store('bonded')->id]);
        $stock->openingBalance($part, $this->store('bonded'), 1, $this->officer, serialId: $newSerial->id);
        $oldSerial = PartSerial::factory()->installed($aircraft)->create(['part_id' => $part->id, 'serial_number' => 'OLD-UNIT']);

        // 5) SIV issues the requisition; the new serial installs onto the aircraft.
        $siv = Siv::factory()->create(['issued_by' => 'Store']);
        $siv->items()->create([
            'line_no' => 1, 'requisition_id' => $req->id, 'part_id' => $part->id,
            'qty_required' => 1, 'qty_issued' => 1, 'source_store_id' => $this->store('bonded')->id,
            'serial_ids' => [$newSerial->id],
        ]);
        $this->actingAs($this->officer)->post("/issuing/{$siv->id}/post")->assertRedirect();

        $this->assertSame('issued', $req->fresh()->status);
        $this->assertSame('installed', $newSerial->fresh()->status);
        $this->assertSame($aircraft->id, $newSerial->fresh()->current_aircraft_id);

        // 6) Technician records the removal of the old unit → at_repair.
        $this->actingAs($this->engineer)->post("/requisitions/{$req->id}/removal", [
            'serial_no_removed' => 'OLD-UNIT', 'reason_for_removal' => 'Tyre burst',
            'repair_facility' => 'NCAT Workshop', 'date_sent' => '2026-07-19',
        ])->assertRedirect();

        $this->assertSame('at_repair', $oldSerial->fresh()->status);
        $this->assertNull($oldSerial->fresh()->current_aircraft_id);
        $this->assertSame($oldSerial->id, $req->fresh()->removed_serial_id);
    }

    public function test_srv_into_quarantine_then_certify_then_issue(): void
    {
        $part = Part::factory()->create();
        $keeper = User::factory()->create()->assignRole('Storekeeper');

        // 1) SRV receives into Quarantine.
        $srv = Srv::factory()->create(['destination_store_id' => $this->store('quarantine')->id]);
        $srv->items()->create(['line_no' => 1, 'part_id' => $part->id, 'quantity' => 6]);
        $this->actingAs($keeper)->post("/receiving/{$srv->id}/post")->assertRedirect();
        $this->assertEquals(6, StockBalance::where('part_id', $part->id)
            ->where('store_id', $this->store('quarantine')->id)->value('quantity'));

        // 2) Stores Officer certifies → released to Bonded.
        $this->actingAs($this->officer)->post('/stock/certify', [
            'part_id' => $part->id, 'quantity' => 6, 'decision' => 'release_to_bonded',
        ])->assertRedirect();
        $this->assertEquals(6, StockBalance::where('part_id', $part->id)
            ->where('store_id', $this->store('bonded')->id)->value('quantity'));

        // 3) SIV issues from Bonded.
        $siv = Siv::factory()->create();
        $siv->items()->create([
            'line_no' => 1, 'part_id' => $part->id, 'qty_required' => 2, 'qty_issued' => 2,
            'source_store_id' => $this->store('bonded')->id,
        ]);
        $this->actingAs($keeper)->post("/issuing/{$siv->id}/post")->assertRedirect();
        $this->assertEquals(4, StockBalance::where('part_id', $part->id)
            ->where('store_id', $this->store('bonded')->id)->value('quantity'));
    }
}
