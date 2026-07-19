<?php

namespace Tests\Feature\Stock;

use App\Models\Part;
use App\Models\Srv;
use App\Models\SrvItem;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\Documents\SrvService;
use App\Support\FuelReceiptSourceBackfill;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tracked debt (Phase 5): fuel receipts now carry a polymorphic source link to
 * their SRV like every other movement, and legacy by-number-only receipts are
 * backfilled without losing their historical reference string.
 */
class FuelSourceMorphTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->user = User::factory()->create();
    }

    protected function fuelStore(): Store
    {
        return Store::where('type', 'fuel')->firstOrFail();
    }

    public function test_posting_a_fuel_srv_links_the_srv_as_polymorphic_source(): void
    {
        $avgas = Part::factory()->create(['is_fuel' => true, 'unit_of_issue' => 'L']);
        $srv = Srv::factory()->create(['destination_store_id' => $this->fuelStore()->id]);
        SrvItem::create([
            'srv_id' => $srv->id, 'line_no' => 1, 'part_id' => $avgas->id,
            'description' => 'AVGAS', 'quantity' => 4000, 'rate' => 1200,
        ]);

        app(SrvService::class)->post($srv->fresh('items'), $this->user);

        $movement = StockMovement::where('movement_type', 'fuel_receive')->firstOrFail();

        $this->assertSame(Srv::class, $movement->source_type);
        $this->assertSame($srv->id, $movement->source_id);
        // The historical reference string is preserved too.
        $this->assertSame($srv->srv_number, $movement->reference);
        // And the morph relation resolves back to the SRV.
        $this->assertTrue($movement->source->is($srv));
    }

    public function test_backfill_links_legacy_fuel_receipts_by_reference(): void
    {
        $avgas = Part::factory()->create(['is_fuel' => true]);
        $srv = Srv::factory()->create(['destination_store_id' => $this->fuelStore()->id]);

        // A legacy fuel receipt: reference only, no polymorphic source (inserted
        // raw to reproduce the pre-Phase-5 shape without the model guard).
        DB::table('stock_movements')->insert([
            'part_id' => $avgas->id,
            'store_id' => $this->fuelStore()->id,
            'direction' => 'in',
            'quantity' => 5000,
            'balance_after' => 5000,
            'movement_type' => 'fuel_receive',
            'reference' => $srv->srv_number,
            'source_type' => null,
            'source_id' => null,
            'posted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $linked = FuelReceiptSourceBackfill::run();

        $this->assertSame(1, $linked);
        $movement = StockMovement::where('movement_type', 'fuel_receive')->firstOrFail();
        $this->assertSame(Srv::class, $movement->source_type);
        $this->assertSame($srv->id, $movement->source_id);
    }

    public function test_backfill_is_idempotent_and_leaves_unmatched_receipts_alone(): void
    {
        $avgas = Part::factory()->create(['is_fuel' => true]);
        DB::table('stock_movements')->insert([
            'part_id' => $avgas->id, 'store_id' => $this->fuelStore()->id,
            'direction' => 'in', 'quantity' => 100, 'balance_after' => 100,
            'movement_type' => 'fuel_receive', 'reference' => 'NON-EXISTENT-SRV',
            'posted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(0, FuelReceiptSourceBackfill::run());
        $this->assertSame(0, FuelReceiptSourceBackfill::run()); // idempotent
    }
}
