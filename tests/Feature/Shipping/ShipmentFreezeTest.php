<?php

namespace Tests\Feature\Shipping;

use App\Exceptions\DomainRefusal;
use App\Exceptions\Shipping\ShipmentClosedException;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Shipping\ShipmentService;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Closing a shipment finalises it (Phase 11, item 1).
 *
 * The bug these cover: `close()` only stamped `closed_at`, and nothing checked
 * that stamp on the way in. Appending any event re-derives `current_status`
 * from the latest entry, so a closed consignment that arrived in June could be
 * walked backwards to "Shipped" by anyone who could reach the form. Closed
 * shipments were the only finalised document in the system without a lock.
 */
class ShipmentFreezeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->seed(DocumentCounterSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['shipping.view', 'shipping.manage']);

        $this->vendor = Vendor::create(['name' => 'Test Supplier', 'type' => 'supplier', 'is_active' => true]);
    }

    protected function service(): ShipmentService
    {
        return app(ShipmentService::class);
    }

    protected function arrivedShipment(): Shipment
    {
        $shipment = $this->service()->create([
            'vendor_id' => $this->vendor->id,
            'description' => 'Engine shock mounts',
            'expected_arrival_date' => today()->subDays(20)->toDateString(),
            'status' => 'Shipped',
            'event_date' => today()->subDays(30)->toDateString(),
        ], $this->user);

        $this->service()->addEvent($shipment, [
            'status' => 'Arrived at NCAT',
            'event_date' => today()->subDays(20)->toDateString(),
            'is_arrival' => true,
        ], $this->user);

        return $shipment->refresh();
    }

    // ---- The freeze -----------------------------------------------------

    public function test_a_closed_shipment_refuses_further_events(): void
    {
        $shipment = $this->arrivedShipment();
        $this->service()->close($shipment, $this->user);

        $this->expectException(ShipmentClosedException::class);

        $this->service()->addEvent($shipment->refresh(), [
            'status' => 'Shipped',
            'event_date' => today()->toDateString(),
        ], $this->user);
    }

    /** A refusal is the engine working, so it must not present as a fault. */
    public function test_the_refusal_is_a_domain_refusal_not_a_fault(): void
    {
        $shipment = $this->arrivedShipment();
        $this->service()->close($shipment, $this->user);

        try {
            $this->service()->addEvent($shipment->refresh(), [
                'status' => 'Shipped',
                'event_date' => today()->toDateString(),
            ], $this->user);
            $this->fail('A closed shipment accepted a new event.');
        } catch (ShipmentClosedException $e) {
            $this->assertInstanceOf(DomainRefusal::class, $e);
            $this->assertStringContainsString('closed', $e->getMessage());
            $this->assertStringContainsString('Re-open', $e->getMessage());
        }
    }

    /** This is the regression: the header must not walk backwards. */
    public function test_the_header_status_cannot_be_walked_backwards_after_closing(): void
    {
        $shipment = $this->arrivedShipment();
        $this->assertSame('Arrived at NCAT', $shipment->current_status);

        $this->service()->close($shipment, $this->user);

        $this->post(route('shipments.events.store', $shipment->id), [
            'status' => 'Shipped',
            'event_date' => today()->toDateString(),
        ]);

        $shipment->refresh();
        $this->assertSame('Arrived at NCAT', $shipment->current_status);
        $this->assertNotNull($shipment->arrived_at);
    }

    public function test_the_route_refuses_rather_than_returning_a_server_error(): void
    {
        $shipment = $this->arrivedShipment();
        $this->service()->close($shipment, $this->user);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Inertia', 'true')
            ->post(route('shipments.events.store', $shipment->id), [
                'status' => 'Shipped',
                'event_date' => today()->toDateString(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode(), 'The freeze surfaced as a server fault.');
        $this->assertSame(1, $shipment->refresh()->events()->where('status', 'Shipped')->count(),
            'Only the original opening event should carry that status.');
    }

    // ---- The way back in ------------------------------------------------

    public function test_reopening_clears_the_close_and_lets_a_correction_through(): void
    {
        $shipment = $this->arrivedShipment();
        $this->service()->close($shipment, $this->user);
        $this->assertNotNull($shipment->refresh()->closed_at);

        $this->service()->reopen($shipment, 'Arrival was recorded against the wrong date.', $this->user);
        $this->assertNull($shipment->refresh()->closed_at);

        $this->service()->addEvent($shipment, [
            'status' => 'Arrived at NCAT',
            'event_date' => today()->subDays(19)->toDateString(),
            'note' => 'Correcting the arrival date.',
        ], $this->user);

        $this->assertSame('Arrived at NCAT', $shipment->refresh()->current_status);
    }

    /** Nothing is deleted: the timeline keeps every entry across the cycle. */
    public function test_reopening_removes_nothing_from_the_timeline(): void
    {
        $shipment = $this->arrivedShipment();
        $before = $shipment->events()->count();

        $this->service()->close($shipment, $this->user);
        $this->service()->reopen($shipment, 'Correction needed.', $this->user);

        $this->assertSame($before, $shipment->refresh()->events()->count());
    }

    public function test_reopening_is_audit_logged_with_its_reason(): void
    {
        $shipment = $this->arrivedShipment();
        $this->service()->close($shipment, $this->user);
        $this->service()->reopen($shipment, 'Arrival date was wrong.', $this->user);

        $entry = Activity::where('log_name', 'shipment')
            ->where('description', 'like', 'Re-opened%')
            ->latest('id')
            ->first();

        $this->assertNotNull($entry, 'Re-opening a shipment left no activity log entry.');
        $this->assertSame($this->user->id, $entry->causer_id);
        $this->assertStringContainsString('Arrival date was wrong.', $entry->description);
        $this->assertSame('Arrival date was wrong.', $entry->properties['reason'] ?? null);
    }

    public function test_reopening_an_open_shipment_changes_nothing(): void
    {
        $shipment = $this->arrivedShipment();

        $this->service()->reopen($shipment, 'No-op.', $this->user);

        $this->assertNull($shipment->refresh()->closed_at);
        $this->assertSame(0, Activity::where('description', 'like', 'Re-opened%')->count());
    }

    // ---- The gate -------------------------------------------------------

    public function test_reopening_requires_shipping_manage(): void
    {
        $shipment = $this->arrivedShipment();
        $this->service()->close($shipment, $this->user);

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('shipping.view');

        $this->actingAs($viewer)
            ->post(route('shipments.reopen', $shipment->id), ['reason' => 'Trying it on.'])
            ->assertForbidden();

        $this->assertNotNull($shipment->refresh()->closed_at);
    }

    public function test_reopening_demands_a_reason(): void
    {
        $shipment = $this->arrivedShipment();
        $this->service()->close($shipment, $this->user);

        $this->actingAs($this->user)
            ->post(route('shipments.reopen', $shipment->id), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertNotNull($shipment->refresh()->closed_at, 'The shipment re-opened without a reason.');
    }

    public function test_closing_is_audit_logged_too(): void
    {
        $shipment = $this->arrivedShipment();
        $this->service()->close($shipment, $this->user);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'shipment',
            'description' => "Closed shipment {$shipment->reference}",
            'causer_id' => $this->user->id,
        ]);
    }
}
