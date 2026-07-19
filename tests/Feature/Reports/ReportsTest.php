<?php

namespace Tests\Feature\Reports;

use App\Models\Aircraft;
use App\Models\AircraftType;
use App\Models\Part;
use App\Models\Store;
use App\Models\User;
use App\Services\Reports\ReportService;
use App\Services\Stock\StockService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create()->assignRole('Super Admin');
    }

    protected function reports(): ReportService
    {
        return app(ReportService::class);
    }

    protected function stock(): StockService
    {
        return app(StockService::class);
    }

    protected function bonded(): Store
    {
        return Store::where('slug', 'bonded')->firstOrFail();
    }

    public function test_stock_summary_computes_value_and_filters_by_state(): void
    {
        $low = Part::factory()->create(['reorder_level' => 10, 'unit_price' => 100]);
        $ok = Part::factory()->create(['reorder_level' => 1, 'unit_price' => 50]);
        $this->stock()->openingBalance(part: $low, store: $this->bonded(), quantity: 5, user: $this->admin);  // value 500, below_reorder
        $this->stock()->openingBalance(part: $ok, store: $this->bonded(), quantity: 20, user: $this->admin);  // value 1000, ok

        // rows() is a single-use lazy cursor — materialise once for assertions.
        // Column keys contain dots ("Part No."), so match with exact-key access,
        // not firstWhere/data_get (which would treat the dot as a nested path).
        $all = collect($this->reports()->rows('stock-summary'));
        $this->assertCount(2, $all);
        $lowRow = $all->first(fn ($r) => $r['Part No.'] === $low->part_number);
        $this->assertEqualsWithDelta(500.0, $lowRow['Value (₦)'], 0.01);
        $this->assertSame('below_reorder', $lowRow['State']);

        $filtered = collect($this->reports()->rows('stock-summary', ['state' => 'below_reorder']));
        $this->assertCount(1, $filtered);
        $this->assertSame($low->part_number, $filtered->first()['Part No.']);
    }

    public function test_movement_register_honors_date_filter(): void
    {
        $part = Part::factory()->create();
        $this->stock()->openingBalance(part: $part, store: $this->bonded(), quantity: 10, user: $this->admin);

        $inRange = $this->reports()->rows('movements', ['from' => today()->toDateString()])->count();
        $this->assertSame(1, $inRange);

        $future = $this->reports()->rows('movements', ['from' => today()->addDays(5)->toDateString()])->count();
        $this->assertSame(0, $future);
    }

    public function test_consumption_groups_issues_by_aircraft(): void
    {
        $type = AircraftType::factory()->create(['name' => 'TYPE-C']);
        $aircraft = Aircraft::factory()->create(['aircraft_type_id' => $type->id]);
        $part = Part::factory()->create();
        $this->stock()->openingBalance(part: $part, store: $this->bonded(), quantity: 100, user: $this->admin);
        $this->stock()->issue(part: $part, store: $this->bonded(), quantity: 12, user: $this->admin, aircraftId: $aircraft->id);

        $rows = $this->reports()->rows('consumption')->values();
        $row = $rows->firstWhere('Registration', $aircraft->registration);
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(12.0, $row['Qty Issued'], 0.01);
        $this->assertSame('TYPE-C', $row['Type']);
    }

    public function test_csv_export_streams_with_bom_header_and_honors_filters(): void
    {
        $low = Part::factory()->create(['reorder_level' => 10, 'unit_price' => 100]);
        $ok = Part::factory()->create(['reorder_level' => 1]);
        $this->stock()->openingBalance(part: $low, store: $this->bonded(), quantity: 5, user: $this->admin);
        $this->stock()->openingBalance(part: $ok, store: $this->bonded(), quantity: 20, user: $this->admin);

        $res = $this->actingAs($this->admin)
            ->get(route('reports.export', ['report' => 'stock-summary', 'format' => 'csv', 'state' => 'below_reorder']));

        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));
        $body = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);          // UTF-8 BOM
        $this->assertStringContainsString('Part No.', $body);          // header row
        $this->assertStringContainsString($low->part_number, $body);   // matched row
        $this->assertStringNotContainsString($ok->part_number, $body); // filtered out
    }

    public function test_pdf_export_streams(): void
    {
        Part::factory()->create();
        $res = $this->actingAs($this->admin)
            ->get(route('reports.export', ['report' => 'stock-summary', 'format' => 'pdf']));
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $res->getContent());
    }

    public function test_reports_are_permission_gated(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('reports'))->assertForbidden();
        $this->actingAs($stranger)->get(route('reports.export', ['report' => 'movements', 'format' => 'csv']))->assertForbidden();
    }

    public function test_unknown_report_404s(): void
    {
        $this->actingAs($this->admin)->get(route('reports.export', ['report' => 'nope', 'format' => 'csv']))->assertNotFound();
    }
}
