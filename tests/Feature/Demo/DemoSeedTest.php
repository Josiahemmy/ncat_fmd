<?php

namespace Tests\Feature\Demo;

use App\Models\DemoState;
use App\Models\Requisition;
use App\Models\User;
use App\Services\Demo\DemoMode;
use App\Services\Stock\StockAlertService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * demo:seed builds a coherent, backdated demo narrative that lights up every
 * dashboard mechanism. Reference data must be seeded first (the command builds
 * on the real fleet/stores/counters).
 */
class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class); // reference data + roles + admin
    }

    protected function alerts(): StockAlertService
    {
        return app(StockAlertService::class);
    }

    public function test_seed_populates_every_transactional_table(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        foreach ([
            'parts', 'part_batches', 'part_serials', 'stock_balances', 'stock_movements',
            'work_orders', 'requisitions', 'srvs', 'srv_items', 'sivs', 'siv_items', 'activity_log',
            'purchase_orders', 'repair_orders', 'shipments', 'shipment_events', 'loans',
        ] as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), "Expected {$table} to be seeded.");
        }
    }

    /**
     * Each of the four Phase 8 narratives exists to make one thing visible in
     * the demo, so each is asserted by the thing it is supposed to show.
     */
    public function test_seed_tells_the_shipping_and_loans_story(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $inTransit = \App\Models\Shipment::whereNull('arrived_at')->get();
        $this->assertGreaterThan(0, $inTransit->count(), 'a consignment still on the way');
        $this->assertGreaterThanOrEqual(4, $inTransit->first()->events()->count(),
            'the mid-transit shipment needs several events for the timeline to read as one');
        $this->assertTrue($inTransit->contains(fn ($s) => $s->isOverdue()), 'an overdue shipment');
        $this->assertTrue($inTransit->contains(fn ($s) => $s->source_type !== null),
            'a shipment raised against the demo purchase order');

        $arrived = \App\Models\Shipment::whereNotNull('arrived_at')->first();
        $this->assertNotNull($arrived, 'a shipment that landed');
        $this->assertGreaterThan(0, $arrived->srvs()->count(), 'the arrived shipment produced an SRV');

        $this->assertGreaterThan(0, \App\Models\Loan::overdue()->where('direction', 'out')->count(),
            'an overdue outbound loan to light the alert');

        $inbound = \App\Models\Loan::inbound()->whereNotNull('installed_aircraft_id')->first();
        $this->assertNotNull($inbound, 'a borrowed item fitted to an aircraft');
        $this->assertTrue($inbound->serial?->is_loaned, 'and marked as loaned property');
    }

    /**
     * The demo exists to show management working modules, and a list with one
     * row does not do that. These are floors rather than exact counts, so the
     * narrative can be adjusted without the test becoming a nuisance, but they
     * fail if a module is ever thinned back to a single example.
     */
    public function test_seed_puts_enough_volume_in_the_new_modules_to_demonstrate(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $this->assertGreaterThanOrEqual(10, \App\Models\Vendor::count(), 'vendor book');
        $this->assertGreaterThanOrEqual(8, \App\Models\PurchaseOrder::count(), 'purchase orders');
        $this->assertGreaterThanOrEqual(5, \App\Models\RepairOrder::count(), 'repair orders');
        $this->assertGreaterThanOrEqual(6, \App\Models\Shipment::count(), 'shipments');
        $this->assertGreaterThanOrEqual(6, \App\Models\Loan::count(), 'loans');

        // Filters need something to separate, so the statuses have to vary.
        $poStatuses = \App\Models\PurchaseOrder::pluck('status')->unique();
        foreach (['draft', 'issued', 'partially_received', 'received', 'closed', 'cancelled'] as $status) {
            $this->assertTrue($poStatuses->contains($status), "a purchase order in status {$status}");
        }

        $roStatuses = \App\Models\RepairOrder::pluck('status')->unique();
        foreach (['draft', 'issued', 'at_vendor', 'returned', 'closed'] as $status) {
            $this->assertTrue($roStatuses->contains($status), "a repair order in status {$status}");
        }

        // Every vendor must carry the marker, because that is the only thing
        // that gets them removed again: they are reference data, not
        // transactional, so the purge does not truncate the table.
        $this->assertSame(
            \App\Models\Vendor::count(),
            \App\Models\Vendor::where('is_demo', true)->count(),
            'an unflagged demo vendor would survive demo:purge',
        );

        $this->assertGreaterThanOrEqual(1, \App\Models\Shipment::whereNotNull('closed_at')->count(),
            'a closed shipment, so the frozen timeline can be shown');
        $this->assertGreaterThanOrEqual(2, \App\Models\Srv::whereNotNull('shipment_id')->count(),
            'two arrivals that produced receipt vouchers');
        $this->assertGreaterThanOrEqual(1, \App\Models\Srv::whereNotNull('purchase_order_id')->count(),
            'a receipt booked against a purchase order, so the linkage is visible');
    }

    /**
     * The attachment feature is only demonstrable if the seeded data actually
     * carries paperwork. The files are generated at seed time, so this also
     * checks they reached disk rather than only the table.
     */
    public function test_seed_attaches_real_paperwork_to_a_shipment_event(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $attachments = \App\Models\ShipmentEventAttachment::all();
        $this->assertGreaterThanOrEqual(1, $attachments->count(), 'paperwork on a shipment event');

        foreach ($attachments as $a) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Storage::disk($a->disk)->exists($a->path),
                "seeded attachment {$a->original_name} has no file on disk",
            );
            $this->assertGreaterThan(0, $a->size_bytes);
            $this->assertSame('application/pdf', $a->mime_type);
        }
    }

    /** The proof that borrowed stock never inflates what NCAT reports it owns. */
    public function test_the_demo_inbound_loan_stays_out_of_stock_value(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $loanIn = \App\Models\Store::loanIn();
        $this->assertGreaterThan(0, \App\Models\StockBalance::where('store_id', $loanIn->id)->sum('quantity'),
            'the demo does hold borrowed stock');

        $summaryStores = app(\App\Services\Reports\ReportService::class)
            ->rows('stock-summary')->pluck('Store')->unique();

        $this->assertNotContains($loanIn->name, $summaryStores);
    }

    public function test_seed_lights_up_every_alert_scope(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $this->assertGreaterThan(0, $this->alerts()->belowReorder()->count(), 'below reorder');
        $this->assertGreaterThan(0, $this->alerts()->belowMin()->count(), 'below min');
        $this->assertGreaterThan(0, $this->alerts()->aboveMax()->count(), 'above max');
        $this->assertGreaterThan(0, $this->alerts()->expired()->count(), 'expired');
        $this->assertGreaterThan(0, $this->alerts()->expiringWithin(90)->count(), 'expiring');
        $this->assertGreaterThan(0, $this->alerts()->quarantineAging(12)->count(), 'quarantine aging 12+ days');
    }

    public function test_seed_covers_every_requisition_status(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $statuses = Requisition::query()->distinct()->pluck('status')->all();
        foreach (['draft', 'submitted', 'approved', 'rejected', 'issued', 'closed'] as $status) {
            $this->assertContains($status, $statuses, "Expected a requisition in '{$status}' status.");
        }
    }

    public function test_seed_creates_demo_users_flag_and_counter_snapshot(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $demoUsers = User::where('is_demo', true)->get();
        $this->assertGreaterThanOrEqual(4, $demoUsers->count());
        $this->assertTrue($demoUsers->every(fn ($u) => str_ends_with($u->email, '@demo.ncatfmd.local')));

        $this->assertTrue(app(DemoMode::class)->isActive());
        $snapshot = DemoState::query()->value('counters_snapshot');
        $this->assertArrayHasKey('work_order', $snapshot);
        // Counters advanced past their snapshot as documents were seeded.
        $this->assertGreaterThan($snapshot['work_order'], DB::table('document_counters')->where('series', 'work_order')->value('next_number'));
    }

    public function test_seed_backdates_movements_across_weeks(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $oldest = DB::table('stock_movements')->min('posted_at');
        $this->assertNotNull($oldest);
        // Oldest movement is comfortably in the past (multi-week history).
        $this->assertTrue(\Illuminate\Support\Carbon::parse($oldest)->lt(now()->subWeeks(4)), 'History should span several weeks.');
    }

    public function test_seed_refuses_to_run_twice_without_force(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        $this->artisan('demo:seed')->assertFailed();
        // --force overrides the guard.
        $this->artisan('demo:seed', ['--force' => true])->assertSuccessful();
    }
}
