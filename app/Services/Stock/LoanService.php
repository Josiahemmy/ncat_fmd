<?php

namespace App\Services\Stock;

use App\Exceptions\Stock\StockException;
use App\Models\Loan;
use App\Models\Part;
use App\Models\PartSerial;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Loans in both directions (spec §12.7). This service owns the loan record and
 * the decision of what to post; every actual ledger write goes through
 * {@see StockService}, so there is no second write path into stock_movements.
 *
 * The two directions are deliberately asymmetric because the accounting is:
 *
 *  · Lending out moves NCAT's own stock to a holding location. Value is
 *    unchanged, the issuing store's balance drops, and an unreturned loan is
 *    written off as an adjustment so it leaves the ledger with a reason
 *    attached rather than sitting on loan forever.
 *
 *  · Borrowing in puts someone else's property into a store flagged
 *    `owned = false`. It is tracked and issuable, but it is invisible to every
 *    query that reports what NCAT owns. Uncatalogued borrowed items never touch
 *    the ledger at all, because there is no part to post against.
 */
class LoanService
{
    public function __construct(protected StockService $stock)
    {
    }

    /**
     * Lend NCAT stock out. The store must be one the department can issue from,
     * which StockService enforces rather than this method re-checking it.
     *
     * @param  array<string, mixed>  $data
     */
    public function issueOutbound(array $data, ?User $user = null): Loan
    {
        return DB::transaction(function () use ($data, $user) {
            $part = Part::findOrFail($data['part_id']);
            $from = Store::findOrFail($data['from_store_id']);

            $loan = Loan::create($this->attributes($data, 'out') + [
                'from_store_id' => $from->id,
                'created_by_user_id' => $user?->id,
            ]);

            $this->stock->loanOut(
                part: $part,
                from: $from,
                quantity: (float) $loan->quantity,
                user: $user,
                batchId: $loan->part_batch_id,
                serialId: $loan->part_serial_id,
                source: $loan,
                remarks: 'On loan to '.$loan->counterparty(),
            );

            return $loan;
        });
    }

    /**
     * Take custody of another organisation's property.
     *
     * A loan against a catalogued part is posted into the non-owned store so it
     * can be issued through the normal SIV path. A loan of something not in the
     * catalogue is recorded on the loan only: there is nothing to post, and
     * inventing a part for it would put a phantom in the catalogue.
     *
     * @param  array<string, mixed>  $data
     */
    public function receiveInbound(array $data, ?User $user = null): Loan
    {
        return DB::transaction(function () use ($data, $user) {
            $loan = Loan::create($this->attributes($data, 'in') + [
                'created_by_user_id' => $user?->id,
            ]);

            if ($loan->part_id === null) {
                return $loan;
            }

            $part = Part::findOrFail($loan->part_id);
            $serialId = $this->borrowedSerial($loan, $part);

            $this->stock->loanIn(
                part: $part,
                quantity: (float) $loan->quantity,
                user: $user,
                serialId: $serialId,
                source: $loan,
                remarks: 'Loaned in from '.$loan->counterparty(),
            );

            return $loan;
        });
    }

