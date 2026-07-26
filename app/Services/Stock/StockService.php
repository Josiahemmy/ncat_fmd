<?php

namespace App\Services\Stock;

use App\Exceptions\Stock\InsufficientStockException;
use App\Models\Part;
use App\Models\PartBatch;
use App\Models\PartSerial;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The ONLY write-path to the stock_movements ledger (spec §3.2/§3.3).
 * No controller inserts movements directly.
 *
 * Every posting runs in a transaction that locks the per-part/per-store
 * balance row (lockForUpdate) before reading it, so concurrent postings are
 * serialised and a balance can never be read or written wrong — invariant (f).
 * balance_after is kept non-negative for `out` movements — invariant (b).
 */
class StockService
{
    /**
     * The single locked write primitive. All public posting methods funnel
     * through here so the invariants are enforced in exactly one place.
     *
     * @param  array<string, mixed>  $attributes  extra movement columns
     */
    protected function post(
        Part $part,
        Store $store,
        string $direction,   // 'in' | 'out'
        float $quantity,
        string $movementType,
        ?User $user = null,
        array $attributes = [],
        ?string $serialStatus = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \App\Exceptions\Stock\StockException('Movement quantity must be positive.');
        }

        // Invariant (c): a serialised part moves exactly one serial at a time.
        $serialId = $attributes['part_serial_id'] ?? null;
        if ($serialId !== null && abs($quantity - 1.0) > 0.0001) {
            throw new \App\Exceptions\Stock\StockException('Serialized parts move one serial at a time (quantity must be 1).');
        }

