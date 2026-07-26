<?php

namespace Tests\Feature\Documents;

use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\Srv;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Documents\PurchaseOrderService;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Purchase Orders (spec §12.5) and receiving against them. */
class PurchaseOrderTest extends TestCase
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

    protected function buyer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo([
            'orders.view', 'orders.create', 'orders.edit', 'orders.close',
            'receiving.view', 'receiving.post',
        ]);

        return $user;
    }

    protected function service(): PurchaseOrderService
    {
        return app(PurchaseOrderService::class);
    }

    /** A draft with three lines, as raised through the controller. */
    protected function draft(User $as, array $overrides = []): PurchaseOrder
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($as)->post(route('purchase-orders.store'), array_merge([
            'order_date' => '2026-06-30',
            'vendor_id' => $vendor->id,
            'aircraft_type_label' => 'DIAMOND DA-40NG/DA-42NG',
            'priority' => 'very_urgent',
            'lines' => [
                ['description' => 'ENGINE SHOCK MOUNTS', 'part_number' => 'D44-7106-00-56', 'qty_to_order' => 4, 'line_status' => 'NEW', 'timeline_month' => 7, 'timeline_year' => 2026],
                ['description' => '3 POINT SAFETY HARNESS, FRONT', 'part_number' => '5-01-1C0710', 'qty_to_order' => 2, 'line_status' => 'NEW', 'timeline_month' => 7, 'timeline_year' => 2026],
                ['description' => '3 POINT SAFETY HARNESS, REAR', 'part_number' => '5-01-1B0710', 'qty_to_order' => 2, 'line_status' => 'NEW', 'timeline_month' => 7, 'timeline_year' => 2026],
            ],
        ], $overrides))->assertSessionHasNoErrors()->assertRedirect();

        return PurchaseOrder::latest('id')->firstOrFail();
    }

    public function test_a_draft_carries_no_reference_until_it_is_issued(): void
    {
        $buyer = $this->buyer();
        $order = $this->draft($buyer);

        $this->assertNull($order->po_number);
        $this->assertSame('draft', $order->status);
        // The counter must not have moved: a draft that is never sent leaves no gap.
        $this->assertSame(308, (int) \App\Models\DocumentCounter::where('series', 'purchase_order')->value('next_number'));

        $this->actingAs($buyer)->post(route('purchase-orders.issue', $order))->assertRedirect();

        $order->refresh();

        $this->assertSame('NCAT/FMD/PO/TS/30/6/308', $order->po_number);
        $this->assertSame('issued', $order->status);
    }

    /** The sample reference is NCAT/FMD/PO/TS/30/6/307: day and month unpadded. */
    public function test_the_reference_uses_the_unpadded_day_and_month_of_the_order_date(): void
    {
        $buyer = $this->buyer();
        $order = $this->draft($buyer, ['order_date' => '2026-03-04']);

        $this->actingAs($buyer)->post(route('purchase-orders.issue', $order));

        $this->assertSame('NCAT/FMD/PO/TS/4/3/308', $order->refresh()->po_number);
    }

    public function test_an_issued_order_cannot_be_edited(): void
    {
        $buyer = $this->buyer();
        $order = $this->draft($buyer);
        $this->actingAs($buyer)->post(route('purchase-orders.issue', $order));

        $this->actingAs($buyer)->put(route('purchase-orders.update', $order), [
            'order_date' => '2026-06-30',
            'vendor_id' => $order->vendor_id,
            'lines' => [['description' => 'SNEAKY EXTRA', 'qty_to_order' => 99]],
        ])->assertStatus(422);

        $this->assertSame(3, $order->refresh()->lines()->count());
    }

    /**
     * Observation #12 regression class: `validated()` reassembles array payloads
     * rule by rule, so a line that omits a nullable key an earlier line supplied
     * comes back out of position. Line order is the printed S/NO.
     */
    public function test_line_order_survives_partial_rows(): void
    {
        $buyer = $this->buyer();
        $vendor = Vendor::factory()->create();

        $this->actingAs($buyer)->post(route('purchase-orders.store'), [
            'order_date' => '2026-06-30',
            'vendor_id' => $vendor->id,
            'lines' => [
                // First line omits every nullable key the later lines supply.
                ['qty_to_order' => 4],
                ['description' => 'SECOND', 'part_number' => 'P-2', 'qty_to_order' => 2, 'line_status' => 'NEW', 'timeline_month' => 7, 'timeline_year' => 2026],
                ['description' => 'THIRD', 'part_number' => 'P-3', 'qty_to_order' => 1, 'line_status' => 'NEW', 'timeline_month' => 8, 'timeline_year' => 2026],
            ],
        ])->assertSessionHasNoErrors();

        $lines = PurchaseOrder::latest('id')->firstOrFail()->lines;

        $this->assertSame([1, 2, 3], $lines->pluck('line_no')->all());
        $this->assertSame([null, 'SECOND', 'THIRD'], $lines->pluck('description')->all());
        $this->assertSame([4.0, 2.0, 1.0], $lines->pluck('qty_to_order')->all());
    }

    public function test_receiving_an_srv_against_the_order_accumulates_and_advances_status(): void
    {
        $buyer = $this->buyer();
        $order = $this->draft($buyer);
        $this->actingAs($buyer)->post(route('purchase-orders.issue', $order));
        $order->refresh();

        $part = Part::factory()->create();
        $line = $order->lines->first();

        // Partial: 1 of the 4 ordered.
        $this->postSrv($buyer, $order, $part, $line->id, 1);

        $order->refresh();
        $this->assertSame(1.0, $order->lines->first()->qty_received);
        $this->assertSame('partially_received', $order->status);

        // The rest of line 1 and all of lines 2 and 3.
        $this->postSrv($buyer, $order, $part, $line->id, 3);
        $this->postSrv($buyer, $order, $part, $order->lines[1]->id, 2);
        $this->postSrv($buyer, $order, $part, $order->lines[2]->id, 2);

        $this->assertSame('received', $order->refresh()->status);
    }

    public function test_over_receipt_against_a_line_is_rejected(): void
    {
        $buyer = $this->buyer();
        $order = $this->draft($buyer);
        $this->actingAs($buyer)->post(route('purchase-orders.issue', $order));
        $order->refresh();

        $part = Part::factory()->create();
        $line = $order->lines->first();

        $this->postSrv($buyer, $order, $part, $line->id, 5, expectErrors: true);

        $this->assertSame(0.0, $order->refresh()->lines->first()->qty_received);
        $this->assertSame('issued', $order->status);
    }

    public function test_cancelling_requires_a_reason_and_is_recorded(): void
    {
        $buyer = $this->buyer();
        $order = $this->draft($buyer);

        $this->actingAs($buyer)->post(route('purchase-orders.cancel', $order), [])
            ->assertSessionHasErrors('cancel_reason');

        $this->actingAs($buyer)->post(route('purchase-orders.cancel', $order), [
            'cancel_reason' => 'Vendor withdrew the quotation.',
        ])->assertSessionHasNoErrors();

        $order->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertSame('Vendor withdrew the quotation.', $order->cancel_reason);
    }

    public function test_the_pdf_renders_every_region_of_the_sample(): void
    {
        $buyer = $this->buyer();
        $order = $this->draft($buyer);
        $this->actingAs($buyer)->post(route('purchase-orders.issue', $order));

        $response = $this->actingAs($buyer)->get(route('purchase-orders.pdf', $order->refresh()));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        $html = view('pdf.orders.purchase-order', [
            'order' => $order->load(['vendor', 'lines']),
            'settings' => app(\App\Services\Documents\OrderSettings::class)->all(),
            'crest' => public_path('brand/ncat-logo.png'),
        ])->render();

        foreach ([
            'NIGERIAN COLLEGE OF AVIATION TECHNOLOGY, ZARIA',
            'ZARIA AERODROME PMB 1031',
            'www.ncat.gov.ng',
            'PURCHASE ORDER',
            'NCAT/FMD/PO/TS/30/6/308',
            'AIRCRAFT TYPE: DIAMOND DA-40NG/DA-42NG',
            'QTY TO',
            'TIME LINE',
            'ENGINE SHOCK MOUNTS',
            'JULY, 2026',
            'NOTE:',
            'No invoice or debit note covering supplies',
            'NCAT CONTACT:',
            'IBRAHIM M. HIRSE',
            'GAMMANIEL M. DANBATURE',
            'A.O.G',
            'Very Urgent',
            'For inventory',
            'Prepared by:',
            'Head, Materials and Stores.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        // Only the selected priority is ticked.
        $this->assertSame(1, substr_count($html, '✓'));
    }

    /** Post an SRV that books `qty` against one purchase order line. */
    protected function postSrv(User $as, PurchaseOrder $order, Part $part, int $lineId, float $qty, bool $expectErrors = false): void
    {
        $this->actingAs($as)->post(route('receiving.store'), [
            'srv_date' => now()->toDateString(),
            'destination_store_id' => Store::where('type', 'quarantine')->value('id'),
            'supplier' => $order->vendor->name,
            'purchase_order_id' => $order->id,
            'items' => [[
                'part_id' => $part->id,
                'purchase_order_line_id' => $lineId,
                'quantity' => $qty,
            ]],
        ])->assertSessionHasNoErrors();

        $srv = Srv::latest('id')->firstOrFail();
        $response = $this->actingAs($as)->post(route('receiving.post', $srv));

        $expectErrors
            ? $response->assertSessionHasErrors()
            : $response->assertSessionHasNoErrors();
    }
}
