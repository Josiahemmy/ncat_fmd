<?php

namespace Tests\Feature\Demo;

use App\Models\Aircraft;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Demo\DemoBackup;
use App\Services\Demo\DemoMode;
use App\Services\Demo\DemoPurger;
use App\Services\Demo\DemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * demo:purge is the destructive command — tested hardest. It must back up
 * first (abort if that fails), truncate every transactional table, delete demo
 * users, restore counters, clear the flag, and preserve all reference data.
 */
class DemoPurgeTest extends TestCase
{
    use RefreshDatabase;

    /** Safety flags for non-interactive test invocation. */
    protected const SAFE = [
        '--i-understand-this-deletes-all-transactional-data' => true,
        '--no-interaction-confirmed' => true,
    ];

    protected const TRANSACTIONAL = [
        'stock_movements', 'stock_balances', 'part_serials', 'part_batches', 'parts',
        'work_orders', 'requisitions', 'srvs', 'srv_items', 'sivs', 'siv_items',
        'purchase_orders', 'purchase_order_lines', 'repair_orders', 'repair_order_lines',
        'notifications', 'activity_log',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        // Make the backup a no-op success by default (real backup needs a DB dump tool).
        $this->app->bind(DemoBackup::class, fn () => new class extends DemoBackup
        {
            public function run(): bool
            {
                return true;
            }
        });
    }

    protected function seedDemo(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
    }

    public function test_purge_truncates_every_transactional_table(): void
    {
        $this->seedDemo();
        foreach (self::TRANSACTIONAL as $t) {
            $this->assertGreaterThan(0, DB::table($t)->count(), "precondition: {$t} seeded");
        }

        $this->artisan('demo:purge', self::SAFE)->assertSuccessful();

        foreach (self::TRANSACTIONAL as $t) {
            $this->assertSame(0, DB::table($t)->count(), "{$t} must be empty after purge");
        }
    }

    public function test_purge_preserves_reference_data_and_real_users(): void
    {
        $realAdmin = User::where('email', 'superadmin@ncatfmd.com.ng')->firstOrFail();
        $aircraftBefore = Aircraft::count();
        $storesBefore = DB::table('stores')->count();
        $rolesBefore = DB::table('roles')->count();
        $ataBefore = DB::table('ata_chapters')->count();

        $this->seedDemo();
        $this->artisan('demo:purge', self::SAFE)->assertSuccessful();

        $this->assertSame($aircraftBefore, Aircraft::count());
        $this->assertSame($storesBefore, DB::table('stores')->count());
        $this->assertSame($rolesBefore, DB::table('roles')->count());
        $this->assertSame($ataBefore, DB::table('ata_chapters')->count());
        $this->assertNotNull($realAdmin->fresh(), 'real admin must survive');
        $this->assertSame(0, User::where('is_demo', true)->count(), 'demo users removed');
    }

    public function test_purge_restores_counters_and_marks_unconfirmed(): void
    {
        $before = DB::table('document_counters')->pluck('next_number', 'series')->all();

        $this->seedDemo();
        // Counters advanced during seeding.
        $this->assertGreaterThan($before['work_order'], DB::table('document_counters')->where('series', 'work_order')->value('next_number'));

        $this->artisan('demo:purge', self::SAFE)->assertSuccessful();

        foreach ($before as $series => $next) {
            $row = DB::table('document_counters')->where('series', $series)->first();
            $this->assertSame((int) $next, (int) $row->next_number, "counter {$series} restored");
            $this->assertFalse((bool) $row->confirmed, "counter {$series} marked unconfirmed");
        }
    }

    public function test_purge_clears_demo_mode_flag(): void
    {
        $this->seedDemo();
        $this->assertTrue(app(DemoMode::class)->isActive());

        $this->artisan('demo:purge', self::SAFE)->assertSuccessful();

        app(DemoMode::class)->bust();
        $this->assertFalse(app(DemoMode::class)->isActive());
    }

    /**
     * Vendors are reference data, so the purge cannot empty the table. The
     * guarantee for them is narrower: nothing demo-flagged survives, including
     * soft-deleted rows, and a vendor the department added stays put.
     */
    public function test_purge_removes_demo_vendors_and_keeps_real_ones(): void
    {
        $real = Vendor::factory()->create(['name' => 'REAL DEPARTMENT VENDOR']);

        $this->seedDemo();

        $this->assertGreaterThan(0, Vendor::where('is_demo', true)->count(), 'precondition: demo vendors seeded');

        $this->artisan('demo:purge', self::SAFE)->assertSuccessful();

        $this->assertSame(0, Vendor::withTrashed()->where('is_demo', true)->count());
        $this->assertSame(0, app(DemoPurger::class)->report()['demo_vendors']);
        $this->assertTrue(app(DemoPurger::class)->report()['clean']);
        $this->assertDatabaseHas('vendors', ['id' => $real->id, 'name' => 'REAL DEPARTMENT VENDOR']);
    }

    public function test_purge_refuses_without_the_safety_flag(): void
    {
        $this->seedDemo();
        $partsBefore = DB::table('parts')->count();

        $this->artisan('demo:purge', ['--no-interaction-confirmed' => true])->assertFailed();

        // Nothing was truncated.
        $this->assertSame($partsBefore, DB::table('parts')->count());
    }

    public function test_purge_aborts_when_backup_fails(): void
    {
        $this->seedDemo();
        $partsBefore = DB::table('parts')->count();

        // Force the backup to fail.
        $this->app->bind(DemoBackup::class, fn () => new class extends DemoBackup
        {
            public function run(): bool
            {
                return false;
            }
        });

        $this->artisan('demo:purge', self::SAFE)->assertFailed();

        // Purge must NOT have run — data intact.
        $this->assertSame($partsBefore, DB::table('parts')->count());
        $this->assertGreaterThan(0, DB::table('stock_movements')->count());
    }

    public function test_seed_purge_seed_cycle_is_rerunnable(): void
    {
        $this->seedDemo();
        $this->artisan('demo:purge', self::SAFE)->assertSuccessful();
        // Second demo session from a clean slate.
        $this->artisan('demo:seed')->assertSuccessful();
        $this->assertGreaterThan(0, DB::table('parts')->count());
        $this->assertTrue(app(DemoMode::class)->isActive());
    }
}