    /**
     * Record a return in either direction. Outbound returns land back in the
     * store the units left; inbound returns leave the ledger entirely.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordReturn(Loan $loan, array $data = [], ?User $user = null): Loan
    {
        if (! $loan->isOpen()) {
            throw new StockException('This loan is already closed.');
        }

        return DB::transaction(function () use ($loan, $data, $user) {
            if ($loan->part_id !== null) {
                $part = Part::findOrFail($loan->part_id);

                if ($loan->direction === 'out') {
                    $this->stock->loanReturn(
                        part: $part,
                        to: Store::findOrFail($loan->from_store_id),
                        quantity: (float) $loan->quantity,
                        user: $user,
                        batchId: $loan->part_batch_id,
                        serialId: $loan->part_serial_id,
                        source: $loan,
                        remarks: 'Returned from '.$loan->counterparty(),
                    );
                } else {
                    $this->stock->loanInReturn(
                        part: $part,
                        quantity: (float) $loan->quantity,
                        user: $user,
                        serialId: $loan->part_serial_id,
                        source: $loan,
                        remarks: 'Returned to '.$loan->counterparty(),
                    );

                    // The borrowed serial leaves with its owner: it is neither in
                    // an NCAT store nor fitted to an NCAT aircraft any more.
                    $loan->serial?->update([
                        'status' => 'removed_unserviceable' === $loan->serial->status ? 'removed_unserviceable' : 'in_store',
                        'current_store_id' => null,
                        'current_aircraft_id' => null,
                    ]);
                }
            }

            $loan->update([
                'status' => 'returned',
                'returned_at' => $data['returned_at'] ?? today()->toDateString(),
                'return_condition' => $data['return_condition'] ?? null,
                'installed_aircraft_id' => null,
            ]);

            return $loan;
        });
    }

    /**
     * Write off an outbound loan that is never coming back. Posted as an
     * adjustment out of the holding location, so the units leave the ledger
     * with a signed reason rather than lingering as stock NCAT no longer has.
     * The caller is responsible for the `stock.adjust` permission check.
     */
    public function writeOff(Loan $loan, string $reason, ?User $user = null): Loan
    {
        if ($loan->direction !== 'out') {
            throw new StockException('Only an outbound loan can be written off. Borrowed property is not NCAT stock to write off.');
        }
        if (! $loan->isOpen()) {
            throw new StockException('This loan is already closed.');
        }
        if (trim($reason) === '') {
            throw new StockException('A write-off requires a reason.');
        }

        return DB::transaction(function () use ($loan, $reason, $user) {
            if ($loan->part_id !== null) {
                $this->stock->adjust(
                    part: Part::findOrFail($loan->part_id),
                    store: Store::where('type', Store::LOAN_OUT)->firstOrFail(),
                    delta: -1 * (float) $loan->quantity,
                    reason: 'Loan write-off ('.$loan->counterparty().'): '.trim($reason),
                    user: $user,
                );

                $loan->serial?->update(['status' => 'scrapped', 'current_store_id' => null]);
            }

            $loan->update([
                'status' => 'written_off',
                'written_off_at' => now(),
                'written_off_by_user_id' => $user?->id,
                'write_off_reason' => trim($reason),
            ]);

            return $loan;
        });
    }

    /**
     * Fit a borrowed unit to an aircraft. Allowed, and marked: the loan carries
     * the aircraft and the serial keeps its `is_loaned` flag, which is what the
     * parts-on-aircraft view reads to show it as someone else's property.
     */
    public function installInbound(Loan $loan, int $aircraftId): Loan
    {
        if ($loan->direction !== 'in' || ! $loan->isOpen()) {
            throw new StockException('Only an open inbound loan can be fitted to an aircraft.');
        }

        $loan->update(['installed_aircraft_id' => $aircraftId]);
        $loan->serial?->update([
            'status' => 'installed',
            'current_store_id' => null,
            'current_aircraft_id' => $aircraftId,
        ]);

        return $loan;
    }

    /**
     * Create (or reuse) the serial record for a borrowed serialized item so it
     * can be tracked and, above all, marked as not NCAT's.
     */
    protected function borrowedSerial(Loan $loan, Part $part): ?int
    {
        $number = $loan->serial_text;

        if ($number === null || trim($number) === '' || (float) $loan->quantity !== 1.0) {
            return null;
        }

        $serial = PartSerial::firstOrCreate(
            ['part_id' => $part->id, 'serial_number' => trim($number)],
            ['status' => 'in_store'],
        );
        $serial->update(['is_loaned' => true]);

        $loan->update(['part_serial_id' => $serial->id]);

        return $serial->id;
    }

    /**
     * The columns common to both directions. Anything the caller did not supply
     * stays null rather than being invented.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(array $data, string $direction): array
    {
        return [
            'direction' => $direction,
            'vendor_id' => $data['vendor_id'] ?? null,
            'party_name' => $data['party_name'] ?? null,
            'party_contact' => $data['party_contact'] ?? null,
            'part_id' => $data['part_id'] ?? null,
            'part_batch_id' => $data['part_batch_id'] ?? null,
            'part_serial_id' => $data['part_serial_id'] ?? null,
            'item_description' => $data['item_description'] ?? null,
            'serial_text' => $data['serial_text'] ?? null,
            'quantity' => $data['quantity'],
            'started_at' => $data['started_at'],
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
