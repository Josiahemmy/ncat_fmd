<?php

namespace Tests\Feature\Stock;

use App\Models\Part;
use App\Models\PartBatch;
use App\Models\Store;
use App\Models\User;
use App\Services\Stock\StockAlertService;
use App\Services\Stock\StockService;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->user = User::factory()->create();
    }

    protected function bonded(): Store
    {
        return Store::where('slug', 'bonded')->firstOrFail();
    }

    protected function stockService(): StockService
    {
        return app(StockService::class);
    }

    protected function alerts(): StockAlertService
    {
        return app(StockAlertService::class);
    }

    public function test_flags_parts_at_or_below_reorder_and_min_levels(): void
    {
        $low = Part::factory()->create(['reorder_level' => 10, 'min_level' => 5]);
        $ok = Part::factory()->create(['reorder_level' => 10, 'min_level' => 5]);

        $this->stockService()->openingBalance(part: $low, store: $this->bonded(), quantity: 4, user: $this->user);
        $this->stockService()->openingBalance(part: $ok, store: $this->bonded(), quantity: 50, user: $this->user);

        $this->assertTrue($this->alerts()->belowReorder()->pluck('id')->contains($low->id));
        $this->assertFalse($this->alerts()->belowReorder()->pluck('id')->contains($ok->id));
        $this->assertTrue($this->alerts()->belowMin()->pluck('id')->contains($low->id));
    }

    public function test_flags_parts_above_max_level(): void
    {
        $over = Part::factory()->create(['max_level' => 10]);
        $this->stockService()->openingBalance(part: $over, store: $this->bonded(), quantity: 25, user: $this->user);

        $this->assertTrue($this->alerts()->aboveMax()->pluck('id')->contains($over->id));
    }

    public function test_flags_expiring_and_expired_batches(): void
    {
        $part = Part::factory()->shelfLife()->create();
        $expiringSoon = PartBatch::create(['part_id' => $part->id, 'batch_number' => 'SOON', 'expiry_date' => now()->addDays(30)]);
        $expired = PartBatch::create(['part_id' => $part->id, 'batch_number' => 'OLD', 'expiry_date' => now()->subDay()]);
        $fine = PartBatch::create(['part_id' => $part->id, 'batch_number' => 'FINE', 'expiry_date' => now()->addYears(3)]);

        $expiringIds = $this->alerts()->expiringWithin(90)->pluck('id');
        $this->assertTrue($expiringIds->contains($expiringSoon->id));
        $this->assertFalse($expiringIds->contains($fine->id));

        $this->assertTrue($this->alerts()->expired()->pluck('id')->contains($expired->id));
    }
}
