<?php

namespace Tests\Feature\Stock;

use App\Models\Part;
use App\Models\Store;
use App\Models\User;
use App\Services\Stock\StockService;
use App\Services\Stock\TallyService;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TallyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Part $part;
    protected Store $bonded;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->user = User::factory()->create();
        $this->part = Part::factory()->create();
        $this->bonded = Store::where('type', 'bonded')->first();
    }

    protected function postAt(string $date, callable $fn): void
    {
        Carbon::setTestNow(Carbon::parse($date));
        $fn();
        Carbon::setTestNow();
    }

    public function test_card_computes_brought_forward_lines_and_carried_forward(): void
    {
        $stock = app(StockService::class);

        // History: +100 (Jan), -30 (Feb) -> these are BEFORE the window.
        $this->postAt('2026-01-10', fn () => $stock->openingBalance(part: $this->part, store: $this->bonded, quantity: 100, user: $this->user));
        $this->postAt('2026-02-05', fn () => $stock->issue(part: $this->part, store: $this->bonded, quantity: 30, user: $this->user));
        // In-window (March): +50 receive, -20 issue.
        $this->postAt('2026-03-03', fn () => $stock->receive(part: $this->part, store: $this->bonded, quantity: 50, user: $this->user));
        $this->postAt('2026-03-20', fn () => $stock->issue(part: $this->part, store: $this->bonded, quantity: 20, user: $this->user));

        $card = app(TallyService::class)->card(
            part: $this->part, store: $this->bonded, from: '2026-03-01', to: '2026-03-31',
        );

        // Brought forward = balance at end of Feb = 100 - 30 = 70.
        $this->assertEquals(70, $card['brought_forward']);
        // Two in-window lines.
        $this->assertCount(2, $card['lines']);
        $this->assertEquals(120, $card['lines'][0]['balance']); // 70 + 50
        $this->assertEquals(100, $card['lines'][1]['balance']); // 120 - 20
        // Carried forward totals.
        $this->assertEquals(50, $card['total_received']);
        $this->assertEquals(20, $card['total_issued']);
        $this->assertEquals(100, $card['carried_forward']);
    }

    public function test_card_without_prior_history_has_zero_brought_forward(): void
    {
        app(StockService::class)->openingBalance(part: $this->part, store: $this->bonded, quantity: 10, user: $this->user);

        $card = app(TallyService::class)->card(part: $this->part, store: $this->bonded);

        $this->assertEquals(0, $card['brought_forward']);
        $this->assertEquals(10, $card['carried_forward']);
    }
}
