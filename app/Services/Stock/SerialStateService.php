<?php

namespace App\Services\Stock;

use App\Exceptions\Stock\InvalidSerialTransitionException;
use App\Models\Aircraft;
use App\Models\PartSerial;
use App\Models\User;

/**
 * The part-serial state machine. Phase 2 left `part_serials.status` as a bare
 * DB enum with no enforcement (StockService only flips in_store on receipt).
 * Phase 3 documents drive real lifecycle transitions — SIV installs a serial
 * onto an aircraft; a Requisition's removal section pulls the old unit off to
 * repair — so those transitions live here, guarded and audit-logged.
 */
class SerialStateService
{
    /** Allowed status transitions. Empty target list = terminal state. */
    public const TRANSITIONS = [
        'in_store' => ['installed', 'removed_unserviceable', 'at_repair', 'scrapped'],
        'installed' => ['in_store', 'removed_unserviceable', 'at_repair', 'scrapped'],
        'at_repair' => ['in_store', 'removed_unserviceable', 'scrapped'],
        'removed_unserviceable' => ['at_repair', 'in_store', 'scrapped'],
        'scrapped' => [],
    ];

    /** Install a serviceable serial onto an aircraft (SIV issue of a serialized line). */
    public function install(PartSerial $serial, Aircraft $aircraft, ?string $position = null, ?User $user = null): PartSerial
    {
        return $this->transition($serial, 'installed', [
            'current_aircraft_id' => $aircraft->id,
            'current_store_id' => null,
            'position' => $position ?? $serial->position,
        ], $user, "Installed serial {$serial->serial_number} on {$aircraft->registration}");
    }

    /**
     * Remove a serial from service (Requisition removal section). Target is
     * either `at_repair` (sent to a repair facility) or `removed_unserviceable`.
     * Store and aircraft are cleared — the unit is off the airframe.
     */
    public function remove(PartSerial $serial, string $to, ?User $user = null, ?string $reason = null): PartSerial
    {
        if (! in_array($to, ['removed_unserviceable', 'at_repair'], true)) {
            throw new InvalidSerialTransitionException(
                "Removal target must be removed_unserviceable or at_repair, got '{$to}'.",
            );
        }

        return $this->transition($serial, $to, [
            'current_aircraft_id' => null,
            'current_store_id' => null,
        ], $user, "Removed serial {$serial->serial_number} → {$to}".($reason ? " ({$reason})" : ''));
    }

    /**
     * The general guarded transition, for callers outside the install/remove
     * pair. Phase 7 uses it to book units back from a repair order: serviceable
     * to `in_store` in Quarantine, or written off to `scrapped`.
     *
     * @param  array<string, mixed>  $attributes  extra columns to set alongside the status
     */
    public function transitionTo(
        PartSerial $serial,
        string $to,
        ?User $user = null,
        ?string $message = null,
        array $attributes = [],
    ): PartSerial {
        return $this->transition(
            $serial, $to, $attributes, $user,
            $message ?? "Serial {$serial->serial_number} moved {$serial->status} to {$to}",
        );
    }

    protected function transition(PartSerial $serial, string $to, array $attrs, ?User $user, string $message): PartSerial
    {
        $from = $serial->status;
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidSerialTransitionException(
                "Serial {$serial->serial_number} cannot transition {$from} → {$to}.",
            );
        }

        $serial->update(['status' => $to] + $attrs);

        activity('serial')->causedBy($user)->performedOn($serial)
            ->withProperties(['from' => $from, 'to' => $to])
            ->event('transitioned')->log($message);

        return $serial;
    }
}
