<?php

namespace Tests\Feature\Loans;

use App\Exceptions\Stock\StockException;
use App\Models\Loan;
use App\Models\Part;
use App\Models\PartSerial;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\Reports\ReportService;
use App\Services\Stock\StockAlertService;
use App\Services\Stock\StockService;
use App\Services\Stock\LoanService;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The loan engine's ledger behaviour (spec §12.7). Lending does not dispose of
 * an asset and borrowing does not acquire one, and these tests are what hold
 * the implementation to that.
 */
class LoanLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->user = User::factory()->create();
    }

    protected function loans(): LoanService
    {
        return app(LoanService::class);
    }

    protected function store(string $slug): Store
    {
        return Store::where('slug', $slug)->firstOrFail();
    }

    protected function balance(Part $part, string $slug): float
    {
        return (float) StockBalance::where('part_id', $part->id)
            ->where('store_id', $this->store($slug)->id)
            ->value('quantity');
    }

    protected function stockedPart(float $qty = 10, string $slug = 'bonded'): Part
    {
        $part = Part::factory()->create(['unit_price' => 1000]);
        app(StockService::class)->openingBalance(
            part: $part, store: $this->store($slug), quantity: $qty, user: $this->user,
        );

        return $part;
    }

    // ---- Outbound --------------------------------------------------------

    public function test_issuing_an_outbound_loan_moves_stock_into_the_on_loan_store(): void
    {
        $part = $this->stockedPart(10);

        $loan = $this->loans()->issueOutbound([
            'part_id' => $part->id,
            'quantity' => 3,
            'from_store_id' => $this->store('bonded')->id,
            'party_name' => 'Kaduna Flying Club',
            'started_at' => today()->toDateString(),
            'due_date' => today()->addDays(14)->toDateString(),
        ], $this->user);

        $this->assertSame('out', $loan->direction);
        $this->assertSame('on_loan', $loan->status);
        $this->assertSame(7.0, $this->balance($part, 'bonded'));
        $this->assertSame(3.0, $this->balance($part, 'on-loan-out'));
    }

    public function test_the_outbound_posting_goes_through_the_ledger_as_a_linked_pair(): void
    {
        $part = $this->stockedPart(10);

        $loan = $this->loans()->issueOutbound([
            'part_id' => $part->id, 'quantity' => 2,
            'from_store_id' => $this->store('bonded')->id,
            'party_name' => 'Kaduna Flying Club',
            'started_at' => today()->toDateString(),
        ], $this->user);

        $movements = StockMovement::where('movement_type', 'loan_out')->get();

        $this->assertCount(2, $movements, 'A loan is one out leg and one in leg.');
        $this->assertSame(1, $movements->pluck('transfer_group')->unique()->count());
        $this->assertSame(Loan::class, $movements->first()->source_type);
        $this->assertEquals($loan->id, $movements->first()->source_id);
    }

    public function test_an_outbound_loan_can_only_be_issued_from_bonded_or_dope(): void
    {
        $part = $this->stockedPart(5, 'quarantine');

        $this->expectException(StockException::class);

        $this->loans()->issueOutbound([
            'part_id' => $part->id, 'quantity' => 1,
            'from_store_id' => $this->store('quarantine')->id,
            'party_name' => 'Kaduna Flying Club',
            'started_at' => today()->toDateString(),
        ], $this->user);
    }

    public function test_a_serialized_loan_moves_the_serial_and_stops_it_reading_as_in_store(): void
    {
        $part = Part::factory()->create(['is_serialized' => true]);
        $serial = PartSerial::create([
            'part_id' => $part->id, 'serial_number' => 'SN-1', 'status' => 'in_store',
            'current_store_id' => $this->store('bonded')->id,
        ]);
        app(StockService::class)->openingBalance(
            part: $part, store: $this->store('bonded'), quantity: 1, user: $this->user, serialId: $serial->id,
        );

        $this->loans()->issueOutbound([
            'part_id' => $part->id, 'part_serial_id' => $serial->id, 'quantity' => 1,
            'from_store_id' => $this->store('bonded')->id,
            'party_name' => 'Kaduna Flying Club',
            'started_at' => today()->toDateString(),
        ], $this->user);

        $serial->refresh();
        $this->assertSame('on_loan', $serial->status);
        $this->assertNotSame('in_store', $serial->status);
        $this->assertSame($this->store('on-loan-out')->id, $serial->current_store_id);
    }

    public function test_recording_a_return_restores_the_originating_store_balance(): void
    {
        $part = $this->stockedPart(10);
        $loan = $this->loans()->issueOutbound([
            'part_id' => $part->id, 'quantity' => 4,
            'from_store_id' => $this->store('bonded')->id,
            'party_name' => 'Kaduna Flying Club',
            'started_at' => today()->toDateString(),
        ], $this->user);

        $this->loans()->recordReturn($loan, ['return_condition' => 'Serviceable, no damage'], $this->user);

        $loan->refresh();
        $this->assertSame('returned', $loan->status);
        $this->assertSame(10.0, $this->balance($part, 'bonded'));
        $this->assertSame(0.0, $this->balance($part, 'on-loan-out'));
        $this->assertSame(2, StockMovement::where('movement_type', 'loan_return')->count());
    }

    public function test_a_returned_serial_reads_as_in_store_again(): void
    {
        $part = Part::factory()->create(['is_serialized' => true]);
        $serial = PartSerial::create([
            'part_id' => $part->id, 'serial_number' => 'SN-2', 'status' => 'in_store',
            'current_store_id' => $this->store('bonded')->id,
        ]);
        app(StockService::class)->openingBalance(
            part: $part, store: $this->store('bonded'), quantity: 1, user: $this->user, serialId: $serial->id,
        );

        $loan = $this->loans()->issueOutbound([
            'part_id' => $part->id, 'part_serial_id' => $serial->id, 'quantity' => 1,
            'from_store_id' => $this->store('bonded')->id,
            'party_name' => 'Kaduna Flying Club',
            'started_at' => today()->toDateString(),
        ], $this->user);

        $this->loans()->recordReturn($loan, [], $this->user);

        $serial->refresh();
        $this->assertSame('in_store', $serial->status);
        $this->assertSame($this->store('bonded')->id, $serial->current_store_id);
    }

    public function test_a_write_off_takes_the_stock_out_of_the_ledger_with_a_reason(): void
    {
        $part = $this->stockedPart(10);
        $loan = $this->loans()->issueOutbound([
            'part_id' => $part->id, 'quantity' => 3,
            'from_store_id' => $this->store('bonded')->id,
            'party_name' => 'Kaduna Flying Club',
            'started_at' => today()->toDateString(),
        ], $this->user);

        $this->loans()->writeOff($loan, 'Borrower ceased trading; unit not recoverable.', $this->user);

        $loan->refresh();
        $this->assertSame('written_off', $loan->status);
        $this->assertSame(0.0, $this->balance($part, 'on-loan-out'), 'Written-off stock must not linger on loan.');
        $this->assertSame(7.0, $this->balance($part, 'bonded'), 'A write-off does not put stock back.');

        $adjustment = StockMovement::where('movement_type', 'adjustment')->latest('id')->first();
        $this->assertSame('out', $adjustment->direction);
        $this->assertStringContainsString('ceased trading', $adjustment->remarks);
    }

    public function test_a_write_off_requires_a_reason(): void
    {
        $part = $this->stockedPart(10);
        $loan = $this->loans()->issueOutbound([
            'part_id' => $part->id, 'quantity' => 1,
            'from_store_id' => $this->store('bonded')->id,
            'party_name' => 'Kaduna Flying Club',
            'started_at' => today()->toDateString(),
        ], $this->user);

        $this->expectException(StockException::class);
        $this->loans()->writeOff($loan, '   ', $this->user);
    }

    public function test_overdue_is_derived_from_the_due_date_not_stored(): void
    {
        $part = $this->stockedPart(10);
        $loan = $this->loans()->issueOutbound([
            'part_id' => $part->id, 'quantity' => 1,
            'from_store_id' => $this->store('bonded')->id,
            'party_name' => 'Kaduna Flying Club',
            'started_at' => today()->subDays(30)->toDateString(),
            'due_date' => today()->subDays(3)->toDateString(),
        ], $this->user);

        $this->assertTrue($loan->isOverdue());
        $this->assertSame(3, $loan->daysOverdue());
        $this->assertSame('on_loan', $loan->status, 'Overdue is a derivation, not a stored status.');
        $this->assertSame(1, Loan::overdue()->count());

        $this->loans()->recordReturn($loan, [], $this->user);
        $this->assertFalse($loan->refresh()->isOverdue());
    }

    // ---- Inbound: the ownership accounting -------------------------------

    public function test_an_inbound_loan_leaves_stock_value_and_stock_summary_unchanged(): void
    {
        // A priced, owned baseline so the assertion is about the inbound loan
        // rather than about an empty database.
        $owned = $this->stockedPart(10);

        $valueBefore = app(\App\Services\Dashboard\DashboardService::class)->kpis()['stock_value'];
        $summaryBefore = app(ReportService::class)->rows('stock-summary')->count();
        $this->assertGreaterThan(0, $valueBefore);

        $borrowed = Part::factory()->create(['unit_price' => 500000]);
        $this->loans()->receiveInbound([
            'part_id' => $borrowed->id,
            'quantity' => 4,
            'party_name' => 'Nigerian College of Aviation partner',
            'started_at' => today()->toDateString(),
            'due_date' => today()->addDays(30)->toDateString(),
        ], $this->user);

        app(\App\Services\Dashboard\DashboardService::class)->bust();

        $this->assertSame(
            $valueBefore,
            app(\App\Services\Dashboard\DashboardService::class)->kpis()['stock_value'],
            'Borrowed stock must never inflate NCAT-owned stock value.',
        );
        $this->assertSame(
            $summaryBefore,
            app(ReportService::class)->rows('stock-summary')->count(),
            'Borrowed stock must not appear as a stock-summary row.',
        );

        // It is still tracked: the ledger knows exactly where it is.
        $this->assertSame(4.0, $this->balance($borrowed, 'loaned-in'));
        $this->assertSame(0.0, $this->balance($owned, 'loaned-in'));
    }

    public function test_borrowed_stock_does_not_satisfy_a_reorder_alert(): void
    {
        $part = Part::factory()->create(['reorder_level' => 5, 'min_level' => 0, 'max_level' => null]);

        $this->assertSame(1, app(StockAlertService::class)->belowReorder()->count());

        $this->loans()->receiveInbound([
            'part_id' => $part->id, 'quantity' => 50,
            'party_name' => 'Partner organisation',
            'started_at' => today()->toDateString(),
        ], $this->user);

        $this->assertSame(
            1,
            app(StockAlertService::class)->belowReorder()->count(),
            'Borrowing 50 units does not mean NCAT no longer needs to order any.',
        );
    }

    public function test_an_inbound_serial_is_marked_as_loaned_property(): void
    {
        $part = Part::factory()->create(['is_serialized' => true]);

        $loan = $this->loans()->receiveInbound([
            'part_id' => $part->id, 'quantity' => 1,
            'serial_text' => 'LENT-SN-9',
            'party_name' => 'Partner organisation',
            'started_at' => today()->toDateString(),
        ], $this->user);

        $serial = $loan->refresh()->serial;

        $this->assertNotNull($serial, 'An inbound serialized loan creates the serial it is tracking.');
        $this->assertTrue($serial->is_loaned);
        $this->assertSame($this->store('loaned-in')->id, $serial->current_store_id);
    }

    public function test_returning_an_inbound_loan_takes_the_stock_back_out_of_the_ledger(): void
    {
        $part = Part::factory()->create();
        $loan = $this->loans()->receiveInbound([
            'part_id' => $part->id, 'quantity' => 6,
            'party_name' => 'Partner organisation',
            'started_at' => today()->toDateString(),
        ], $this->user);

        $this->loans()->recordReturn($loan, ['return_condition' => 'Returned intact'], $this->user);

        $this->assertSame('returned', $loan->refresh()->status);
        $this->assertSame(0.0, $this->balance($part, 'loaned-in'));
        $this->assertSame(1, StockMovement::where('movement_type', 'loan_in_return')->count());
    }

    public function test_an_uncatalogued_inbound_loan_is_recorded_without_touching_the_ledger(): void
    {
        $movementsBefore = StockMovement::count();

        $loan = $this->loans()->receiveInbound([
            'item_description' => 'Borrowed torque wrench, 20-100 Nm, no NCAT part number',
            'quantity' => 1,
            'party_name' => 'Partner organisation',
            'started_at' => today()->toDateString(),
        ], $this->user);

        $this->assertNull($loan->part_id);
        $this->assertSame('on_loan', $loan->status);
        $this->assertSame($movementsBefore, StockMovement::count());
    }
}
