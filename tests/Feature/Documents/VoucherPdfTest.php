<?php

namespace Tests\Feature\Documents;

use App\Models\Aircraft;
use App\Models\Part;
use App\Models\Requisition;
use App\Models\Siv;
use App\Models\SivItem;
use App\Models\Srv;
use App\Models\SrvItem;
use App\Models\Store;
use App\Models\User;
use App\Services\Stock\StockService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paper-exact voucher PDFs (dompdf). Each route must stream a real PDF, be
 * permission-gated, and carry the form's key identifying strings.
 */
class VoucherPdfTest extends TestCase
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

    protected function assertIsPdf($response): void
    {
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_requisition_pdf_streams_and_is_gated(): void
    {
        $req = Requisition::factory()->create([
            'aircraft_id' => Aircraft::factory()->create()->id,
            'full_description' => 'GPS/NAV/COMM Unit',
        ]);

        // Blade renders the form's identifying strings.
        $html = view('pdf.requisition', ['r' => $req->load('aircraft'), 'crest' => 'x'])->render();
        $this->assertStringContainsString('Aircraft Spare Parts Requisition Sheet', $html);
        $this->assertStringContainsString('THIS COPY TO', $html);
        $this->assertStringContainsString($req->requisition_no, $html);

        $this->assertIsPdf($this->actingAs($this->admin)->get(route('requisitions.pdf', $req)));

        // Gated: a permission-less user is forbidden.
        $this->actingAs(User::factory()->create())
            ->get(route('requisitions.pdf', $req))->assertForbidden();
    }

    public function test_siv_pdf_streams_with_lines_and_words(): void
    {
        $siv = Siv::factory()->create(['siv_number' => '0293', 'requisition_for' => 'Engine bay']);
        SivItem::create([
            'siv_id' => $siv->id, 'line_no' => 1, 'part_id' => Part::factory()->create()->id,
            'source_store_id' => Store::where('slug', 'bonded')->value('id'),
            'description' => 'O-Ring', 'qty_required' => 3, 'qty_issued' => 3, 'rate' => 350, 'amount' => 1050,
        ]);

        $this->assertIsPdf($this->actingAs($this->admin)->get(route('issuing.pdf', $siv)));
    }

    public function test_srv_pdf_streams_with_destination_store(): void
    {
        $srv = Srv::factory()->create([
            'srv_number' => '0201',
            'destination_store_id' => Store::where('slug', 'quarantine')->value('id'),
        ]);
        SrvItem::create([
            'srv_id' => $srv->id, 'line_no' => 1, 'part_id' => Part::factory()->create()->id,
            'quantity' => 10, 'rate' => 500, 'amount' => 5000,
        ]);

        $this->assertIsPdf($this->actingAs($this->admin)->get(route('receiving.pdf', $srv)));
    }

    public function test_tally_pdf_streams_for_a_store_and_consolidated(): void
    {
        $part = Part::factory()->create();
        $stock = app(StockService::class);
        $bonded = Store::where('slug', 'bonded')->firstOrFail();
        $stock->openingBalance(part: $part, store: $bonded, quantity: 20, user: $this->admin);

        $this->assertIsPdf($this->actingAs($this->admin)->get(route('tally-cards.pdf', ['part' => $part->id, 'store' => $bonded->id])));
        $this->assertIsPdf($this->actingAs($this->admin)->get(route('tally-cards.pdf', ['part' => $part->id, 'store' => 'all'])));
    }
}
