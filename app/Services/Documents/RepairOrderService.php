<?php

namespace App\Services\Documents;

use App\Exceptions\Stock\StockException;
use App\Models\PartSerial;
use App\Models\RepairOrder;
use App\Models\RepairOrderLine;
use App\Models\Store;
use App\Models\User;
use App\Services\Stock\SerialStateService;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Repair Order lifecycle (spec §12.5) and the removal loop it closes.
 *
 * The circle a tracked serial travels:
 *
 *   requisition removal   serial → removed_unserviceable
 *   RO issued             serial → at_repair, requisition.repair_order_ref stamped
 *   RO returned, serviceable
 *                         StockService receipt into Quarantine, serial → in_store
 *                         so it surfaces in the certification queue
 *   certified             Quarantine → Bonded, issuable again
 *   RO returned, scrapped serial → scrapped, terminal, no stock posted
 *
 * The serviceable path deliberately posts through StockService into Quarantine
 * rather than putting the unit straight back on a serviceable shelf. A unit
 * back from a vendor is uncertified stock like any other receipt (§5 rule 3),
 * and routing it through Quarantine keeps the certifier in the loop.
 */
class RepairOrderService
{
    public function __construct(
        protected DocumentNumberService $numbers,
        protected SerialStateService $serials,
        protected StockService $stock,
    ) {
    }

    /** Serials a repair order line may be raised from. */
    public function selectableSerials()
    {
        return PartSerial::query()
            ->whereIn('status', ['removed_unserviceable', 'at_repair'])
            ->with('part:id,part_number,description')
            ->orderBy('serial_number');
    }

    /**
     * Replace the line set of a draft, renumbering from 1 so the printed S/N.
     * column matches the on-screen order.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function saveLines(RepairOrder $order, array $lines): RepairOrder
    {
        $this->assertDraft($order, 'Lines can only be changed while the order is a draft.');

        return DB::transaction(function () use ($order, $lines) {
            $kept = [];

            foreach (array_values($lines) as $i => $line) {
                $serial = ! empty($line['part_serial_id'])
                    ? PartSerial::findOrFail($line['part_serial_id'])
                    : null;

                $attributes = [
                    'repair_order_id' => $order->id,
                    'line_no' => $i + 1,
                    'description' => $line['description'] ?? null,
                    'part_id' => $line['part_id'] ?? $serial?->part_id,
                    'part_number' => $line['part_number'] ?? $serial?->part?->part_number,
                    'part_serial_id' => $serial?->id,
                    // A picked serial always prints its own number; free-text
                    // lines keep whatever was typed.
                    'serial_no' => $serial?->serial_number ?? ($line['serial_no'] ?? null),
                    'requisition_id' => $line['requisition_id'] ?? $this->originatingRequisitionId($serial),
                    // A tracked unit is one physical item, whatever was typed.
                    'qty' => $serial ? 1 : ($line['qty'] ?? 1),
                    'action' => $line['action'] ?? null,
                ];

                $model = ! empty($line['id'])
                    ? tap($order->lines()->findOrFail($line['id']))->update($attributes)
                    : RepairOrderLine::create($attributes);

                $kept[] = $model->id;
            }

            $order->lines()->whereKeyNot($kept)->delete();

            return $order->refresh();
        });
    }

    /**
     * Mint the reference, send the order, and push every tracked serial to
     * `at_repair` with the reference stamped back onto its requisition.
     */
    public function issue(RepairOrder $order, ?User $user = null): RepairOrder
    {
        $this->assertDraft($order, 'Only a draft repair order can be issued.');
        $this->assertRepairVendor($order);

        if ($order->lines()->count() === 0) {
            throw new StockException('Cannot issue a repair order with no lines.');
        }

        return DB::transaction(function () use ($order, $user) {
            $order->update([
                'ro_number' => $this->numbers->reserveRepairOrder($order->order_date),
                'status' => 'issued',
                'issued_at' => now(),
                'issued_by_user_id' => $user?->id,
            ]);

            foreach ($order->lines()->with('partSerial')->get() as $line) {
                $serial = $line->partSerial;

                if (! $serial) {
                    continue;
                }

                // Already at repair when the unit was booked out by the removal
                // itself; the state machine would reject at_repair → at_repair.
                if ($serial->status !== 'at_repair') {
                    $this->serials->remove($serial, 'at_repair', $user, "Repair order {$order->ro_number}");
                }

                $line->requisition?->update(['repair_order_ref' => $order->ro_number]);
            }

            activity('repair_order')->causedBy($user)->performedOn($order)
                ->event('issued')->log("Issued repair order {$order->ro_number} to {$order->vendor->name}");

            return $order->refresh();
        });
    }

