<?php

namespace Tests\Feature\Shipping;

use App\Exceptions\Shipping\ShipmentEventImmutableException;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Documents\PurchaseOrderService;
use App\Services\Shipping\ShipmentService;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ShipmentTest extends TestCase
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
        $this->user->givePermissionTo(['shipping.view', 'shipping.manage', 'receiving.view', 'receiving.post', 'orders.view']);

        $this->vendor = Vendor::create(['name' => 'Test Supplier', 'type' => 'supplier', 'is_active' => true]);
    }

    protected function service(): ShipmentService
    {
        return app(ShipmentService::class);
    }

    /** @param  array<string, mixed>  $overrides */
    protected function shipment(array $overrides = []): Shipment
    {
        return $this->service()->create(array_merge([
            'vendor_id' => $this->vendor->id,
            'description' => 'Two cartons of consumables',
            'expected_arrival_date' => today()->addDays(10)->toDateString(),
            'status' => 'Shipped',
            'event_date' => today()->subDays(5)->toDateString(),
        ], $overrides), $this->user);
    }

    // ---- Reference and header -------------------------------------------

    public function test_a_shipment_takes_its_reference_from_its_own_counter_series(): void
    {
        $first = $this->shipment();
        $second = $this->shipment();

        $year = now()->format('y');
        $this->assertSame("SHP-{$year}-0001", $first->reference);
        $this->assertSame("SHP-{$year}-0002", $second->reference);
    }

    // ---- Append-only ------------------------------------------------------

    public function test_a_posted_event_cannot_be_edited(): void
    {
        $event = $this->shipment()->events()->first();

        $this->expectException(ShipmentEventImmutableException::class);
        $event->update(['status' => 'Rewritten history']);
    }

    public function test_a_posted_event_cannot_be_deleted(): void
    {
        $event = $this->shipment()->events()->first();

        $this->expectException(ShipmentEventImmutableException::class);
        $event->delete();
    }

    public function test_no_route_exists_that_could_edit_or_remove_an_event(): void
    {
        $eventRoutes = collect(Route::getRoutes())
            ->filter(fn ($r) => str_contains((string) $r->uri(), 'events'))
            ->flatMap(fn ($r) => $r->methods())
            ->unique()
            ->values();

        $this->assertNotContains('PUT', $eventRoutes);
        $this->assertNotContains('PATCH', $eventRoutes);
        $this->assertNotContains('DELETE', $eventRoutes);
    }

    public function test_a_correction_is_recorded_as_a_superseding_event(): void
    {
        $shipment = $this->shipment();

        $this->actingAs($this->user)
            ->post(route('shipments.events.store', $shipment), [
                'status' => 'Cleared customs',
                'event_date' => today()->subDays(1)->toDateString(),
                'note' => 'Supersedes the earlier entry: the release note was dated a day later.',
            ])->assertRedirect();

        $this->assertSame(2, $shipment->events()->count());
        $this->assertSame('Cleared customs', $shipment->refresh()->current_status);
    }

    // ---- Denormalised status ---------------------------------------------

    public function test_the_header_status_always_matches_the_latest_event(): void
    {
        $shipment = $this->shipment();

        $this->service()->addEvent($shipment, [
            'status' => 'Arrived at local port', 'event_date' => today()->subDays(3)->toDateString(),
        ], $this->user);
        $this->service()->addEvent($shipment, [
            'status' => 'In transit to NCAT', 'event_date' => today()->subDay()->toDateString(),
        ], $this->user);

        $shipment->refresh();
        $this->assertSame('In transit to NCAT', $shipment->current_status);
        $this->assertSame(today()->subDay()->toDateString(), $shipment->current_status_date->toDateString());
        $this->assertSame($shipment->events()->get()->last()->status, $shipment->current_status);
    }

    public function test_same_day_events_keep_the_order_they_were_entered_in(): void
    {
        $shipment = $this->shipment(['status' => 'Shipped', 'event_date' => today()->toDateString()]);
        $day = today()->toDateString();

        foreach (['Arrived at local port', 'Cleared customs', 'Arrived at NCAT'] as $status) {
            $this->service()->addEvent($shipment, ['status' => $status, 'event_date' => $day], $this->user);
        }

        $this->assertSame(
            ['Shipped', 'Arrived at local port', 'Cleared customs', 'Arrived at NCAT'],
            $shipment->events()->get()->pluck('status')->all(),
        );
        $this->assertSame('Arrived at NCAT', $shipment->refresh()->current_status);
    }

    // ---- Overdue ----------------------------------------------------------

    public function test_a_shipment_past_its_expected_date_and_not_arrived_is_overdue(): void
    {
        $shipment = $this->shipment(['expected_arrival_date' => today()->subDays(4)->toDateString()]);

        $this->assertTrue($shipment->isOverdue());
        $this->assertSame(4, $shipment->daysOverdue());
        $this->assertSame(1, $this->service()->overdue()->count());
    }

    public function test_arrival_stops_a_shipment_being_overdue(): void
    {
        $shipment = $this->shipment(['expected_arrival_date' => today()->subDays(4)->toDateString()]);

        $this->service()->addEvent($shipment, [
            'status' => 'Arrived at NCAT', 'event_date' => today()->toDateString(), 'is_arrival' => true,
        ], $this->user);

        $shipment->refresh();
        $this->assertTrue($shipment->hasArrived());
        $this->assertFalse($shipment->isOverdue());
        $this->assertSame(0, $this->service()->overdue()->count());
    }

    public function test_a_shipment_with_no_expected_date_is_never_overdue(): void
    {
        $this->assertFalse($this->shipment(['expected_arrival_date' => null])->isOverdue());
    }

    public function test_a_later_arrival_correction_does_not_move_the_recorded_arrival_date(): void
    {
        $shipment = $this->shipment();

        $this->service()->addEvent($shipment, [
            'status' => 'Arrived at NCAT', 'event_date' => today()->subDays(3)->toDateString(), 'is_arrival' => true,
        ], $this->user);
        $this->service()->addEvent($shipment, [
            'status' => 'Arrived at NCAT', 'event_date' => today()->toDateString(), 'is_arrival' => true,
            'note' => 'Duplicate entry, the goods landed on the earlier date.',
        ], $this->user);

        $this->assertSame(
            today()->subDays(3)->toDateString(),
            $shipment->refresh()->arrived_at->toDateString(),
        );
    }

    // ---- SRV handoff ------------------------------------------------------

    public function test_creating_an_srv_from_a_shipment_prefills_the_outstanding_order_lines(): void
    {
        $order = $this->issuedOrder();

        $shipment = $this->shipment([
            'source_kind' => 'purchase_order',
            'source_id' => $order->id,
        ]);
        $this->service()->addEvent($shipment, [
            'status' => 'Arrived at NCAT', 'event_date' => today()->toDateString(), 'is_arrival' => true,
        ], $this->user);

        $response = $this->actingAs($this->user)
            ->withHeaders($this->inertia())
            ->get(route('receiving.create', ['shipment' => $shipment->id]));

        $response->assertOk();
        $prefill = $response->json('props.shipmentPrefill');

        $this->assertSame($shipment->id, $prefill['shipment_id']);
        $this->assertSame('Test Supplier', $prefill['supplier']);
        $this->assertSame($order->id, $prefill['purchase_order_id']);
        $this->assertCount(2, $prefill['lines']);
        $this->assertEqualsCanonicalizing([4.0, 2.0], array_column($prefill['lines'], 'quantity'));
    }

    public function test_an_srv_raised_from_a_shipment_is_recorded_against_it(): void
    {
        $shipment = $this->shipment();
        $this->service()->addEvent($shipment, [
            'status' => 'Arrived at NCAT', 'event_date' => today()->toDateString(), 'is_arrival' => true,
        ], $this->user);

        $part = \App\Models\Part::factory()->create();

        $this->actingAs($this->user)->post(route('receiving.store'), [
            'srv_date' => today()->toDateString(),
            'destination_store_id' => \App\Models\Store::where('type', 'quarantine')->value('id'),
            'shipment_id' => $shipment->id,
            'items' => [['part_id' => $part->id, 'quantity' => 3]],
        ])->assertRedirect();

        $this->assertSame(1, $shipment->srvs()->count());
    }

    public function test_the_srv_handoff_is_refused_until_the_shipment_has_arrived(): void
    {
        $shipment = $this->shipment();

        $this->actingAs($this->user)
            ->get(route('shipments.srv', $shipment))
            ->assertStatus(422);
    }

    // ---- Permissions ------------------------------------------------------

    public function test_shipping_view_is_required_to_see_the_list(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('shipments.index'))->assertForbidden();
        $this->actingAs($this->user)->get(route('shipments.index'))->assertOk();
    }

    public function test_shipping_manage_is_required_to_record_an_event(): void
    {
        $shipment = $this->shipment();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('shipping.view');

        $this->actingAs($viewer)
            ->post(route('shipments.events.store', $shipment), [
                'status' => 'Cleared customs', 'event_date' => today()->toDateString(),
            ])->assertForbidden();

        $this->assertSame(1, $shipment->events()->count());
    }

    // ---- Multi-row ordering regression (observation #12 class) -----------

    public function test_the_admin_status_list_keeps_the_order_it_was_arranged_in(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('shipping.manage');

        $ordered = ['Booked', 'Shipped', 'At the port', 'Cleared', 'Arrived at NCAT'];

        $this->actingAs($admin)
            ->put(route('admin.shipment-statuses.update'), [
                'statuses' => $ordered,
                'arrival_status' => 'Arrived at NCAT',
            ])->assertRedirect();

        $this->assertSame($ordered, app(\App\Services\Shipping\ShipmentSettings::class)->statuses());
    }

    public function test_free_text_statuses_are_accepted_alongside_the_suggestions(): void
    {
        $shipment = $this->shipment();

        $this->actingAs($this->user)
            ->post(route('shipments.events.store', $shipment), [
                'status' => 'Held by customs pending an end-user certificate',
                'event_date' => today()->toDateString(),
            ])->assertRedirect();

        $this->assertSame(
            'Held by customs pending an end-user certificate',
            $shipment->refresh()->current_status,
        );
    }

    protected function issuedOrder(): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'order_date' => today()->toDateString(),
            'vendor_id' => $this->vendor->id,
            'created_by_user_id' => $this->user->id,
        ]);

        app(PurchaseOrderService::class)->saveLines($order, [
            ['description' => 'Shock mounts', 'part_number' => 'D44-7106', 'qty_to_order' => 4],
            ['description' => 'Harness', 'part_number' => '5-01-1C0710', 'qty_to_order' => 2],
        ]);

        return app(PurchaseOrderService::class)->issue($order, $this->user);
    }

    /**
     * Drive a page as an Inertia XHR so the assertion is on the JSON page
     * object rather than on a blade render that would need the Vite manifest.
     *
     * @return array<string, string>
     */
    protected function inertia(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ];
    }
}
