<?php

namespace App\Support;

use App\Models\Srv;
use Illuminate\Support\Facades\DB;

/**
 * Backfills the polymorphic source link on legacy fuel-receipt movements.
 *
 * Before Phase 5, fuelReceive() stored the SRV only as a free-text `reference`
 * (the srv_number). This links each such movement to its SRV model by matching
 * that reference, without disturbing the reference string itself. Idempotent:
 * only rows still missing a source are touched.
 */
class FuelReceiptSourceBackfill
{
    /** @return int number of movements linked */
    public static function run(): int
    {
        $linked = 0;

        DB::table('stock_movements')
            ->where('movement_type', 'fuel_receive')
            ->whereNull('source_type')
            ->whereNotNull('reference')
            ->orderBy('id')
            ->chunkById(500, function ($movements) use (&$linked) {
                $numbers = collect($movements)->pluck('reference')->unique()->filter();
                $srvByNumber = Srv::whereIn('srv_number', $numbers)->pluck('id', 'srv_number');

                foreach ($movements as $movement) {
                    $srvId = $srvByNumber[$movement->reference] ?? null;
                    if ($srvId === null) {
                        continue;
                    }

                    DB::table('stock_movements')->where('id', $movement->id)->update([
                        'source_type' => Srv::class,
                        'source_id' => $srvId,
                    ]);
                    $linked++;
                }
            });

        return $linked;
    }
}
