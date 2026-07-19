<?php

namespace Tests\Feature\Aircraft;

use App\Models\Aircraft;
use App\Models\AircraftType;
use App\Models\Part;
use App\Models\PartSerial;
use App\Models\Requisition;
use App\Models\Siv;
use App\Models\SivItem;
use App\Models\Store;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Stock\SerialStateService;
use App\Services\Stock\StockService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AircraftWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create()->assignRole('Super Admin');
    }

    protected function bonded(): Store
    {
        return Store::where('slug', 'bonded')->firstOrFail();
    }

    /**
     * GET a page as an Inertia (XHR) request so the assertion runs against the
     * page object directly — behaviour is proven without a built Vite manifest
     * (page render is a separate, build-gated concern).
     */
    protected function inertiaGet(string $url)
    {
        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version]);
    }

    public function test_fleet_grid_lists_types_with_counts(): void
    {
        $type = AircraftType::factory()->create();
        $a = Aircraft::factory()->create(['aircraft_type_id' => $type->id]);
        WorkOrder::factory()->create(['aircraft_id' => $a->id, 'status' => 'open']);

        $res = $this->actingAs($this->admin)->inertiaGet(route('aircraft-types'))
            ->assertOk()
            ->assertJsonPath('component', 'Aircraft/Fleet');

        $types = collect($res->json('props.types'));
        $row = $types->firstWhere('id', $type->id);
        $this->assertNotNull($row);
        foreach (['name', 'image', 'fleet_count', 'open_wo', 'registrations'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }
        $this->assertSame(1, $row['fleet_count']);
        $this->assertSame(1, $row['open_wo']);
    }

    public function test_workspace_reports_stats_and_parts_on_aircraft(): void
    {
        $aircraft = Aircraft::factory()->create();
        $gps = Part::factory()->serialized()->create();
        $serial = PartSerial::factory()->create(['part_id' => $gps->id, 'status' => 'in_store']);

        // Stock it, then drive a real install (SIV flow: issue serial to aircraft + state transition).
        app(StockService::class)->openingBalance(part: $gps, store: $this->bonded(), quantity: 1, user: $this->admin, serialId: $serial->id);
        app(StockService::class)->issue(part: $gps, store: $this->bonded(), quantity: 1, user: $this->admin, serialId: $serial->id, aircraftId: $aircraft->id);
        app(SerialStateService::class)->install($serial, $aircraft, position: 'FWD AVIONICS BAY', user: $this->admin);

        WorkOrder::factory()->create(['aircraft_id' => $aircraft->id, 'status' => 'open']);
        Requisition::factory()->submitted()->create(['aircraft_id' => $aircraft->id]);

        $res = $this->actingAs($this->admin)->inertiaGet(route('aircraft.show', $aircraft))
            ->assertOk()
            ->assertJsonPath('component', 'Aircraft/Workspace')
            ->assertJsonPath('props.stats.open_work_orders', 1)
            ->assertJsonPath('props.stats.pending_requisitions', 1)
            ->assertJsonPath('props.stats.parts_installed', 1);

        $parts = $res->json('props.partsOnAircraft');
        $this->assertCount(1, $parts);
        $this->assertSame($serial->serial_number, $parts[0]['serial_number']);
        $this->assertSame('FWD AVIONICS BAY', $parts[0]['position']);
        $this->assertSame($gps->part_number, $parts[0]['part_number']);
        $this->assertNotNull($parts[0]['installed_at']);
        $this->assertArrayHasKey('href', $parts[0]);
    }

    public function test_parts_on_aircraft_clears_after_removal(): void
    {
        $aircraft = Aircraft::factory()->create();
        $serial = PartSerial::factory()->installed($aircraft)->create();

        app(SerialStateService::class)->remove($serial, 'at_repair', reason: 'unserviceable');

        $this->actingAs($this->admin)->inertiaGet(route('aircraft.show', $aircraft))
            ->assertOk()
            ->assertJsonPath('props.stats.parts_installed', 0)
            ->assertJsonCount(0, 'props.partsOnAircraft');
    }

    public function test_workspace_links_pre_filter_the_registers(): void
    {
        $aircraft = Aircraft::factory()->create();

        $this->actingAs($this->admin)->inertiaGet(route('aircraft.show', $aircraft))
            ->assertOk()
            ->assertJsonPath('props.links.work_orders', route('work-orders.index', ['aircraft' => $aircraft->id]))
            ->assertJsonPath('props.links.requisitions', route('requisitions.index', ['aircraft' => $aircraft->id]))
            ->assertJsonPath('props.links.issuing', route('issuing.index', ['aircraft' => $aircraft->id]));
    }

    public function test_siv_index_filters_by_aircraft_via_requisition_link(): void
    {
        $aircraftA = Aircraft::factory()->create();

        $part = Part::factory()->create();
        $reqA = Requisition::factory()->create(['aircraft_id' => $aircraftA->id]);
        $sivA = Siv::factory()->create();
        SivItem::create(['siv_id' => $sivA->id, 'line_no' => 1, 'requisition_id' => $reqA->id, 'part_id' => $part->id, 'source_store_id' => $this->bonded()->id, 'description' => 'x', 'qty_required' => 1]);

        $sivB = Siv::factory()->create(); // no aircraft link

        $rows = $this->actingAs($this->admin)->get(route('issuing.index', ['aircraft' => $aircraftA->id]))
            ->assertOk()
            ->viewData('page')['props']['sivs'];

        $ids = collect($rows)->pluck('id');
        $this->assertTrue($ids->contains($sivA->id));
        $this->assertFalse($ids->contains($sivB->id));
    }

    public function test_tally_index_filters_by_aircraft_type(): void
    {
        $typeA = AircraftType::factory()->create();
        $typeB = AircraftType::factory()->create();
        $partA = Part::factory()->create(['aircraft_type_id' => $typeA->id]);
        $partB = Part::factory()->create(['aircraft_type_id' => $typeB->id]);

        $rows = $this->actingAs($this->admin)->get(route('tally-cards.index', ['aircraft_type' => $typeA->id]))
            ->assertOk()
            ->viewData('page')['props']['parts'];

        $ids = collect($rows)->pluck('id');
        $this->assertTrue($ids->contains($partA->id));
        $this->assertFalse($ids->contains($partB->id));
    }
}
