<?php

namespace Tests\Feature\Documents;

use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\RepairOrder;
use App\Models\Srv;
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
 * Every action that can be refused server-side must say why (Phase 9, item 1).
 *
 * Two channels carry a refusal, and the split is deliberate:
 *  · ValidationException → the Inertia error bag, keyed by field path, so a
 *    line-specific reason renders against that line.
 *  · DomainRefusal → a flashed `error`, so a document-level reason toasts.
 *
 * A refusal that reaches neither channel is the bug this covers: it used to
 * leave the voucher in draft with nothing on screen, or return HTTP 500.
 */
class ActionErrorSurfacingTest extends TestCase
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

    protected function user(array $permissions): User
    {
        return tap(User::factory()->create())->givePermissionTo($permissions);
    }

    protected function storeId(string $type): int
    {
        return Store::where('type', $type)->value('id');
    }

    /** A refusal must never reach the clerk as a server fault. */
    protected function assertRefusedNotCrashed($response): void
    {
        $this->assertNotSame(500, $response->getStatusCode(), 'A business-rule refusal surfaced as a server error.');
    }

    // ---------------------------------------------------------------- posting

    public function test_posting_an_srv_reports_the_line_that_blocks_it(): void
    {
        $keeper = $this->user(['receiving.view', 'receiving.post']);
        $part = Part::factory()->shelfLife()->create();

        $this->actingAs($keeper)->post('/receiving', [
            'srv_date' => '2026-07-19',
            'destination_store_id' => $this->storeId('quarantine'),
            'supplier' => 'ACME Aviation',
            'items' => [['part_id' => $part->id, 'quantity' => 3]],
        ])->assertRedirect();

        $srv = Srv::latest('id')->first();
        $response = $this->actingAs($keeper)->post("/receiving/{$srv->id}/post");

        $this->assertRefusedNotCrashed($response);

        // Keyed to the line, not the document, so the page can point at it.
        $response->assertSessionHasErrors('items.0.batch_no');
        $this->assertStringContainsString(
            $part->part_number,
            session('errors')->first('items.0.batch_no'),
            'The reason should name the part so the clerk knows which line to fix.',
        );

        $this->assertSame('draft', $srv->refresh()->status, 'The refusal must leave the voucher in draft.');
    }

    public function test_posting_an_siv_reports_the_line_that_blocks_it(): void
    {
        $keeper = $this->user(['issues.view', 'issues.post']);
        $part = Part::factory()->serialized()->create();

        $this->actingAs($keeper)->post('/issuing', [
            'siv_date' => '2026-07-19',
            'items' => [[
                'part_id' => $part->id,
                'source_store_id' => $this->storeId('bonded'),
                'qty_required' => 2,
                'qty_issued' => 2,
            ]],
        ])->assertRedirect();

        $siv = \App\Models\Siv::latest('id')->first();
        $response = $this->actingAs($keeper)->post("/issuing/{$siv->id}/post");

        $this->assertRefusedNotCrashed($response);
        $response->assertSessionHasErrors();
        $this->assertSame('draft', $siv->refresh()->status);
    }

    // ------------------------------------------------------- issue and close

    public function test_closing_a_draft_purchase_order_says_why(): void
    {
        $buyer = $this->user(['orders.view', 'orders.close']);
        $order = PurchaseOrder::factory()->create(['vendor_id' => Vendor::factory()->create()->id]);

        $response = $this->actingAs($buyer)
            ->withHeader('X-Inertia', 'true')
            ->post(route('purchase-orders.close', $order->id));

        $this->assertRefusedNotCrashed($response);
        $response->assertSessionHas('error');
        $this->assertStringContainsString('issued', session('error'));
    }

    public function test_issuing_a_purchase_order_with_no_lines_says_why(): void
    {
        $buyer = $this->user(['orders.view', 'orders.edit']);
        $order = PurchaseOrder::factory()->create(['vendor_id' => Vendor::factory()->create()->id]);
        $order->lines()->delete();

        $response = $this->actingAs($buyer)
            ->withHeader('X-Inertia', 'true')
            ->post(route('purchase-orders.issue', $order->id));

        $this->assertRefusedNotCrashed($response);
        $response->assertSessionHas('error');
    }

    public function test_returning_a_repair_order_that_is_not_with_the_vendor_says_why(): void
    {
        $buyer = $this->user(['orders.view', 'orders.edit']);
        $order = RepairOrder::factory()->create([
            'vendor_id' => Vendor::factory()->repairOrganization()->create()->id,
        ]);

        $response = $this->actingAs($buyer)
            ->withHeader('X-Inertia', 'true')
            ->post(route('repair-orders.returned', $order->id), [
                'dispositions' => [['line_id' => 1, 'disposition' => 'serviceable']],
            ]);

        $this->assertRefusedNotCrashed($response);
        $this->assertTrue(
            session()->has('error') || session('errors')?->any(),
            'The refusal reached neither the flash channel nor the error bag.',
        );
    }

    // --------------------------------------------------- approve and reject

    public function test_approving_your_own_requisition_says_why(): void
    {
        $raiser = $this->user(['requisitions.view', 'requisitions.create', 'requisitions.approve']);

        $requisition = \App\Models\Requisition::factory()->create([
            'requested_by_user_id' => $raiser->id,
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($raiser)->post(route('requisitions.approve', $requisition->id));

        $this->assertRefusedNotCrashed($response);
        $this->assertTrue(
            session()->has('error') || session('errors')?->any(),
            'Approving your own requisition was refused silently.',
        );
    }

    // ------------------------------------------ certify, transfer and adjust

    public function test_transferring_more_than_is_on_hand_says_why(): void
    {
        $keeper = $this->user(['stock.view', 'stock.transfer']);
        $part = Part::factory()->create();

        $response = $this->actingAs($keeper)
            ->withHeader('X-Inertia', 'true')
            ->post(route('stock.transfer'), [
                'part_id' => $part->id,
                'from_store_id' => $this->storeId('bonded'),
                'to_store_id' => $this->storeId('quarantine'),
                'quantity' => 999,
            ]);

        $this->assertRefusedNotCrashed($response);
        $this->assertTrue(
            session()->has('error') || session('errors')?->any(),
            'Transferring more than is on hand was refused silently.',
        );
    }

    // ------------------------------------------------------------- shipments

    public function test_a_posted_shipment_event_cannot_be_rewritten(): void
    {
        $clerk = $this->user(['shipping.view', 'shipping.manage']);

        $shipment = app(\App\Services\Shipping\ShipmentService::class)->create([
            'vendor_id' => Vendor::factory()->create()->id,
            'description' => 'Two cartons of consumables',
            'expected_arrival_date' => today()->addDays(10)->toDateString(),
            'status' => 'Shipped',
            'event_date' => today()->subDays(5)->toDateString(),
        ], $clerk);

        $event = $shipment->events()->latest('id')->firstOrFail();

        // The log is append-only; the model guards it. That guard is a refusal,
        // so it must not present as a fault.
        try {
            $event->update(['note' => 'rewritten']);
            $this->fail('A posted shipment event was rewritten.');
        } catch (\App\Exceptions\Shipping\ShipmentEventImmutableException $e) {
            $this->assertInstanceOf(\App\Exceptions\DomainRefusal::class, $e);
        }
    }
}
