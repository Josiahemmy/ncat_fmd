<?php

namespace Tests\Feature\Dashboard;

use App\Models\Aircraft;
use App\Models\Part;
use App\Models\PartBatch;
use App\Models\Requisition;
use App\Models\Store;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Dashboard\DashboardService;
use App\Services\Stock\StockService;
use Database\Seeders\RolesAndAdminSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The consolidated dashboard aggregation service (Phase 4). Proves the alert
 * counts track the stock-alert scopes, the KPIs sum real data, the charts
 * aggregate server-side, and the 60s cache is busted when stock is posted.
 */
class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->user = User::factory()->create();
    }

    protected function dashboard(): DashboardService
    {
        return app(DashboardService::class);
    }

    protected function stock(): StockService
    {
        return app(StockService::class);
    }

    protected function bonded(): Store
    {
        return Store::where('slug', 'bonded')->firstOrFail();
    }

    protected function quarantine(): Store
    {
        return Store::where('slug', 'quarantine')->firstOrFail();
    }

    public function test_kpis_reflect_real_data(): void
    {
        $a = Part::factory()->create(['unit_price' => 1000]);   // 5 × 1000 = 5000
        $b = Part::factory()->create(['unit_price' => 250]);    // 8 × 250  = 2000
        Part::factory()->create(['unit_price' => null]);        // priced-unknown: excluded from value, counted in parts

        $this->stock()->openingBalance(part: $a, store: $this->bonded(), quantity: 5, user: $this->user);
        $this->stock()->openingBalance(part: $b, store: $this->bonded(), quantity: 8, user: $this->user);

        WorkOrder::factory()->create(['status' => 'open']);
        WorkOrder::factory()->create(['status' => 'in_progress']);
        WorkOrder::factory()->closed()->create();

        $fuel = Part::factory()->create(['is_fuel' => true]);
        $this->stock()->fuelReceive(part: $fuel, quantity: 3200, user: $this->user);

        $kpis = $this->dashboard()->kpis();

        $this->assertSame(4, $kpis['distinct_parts']);
        $this->assertEqualsWithDelta(7000.0, (float) $kpis['stock_value'], 0.01);
        $this->assertSame(2, $kpis['open_work_orders']); // open + in_progress, not closed
        $this->assertEqualsWithDelta(3200.0, (float) $kpis['fuel_litres'], 0.01);
    }

    public function test_alert_counts_match_the_stock_alert_scopes(): void
    {
        // Below reorder / min.
        $low = Part::factory()->create(['reorder_level' => 10, 'min_level' => 5]);
        $this->stock()->openingBalance(part: $low, store: $this->bonded(), quantity: 4, user: $this->user);

        // Above max.
        $over = Part::factory()->create(['max_level' => 10]);
        $this->stock()->openingBalance(part: $over, store: $this->bonded(), quantity: 25, user: $this->user);

        // Expiring & expired batches.
        $shelf = Part::factory()->shelfLife()->create();
        PartBatch::create(['part_id' => $shelf->id, 'batch_number' => 'SOON', 'expiry_date' => now()->addDays(30)]);
        PartBatch::create(['part_id' => $shelf->id, 'batch_number' => 'OLD', 'expiry_date' => now()->subDay()]);

        // Quarantine awaiting certification.
        $q = Part::factory()->create();
        $this->stock()->receive(part: $q, store: $this->quarantine(), quantity: 3, user: $this->user);

        // Documents.
        Requisition::factory()->submitted()->create();
        Requisition::factory()->submitted()->create();
        WorkOrder::factory()->create(['status' => 'open']);

        $counts = $this->dashboard()->alertCounts();

        $this->assertSame(1, $counts['below_reorder']);
        $this->assertSame(1, $counts['below_min']);
        $this->assertSame(1, $counts['above_max']);
        $this->assertSame(1, $counts['expiring']);
        $this->assertSame(1, $counts['expired']);
        $this->assertSame(1, $counts['quarantine']);
        $this->assertSame(2, $counts['requisitions_pending']);
        $this->assertSame(1, $counts['open_work_orders']);
    }

    public function test_movements_trend_buckets_in_and_out_by_week(): void
    {
        $part = Part::factory()->create();
        $this->stock()->openingBalance(part: $part, store: $this->bonded(), quantity: 100, user: $this->user);
        $this->stock()->issue(part: $part, store: $this->bonded(), quantity: 10, user: $this->user);

        $trend = $this->dashboard()->charts()['movements_trend'];

        $this->assertNotEmpty($trend);
        // 12 buckets, chronological, each with in/out totals.
        $this->assertCount(12, $trend);
        $last = end($trend);
        $this->assertArrayHasKey('in', $last);
        $this->assertArrayHasKey('out', $last);
        // Latest week carries the opening (in) and the issue (out).
        $this->assertEqualsWithDelta(100.0, (float) $last['in'], 0.01);
        $this->assertEqualsWithDelta(10.0, (float) $last['out'], 0.01);
    }

    public function test_consumption_by_type_sums_issued_quantity_per_aircraft_type(): void
    {
        $typeA = \App\Models\AircraftType::factory()->create(['name' => 'ZZ-TYPE-A']);
        $partA = Part::factory()->create(['aircraft_type_id' => $typeA->id]);
        $this->stock()->openingBalance(part: $partA, store: $this->bonded(), quantity: 50, user: $this->user);
        $this->stock()->issue(part: $partA, store: $this->bonded(), quantity: 12, user: $this->user);

        $rows = collect($this->dashboard()->charts()['consumption_by_type']);
        $row = $rows->firstWhere('type', 'ZZ-TYPE-A');

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(12.0, (float) $row['issued'], 0.01);
    }

    public function test_aggregates_are_cached_and_busted_on_posting(): void
    {
        $part = Part::factory()->create(['reorder_level' => 10]);
        $this->stock()->openingBalance(part: $part, store: $this->bonded(), quantity: 4, user: $this->user);

        $this->assertSame(1, $this->dashboard()->aggregates()['alerts']['below_reorder']);

        // Mutate the balance behind the cache's back — cached value must persist.
        DB::table('stock_balances')->where('part_id', $part->id)->update(['quantity' => 999]);
        $this->assertSame(1, $this->dashboard()->aggregates()['alerts']['below_reorder']);

        // A real posting must bust the cache so the number refreshes.
        $this->stock()->receive(part: $part, store: $this->bonded(), quantity: 500, user: $this->user);
        $this->assertSame(0, $this->dashboard()->aggregates()['alerts']['below_reorder']);
    }

    public function test_recent_activity_is_permission_filtered(): void
    {
        $this->seed(RolesAndAdminSeeder::class);

        // A stock-log line (needs stores.view) and an aircraft-log line.
        activity('stock')->causedBy($this->user)->event('posted')
            ->log('Received 5 of MS-1 into Bonded Store');

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        // A permission-less user acts on nothing — must not see stock lines.
        $restricted = User::factory()->create();

        $adminFeed = $this->dashboard()->recentActivity($admin);
        $this->assertTrue(
            $adminFeed->contains(fn ($a) => ($a['log_name'] ?? null) === 'stock'),
            'Super Admin should see stock-log activity.'
        );

        $restrictedFeed = $this->dashboard()->recentActivity($restricted);
        $this->assertTrue(
            $restrictedFeed->every(fn ($a) => ($a['log_name'] ?? null) !== 'stock'),
            'A user without stores.view must not see stock-log activity.'
        );
    }
}