    /** The vendor has the units in hand. */
    public function markAtVendor(RepairOrder $order, ?User $user = null): RepairOrder
    {
        if ($order->status !== 'issued') {
            throw new StockException('Only an issued repair order can be marked as at the vendor.');
        }

        $order->update(['status' => 'at_vendor']);

        activity('repair_order')->causedBy($user)->performedOn($order)
            ->event('at_vendor')->log("Repair order {$order->ro_number} is with the vendor");

        return $order->refresh();
    }

    /**
     * Book the units back in. Each line gets a disposition: a serviceable unit
     * is received into Quarantine for re-certification, a scrapped one is
     * written off. Every line must be dispositioned in one pass so the order
     * cannot sit half-returned.
     *
     * @param  array<int, array{disposition: string, note?: string|null}>  $dispositions  line id => decision
     */
    public function markReturned(RepairOrder $order, array $dispositions, ?User $user = null): RepairOrder
    {
        if (! $order->isAwaitingReturn()) {
            throw new StockException('Only a repair order that is out with the vendor can be returned.');
        }

        $lines = $order->lines()->with(['partSerial', 'part'])->get();
        $missing = $lines->reject(fn (RepairOrderLine $l) => isset($dispositions[$l->id]));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'dispositions' => 'Every line needs a disposition before the order can be returned.',
            ]);
        }

        return DB::transaction(function () use ($order, $lines, $dispositions, $user) {
            $quarantine = Store::where('type', 'quarantine')->firstOrFail();

            foreach ($lines as $line) {
                $decision = $dispositions[$line->id];
                $disposition = $decision['disposition'];

                $line->update([
                    'disposition' => $disposition,
                    'disposition_note' => $decision['note'] ?? null,
                    'returned_at' => now(),
                ]);

                $serial = $line->partSerial;

                if (! $serial) {
                    continue;
                }

                if ($disposition === 'scrapped') {
                    $this->serials->transitionTo($serial, 'scrapped', $user,
                        "Scrapped on return from repair order {$order->ro_number}");

                    continue;
                }

                // Serviceable: back into Quarantine as an uncertified receipt,
                // exactly as if it had arrived on an SRV.
                $this->serials->transitionTo($serial, 'in_store', $user,
                    "Returned serviceable from repair order {$order->ro_number}", [
                        'current_store_id' => $quarantine->id,
                        'current_aircraft_id' => null,
                    ]);

                if ($line->part) {
                    $this->stock->receive(
                        $line->part, $quarantine, 1, $user,
                        $serial->part_batch_id, $serial->id, $order->ro_number, $order,
                    );
                }
            }

            $order->update(['status' => 'returned', 'returned_at' => now()]);

            activity('repair_order')->causedBy($user)->performedOn($order)
                ->event('returned')->log("Repair order {$order->ro_number} returned from {$order->vendor->name}");

            return $order->refresh();
        });
    }

    public function close(RepairOrder $order, ?User $user = null): RepairOrder
    {
        if ($order->status !== 'returned') {
            throw new StockException('A repair order can only be closed once its units are back.');
        }

        $order->update(['status' => 'closed', 'closed_at' => now()]);

        activity('repair_order')->causedBy($user)->performedOn($order)
            ->event('closed')->log("Closed repair order {$order->ro_number}");

        return $order->refresh();
    }

    public function cancel(RepairOrder $order, string $reason, ?User $user = null): RepairOrder
    {
        if (in_array($order->status, ['closed', 'cancelled'], true)) {
            throw new StockException('This repair order is already finished.');
        }

        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        activity('repair_order')->causedBy($user)->performedOn($order)
            ->event('cancelled')
            ->log(sprintf('Cancelled repair order %s: %s', $order->ro_number ?? '(draft)', $reason));

        return $order->refresh();
    }

    /** The requisition whose removal section booked this serial out, if any. */
    protected function originatingRequisitionId(?PartSerial $serial): ?int
    {
        return $serial
            ? \App\Models\Requisition::where('removed_serial_id', $serial->id)
                ->orderByDesc('id')->value('id')
            : null;
    }

    protected function assertRepairVendor(RepairOrder $order): void
    {
        if (! $order->vendor?->canRepair()) {
            throw ValidationException::withMessages([
                'vendor_id' => 'A repair order must be addressed to a repair organisation.',
            ]);
        }
    }

    protected function assertDraft(RepairOrder $order, string $message): void
    {
        if (! $order->isDraft()) {
            throw new StockException($message);
        }
    }
}
