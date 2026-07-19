<?php

namespace Database\Seeders;

use App\Models\Aircraft;
use App\Models\AtaChapter;
use App\Models\Part;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DEV / LOCAL ONLY — a realistic-volume ledger (~10k movements over 12 weeks)
 * for dashboard performance sanity (N+1 / TTFB). Bulk-inserted directly (the
 * StockService write-path is proven elsewhere); balances are not the point here
 * so we don't recompute them — the aggregate queries read the ledger, not a
 * consistent balance, and the immutable-model guard only blocks updates/deletes.
 *
 * Run explicitly: `php artisan db:seed --class=PerfSeeder`.
 */
class PerfSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();
        $stores = Store::whereIn('type', ['bonded', 'dope'])->pluck('id')->all();
        if (empty($stores)) {
            $stores = Store::pluck('id')->all();
        }
        $aircraftIds = Aircraft::pluck('id')->all();
        $ataId = AtaChapter::value('id');

        // Ensure a pool of ~40 parts to spread movements across.
        $partIds = Part::pluck('id')->all();
        for ($i = count($partIds); $i < 40; $i++) {
            $partIds[] = Part::create([
                'part_number' => 'PERF-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'description' => 'Perf test consumable '.$i,
                'ata_chapter_id' => $ataId,
                'unit_of_issue' => 'EA',
                'unit_price' => 500 + ($i * 25),
                'min_level' => 5,
                'reorder_level' => 10,
                'max_level' => 500,
            ])->id;
        }

        $total = 10000;
        $now = now();
        $rows = [];

        for ($n = 0; $n < $total; $n++) {
            // Spread posted_at across the last ~84 days (12 weeks).
            $postedAt = (clone $now)->subDays($n % 84)->subMinutes($n % 1440);
            $in = ($n % 3 === 0); // ~1/3 receiving, ~2/3 issuing
            $rows[] = [
                'part_id' => $partIds[$n % count($partIds)],
                'store_id' => $stores[$n % count($stores)],
                'direction' => $in ? 'in' : 'out',
                'quantity' => $in ? (5 + ($n % 20)) : (1 + ($n % 5)),
                'balance_after' => 100, // not used by aggregates
                'movement_type' => $in ? 'receiving' : 'issue',
                'aircraft_id' => (! $in && $aircraftIds) ? $aircraftIds[$n % count($aircraftIds)] : null,
                'user_id' => $user->id,
                'posted_at' => $postedAt,
                'created_at' => $postedAt,
                'updated_at' => $postedAt,
            ];

            if (count($rows) >= 500) {
                DB::table('stock_movements')->insert($rows);
                $rows = [];
            }
        }
        if ($rows) {
            DB::table('stock_movements')->insert($rows);
        }

        $this->command?->info("PerfSeeder: inserted {$total} movements across ".count($partIds).' parts.');
    }
}