        return DB::transaction(function () use ($part, $store, $direction, $quantity, $movementType, $user, $attributes, $serialId, $serialStatus) {
            // Ensure the balance row exists, then lock it for the rest of the tx.
            StockBalance::firstOrCreate(
                ['part_id' => $part->id, 'store_id' => $store->id],
                ['quantity' => 0],
            );
            $balance = StockBalance::where('part_id', $part->id)
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->first();

            $current = (float) $balance->quantity;
            $newBalance = $direction === 'in' ? $current + $quantity : $current - $quantity;

            if ($newBalance < 0) {
                throw new InsufficientStockException(
                    "Insufficient stock of {$part->part_number} in {$store->name}: have {$current}, need {$quantity}.",
                );
            }

            $movement = StockMovement::create(array_merge([
                'part_id' => $part->id,
                'store_id' => $store->id,
                'direction' => $direction,
                'quantity' => $quantity,
                'balance_after' => $newBalance,
                'movement_type' => $movementType,
                'user_id' => $user?->id,
                'posted_at' => now(),
            ], $attributes));

            $balance->update(['quantity' => $newBalance]);

            // Keep the serial's single location/state in sync (invariant c).
            // `serialStatus` lets a caller land the serial in a state other than
            // "in store". A loaned-out unit is in NCAT's books but is not on a
            // shelf, and must not read as though it were.
            if ($serialId !== null) {
                $serial = PartSerial::find($serialId);
                $serial?->update($direction === 'in'
                    ? ['current_store_id' => $store->id, 'status' => $serialStatus ?? 'in_store']
                    : ['current_store_id' => null]);
            }

            return $movement;
        });
    }

    /**
     * Record existing physical stock (digitising the paper tally cards).
     * Optionally captures the part's unit price at the same time.
     */
    public function openingBalance(
        Part $part,
        Store $store,
        float $quantity,
        ?User $user = null,
        ?float $unitPrice = null,
        ?int $batchId = null,
        ?int $serialId = null,
        ?string $remarks = null,
    ): StockMovement {
        if ($unitPrice !== null) {
            $part->forceFill(['unit_price' => $unitPrice])->save();
        }

        return $this->post(
            part: $part,
            store: $store,
            direction: 'in',
            quantity: $quantity,
            movementType: 'opening_balance',
            user: $user,
            attributes: [
                'part_batch_id' => $batchId,
                'part_serial_id' => $serialId,
                'reference' => 'OPENING',
                'remarks' => $remarks,
            ],
        );
    }

    /**
     * Receive incoming stock. Per workflow rule 3 this normally lands in
     * Quarantine (or the Fuel Dump for fuel); the caller passes the store.
     */
    public function receive(
        Part $part,
        Store $store,
        float $quantity,
        ?User $user = null,
        ?int $batchId = null,
        ?int $serialId = null,
        ?string $reference = null,
        ?object $source = null,
    ): StockMovement {
        return $this->post(
            part: $part,
            store: $store,
            direction: 'in',
            quantity: $quantity,
            movementType: 'receiving',
            user: $user,
            attributes: $this->sourceAttributes($source) + [
                'part_batch_id' => $batchId,
                'part_serial_id' => $serialId,
                'reference' => $reference,
            ],
        );
    }

    /**
     * Issue stock out of a serviceable store. Only Bonded/Dope (or general)
     * stores are issuable — Quarantine is isolated (invariant e); fuel uses
     * fuelIssue().
     */
    public function issue(
        Part $part,
        Store $store,
        float $quantity,
        ?User $user = null,
        ?int $batchId = null,
        ?int $serialId = null,
        ?int $aircraftId = null,
        ?string $reference = null,
        ?object $source = null,
        bool $allowExpired = false,
    ): StockMovement {
        $this->assertIssuable($store);

        // Invariant (d): expired batches are blocked unless an explicit
        // (stock.adjust-gated) override is passed — and the override is logged.
        if ($batchId !== null) {
            $batch = PartBatch::find($batchId);
            if ($batch?->isExpired()) {
                if (! $allowExpired) {
                    throw new \App\Exceptions\Stock\StockException(
                        'This batch has expired. Issuing it requires an explicit override (stock.adjust).',
                    );
                }
                activity('stock')->causedBy($user)->performedOn($batch)
                    ->event('expired_override')
                    ->log("Issued expired batch {$batch->batch_number} of {$part->part_number} under override");
            }
        }

        return $this->post(
            part: $part,
            store: $store,
            direction: 'out',
            quantity: $quantity,
            movementType: 'issue',
            user: $user,
            attributes: $this->sourceAttributes($source) + [
                'part_batch_id' => $batchId,
                'part_serial_id' => $serialId,
                'aircraft_id' => $aircraftId,
                'reference' => $reference,
            ],
        );
    }

    /**
     * Move stock store→store as an atomic linked pair (OUT of `from`, IN to
     * `to`). Quarantine is not directly transferable — invariant (e); use
     * certify() to release it.
     *
     * @return array{0: StockMovement, 1: StockMovement}
     */
    public function transfer(
        Part $part,
        Store $from,
        Store $to,
        float $quantity,
        ?User $user = null,
        ?int $batchId = null,
        ?int $serialId = null,
        ?string $remarks = null,
    ): array {
        if ($from->type === 'quarantine') {
            throw new \App\Exceptions\Stock\StockException(
                'Quarantined stock can only be moved via certification, not a direct transfer.',
            );
        }
        // The loan stores are written by the loan actions alone. Allowing a hand
        // transfer in or out would put stock there with no loan record behind it.
        if ($from->isLoanStore() || $to->isLoanStore()) {
            throw new \App\Exceptions\Stock\StockException(
                'Loan holding locations are moved by the loan actions, not by a direct transfer.',
            );
        }

        return $this->pairedMove($part, $from, $to, $quantity, 'transfer', $user, [
            'part_batch_id' => $batchId,
            'part_serial_id' => $serialId,
            'remarks' => $remarks,
        ]);
    }

    /**
     * Certify (release) quarantined stock. `decision` is release_to_bonded |
     * release_to_dope | reject. Release posts a paired certification transfer
     * Quarantine → target; reject posts a documented return out of Quarantine.
     *
     * @return array<int, StockMovement>
     */
    public function certify(
        Part $part,
        float $quantity,
        string $decision,
        ?User $user = null,
        ?string $remarks = null,
        ?int $batchId = null,
    ): array {
        $quarantine = Store::where('type', 'quarantine')->firstOrFail();

        if ($decision === 'reject') {
            return [$this->post(
                part: $part,
                store: $quarantine,
                direction: 'out',
                quantity: $quantity,
                movementType: 'return',
                user: $user,
                attributes: ['reference' => 'REJECT', 'remarks' => $remarks, 'part_batch_id' => $batchId],
            )];
        }

        $targetType = $decision === 'release_to_dope' ? 'dope' : 'bonded';
        $target = Store::where('type', $targetType)->firstOrFail();

        return $this->pairedMove($part, $quarantine, $target, $quantity, 'certification_transfer', $user, [
            'part_batch_id' => $batchId,
            'remarks' => $remarks,
        ]);
    }

    /**
     * Post a stock-take adjustment. Signed `delta`; a reason is mandatory
     * (spec §5.8). Positive = found stock (IN), negative = shrinkage (OUT).
     */
    public function adjust(
        Part $part,
        Store $store,
        float $delta,
        string $reason,
        ?User $user = null,
    ): StockMovement {
        if (trim($reason) === '') {
            throw new \App\Exceptions\Stock\StockException('An adjustment requires a reason.');
        }
        if ($delta == 0.0) {
            throw new \App\Exceptions\Stock\StockException('Adjustment delta cannot be zero.');
        }

        return $this->post(
            part: $part,
            store: $store,
            direction: $delta > 0 ? 'in' : 'out',
            quantity: abs($delta),
            movementType: 'adjustment',
            user: $user,
            attributes: ['reference' => 'ADJ', 'remarks' => $reason],
        );
    }

    /**
     * Lend NCAT stock to an external party (spec §12.7). A paired move out of
     * the issuing store and into "On Loan (Out)": the units are still NCAT
     * property, so they stay in the ledger, they are simply somewhere else.
     * The issuing store's balance drops exactly as it would on an issue.
     *
     * @return array{0: StockMovement, 1: StockMovement}
     */
    public function loanOut(
        Part $part,
        Store $from,
        float $quantity,
        ?User $user = null,
        ?int $batchId = null,
        ?int $serialId = null,
        ?object $source = null,
        ?string $remarks = null,
    ): array {
        // Same rule as issuing: nothing leaves Quarantine or the Fuel Dump.
        $this->assertIssuable($from);

        return $this->pairedMove($part, $from, $this->loanOutStore(), $quantity, 'loan_out', $user,
            $this->sourceAttributes($source) + [
                'part_batch_id' => $batchId,
                'part_serial_id' => $serialId,
                'remarks' => $remarks,
            ],
            serialStatus: 'on_loan',
        );
    }

    /**
     * Bring a lent-out unit back into the store it left. The originating store
     * is passed by the caller from the loan record rather than chosen at return
     * time, so a loan cannot come back to the wrong shelf.
     *
     * @return array{0: StockMovement, 1: StockMovement}
     */
    public function loanReturn(
        Part $part,
        Store $to,
        float $quantity,
        ?User $user = null,
        ?int $batchId = null,
        ?int $serialId = null,
        ?object $source = null,
        ?string $remarks = null,
    ): array {
        return $this->pairedMove($part, $this->loanOutStore(), $to, $quantity, 'loan_return', $user,
            $this->sourceAttributes($source) + [
                'part_batch_id' => $batchId,
                'part_serial_id' => $serialId,
                'remarks' => $remarks,
            ],
        );
    }

    /**
     * Take custody of another organisation's stock. It lands in "Loaned In",
     * a store flagged `owned = false`, so it is tracked and issuable but is
     * excluded from every query that reports what NCAT owns.
     */
    public function loanIn(
        Part $part,
        float $quantity,
        ?User $user = null,
        ?int $serialId = null,
        ?object $source = null,
        ?string $remarks = null,
    ): StockMovement {
        return $this->post(
            part: $part,
            store: $this->loanInStore(),
            direction: 'in',
            quantity: $quantity,
            movementType: 'loan_in',
            user: $user,
            attributes: $this->sourceAttributes($source) + [
                'part_serial_id' => $serialId,
                'remarks' => $remarks,
            ],
        );
    }

    /** Hand borrowed stock back to its owner: out of "Loaned In", not to a store. */
    public function loanInReturn(
        Part $part,
        float $quantity,
        ?User $user = null,
        ?int $serialId = null,
        ?object $source = null,
        ?string $remarks = null,
    ): StockMovement {
        return $this->post(
            part: $part,
            store: $this->loanInStore(),
            direction: 'out',
            quantity: $quantity,
            movementType: 'loan_in_return',
            user: $user,
            attributes: $this->sourceAttributes($source) + [
                'part_serial_id' => $serialId,
                'remarks' => $remarks,
            ],
        );
    }

    /** Receive fuel into the Fuel Dump (litres). No certification step. */
    public function fuelReceive(
        Part $part,
        float $quantity,
        ?User $user = null,
        ?float $unitPrice = null,
        ?string $reference = null,
        ?string $remarks = null,
        ?object $source = null,
    ): StockMovement {
        if ($unitPrice !== null) {
            $part->forceFill(['unit_price' => $unitPrice])->save();
        }

        return $this->post(
            part: $part,
            store: $this->fuelStore(),
            direction: 'in',
            quantity: $quantity,
            movementType: 'fuel_receive',
            user: $user,
            // Polymorphic source (e.g. the SRV) like every other movement, plus
            // the human reference string for continuity with legacy records.
            attributes: $this->sourceAttributes($source) + ['reference' => $reference, 'remarks' => $remarks],
        );
    }

    /** Issue fuel from the Fuel Dump to an aircraft (litres). */
    public function fuelIssue(
        Part $part,
        float $quantity,
        \App\Models\Aircraft $aircraft,
        ?User $user = null,
        ?string $reference = null,
        ?string $remarks = null,
    ): StockMovement {
        return $this->post(
            part: $part,
            store: $this->fuelStore(),
            direction: 'out',
            quantity: $quantity,
            movementType: 'fuel_issue',
            user: $user,
            attributes: ['aircraft_id' => $aircraft->id, 'reference' => $reference, 'remarks' => $remarks],
        );
    }

    /**
     * FEFO — suggest the earliest-expiring, non-expired batch of a part.
     * Batches without an expiry date sort last.
     */
    public function suggestBatch(Part $part): ?PartBatch
    {
        return $part->batches()
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()))
            ->orderByRaw('expiry_date is null, expiry_date asc')
            ->first();
    }

    protected function fuelStore(): Store
    {
        return Store::where('type', 'fuel')->firstOrFail();
    }

    protected function loanOutStore(): Store
    {
        return Store::where('type', Store::LOAN_OUT)->firstOrFail();
    }

    protected function loanInStore(): Store
    {
        return Store::where('type', Store::LOAN_IN)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: StockMovement, 1: StockMovement}
     */
    protected function pairedMove(
        Part $part,
        Store $from,
        Store $to,
        float $quantity,
        string $movementType,
        ?User $user,
        array $attributes = [],
        ?string $serialStatus = null,
    ): array {
        return DB::transaction(function () use ($part, $from, $to, $quantity, $movementType, $user, $attributes, $serialStatus) {
            $group = (string) Str::uuid();

            $out = $this->post(
                part: $part, store: $from, direction: 'out', quantity: $quantity,
                movementType: $movementType, user: $user,
                attributes: $attributes + ['transfer_group' => $group],
            );
            // The IN leg lands second and is what sets the serial's resting
            // state, so the status override belongs here.
            $in = $this->post(
                part: $part, store: $to, direction: 'in', quantity: $quantity,
                movementType: $movementType, user: $user,
                attributes: $attributes + ['transfer_group' => $group],
                serialStatus: $serialStatus,
            );

            return [$out, $in];
        });
    }

    protected function assertIssuable(Store $store): void
    {
        if ($store->type === 'quarantine') {
            throw new \App\Exceptions\Stock\StockException(
                'Quarantined stock is not issuable. It must be certified to a serviceable store first.',
            );
        }
        if ($store->type === 'fuel') {
            throw new \App\Exceptions\Stock\StockException('Use the fuel issue action for the Fuel Dump.');
        }
        // "On Loan (Out)" is a holding location, not a shelf. Stock leaves it by
        // being returned or written off, never by being issued to someone else.
        if ($store->type === Store::LOAN_OUT) {
            throw new \App\Exceptions\Stock\StockException(
                'Stock on loan is not issuable. Record its return first.',
            );
        }
    }

    /** @return array<string, mixed> */
    protected function sourceAttributes(?object $source): array
    {
        if ($source === null) {
            return [];
        }

        return [
            'source_type' => $source::class,
            'source_id' => $source->getKey(),
        ];
    }
}
