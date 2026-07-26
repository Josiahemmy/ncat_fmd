<?php

namespace Tests\Feature\Reports;

use App\Models\Part;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Reports\ReportService;
use App\Services\Shipping\ShipmentService;
use App\Services\Stock\LoanService;
use App\Services\Stock\StockService;
use Database\Seeders\DocumentCounterSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The two reports Phase 8 adds, and the permissions that gate them. */
class LogisticsReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->seed(DocumentCounterSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['reports.view', 'loans.view', 'shipping.view']);
    }

    protected function reports(): ReportService
    {
        return app(ReportService::class);
    }

    protected function bondedPart(float $qty = 20): Part
    {
        $part = Part::factory()->create();
        app(StockService::class)->openingBalance(
            part: $part, store: Store::where('slug', 'bonded')->firstOrFail(), quantity: $qty, user: $this->user,
        );

        return $part;
    }

    // ---- Outstanding loans ------------------------------------------------

    public function test_outstanding_loans_reports_both_directions_with_days_overdue(): void
    {
        app(LoanService::class)->issueOutbound([
            'part_id' => $this->bondedPart()->id, 'quantity' => 3,
            'from_store_id' => Store::where('slug', 'bonded')->value('id'),
            'party_name' => 'Kaduna Flying Club',
            'started_at' => today()->subDays(40)->toDateString(),
            'due_date' => today()->subDays(6)->toDateString(),
        ], $this->user);

        app(LoanService::class)->receiveInbound([
            'item_description' => 'Borrowed torque wrench', 'quantity' => 1,
            'party_name' => 'Zaria Aero Maintenance',
            'started_at' => today()->subDays(5)->toDateString(),
            'due_date' => today()->addDays(20)->toDateString(),
        ], $this->user);

        $rows = $this->reports()->rows('outstanding-loans')->values()->all();

        $this->assertCount(2, $rows);

        $out = collect($rows)->firstWhere('Counterparty', 'Kaduna Flying Club');
        $this->assertSame('Out (lent by NCAT)', $out['Direction']);
        $this->assertSame(6, $out['Days Overdue']);
        $this->assertSame('overdue', $out['Status']);

        $in = collect($rows)->firstWhere('Counterparty', 'Zaria Aero Maintenance');
        $this->assertSame('In (borrowed by NCAT)', $in['Direction']);
        $this->assertSame(0, $in['Days Overdue']);
        $this->assertSame('Borrowed torque wrench', $in['Item']);
    }

    public function test_outstanding_loans_honours_the_direction_and_scope_filters(): void
    {
        $service = app(LoanService::class);

        $loan = $service->issueOutbound([
            'part_id' => $this->bondedPart()->id, 'quantity' => 2,
            'from_store_id' => Store::where('slug', 'bonded')->value('id'),
            'party_name' => 'Returned Borrower',
            'started_at' => today()->subDays(20)->toDateString(),
            'due_date' => today()->addDays(5)->toDateString(),
        ], $this->user);
        $service->recordReturn($loan, [], $this->user);

        $service->issueOutbound([
            'part_id' => $this->bondedPart()->id, 'quantity' => 1,
            'from_store_id' => Store::where('slug', 'bonded')->value('id'),
            'party_name' => 'Late Borrower',
            'started_at' => today()->subDays(30)->toDateString(),
            'due_date' => today()->subDays(2)->toDateString(),
        ], $this->user);

        $service->receiveInbound([
            'item_description' => 'Borrowed jig', 'quantity' => 1,
            'party_name' => 'Partner', 'started_at' => today()->toDateString(),
        ], $this->user);

        // Default scope is open loans only, so the returned one drops out.
        $this->assertSame(2, $this->reports()->rows('outstanding-loans')->count());

        $this->assertSame(1, $this->reports()->rows('outstanding-loans', ['direction' => 'in'])->count());
        $this->assertSame(1, $this->reports()->rows('outstanding-loans', ['scope' => 'overdue'])->count());
    }

    // ---- Shipments in transit --------------------------------------------

    public function test_shipments_in_transit_carries_silence_and_the_overdue_flag(): void
    {
        $vendor = Vendor::create(['name' => 'Test Supplier', 'type' => 'supplier', 'is_active' => true]);
        $shipments = app(ShipmentService::class);

        $late = $shipments->create([
            'vendor_id' => $vendor->id,
            'carrier' => 'DHL Aviation',
            'awb_reference' => '172-88104235',
            'expected_arrival_date' => today()->subDays(9)->toDateString(),
            'status' => 'Cleared customs',
            'event_date' => today()->subDays(15)->toDateString(),
        ], $this->user);

        $arrived = $shipments->create([
            'vendor_id' => $vendor->id,
            'expected_arrival_date' => today()->addDays(3)->toDateString(),
            'status' => 'Shipped',
            'event_date' => today()->subDays(4)->toDateString(),
        ], $this->user);
        $shipments->addEvent($arrived, [
            'status' => 'Arrived at NCAT', 'event_date' => today()->toDateString(), 'is_arrival' => true,
        ], $this->user);

        $rows = $this->reports()->rows('shipments-in-transit')->values()->all();

        $this->assertCount(1, $rows, 'A shipment that has landed is no longer in transit.');
        $this->assertSame($late->reference, $rows[0]['Reference']);
        $this->assertSame('yes', $rows[0]['Overdue']);
        $this->assertSame(9, $rows[0]['Days Overdue']);
        $this->assertSame(15, $rows[0]['Days Since Last Event']);
        $this->assertSame('Cleared customs', $rows[0]['Latest Status']);
        $this->assertSame('Test Supplier', $rows[0]['Vendor']);
    }

    // ---- Permissions ------------------------------------------------------

    public function test_the_new_reports_need_their_module_permission_on_top_of_reports_view(): void
    {
        $limited = User::factory()->create();
        $limited->givePermissionTo('reports.view');

        $this->actingAs($limited)->get(route('reports.show', 'outstanding-loans'))->assertForbidden();
        $this->actingAs($limited)->get(route('reports.show', 'shipments-in-transit'))->assertForbidden();
        $this->actingAs($limited)->get(route('reports.export', 'outstanding-loans'))->assertForbidden();

        $this->actingAs($this->user)->get(route('reports.show', 'outstanding-loans'))->assertOk();
        $this->actingAs($this->user)->get(route('reports.show', 'shipments-in-transit'))->assertOk();
    }

    public function test_the_report_index_hides_reports_the_user_cannot_open(): void
    {
        $limited = User::factory()->create();
        $limited->givePermissionTo('reports.view');

        $keys = collect($this->indexReports($limited))->pluck('key');

        $this->assertNotContains('outstanding-loans', $keys);
        $this->assertNotContains('shipments-in-transit', $keys);
        $this->assertContains('stock-summary', $keys);

        $this->assertContains('outstanding-loans', collect($this->indexReports($this->user))->pluck('key'));
    }

    public function test_the_csv_export_streams_the_new_reports(): void
    {
        app(LoanService::class)->issueOutbound([
            'part_id' => $this->bondedPart()->id, 'quantity' => 1,
            'from_store_id' => Store::where('slug', 'bonded')->value('id'),
            'party_name' => 'Kaduna Flying Club',
            'started_at' => today()->toDateString(),
        ], $this->user);

        $response = $this->actingAs($this->user)->get(route('reports.export', 'outstanding-loans'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Kaduna Flying Club', $this->streamed($response));
    }

    /** @return array<int, array<string, mixed>> */
    protected function indexReports(User $user): array
    {
        return $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
            ])
            ->get(route('reports'))
            ->json('props.reports');
    }

    protected function streamed($response): string
    {
        ob_start();
        $response->baseResponse->sendContent();

        return (string) ob_get_clean();
    }
}
