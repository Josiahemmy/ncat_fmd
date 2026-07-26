<?php

namespace App\Services\Documents;

use App\Exceptions\Stock\StockException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Srv;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Purchase Order lifecycle (spec §12.5): draft → issued → partially_received →
 * received → closed, with cancel available until it is closed.
 *
 * Two rules shape the design.
 *
 * The reference is minted at issue, not at creation. Drafts are working copies
 * that may never be sent, and the department's series must have no gaps, so a
 * draft carries no number at all.
 *
 * Issued orders are commercially immutable. Once the vendor holds a copy of the
 * paper, the record has to keep saying what was sent, so lines, vendor, and
 * priority are frozen. A correction is a cancel plus a fresh order, which
 * leaves both documents in the trail rather than rewriting history. Remarks
 * stay editable because they are internal notes, not terms.
 */
class PurchaseOrderService
{
    public function __construct(protected DocumentNumberService $numbers)
    {
    }

    /**
     * Replace the line set of a draft. Lines arrive as an ordered list and are
     * renumbered from 1, so the printed S/NO. column always matches the order
     * the user arranged on screen.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function saveLines(PurchaseOrder $order, array $lines): PurchaseOrder
    {
        $this->assertDraft($order, 'Lines can only be changed while the order is a draft.');

        return DB::transaction(function () use ($order, $lines) {
            $kept = [];

            foreach (array_values($lines) as $i => $line) {
                $attributes = [
                    'purchase_order_id' => $order->id,
                    'line_no' => $i + 1,
                    'description' => $line['description'] ?? null,
                    'part_id' => $line['part_id'] ?? null,
                    'part_number' => $line['part_number'] ?? null,
                    'qty_to_order' => $line['qty_to_order'] ?? 0,
                    'line_status' => $line['line_status'] ?? null,
                    'timeline_month' => $line['timeline_month'] ?? null,
                    'timeline_year' => $line['timeline_year'] ?? null,
                ];

                $model = ! empty($line['id'])
                    ? tap($order->lines()->findOrFail($line['id']))->update($attributes)
                    : PurchaseOrderLine::create($attributes);

                $kept[] = $model->id;
            }

            $order->lines()->whereKeyNot($kept)->delete();

            return $order->refresh();
        });
    }

    /** Mint the reference and send it. This is the point of no return. */
    public function issue(PurchaseOrder $order, ?User $user = null): PurchaseOrder
    {
        $this->assertDraft($order, 'Only a draft purchase order can be issued.');

        if ($order->lines()->count() === 0) {
            throw new StockException('Cannot issue a purchase order with no lines.');
        }

        return DB::transaction(function () use ($order, $user) {
            $order->update([
                'po_number' => $this->numbers->reservePurchaseOrder($order->order_date),
                'status' => 'issued',
                'issued_at' => now(),
                'issued_by_user_id' => $user?->id,
            ]);

            activity('purchase_order')->causedBy($user)->performedOn($order)
                ->event('issued')->log("Issued purchase order {$order->po_number} to {$order->vendor->name}");

            return $order->refresh();
        });
    }

    /**
     * Book received quantities against the order's lines and advance its status.
     * Called from SRV posting, so the ledger and the order can never disagree
     * about what arrived.
     *
     * @param  array<int, float>  $received  purchase_order_line_id => quantity
     */
    public function applyReceipt(PurchaseOrder $order, array $received, ?Srv $srv = null, ?User $user = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $received, $srv, $user) {
            foreach ($received as $lineId => $qty) {
                if ($qty <= 0) {
                    continue;
                }

                $line = $order->lines()->findOrFail($lineId);

                // Over-receipt is refused outright. Receiving more than was
                // ordered means either the vendor over-shipped or the wrong line
                // was picked, and both need a human decision rather than a
                // silently inflated order.
                if ($line->qty_received + $qty > $line->qty_to_order) {
                    throw new StockException(sprintf(
                        'Line %d of %s is for %s but %s would take the received total to %s. Raise a separate order for the excess.',
                        $line->line_no,
                        $order->po_number ?? 'this order',
                        $this->trim($line->qty_to_order),
                        $this->trim($qty),
                        $this->trim($line->qty_received + $qty),
                    ));
                }

                $line->increment('qty_received', $qty);
            }

            $order->refresh();
            $status = $this->receiptStatus($order);

            if ($status !== $order->status) {
                $order->update(['status' => $status]);

                activity('purchase_order')->causedBy($user)->performedOn($order)
                    ->event('received')
                    ->log(sprintf(
                        'Purchase order %s is now %s%s',
                        $order->po_number,
                        str_replace('_', ' ', $status),
                        $srv?->srv_number ? " after SRV {$srv->srv_number}" : '',
                    ));
            }

            return $order->refresh();
        });
    }

    public function close(PurchaseOrder $order, ?User $user = null): PurchaseOrder
    {
        if (! in_array($order->status, ['issued', 'partially_received', 'received'], true)) {
            throw new StockException('Only an issued purchase order can be closed.');
        }

        $order->update(['status' => 'closed', 'closed_at' => now()]);

        activity('purchase_order')->causedBy($user)->performedOn($order)
            ->event('closed')->log("Closed purchase order {$order->po_number}");

        return $order->refresh();
    }

    public function cancel(PurchaseOrder $order, string $reason, ?User $user = null): PurchaseOrder
    {
        if (in_array($order->status, ['closed', 'cancelled'], true)) {
            throw new StockException('This purchase order is already finished.');
        }

        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        activity('purchase_order')->causedBy($user)->performedOn($order)
            ->event('cancelled')
            ->log(sprintf('Cancelled purchase order %s: %s', $order->po_number ?? '(draft)', $reason));

        return $order->refresh();
    }

    /** Fully received when every line has met its ordered quantity. */
    protected function receiptStatus(PurchaseOrder $order): string
    {
        $lines = $order->lines()->get();

        if ($lines->every(fn (PurchaseOrderLine $l) => $l->isFullyReceived())) {
            return 'received';
        }

        return $lines->contains(fn (PurchaseOrderLine $l) => $l->qty_received > 0)
            ? 'partially_received'
            : $order->status;
    }

    protected function assertDraft(PurchaseOrder $order, string $message): void
    {
        if (! $order->isDraft()) {
            throw new StockException($message);
        }
    }

    protected function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
