<?php

namespace Tests\Feature\Loans;

use App\Models\Aircraft;
use App\Models\Loan;
use App\Models\Part;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Stock\LoanService;
use App\Services\Stock\StockService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The loan module's HTTP surface: who is allowed to do what, and the two places
 * a loaned unit has to be visibly marked as somebody else's property.
 */
class LoanModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->manager = User::factory()->create();
        $this->manager->givePermissionTo(['loans.view', 'loans.manage', 'aircraft.view', 'parts.view']);
    }

    protected function store(string $slug): Store
    {
        return Store::where('slug', $slug)->firstOrFail();
    }

    protected function stockedPart(float $qty = 10): Part
    {
        $part = Part::factory()->create();
        app(StockService::class)->openingBalance(
            part: $part, store: $this->store('bonded'), quantity: $qty, user: $this->manager,
        );

        return $part;
    }

    // ---- Permissions ------------------------------------------------------

    public function test_loans_view_is_required_to_open_the_module(): void
    {
        $this->actingAs(User::factory()->create())->get(route('loans.index'))->assertForbidden();
        $this->actingAs($this->manager)->get(route('loans.index'))->assertOk();
    }

    public function test_loans_manage_is_required_to_record_a_loan(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('loans.view');

        $this->actingAs($viewer)->post(route('loans.outbound.store'), [
            'party_name' => 'Kaduna Flying Club',
            'part_id' => $this->stockedPart()->id,
            'quantity' => 1,
            'from_store_id' => $this->store('bonded')->id,
            'started_at' => today()->toDateString(),
        ])->assertForbidden();

        $this->assertSame(0, Loan::count());
    }

    /**
     * A write-off posts a ledger adjustment, so it answers to `stock.adjust`
     * rather than to the loans module's own manage permission.
     */
    public function test_a_write_off_needs_stock_adjust_not_just_loans_manage(): void
    {
        $loan = $this->outboundLoan();

        $this->actingAs($this->manager)
            ->post(route('loans.write-off', $loan), ['write_off_reason' => 'Borrower ceased trading.'])
            ->assertForbidden();

        $this->assertSame('on_loan', $loan->refresh()->status);

        $this->manager->givePermissionTo('stock.adjust');
        $this->manager->forgetCachedPermissions();

        $this->actingAs($this->manager)
            ->post(route('loans.write-off', $loan), ['write_off_reason' => 'Borrower ceased trading.'])
            ->assertRedirect();

        $this->assertSame('written_off', $loan->refresh()->status);
    }

    public function test_a_write_off_without_a_reason_is_rejected(): void
    {
        $loan = $this->outboundLoan();
        $this->manager->givePermissionTo('stock.adjust');

        $this->actingAs($this->manager)
            ->post(route('loans.write-off', $loan), ['write_off_reason' => ''])
            ->assertSessionHasErrors('write_off_reason');

        $this->assertSame('on_loan', $loan->refresh()->status);
    }

    public function test_an_inbound_loan_cannot_be_written_off(): void
    {
        $loan = app(LoanService::class)->receiveInbound([
            'part_id' => Part::factory()->create()->id, 'quantity' => 1,
            'party_name' => 'Partner', 'started_at' => today()->toDateString(),
        ], $this->manager);

        $this->manager->givePermissionTo('stock.adjust');

        $this->actingAs($this->manager)
            ->post(route('loans.write-off', $loan), ['write_off_reason' => 'Trying to write off borrowed kit.'])
            ->assertRedirect();

        $this->assertSame('on_loan', $loan->refresh()->status, 'Borrowed property is not NCAT stock to write off.');
    }

    // ---- Outbound over HTTP ----------------------------------------------

    public function test_a_loan_out_of_quarantine_is_refused_with_a_message(): void
    {
        $part = Part::factory()->create();
        app(StockService::class)->openingBalance(
            part: $part, store: $this->store('quarantine'), quantity: 5, user: $this->manager,
        );

        $this->actingAs($this->manager)->post(route('loans.outbound.store'), [
            'party_name' => 'Kaduna Flying Club',
            'part_id' => $part->id,
            'quantity' => 1,
            'from_store_id' => $this->store('quarantine')->id,
            'started_at' => today()->toDateString(),
        ])->assertSessionHas('error');

        $this->assertSame(0, Loan::count());
    }

    public function test_a_counterparty_is_required_in_one_form_or_the_other(): void
    {
        $this->actingAs($this->manager)->post(route('loans.outbound.store'), [
            'part_id' => $this->stockedPart()->id,
            'quantity' => 1,
            'from_store_id' => $this->store('bonded')->id,
            'started_at' => today()->toDateString(),
        ])->assertSessionHasErrors('party_name');
    }

    public function test_recording_a_return_over_http_closes_the_loan(): void
    {
        $loan = $this->outboundLoan();

        $this->actingAs($this->manager)
            ->post(route('loans.return', $loan), ['return_condition' => 'Serviceable'])
            ->assertRedirect();

        $this->assertSame('returned', $loan->refresh()->status);
        $this->assertSame('Serviceable', $loan->return_condition);
    }

    // ---- Loaned-property marking -----------------------------------------

    public function test_a_borrowed_unit_on_an_aircraft_is_marked_as_loaned_property(): void
    {
        $aircraft = Aircraft::factory()->create();
        $part = Part::factory()->create(['is_serialized' => true]);

        $loan = app(LoanService::class)->receiveInbound([
            'part_id' => $part->id, 'quantity' => 1, 'serial_text' => 'LENT-1',
            'party_name' => 'Zaria Aero Maintenance', 'started_at' => today()->toDateString(),
        ], $this->manager);

        app(LoanService::class)->installInbound($loan, $aircraft->id);

        $response = $this->actingAs($this->manager)
            ->withHeaders($this->inertia())
            ->get(route('aircraft.show', $aircraft));

        $response->assertOk();
        $row = collect($response->json('props.partsOnAircraft'))
            ->firstWhere('serial_number', 'LENT-1');

        $this->assertNotNull($row, 'A borrowed unit fitted to an aircraft still appears on the airframe.');
        $this->assertTrue($row['is_loaned']);
        $this->assertSame('Zaria Aero Maintenance', $row['loaned_from']);
    }

    public function test_the_vendor_detail_page_lists_that_vendors_loans(): void
    {
        $vendor = Vendor::create(['name' => 'Partner Org', 'type' => 'supplier', 'is_active' => true]);

        app(LoanService::class)->receiveInbound([
            'vendor_id' => $vendor->id, 'quantity' => 1,
            'item_description' => 'Borrowed torque wrench',
            'started_at' => today()->toDateString(),
        ], $this->manager);

        $this->manager->givePermissionTo('vendors.view');

        $response = $this->actingAs($this->manager)
            ->withHeaders($this->inertia())
            ->get(route('vendors.show', $vendor));

        $response->assertOk();
        $this->assertCount(1, $response->json('props.loans'));
        $this->assertSame('Borrowed torque wrench', $response->json('props.loans.0.item_label'));
    }

    public function test_a_user_without_the_loans_module_sees_no_loan_history_on_a_vendor(): void
    {
        $vendor = Vendor::create(['name' => 'Partner Org', 'type' => 'supplier', 'is_active' => true]);

        app(LoanService::class)->receiveInbound([
            'vendor_id' => $vendor->id, 'quantity' => 1,
            'item_description' => 'Borrowed torque wrench',
            'started_at' => today()->toDateString(),
        ], $this->manager);

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('vendors.view');

        $response = $this->actingAs($viewer)
            ->withHeaders($this->inertia())
            ->get(route('vendors.show', $vendor));

        $response->assertOk();
        $this->assertSame([], $response->json('props.loans'));
        $this->assertFalse($response->json('props.can.loans'));
    }

    protected function outboundLoan(): Loan
    {
        return app(LoanService::class)->issueOutbound([
            'party_name' => 'Kaduna Flying Club',
            'part_id' => $this->stockedPart()->id,
            'quantity' => 2,
            'from_store_id' => $this->store('bonded')->id,
            'started_at' => today()->toDateString(),
            'due_date' => today()->addDays(14)->toDateString(),
        ], $this->manager);
    }

    /** @return array<string, string> */
    protected function inertia(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ];
    }
}
