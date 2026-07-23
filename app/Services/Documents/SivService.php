<?php

namespace App\Services\Documents;

use App\Exceptions\Stock\StockException;
use App\Models\PartSerial;
use App\Models\Siv;
use App\Models\SivItem;
use App\Models\User;
use App\Services\Stock\SerialStateService;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\DB;

/**
 * Posts a Store Issue Voucher into the ledger. Every issued line leaves through
 * StockService::issue() OUT of Bonded / Dope only. Batches follow FEFO (Phase 2
 * behaviour); serialized lines issue specific serials and, when the line is tied
 * to a requisition that names an aircraft, the serial is installed onto it.
 * Requisition-linked lines feed back: a fully-issued line flips its requisition
 * to `issued` (partial issue leaves it `approved`). Posting is irreversible.
 */
class SivService
{
    /** Stores a SIV may issue from — serviceable stock only. */
    public const ISSUABLE_STORE_TYPES = ['bonded', 'dope'];

    public function __construct(
        protected StockService $stock,
        protected SerialStateService $serials,
    ) {
    }

    /** Field errors surfaced before posting so users get clean messages. */
    public function validationErrors(Siv $siv): array
    {
        $errors = [];
        foreach ($siv->items as $i => $item) {
            if (! in_array($item->sourceStore->type, self::ISSUABLE_STORE_TYPES, true)) {
                $errors["items.{$i}.source_store_id"] = 'Issues may only be made from Bonded or Dope stores.';
            }
            $issue = (float) $item->qty_issued;
            if ($issue > (float) $item->qty_required) {
                $errors["items.{$i}.qty_issued"] = 'Quantity issued cannot exceed quantity required.';
            }
            if ($item->part->is_serialized) {
                $serials = array_filter((array) $item->serial_ids);
                if (count($serials) !== (int) $issue) {
                    $errors["items.{$i}.serial_ids"] = "Select exactly {$issue} serial(s) to issue for {$item->part->part_number}.";
                }
            }
        }

        return $errors;
    }

    public function post(Siv $siv, ?User $user = null): Siv
    {
        if ($siv->isPosted()) {
            throw new StockException('This SIV has already been posted and cannot be re-posted.');
        }
        if ($siv->items->isEmpty()) {
            throw new StockException('Cannot post a SIV with no items.');
        }

        return DB::transaction(function () use ($siv, $user) {
            foreach ($siv->items as $item) {
                $this->postItem($siv, $item, $user);
            }

            $siv->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by_user_id' => $user?->id,
            ]);

            activity('siv')->causedBy($user)->performedOn($siv)
                ->event('posted')->log("Posted SIV {$siv->siv_number}");

            return $siv;
        });
    }

    protected function postItem(Siv $siv, SivItem $item, ?User $user): void
    {
        $store = $item->sourceStore;
        if (! in_array($store->type, self::ISSUABLE_STORE_TYPES, true)) {
            throw new StockException("Cannot issue from {$store->name}. Issues are Bonded/Dope only.");
        }

        $qty = (float) $item->qty_issued;
        if ($qty <= 0) {
            return; // nothing issued on this line (fully deferred)
        }

        $part = $item->part;
        $requisition = $item->requisition;
        $aircraftId = $requisition?->aircraft_id;

        if ($part->is_serialized) {
            foreach (array_filter((array) $item->serial_ids) as $serialId) {
                $serial = PartSerial::findOrFail($serialId);
                $this->stock->issue(
                    part: $part, store: $store, quantity: 1, user: $user,
                    batchId: $serial->part_batch_id, serialId: $serial->id,
                    aircraftId: $aircraftId, reference: $siv->siv_number, source: $siv,
                );
                // Installed onto the airframe when the requisition names an aircraft.
                if ($requisition && $aircraftId) {
                    $this->serials->install($serial->fresh(), $requisition->aircraft, $requisition->position, $user);
                }
            }
        } else {
            // FEFO: earliest-expiring non-expired batch, unless one is chosen.
            $batchId = $item->part_batch_id ?? $this->stock->suggestBatch($part)?->id;
            $this->stock->issue(
                part: $part, store: $store, quantity: $qty, user: $user,
                batchId: $batchId, serialId: null,
                aircraftId: $aircraftId, reference: $siv->siv_number, source: $siv,
            );
        }

        // Requisition feedback: fully-issued flips to `issued`; partial stays `approved`.
        if ($requisition && $requisition->status === 'approved' && $qty >= (float) $item->qty_required) {
            $requisition->update([
                'status' => 'issued',
                'issued_at' => now(),
                'issued_by' => $siv->issued_by ?: $user?->name,
            ]);
        }
    }
}
