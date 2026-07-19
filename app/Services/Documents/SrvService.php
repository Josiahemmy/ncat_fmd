<?php

namespace App\Services\Documents;

use App\Exceptions\Stock\StockException;
use App\Models\PartBatch;
use App\Models\PartSerial;
use App\Models\Srv;
use App\Models\User;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Posts a Store Receipt Voucher into the ledger. Every line lands through
 * StockService::receive() (or fuelReceive() for bulk fuel) — no direct ledger
 * writes. Normal parts receive into the SRV's destination store (Quarantine by
 * default, so they surface in the certification queue); fuel routes to the Fuel
 * Dump. Posting is irreversible: corrections are later adjustments / returns.
 */
class SrvService
{
    public function __construct(protected StockService $stock)
    {
    }

    /** Guard used by the controller before posting so users get clean field errors. */
    public function validationErrors(Srv $srv): array
    {
        $errors = [];
        foreach ($srv->items as $i => $item) {
            $part = $item->part;
            if ($part->has_shelf_life && ! $item->batch_no) {
                $errors["items.{$i}.batch_no"] = "Batch number is required for {$part->part_number} (shelf-life part).";
            }
            if ($part->is_serialized) {
                $serials = array_filter((array) $item->serials);
                if (count($serials) !== (int) $item->quantity) {
                    $errors["items.{$i}.serials"] = "Capture exactly {$item->quantity} serial number(s) for {$part->part_number}.";
                }
            }
        }

        return $errors;
    }

    public function post(Srv $srv, ?User $user = null): Srv
    {
        if ($srv->isPosted()) {
            throw new StockException('This SRV has already been posted and cannot be re-posted.');
        }
        if ($srv->items->isEmpty()) {
            throw new StockException('Cannot post an SRV with no items.');
        }

        return DB::transaction(function () use ($srv, $user) {
            foreach ($srv->items as $item) {
                $part = $item->part;
                $qty = (float) $item->quantity;

                // Bulk fuel routes to the Fuel Dump via the dedicated path,
                // now carrying the SRV as its polymorphic source (Phase 5).
                if ($part->is_fuel) {
                    $this->stock->fuelReceive($part, $qty, $user, $item->rate, $srv->srv_number, $item->supplier_details, $srv);
                    continue;
                }

                $batchId = null;
                if ($item->batch_no || $item->expiry_date) {
                    $batchId = PartBatch::firstOrCreate(
                        ['part_id' => $part->id, 'batch_number' => $item->batch_no],
                        ['batch_year' => $item->batch_year, 'expiry_date' => $item->expiry_date, 'qty_received' => $qty],
                    )->id;
                }

                if ($part->is_serialized) {
                    // One serial per movement (StockService invariant): create each
                    // captured serial, then receive it individually.
                    foreach (array_filter((array) $item->serials) as $sn) {
                        $serial = PartSerial::create([
                            'part_id' => $part->id,
                            'part_batch_id' => $batchId,
                            'serial_number' => $sn,
                            'status' => 'in_store',
                        ]);
                        $this->stock->receive($part, $srv->destinationStore, 1, $user, $batchId, $serial->id, $srv->srv_number, $srv);
                    }
                } else {
                    $this->stock->receive($part, $srv->destinationStore, $qty, $user, $batchId, null, $srv->srv_number, $srv);
                }
            }

            $srv->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by_user_id' => $user?->id,
            ]);

            activity('srv')->causedBy($user)->performedOn($srv)
                ->event('posted')->log("Posted SRV {$srv->srv_number} into {$srv->destinationStore->name}");

            return $srv;
        });
    }
}
