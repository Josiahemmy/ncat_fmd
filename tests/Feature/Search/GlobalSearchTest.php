<?php

namespace Tests\Feature\Search;

use App\Models\Aircraft;
use App\Models\Part;
use App\Models\Requisition;
use App\Models\Siv;
use App\Models\Srv;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Search\SearchService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    protected function search(): SearchService
    {
        return app(SearchService::class);
    }

    /** @return array<string, array<int, mixed>> keyed by group */
    protected function grouped(array $result): array
    {
        return collect($result['groups'])->keyBy('key')->map->items->all();
    }

    public function test_results_are_grouped_and_permission_filtered(): void
    {
        Part::factory()->create(['part_number' => 'ZQX-1000', 'description' => 'Widget ZQX']);
        Aircraft::factory()->create(['registration' => '5N-ZQX']);
        WorkOrder::factory()->create(['wo_ref' => 'FMD/ZQX/07/26/1', 'title' => 'ZQX snag']);

        // A user permitted to see parts only.
        $partsOnly = User::factory()->create();
        $partsOnly->givePermissionTo('parts.view');

        $groups = $this->grouped($this->search()->search('ZQX', $partsOnly));

        $this->assertArrayHasKey('parts', $groups);
        $this->assertNotEmpty($groups['parts']);
        // No permission for aircraft / work orders → those groups are absent.
        $this->assertArrayNotHasKey('aircraft', $groups);
        $this->assertArrayNotHasKey('work_orders', $groups);
    }

    public function test_matches_across_every_permitted_group(): void
    {
        Part::factory()->create(['part_number' => 'ZQX-1000']);
        Aircraft::factory()->create(['registration' => '5N-ZQX']);
        WorkOrder::factory()->create(['wo_ref' => 'FMD/ZQX/07/26/1']);
        Requisition::factory()->create(['requisition_no' => 'ZQX-77']);
        Srv::factory()->create(['srv_number' => 'ZQX9']);
        Siv::factory()->create(['siv_number' => 'ZQX8']);

        $user = User::factory()->create();
        $user->givePermissionTo([
            'parts.view', 'aircraft.view', 'work_orders.view',
            'requisitions.view', 'receiving.view', 'issues.view',
        ]);

        $groups = $this->grouped($this->search()->search('ZQX', $user));

        foreach (['parts', 'aircraft', 'work_orders', 'requisitions', 'receiving', 'issuing'] as $key) {
            $this->assertArrayHasKey($key, $groups, "Expected a '{$key}' group.");
            $this->assertNotEmpty($groups[$key], "Group '{$key}' should have a hit.");
        }
    }

    public function test_short_queries_return_nothing(): void
    {
        Part::factory()->create(['part_number' => 'Z-1']);
        $user = User::factory()->create();
        $user->givePermissionTo('parts.view');

        $result = $this->search()->search('Z', $user);

        $this->assertSame([], $result['groups']);
    }

    public function test_endpoint_requires_auth_and_returns_grouped_json(): void
    {
        Part::factory()->create(['part_number' => 'ZQX-1000']);

        $user = User::factory()->create();
        $user->givePermissionTo('parts.view');

        $this->getJson('/search?q=ZQX')->assertUnauthorized();

        $this->actingAs($user)
            ->getJson('/search?q=ZQX')
            ->assertOk()
            ->assertJsonPath('groups.0.key', 'parts')
            ->assertJsonPath('groups.0.items.0.title', 'ZQX-1000');
    }
}
