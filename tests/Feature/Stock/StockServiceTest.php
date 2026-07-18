<?php

namespace Tests\Feature\Stock;

use App\Models\Part;
use App\Models\Store;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\Stock\StockService;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->user = User::factory()->create();
    }

    protected function service(): StockService
    {
        return app(StockService::class);
    }

    protected function store(string $slug): Store
    {
        return Store::where('slug', $slug)->firstOrFail();
    }

    public function test_opening_balance_posts_an_in_movement_and_sets_the_store_balance(): void
    {
        $part = Part::factory()->create();

        $movement = $this->service()->openingBalance(
            part: $part,
            store: $this->store('bonded'),
            quantity: 10,
            user: $this->user,
        );

        $this->assertSame('in', $movement->direction);
        $this->assertSame('opening_balance', $movement->movement_type);
        $this->assertEquals(10, $movement->balance_after);
        $this->assertEquals(10, StockBalance::where([
            'part_id' => $part->id,
            'store_id' => $this->store('bonded')->id,
        ])->value('quantity'));
    }

    public function test_receive_adds_stock_to_the_destination_store(): void
    {
        $part = Part::factory()->create();

        $movement = $this->service()->receive(
            part: $part, store: $this->store('quarantine'), quantity: 5, user: $this->user,
        );

        $this->assertSame('in', $movement->direction);
        $this->assertSame('receiving', $movement->movement_type);
        $this->assertEquals(5, $movement->balance_after);
    }

    public function test_issue_reduces_the_store_balance(): void
    {
        $part = Part::factory()->create();
        $this->service()->openingBalance(part: $part, store: $this->store('bonded'), quantity: 10, user: $this->user);

        $movement = $this->service()->issue(
            part: $part, store: $this->store('bonded'), quantity: 4, user: $this->user,
        );

        $this->assertSame('out', $movement->direction);
        $this->assertSame('issue', $movement->movement_type);
        $this->assertEquals(6, $movement->balance_after);
    }

    public function test_issue_beyond_available_balance_is_rejected_and_nothing_is_written(): void
    {
        $part = Part::factory()->create();
        $this->service()->openingBalance(part: $part, store: $this->store('bonded'), quantity: 3, user: $this->user);

        try {
            $this->service()->issue(part: $part, store: $this->store('bonded'), quantity: 5, user: $this->user);
            $this->fail('Expected InsufficientStockException');
        } catch (\App\Exceptions\Stock\InsufficientStockException) {
            // Balance untouched, no OUT movement written (invariant b, atomic).
            $this->assertEquals(3, StockBalance::where('part_id', $part->id)->value('quantity'));
            $this->assertSame(0, \App\Models\StockMovement::where('movement_type', 'issue')->count());
        }
    }

    public function test_stock_cannot_be_issued_from_quarantine(): void
    {
        $part = Part::factory()->create();
        $this->service()->receive(part: $part, store: $this->store('quarantine'), quantity: 10, user: $this->user);

        $this->expectException(\App\Exceptions\Stock\StockException::class);
        $this->service()->issue(part: $part, store: $this->store('quarantine'), quantity: 1, user: $this->user);
    }

    public function test_transfer_moves_stock_between_stores_as_a_linked_pair(): void
    {
        $part = Part::factory()->create();
        $this->service()->openingBalance(part: $part, store: $this->store('bonded'), quantity: 10, user: $this->user);

        $movements = $this->service()->transfer(
            part: $part, from: $this->store('bonded'), to: $this->store('dope'), quantity: 4, user: $this->user,
        );

        $this->assertEquals(6, StockBalance::where('part_id', $part->id)->where('store_id', $this->store('bonded')->id)->value('quantity'));
        $this->assertEquals(4, StockBalance::where('part_id', $part->id)->where('store_id', $this->store('dope')->id)->value('quantity'));
        // Two legs, same transfer_group.
        $this->assertCount(2, $movements);
        $this->assertNotNull($movements[0]->transfer_group);
        $this->assertSame($movements[0]->transfer_group, $movements[1]->transfer_group);
    }

    public function test_transfer_out_of_quarantine_is_blocked(): void
    {
        $part = Part::factory()->create();
        $this->service()->receive(part: $part, store: $this->store('quarantine'), quantity: 5, user: $this->user);

        $this->expectException(\App\Exceptions\Stock\StockException::class);
        $this->service()->transfer(part: $part, from: $this->store('quarantine'), to: $this->store('bonded'), quantity: 1, user: $this->user);
    }

    public function test_certify_releases_quarantined_stock_to_bonded(): void
    {
        $part = Part::factory()->create();
        $this->service()->receive(part: $part, store: $this->store('quarantine'), quantity: 8, user: $this->user);

        $movements = $this->service()->certify(
            part: $part, quantity: 8, decision: 'release_to_bonded', user: $this->user,
        );

        $this->assertEquals(0, StockBalance::where('part_id', $part->id)->where('store_id', $this->store('quarantine')->id)->value('quantity'));
        $this->assertEquals(8, StockBalance::where('part_id', $part->id)->where('store_id', $this->store('bonded')->id)->value('quantity'));
        $this->assertSame('certification_transfer', $movements[0]->movement_type);
    }

    public function test_certify_routes_flammable_parts_to_the_dope_store(): void
    {
        $part = Part::factory()->flammable()->create();
        $this->service()->receive(part: $part, store: $this->store('quarantine'), quantity: 3, user: $this->user);

        $this->service()->certify(part: $part, quantity: 3, decision: 'release_to_dope', user: $this->user);

        $this->assertEquals(3, StockBalance::where('part_id', $part->id)->where('store_id', $this->store('dope')->id)->value('quantity'));
    }

    public function test_adjustment_requires_a_reason(): void
    {
        $part = Part::factory()->create();
        $this->service()->openingBalance(part: $part, store: $this->store('bonded'), quantity: 5, user: $this->user);

        $this->expectException(\App\Exceptions\Stock\StockException::class);
        $this->service()->adjust(part: $part, store: $this->store('bonded'), delta: -2, reason: '', user: $this->user);
    }

    public function test_adjustment_applies_a_signed_delta_with_a_reason(): void
    {
        $part = Part::factory()->create();
        $this->service()->openingBalance(part: $part, store: $this->store('bonded'), quantity: 5, user: $this->user);

        $movement = $this->service()->adjust(
            part: $part, store: $this->store('bonded'), delta: -2, reason: 'Stock-take correction', user: $this->user,
        );

        $this->assertSame('adjustment', $movement->movement_type);
        $this->assertSame('out', $movement->direction);
        $this->assertEquals(3, $movement->balance_after);
        $this->assertSame('Stock-take correction', $movement->remarks);
    }

    public function test_fuel_is_received_and_issued_against_an_aircraft(): void
    {
        $part = Part::factory()->fuel()->create();
        $aircraft = \App\Models\Aircraft::factory()->create();

        $this->service()->fuelReceive(part: $part, quantity: 500, user: $this->user);
        $issue = $this->service()->fuelIssue(part: $part, quantity: 120, aircraft: $aircraft, user: $this->user);

        $fuelStore = $this->store('fuel-dump');
        $this->assertSame('fuel_issue', $issue->movement_type);
        $this->assertEquals($aircraft->id, $issue->aircraft_id);
        $this->assertEquals(380, StockBalance::where('part_id', $part->id)->where('store_id', $fuelStore->id)->value('quantity'));
    }

    public function test_a_serialized_part_moves_one_serial_at_a_time(): void
    {
        $part = Part::factory()->serialized()->create();
        $serial = \App\Models\PartSerial::create([
            'part_id' => $part->id, 'serial_number' => 'SN-1', 'status' => 'in_store',
        ]);

        // Quantity must be 1 when a serial is referenced.
        $this->expectException(\App\Exceptions\Stock\StockException::class);
        $this->service()->receive(part: $part, store: $this->store('quarantine'), quantity: 2, user: $this->user, serialId: $serial->id);
    }

    public function test_fefo_suggests_the_earliest_expiring_non_expired_batch(): void
    {
        $part = Part::factory()->shelfLife()->create();
        $later = \App\Models\PartBatch::create(['part_id' => $part->id, 'batch_number' => 'B2', 'expiry_date' => now()->addYears(2)]);
        $sooner = \App\Models\PartBatch::create(['part_id' => $part->id, 'batch_number' => 'B1', 'expiry_date' => now()->addMonths(3)]);

        $suggested = $this->service()->suggestBatch($part);

        $this->assertSame($sooner->id, $suggested->id);
    }

    public function test_issuing_an_expired_batch_is_blocked_without_an_override(): void
    {
        $part = Part::factory()->shelfLife()->create();
        $expired = \App\Models\PartBatch::create(['part_id' => $part->id, 'batch_number' => 'OLD', 'expiry_date' => now()->subDay()]);
        $this->service()->openingBalance(part: $part, store: $this->store('bonded'), quantity: 10, user: $this->user, batchId: $expired->id);

        $this->expectException(\App\Exceptions\Stock\StockException::class);
        $this->service()->issue(part: $part, store: $this->store('bonded'), quantity: 1, user: $this->user, batchId: $expired->id);
    }

    public function test_expired_batch_can_be_issued_with_an_explicit_override(): void
    {
        $part = Part::factory()->shelfLife()->create();
        $expired = \App\Models\PartBatch::create(['part_id' => $part->id, 'batch_number' => 'OLD', 'expiry_date' => now()->subDay()]);
        $this->service()->openingBalance(part: $part, store: $this->store('bonded'), quantity: 10, user: $this->user, batchId: $expired->id);

        $movement = $this->service()->issue(
            part: $part, store: $this->store('bonded'), quantity: 1, user: $this->user,
            batchId: $expired->id, allowExpired: true,
        );

        $this->assertEquals(9, $movement->balance_after);
    }

    public function test_repeated_issues_can_never_oversell_or_write_a_negative_balance(): void
    {
        // Invariant (f): the balance guard (enforced under lockForUpdate on
        // MySQL) prevents overselling. Here we prove the arithmetic guard: a
        // sequence that would go negative is rejected, and no movement ever
        // records balance_after < 0.
        $part = Part::factory()->create();
        $bonded = $this->store('bonded');
        $this->service()->openingBalance(part: $part, store: $bonded, quantity: 5, user: $this->user);

        $succeeded = 0;
        for ($i = 0; $i < 4; $i++) {
            try {
                $this->service()->issue(part: $part, store: $bonded, quantity: 2, user: $this->user);
                $succeeded++;
            } catch (\App\Exceptions\Stock\InsufficientStockException) {
                // expected once stock runs out
            }
        }

        $this->assertSame(2, $succeeded); // 2×2 = 4 issued from 5; the 3rd would oversell
        $this->assertEquals(1, StockBalance::where('part_id', $part->id)->where('store_id', $bonded->id)->value('quantity'));
        $this->assertSame(0, \App\Models\StockMovement::where('balance_after', '<', 0)->count());
    }

    public function test_movements_are_append_only_and_cannot_be_edited_or_deleted(): void
    {
        $part = Part::factory()->create();
        $movement = $this->service()->openingBalance(part: $part, store: $this->store('bonded'), quantity: 5, user: $this->user);

        try {
            $movement->update(['quantity' => 999]);
            $this->fail('Movements must not be updatable');
        } catch (\RuntimeException) {
        }

        try {
            $movement->delete();
            $this->fail('Movements must not be deletable');
        } catch (\RuntimeException) {
        }

        $this->assertEquals(5, $movement->fresh()->quantity);
    }
}
