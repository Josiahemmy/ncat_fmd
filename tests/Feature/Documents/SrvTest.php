<?php

namespace Tests\Feature\Documents;

use App\Models\Part;
use App\Models\PartSerial;
use App\Models\StockBalance;
use App\Models\Srv;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SrvTest extends TestCase
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

    protected function storekeeper(): User
    {
        return User::factory()->create()->assignRole('Storekeeper'); // receiving.post
    }

    protected function storeId(string $type): int
    {
        return Store::where('type', $type)->value('id');
    }

    /** Create a draft SRV via the controller and return it. */
    protected function draft(User $user, array $items, array $header = []): Srv
    {
        $this->actingAs($user)->post('/receiving', array_merge([
            'srv_date' => '2026-07-19',
            'destination_store_id' => $this->storeId('quarantine'),
            'supplier' => 'ACME Aviation',
            'items' => $items,
        ], $header))->assertRedirect();

        return Srv::latest('id')->first();
    }

    public function test_posting_lands_stock_in_quarantine_for_certification(): void
    {
        $part = Part::factory()->create();
        $keeper = $this->storekeeper();
        $srv = $this->draft($keeper, [['part_id' => $part->id, 'quantity' => 5]]);

        $this->actingAs($keeper)->post("/receiving/{$srv->id}/post")->assertRedirect();

        $this->assertSame('posted', $srv->fresh()->status);
        $this->assertEquals(5, StockBalance::where('part_id', $part->id)
            ->where('store_id', $this->storeId('quarantine'))->value('quantity'));
    }

    public function test_fuel_parts_auto_route_to_the_fuel_dump(): void
    {
        $fuel = Part::factory()->fuel()->create();
        $keeper = $this->storekeeper();
        // Even with a quarantine header, fuel routes to the Fuel Dump.
        $srv = $this->draft($keeper, [['part_id' => $fuel->id, 'quantity' => 200]]);

        $this->actingAs($keeper)->post("/receiving/{$srv->id}/post")->assertRedirect();

        $this->assertEquals(200, StockBalance::where('part_id', $fuel->id)
            ->where('store_id', $this->storeId('fuel'))->value('quantity'));
        $this->assertEquals(0, StockBalance::where('part_id', $fuel->id)
            ->where('store_id', $this->storeId('quarantine'))->value('quantity') ?? 0);
    }

    public function test_serialized_lines_require_matching_serial_capture(): void
    {
        $part = Part::factory()->serialized()->create();
        $keeper = $this->storekeeper();
        // qty 2 but only one serial captured → post is blocked.
        $srv = $this->draft($keeper, [['part_id' => $part->id, 'quantity' => 2, 'serials' => ['SN-1']]]);

        $this->actingAs($keeper)->post("/receiving/{$srv->id}/post")
            ->assertSessionHasErrors('items.0.serials');
        $this->assertSame('draft', $srv->fresh()->status);
    }

    public function test_serialized_receipt_creates_serials_in_the_store(): void
    {
        $part = Part::factory()->serialized()->create();
        $keeper = $this->storekeeper();
        $srv = $this->draft($keeper, [['part_id' => $part->id, 'quantity' => 2, 'serials' => ['SN-1', 'SN-2']]]);

        $this->actingAs($keeper)->post("/receiving/{$srv->id}/post")->assertRedirect();

        $this->assertEquals(2, PartSerial::where('part_id', $part->id)->where('status', 'in_store')->count());
        $this->assertEquals(2, StockBalance::where('part_id', $part->id)
            ->where('store_id', $this->storeId('quarantine'))->value('quantity'));
    }

    public function test_shelf_life_lines_require_a_batch_number(): void
    {
        $part = Part::factory()->shelfLife()->create();
        $keeper = $this->storekeeper();
        $srv = $this->draft($keeper, [['part_id' => $part->id, 'quantity' => 3]]);

        $this->actingAs($keeper)->post("/receiving/{$srv->id}/post")
            ->assertSessionHasErrors('items.0.batch_no');
    }

    public function test_a_posted_srv_is_immutable(): void
    {
        $part = Part::factory()->create();
        $keeper = $this->storekeeper();
        $srv = $this->draft($keeper, [['part_id' => $part->id, 'quantity' => 1]]);
        $this->actingAs($keeper)->post("/receiving/{$srv->id}/post")->assertRedirect();

        // Editing a posted SRV is rejected.
        $this->actingAs($keeper)->put("/receiving/{$srv->id}", [
            'srv_date' => '2026-07-19', 'destination_store_id' => $this->storeId('quarantine'),
            'items' => [['part_id' => $part->id, 'quantity' => 9]],
        ])->assertStatus(422);

        // Re-posting is rejected.
        $this->actingAs($keeper)->post("/receiving/{$srv->id}/post")->assertStatus(422);
    }

    public function test_posting_requires_the_receiving_post_permission(): void
    {
        $viewer = User::factory()->create()->assignRole('Viewer'); // receiving.view only
        $srv = Srv::factory()->create();

        $this->actingAs($viewer)->post("/receiving/{$srv->id}/post")->assertForbidden();
    }
}
