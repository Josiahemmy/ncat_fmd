<?php

namespace Tests\Feature\Stock;

use App\Models\Part;
use App\Models\Store;
use App\Models\User;
use App\Services\Stock\StockService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(StoreSeeder::class);
    }

    public function test_parts_catalogue_requires_the_view_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/parts')->assertForbidden();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('parts.view');
        $this->actingAs($viewer)->get('/parts')->assertOk();
    }

    public function test_a_storekeeper_cannot_certify_quarantined_stock(): void
    {
        // Segregation of duties: the Storekeeper role deliberately lacks
        // quarantine.certify (receiver ≠ certifier).
        $storekeeper = User::factory()->create()->assignRole('Storekeeper');
        $part = Part::factory()->create();

        $this->actingAs($storekeeper)->post('/stock/certify', [
            'part_id' => $part->id, 'quantity' => 1, 'decision' => 'release_to_bonded',
        ])->assertForbidden();
    }

    public function test_a_stores_officer_can_certify_quarantined_stock(): void
    {
        $officer = User::factory()->create()->assignRole('Stores Officer');
        $part = Part::factory()->create();
        app(StockService::class)->receive(
            part: $part, store: Store::where('type', 'quarantine')->first(), quantity: 3, user: $officer,
        );

        $this->actingAs($officer)->post('/stock/certify', [
            'part_id' => $part->id, 'quantity' => 3, 'decision' => 'release_to_bonded',
        ])->assertRedirect();

        $this->assertEquals(3, \App\Models\StockBalance::where('part_id', $part->id)
            ->where('store_id', Store::where('type', 'bonded')->value('id'))->value('quantity'));
    }

    public function test_transfer_and_adjust_require_their_permissions(): void
    {
        $user = User::factory()->create();
        $part = Part::factory()->create();
        $bonded = Store::where('type', 'bonded')->value('id');
        $dope = Store::where('type', 'dope')->value('id');

        $this->actingAs($user)->post('/stock/transfer', [
            'part_id' => $part->id, 'from_store_id' => $bonded, 'to_store_id' => $dope, 'quantity' => 1,
        ])->assertForbidden();

        $this->actingAs($user)->post('/stock/adjust', [
            'part_id' => $part->id, 'store_id' => $bonded, 'delta' => 1, 'reason' => 'x',
        ])->assertForbidden();
    }
}
