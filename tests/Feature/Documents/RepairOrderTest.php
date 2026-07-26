<?php

namespace Tests\Feature\Documents;

use App\Models\Part;
use App\Models\PartSerial;
use App\Models\RepairOrder;
use App\Models\Requisition;
use App\Models\StockBalance;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Repair Orders (spec §12.5), including the loop a removed serial travels:
 * removal → RO → return → Quarantine → certified → issuable again.
 */
class RepairOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(StoreSeeder::class);
        $this->seed(DocumentCounterSeeder::class);
    }

    protected function officer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            'orders.view', 'orders.create', 'orders.edit', 'orders.close',
            'quarantine.certify', 'stock.view',
        ]);

        return $user;
    }

    /** A serial that a requisition's removal section has already booked out. */
    protected function removedSerial(): array
    {
        $part = Part::factory()->serialized()->create();
        $serial = PartSerial::factory()->create([
            'part_id' => $part->id,
            'status' => 'removed_unserviceable',
            'current_store_id' => null,
        ]);
        $requisition = Requisition::factory()->create([
            'removed_serial_id' => $serial->id,
            'serial_no_removed' => $serial->serial_number,
        ]);

        return [$part, $serial, $requisition];
    }

    protected function draftFromSerial(User $as, PartSerial $serial, ?Vendor $vendor = null): RepairOrder
    {
        $vendor ??= Vendor::factory()->repairOrganization()->create();

        $this->actingAs($as)->post(route('repair-orders.store'), [
            'order_date' => '2026-03-04',
            'vendor_id' => $vendor->id,
            'aircraft_type_label' => 'DIAMOND DA40G',
            'priority' => 'very_urgent',
            'lines' => [[
                'description' => 'PROPELLER GOVERNOR',
                'part_serial_id' => $serial->id,
                'qty' => 1,
                'action' => 'OVERHAUL',
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        return RepairOrder::latest('id')->firstOrFail();
    }

    public function test_the_vendor_must_be_a_repair_organisation(): void
    {
        $officer = $this->officer();
        [, $serial] = $this->removedSerial();
        $supplier = Vendor::factory()->create();     // supplier only

        $this->actingAs($officer)->post(route('repair-orders.store'), [
            'order_date' => '2026-03-04',
            'vendor_id' => $supplier->id,
            'lines' => [['description' => 'PROPELLER GOVERNOR', 'part_serial_id' => $serial->id, 'qty' => 1, 'action' => 'OVERHAUL']],
        ])->assertSessionHasErrors('vendor_id');

        $this->assertSame(0, RepairOrder::count());

        // A vendor typed as `both` is acceptable.
        $both = Vendor::factory()->both()->create();
        $this->draftFromSerial($officer, $serial, $both);

        $this->assertSame(1, RepairOrder::count());
    }

    /** The sample reference is NCAT/FMD/RO/TS/03/298: month zero-padded, no day. */
    public function test_the_reference_uses_the_zero_padded_month_and_no_day(): void
    {
        $officer = $this->officer();
        [, $serial] = $this->removedSerial();
        $order = $this->draftFromSerial($officer, $serial);

        $this->assertNull($order->ro_number);

        $this->actingAs($officer)->post(route('repair-orders.issue', $order))->assertRedirect();

        $this->assertSame('NCAT/FMD/RO/TS/03/299', $order->refresh()->ro_number);
    }

    public function test_issuing_sends_the_serial_to_repair_and_back_links_the_requisition(): void
    {
        $officer = $this->officer();
        [, $serial, $requisition] = $this->removedSerial();
        $order = $this->draftFromSerial($officer, $serial);

        $this->actingAs($officer)->post(route('repair-orders.issue', $order));

        $order->refresh();

        $this->assertSame('at_repair', $serial->refresh()->status);
        $this->assertSame($order->ro_number, $requisition->refresh()->repair_order_ref);

        // The line links back to both the serial and the originating requisition.
        $line = $order->lines()->firstOrFail();
        $this->assertSame($serial->id, $line->part_serial_id);
        $this->assertSame($requisition->id, $line->requisition_id);
        $this->assertSame($serial->serial_number, $line->serial_no);
    }

    /**
     * The full circle. A serviceable return is an uncertified receipt like any
     * other, so it lands in Quarantine and has to be certified before it can be
     * issued again (§5 rule 3).
     */
    public function test_a_serviceable_return_lands_in_quarantine_and_becomes_issuable_once_certified(): void
    {
        $officer = $this->officer();
        [$part, $serial, $requisition] = $this->removedSerial();
        $order = $this->draftFromSerial($officer, $serial);
        $this->actingAs($officer)->post(route('repair-orders.issue', $order));
        $this->actingAs($officer)->post(route('repair-orders.at-vendor', $order->refresh()));

        $quarantine = Store::where('type', 'quarantine')->firstOrFail();
        $line = $order->lines()->firstOrFail();

        $this->actingAs($officer)->post(route('repair-orders.returned', $order), [
            'dispositions' => [[
                'line_id' => $line->id,
                'disposition' => 'serviceable',
                'note' => 'Overhauled, tagged serviceable.',
            ]],
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $serial->refresh();

        $this->assertSame('returned', $order->status);
        $this->assertSame('in_store', $serial->status);
        $this->assertSame($quarantine->id, $serial->current_store_id);

        // The receipt is in the ledger, in Quarantine, referencing the RO.
        $this->assertDatabaseHas('stock_movements', [
            'part_id' => $part->id,
            'store_id' => $quarantine->id,
            'direction' => 'in',
            'part_serial_id' => $serial->id,
            'reference' => $order->ro_number,
        ]);
        $this->assertSame(1.0, (float) StockBalance::where([
            'part_id' => $part->id, 'store_id' => $quarantine->id,
        ])->value('quantity'));

        // Quarantined stock is not issuable until certified.
        $bonded = Store::where('type', 'bonded')->firstOrFail();
        app(\App\Services\Stock\StockService::class)
            ->certify($part, 1, 'release_to_bonded', $officer, $serial->id);

        $this->assertSame(1.0, (float) StockBalance::where([
            'part_id' => $part->id, 'store_id' => $bonded->id,
        ])->value('quantity'));
        $this->assertSame(0.0, (float) StockBalance::where([
            'part_id' => $part->id, 'store_id' => $quarantine->id,
        ])->value('quantity'));
    }

    public function test_a_scrapped_return_terminates_the_serial_and_posts_no_stock(): void
    {
        $officer = $this->officer();
        [$part, $serial] = $this->removedSerial();
        $order = $this->draftFromSerial($officer, $serial);
        $this->actingAs($officer)->post(route('repair-orders.issue', $order));

        $line = $order->refresh()->lines()->firstOrFail();

        $this->actingAs($officer)->post(route('repair-orders.returned', $order), [
            'dispositions' => [[
                'line_id' => $line->id,
                'disposition' => 'scrapped',
                'note' => 'Beyond economical repair.',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('scrapped', $serial->refresh()->status);
        $this->assertDatabaseMissing('stock_movements', ['part_serial_id' => $serial->id, 'direction' => 'in']);
    }

    public function test_every_line_must_be_dispositioned_before_the_order_can_be_returned(): void
    {
        $officer = $this->officer();
        [, $serialA] = $this->removedSerial();
        [, $serialB] = $this->removedSerial();
        $vendor = Vendor::factory()->repairOrganization()->create();

        $this->actingAs($officer)->post(route('repair-orders.store'), [
            'order_date' => '2026-03-04',
            'vendor_id' => $vendor->id,
            'lines' => [
                ['description' => 'GOVERNOR A', 'part_serial_id' => $serialA->id, 'qty' => 1, 'action' => 'OVERHAUL'],
                ['description' => 'GOVERNOR B', 'part_serial_id' => $serialB->id, 'qty' => 1, 'action' => 'OVERHAUL'],
            ],
        ])->assertSessionHasNoErrors();

        $order = RepairOrder::latest('id')->firstOrFail();
        $this->actingAs($officer)->post(route('repair-orders.issue', $order));

        $first = $order->refresh()->lines()->firstOrFail();

        $this->actingAs($officer)->post(route('repair-orders.returned', $order), [
            'dispositions' => [['line_id' => $first->id, 'disposition' => 'serviceable']],
        ])->assertSessionHasErrors('dispositions');

        $this->assertSame('issued', $order->refresh()->status);
    }

    /** Observation #12 regression class, on the repair order form. */
    public function test_line_order_survives_partial_rows(): void
    {
        $officer = $this->officer();
        [, $serial] = $this->removedSerial();
        $vendor = Vendor::factory()->repairOrganization()->create();

        $this->actingAs($officer)->post(route('repair-orders.store'), [
            'order_date' => '2026-03-04',
            'vendor_id' => $vendor->id,
            'lines' => [
                // No serial, no part number, no action: every nullable key omitted.
                ['description' => 'FIRST', 'qty' => 1],
                ['description' => 'SECOND', 'part_serial_id' => $serial->id, 'part_number' => 'P-853-16', 'qty' => 1, 'action' => 'OVERHAUL'],
                ['description' => 'THIRD', 'serial_no' => '19G329K/H25', 'part_number' => 'P-853-16', 'qty' => 1, 'action' => 'TEST'],
            ],
        ])->assertSessionHasNoErrors();

        $lines = RepairOrder::latest('id')->firstOrFail()->lines;

        $this->assertSame([1, 2, 3], $lines->pluck('line_no')->all());
        $this->assertSame(['FIRST', 'SECOND', 'THIRD'], $lines->pluck('description')->all());
        $this->assertSame([null, $serial->id, null], $lines->pluck('part_serial_id')->all());
        $this->assertSame([null, null, '19G329K/H25'], $lines->pluck('serial_no')->map(
            fn ($v, $i) => $i === 1 ? null : $v,
        )->all());
    }

    public function test_the_pdf_renders_every_region_of_the_sample(): void
    {
        $officer = $this->officer();
        [, $serial] = $this->removedSerial();
        $order = $this->draftFromSerial($officer, $serial);
        $this->actingAs($officer)->post(route('repair-orders.issue', $order));
        $order->refresh();

        $this->actingAs($officer)->get(route('repair-orders.pdf', $order))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $html = view('pdf.orders.repair-order', [
            'order' => $order->load(['vendor', 'lines.partSerial']),
            'settings' => app(\App\Services\Documents\OrderSettings::class)->all(),
            'crest' => public_path('brand/ncat-logo.png'),
        ])->render();

        foreach ([
            'NIGERIAN COLLEGE OF AVIATION TECHNOLOGY, ZARIA',
            'REPAIR ORDER',
            'NCAT/FMD/RO/TS/03/299',
            'AIRCRAFT TYPE: DIAMOND DA40G',
            'SERIAL NO.',
            'ACTION',
            'PROPELLER GOVERNOR',
            'OVERHAUL',
            $serial->serial_number,
            'NOTE:',
            'This item is for Repair and Test',
            'NCAT CONTACTS:',
            'IBRAHIM M. HIRSE',
            'A.O.G',
            'Very Urgent',
            'For inventory',
            'Prepared by:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        // The RO signs off without the "Head," the PO carries. Forms win.
        $this->assertStringContainsString('Materials and Stores.', $html);
        $this->assertStringNotContainsString('Head, Materials and Stores.', $html);
    }
}
