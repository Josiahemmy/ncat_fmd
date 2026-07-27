<?php

namespace Tests\Feature\Demo;

use App\Models\User;
use App\Services\Demo\DemoBackup;
use App\Services\Demo\DemoMode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `demo:reseed` is the one-click path for refreshing demonstration data, so the
 * thing worth pinning is what stops it: it must only ever delete rows the demo
 * itself created, and it decides that from the demo-mode flag rather than from
 * whether the tables happen to look like demo data.
 */
class DemoReseedTest extends TestCase
{
    use RefreshDatabase;

    protected const SAFE = ['--i-understand-this-replaces-the-current-demo' => true];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        // The safety backup shells out to a dump tool; stub it so the suite
        // does not depend on one being installed.
        $this->mock(DemoBackup::class, fn ($m) => $m->shouldReceive('run')->andReturnTrue());
    }

    public function test_it_refuses_without_the_acknowledgement(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $this->artisan('demo:reseed')->assertFailed();

        $this->assertTrue(app(DemoMode::class)->isActive(), 'the existing demo must survive a refused reseed');
    }

    /**
     * The case that matters. Data with demo mode off is real work as far as
     * this command can tell, and it must not be touched.
     */
    public function test_it_refuses_when_demo_mode_is_off_even_though_data_exists(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        // Simulate data that the demo did not create: keep the rows, drop the flag.
        app(DemoMode::class)->deactivate();
        $partsBefore = DB::table('parts')->count();
        $this->assertGreaterThan(0, $partsBefore, 'precondition: there is data to protect');

        $this->artisan('demo:reseed', self::SAFE)->assertFailed();

        $this->assertSame($partsBefore, DB::table('parts')->count(), 'reseed deleted data it had no business touching');
    }

    public function test_it_replaces_an_active_demo_with_a_fresh_one(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $firstShipment = DB::table('shipments')->orderBy('id')->value('id');
        $this->assertNotNull($firstShipment);

        $this->artisan('demo:reseed', self::SAFE)->assertSuccessful();

        $this->assertTrue(app(DemoMode::class)->isActive(), 'demo mode should be back on after reseeding');
        $this->assertGreaterThan(0, DB::table('shipments')->count(), 'a fresh narrative should exist');

        // The old rows are gone rather than added to: the ids move on.
        $this->assertNull(
            DB::table('shipments')->where('id', $firstShipment)->first(),
            'the previous demo rows should have been removed, not seeded on top of',
        );

        // And the demo users are still the ones you can sign in with.
        $this->assertGreaterThan(0, User::where('is_demo', true)->count());
    }

    public function test_reseeding_leaves_no_duplicate_vendors(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        $before = DB::table('vendors')->count();

        $this->artisan('demo:reseed', self::SAFE)->assertSuccessful();

        $this->assertSame($before, DB::table('vendors')->count(),
            'reseeding should replace the vendor book, not stack a second copy on it');
    }

    public function test_status_reports_without_changing_anything(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();
        $before = DB::table('parts')->count();

        $this->artisan('demo:status')->assertSuccessful();

        $this->assertSame($before, DB::table('parts')->count());
        $this->assertTrue(app(DemoMode::class)->isActive());
    }
}
