<?php

namespace Tests\Feature\Stock;

use App\Models\Part;
use App\Models\Store;
use App\Models\User;
use App\Services\Stock\StockService;
use App\Services\Stock\TallyService;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tracked debt (Phase 5): a consolidated all-stores tally — one combined
 * ledger per part across every store, with a recomputed running balance
 * (per-store balance_after can't be summed chronologically). Non-AD38.
 */
class ConsolidatedTallyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->user = User::factory()->create();
    }

    protected function store(string $slug): Store
    {
        return Store::where('slug', $slug)->firstOrFail();
    }

    public function test_consolidated_card_combines_all_stores_into_one_running_balance(): void
    {
        $part = Part::factory()->create();
        $stock = app(StockService::class);

        // Bonded: +100 then -30. Dope: +50. Combined on-hand = 120.
        $stock->openingBalance(part: $part, store: $this->store('bonded'), quantity: 100, user: $this->user);
        $stock->issue(part: $part, store: $this->store('bonded'), quantity: 30, user: $this->user);
        $stock->openingBalance(part: $part, store: $this->store('dope'), quantity: 50, user: $this->user);

        $card = app(TallyService::class)->consolidated($part);

        $this->assertTrue($card['consolidated']);
        $this->assertCount(3, $card['lines']);
        // Running combined balance: 100 → 70 → 120.
        $this->assertEqualsWithDelta(100.0, $card['lines'][0]['balance'], 0.01);
        $this->assertEqualsWithDelta(70.0, $card['lines'][1]['balance'], 0.01);
        $this->assertEqualsWithDelta(120.0, $card['lines'][2]['balance'], 0.01);
        $this->assertEqualsWithDelta(150.0, $card['total_received'], 0.01);
        $this->assertEqualsWithDelta(30.0, $card['total_issued'], 0.01);
        $this->assertEqualsWithDelta(120.0, $card['carried_forward'], 0.01);
        // Each line names its store.
        $this->assertNotNull($card['lines'][0]['store']);
    }
}
