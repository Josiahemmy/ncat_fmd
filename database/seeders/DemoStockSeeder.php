<?php

namespace Database\Seeders;

use App\Models\Aircraft;
use App\Models\AtaChapter;
use App\Models\Part;
use App\Models\PartBatch;
use App\Models\PartSerial;
use App\Models\Requisition;
use App\Models\Store;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Stock\SerialStateService;
use App\Services\Stock\StockService;
use Illuminate\Database\Seeder;

/**
 * DEV / LOCAL ONLY — realistic demo stock for browser verification.
 * NOT referenced by DatabaseSeeder; run explicitly: `db:seed --class=DemoStockSeeder`.
 */
class DemoStockSeeder extends Seeder
{
    public function run(): void
    {
        $stock = app(StockService::class);
        $user = User::first() ?? User::factory()->create();
        $bonded = Store::where('type', 'bonded')->first();
        $quarantine = Store::where('type', 'quarantine')->first();
        $ata = fn (string $n) => AtaChapter::where('chapter_number', $n)->value('id');

        // Normal consumable — healthy Bonded stock.
        $oring = Part::firstOrCreate(['part_number' => 'MS29513-014'], [
            'description' => 'O-Ring, Packing', 'ata_chapter_id' => $ata('20'),
            'unit_of_issue' => 'EA', 'unit_price' => 350, 'min_level' => 20, 'reorder_level' => 40, 'max_level' => 200,
        ]);
        $stock->openingBalance(part: $oring, store: $bonded, quantity: 150, user: $user);

        // Below-reorder tyre.
        $tyre = Part::firstOrCreate(['part_number' => '505C61-8'], [
            'description' => 'Main Wheel Tyre', 'ata_chapter_id' => $ata('32'),
            'unit_of_issue' => 'EA', 'unit_price' => 92000, 'min_level' => 4, 'reorder_level' => 8, 'max_level' => 24,
        ]);
        $stock->openingBalance(part: $tyre, store: $bonded, quantity: 6, user: $user);

        // Flammable in Quarantine → awaiting certification (routes to Dope).
        $sealant = Part::firstOrCreate(['part_number' => 'PR-1440-B2'], [
            'description' => 'Sealant, Fuel Tank (flammable)', 'ata_chapter_id' => $ata('28'),
            'unit_of_issue' => 'KIT', 'unit_price' => 18000, 'min_level' => 2, 'reorder_level' => 4, 'is_flammable' => true,
        ]);
        $stock->receive(part: $sealant, store: $quarantine, quantity: 5, user: $user);

        // Shelf-life part with batches (one expiring) in Bonded.
        $battery = Part::firstOrCreate(['part_number' => 'GILL-7025-28'], [
            'description' => 'Battery, Ni-Cd', 'ata_chapter_id' => $ata('24'),
            'unit_of_issue' => 'EA', 'unit_price' => 210000, 'min_level' => 1, 'reorder_level' => 2, 'has_shelf_life' => true,
        ]);
        $soon = PartBatch::firstOrCreate(['part_id' => $battery->id, 'batch_number' => 'B-2411'], ['batch_year' => 2024, 'expiry_date' => now()->addDays(45), 'qty_received' => 2]);
        $stock->openingBalance(part: $battery, store: $bonded, quantity: 2, user: $user, batchId: $soon->id);

        // Serialized avionics unit in Bonded.
        $gps = Part::firstOrCreate(['part_number' => 'GTN-650'], [
            'description' => 'GPS/NAV/COMM Unit', 'ata_chapter_id' => $ata('34'),
            'unit_of_issue' => 'EA', 'unit_price' => 4500000, 'is_serialized' => true,
        ]);
        $sn = PartSerial::firstOrCreate(['part_id' => $gps->id, 'serial_number' => 'SN-778812'], ['status' => 'in_store']);
        $stock->openingBalance(part: $gps, store: $bonded, quantity: 1, user: $user, serialId: $sn->id);

        // Fuel in the Fuel Dump.
        $avgas = Part::firstOrCreate(['part_number' => 'AVGAS-100LL'], [
            'description' => 'Aviation Gasoline 100LL', 'ata_chapter_id' => $ata('28'),
            'unit_of_issue' => 'L', 'unit_price' => 1200, 'is_fuel' => true, 'min_level' => 2000, 'reorder_level' => 5000,
        ]);
        $stock->fuelReceive(part: $avgas, quantity: 8000, user: $user);

        // Parts on Aircraft — install the GPS serial onto a real airframe via the
        // Phase 3 flow (issue → serial install), so the workspace has content.
        $tbm = Aircraft::where('registration', '5N-BZA')->first();
        if ($tbm) {
            $stock->fuelIssue(part: $avgas, quantity: 320, aircraft: $tbm, user: $user);
        }
        if ($tbm && $sn->status === 'in_store') {
            $stock->issue(part: $gps, store: $bonded, quantity: 1, user: $user, serialId: $sn->id, aircraftId: $tbm->id);
            app(SerialStateService::class)->install($sn, $tbm, position: 'FWD AVIONICS BAY', user: $user);
        }

        // A couple of open work orders + a pending requisition across the fleet
        // so the fleet grid badges and dashboard alerts show real numbers.
        if ($tbm) {
            WorkOrder::factory()->create([
                'aircraft_id' => $tbm->id, 'status' => 'open',
                'title' => 'SNAG: GPS unit intermittent', 'wo_ref' => 'FMD/TBM850/07/26/1401',
            ]);
            Requisition::factory()->submitted()->create([
                'aircraft_id' => $tbm->id, 'full_description' => 'GPS/NAV/COMM Unit', 'part_no' => 'GTN-650',
            ]);
        }

        $baron = Aircraft::where('registration', '5N-CAZ')->first();
        if ($baron) {
            WorkOrder::factory()->inspection()->create([
                'aircraft_id' => $baron->id, 'status' => 'in_progress', 'wo_ref' => 'FMD/BARON58/07/26/1402',
            ]);
        }
    }
}
