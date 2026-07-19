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
        ] as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), "Expected {$table} to be seeded.");
        }
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
