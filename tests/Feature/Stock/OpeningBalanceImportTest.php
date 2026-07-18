<?php

namespace Tests\Feature\Stock;

use App\Models\Part;
use App\Models\StockBalance;
use App\Models\Store;
use App\Models\User;
use App\Services\Stock\OpeningBalanceImporter;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningBalanceImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        $this->user = User::factory()->create();
    }

    protected function importer(): OpeningBalanceImporter
    {
        return app(OpeningBalanceImporter::class);
    }

    public function test_preview_reports_row_errors_without_writing_anything(): void
    {
        $rows = [
            ['part_number' => 'A-1', 'description' => 'Valid part', 'store' => 'bonded', 'qty' => '5', 'unit_price' => '100'],
            ['part_number' => 'B-2', 'description' => 'Bad store', 'store' => 'nowhere', 'qty' => '3'],
            ['part_number' => '', 'description' => 'No part no', 'store' => 'bonded', 'qty' => '1'],
            ['part_number' => 'C-3', 'description' => 'Bad qty', 'store' => 'bonded', 'qty' => '-2'],
        ];

        $preview = $this->importer()->preview($rows);

        $this->assertTrue($preview[0]['valid']);
        $this->assertFalse($preview[1]['valid']); // unknown store
        $this->assertFalse($preview[2]['valid']); // missing part number
        $this->assertFalse($preview[3]['valid']); // negative qty

        // Dry-run must not create anything.
        $this->assertSame(0, Part::count());
        $this->assertSame(0, StockBalance::count());
    }

    public function test_import_creates_parts_and_posts_opening_balances(): void
    {
        $rows = [
            ['part_number' => 'A-1', 'description' => 'Washer', 'store' => 'bonded', 'qty' => '25', 'unit_price' => '50'],
            ['part_number' => 'A-2', 'description' => 'Bolt', 'store' => 'bonded', 'qty' => '10'],
        ];

        $result = $this->importer()->import($rows, $this->user);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(2, Part::count());

        $bonded = Store::where('type', 'bonded')->value('id');
        $washer = Part::where('part_number', 'A-1')->first();
        $this->assertEquals(25, StockBalance::where('part_id', $washer->id)->where('store_id', $bonded)->value('quantity'));
        $this->assertEquals(50, $washer->unit_price);
    }

    public function test_import_rejects_the_whole_batch_if_any_row_is_invalid(): void
    {
        $rows = [
            ['part_number' => 'A-1', 'description' => 'Good', 'store' => 'bonded', 'qty' => '5'],
            ['part_number' => 'B-2', 'description' => 'Bad store', 'store' => 'nowhere', 'qty' => '1'],
        ];

        $result = $this->importer()->import($rows, $this->user);

        $this->assertFalse($result['committed']);
        $this->assertSame(0, Part::count()); // atomic — nothing written
    }

    public function test_import_creates_serials_and_posts_by_serial_count(): void
    {
        $rows = [
            ['part_number' => 'SER-1', 'description' => 'Serialized unit', 'store' => 'bonded', 'serials' => 'SN1|SN2|SN3'],
        ];

        $result = $this->importer()->import($rows, $this->user);

        $this->assertSame(1, $result['imported']);
        $part = Part::where('part_number', 'SER-1')->first();
        $this->assertTrue($part->is_serialized);
        $this->assertSame(3, $part->serials()->count());
        $this->assertEquals(3, StockBalance::where('part_id', $part->id)->value('quantity'));
    }
}
