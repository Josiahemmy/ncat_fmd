<?php

namespace App\Services\Shipping;

use App\Models\PurchaseOrder;
use App\Models\RepairOrder;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Services\Documents\DocumentNumberService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shipping (spec §12.6). The service owns two things worth stating plainly:
 *
 *  1. Events are only ever appended. There is no update or delete method here,
 *     and the model refuses both, so the timeline is a record of what was
 *     believed and when rather than a summary edited after the fact.
 *
 *  2. `current_status` on the header is a cache of the latest event, refreshed
 *     inside the same transaction that appends the event. The list page filters
 *     and sorts on it, which is the only reason it is denormalised at all; the
 *     events remain the source of truth and {@see refreshFromEvents} can rebuild
 *     the column from them at any time.
 */
class ShipmentService
{
    public function __construct(
        protected DocumentNumberService $numbers,
        protected ShipmentSettings $settings,
    ) {
    }

    /**
     * Create a shipment and, when the caller supplied one, record its opening
     * event in the same transaction so a shipment never exists with an empty
     * timeline and a null status.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user = null): Shipment
    {
        return DB::transaction(function () use ($data, $user) {
            $shipment = Shipment::create([
                'reference' => $this->numbers->reserveShipment(),
                'vendor_id' => $data['vendor_id'],
                'source_type' => $this->sourceType($data['source_kind'] ?? null),
                'source_id' => $this->sourceType($data['source_kind'] ?? null) ? ($data['source_id'] ?? null) : null,
                'description' => $data['description'] ?? null,
                'carrier' => $data['carrier'] ?? null,
                'awb_reference' => $data['awb_reference'] ?? null,
                'expected_arrival_date' => $data['expected_arrival_date'] ?? null,
                'created_by_user_id' => $user?->id,
            ]);

            if (! empty($data['status'])) {
                $this->addEvent($shipment, [
                    'status' => $data['status'],
                    'event_date' => $data['event_date'] ?? today()->toDateString(),
                    'note' => $data['note'] ?? null,
                    'is_arrival' => (bool) ($data['is_arrival'] ?? false),
                ], $user);
            }

            return $shipment;
        });
    }

    /**
     * Append one event and re-derive the header. An event flagged as the
     * arrival stamps `arrived_at`, which is what stops the shipment counting as
     * overdue and what unlocks the SRV handoff.
     *
     * @param  array<string, mixed>  $data
     */
    public function addEvent(Shipment $shipment, array $data, ?User $user = null): ShipmentEvent
    {
        return DB::transaction(function () use ($shipment, $data, $user) {
            $event = $shipment->events()->create([
                'status' => trim($data['status']),
                'event_date' => $data['event_date'],
                'note' => $data['note'] ?? null,
                'is_arrival' => (bool) ($data['is_arrival'] ?? false),
                'recorded_by_user_id' => $user?->id,
            ]);

            $this->refreshFromEvents($shipment);

            return $event;
        });
    }

    /**
     * Rebuild the denormalised header columns from the event log. Cheap, and it
     * means the cache can never drift: anything that appends an event calls it,
     * and it can be run over the whole table if it ever needs proving.
     */
    public function refreshFromEvents(Shipment $shipment): Shipment
    {
        // The relation is ordered by event_date then id, so the last row is the
        // latest event and same-day events keep their entry order.
        $latest = $shipment->events()->get()->last();
        // Arrival is the FIRST arrival event: a later "arrived at NCAT"
        // correction should not move the date the goods actually landed.
        $arrival = $shipment->events()->where('is_arrival', true)->first();

        $shipment->forceFill([
            'current_status' => $latest?->status,
            'current_status_date' => $latest?->event_date,
            'arrived_at' => $arrival?->event_date?->startOfDay(),
        ])->save();

        return $shipment->refresh();
    }

    /** Mark a shipment finished. Reversible only by an admin re-opening it. */
    public function close(Shipment $shipment): Shipment
    {
        $shipment->forceFill(['closed_at' => now()])->save();

        return $shipment;
    }

    /**
     * The lines an SRV raised from this shipment should start with: the source
     * purchase order's outstanding quantities. A repair-order shipment or a
     * standalone one has nothing to pre-fill, and returns an empty list rather
     * than a guess.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function srvPrefillLines(Shipment $shipment): Collection
    {
        if ($shipment->source_type !== PurchaseOrder::class) {
            return collect();
        }

        $order = $shipment->source?->loadMissing('lines.part:id,part_number,description');

        return collect($order?->lines ?? [])
            ->reject(fn ($line) => $line->isFullyReceived())
            ->map(fn ($line) => [
                'purchase_order_line_id' => $line->id,
                'line_no' => $line->line_no,
                'part_id' => $line->part_id,
                'part_number' => $line->part_number ?? $line->part?->part_number,
                'description' => $line->description ?? $line->part?->description,
                'quantity' => (float) $line->outstanding(),
            ])
            ->values();
    }

    /** Shipments past their expected arrival date and still not here. */
    public function overdue(): Collection
    {
        return Shipment::query()
            ->whereNull('arrived_at')
            ->whereNull('closed_at')
            ->whereNotNull('expected_arrival_date')
            ->whereDate('expected_arrival_date', '<', today())
            ->with('vendor:id,name')
            ->orderBy('expected_arrival_date')
            ->get();
    }

    /** Still on the way: not arrived, not closed. */
    public function inTransit(): Collection
    {
        return Shipment::query()
            ->whereNull('arrived_at')
            ->whereNull('closed_at')
            ->with('vendor:id,name')
            ->orderBy('expected_arrival_date')
            ->get();
    }

    protected function sourceType(?string $kind): ?string
    {
        return match ($kind) {
            'purchase_order' => PurchaseOrder::class,
            'repair_order' => RepairOrder::class,
            default => null,
        };
    }
}
