<?php

namespace App\Services\Shipping;

use App\Exceptions\Shipping\ShipmentClosedException;
use App\Models\PurchaseOrder;
use App\Models\RepairOrder;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShipmentEventAttachment;
use App\Models\User;
use App\Services\Documents\DocumentNumberService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     * Refuses on a closed shipment. Without this the header could be walked
     * backwards after the fact: appending any event re-derives `current_status`
     * from the latest entry, so a closed consignment that arrived in June could
     * be made to read "Shipped" again. Correcting a closed shipment goes
     * through {@see reopen}.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files  Paperwork for this entry.
     *
     * @throws \App\Exceptions\Shipping\ShipmentClosedException
     */
    public function addEvent(Shipment $shipment, array $data, ?User $user = null, array $files = []): ShipmentEvent
    {
        if ($shipment->closed_at !== null) {
            throw ShipmentClosedException::forAppend($shipment->reference);
        }

        return DB::transaction(function () use ($shipment, $data, $user, $files) {
            $event = $shipment->events()->create([
                'status' => trim($data['status']),
                'event_date' => $data['event_date'],
                'note' => $data['note'] ?? null,
                'is_arrival' => (bool) ($data['is_arrival'] ?? false),
                'recorded_by_user_id' => $user?->id,
            ]);

            foreach ($files as $file) {
                $this->attach($event, $file, $user);
            }

            $this->refreshFromEvents($shipment);

            return $event;
        });
    }

    /**
     * Store one file against an event.
     *
     * The name on disk is generated and the clerk's name is kept only as a
     * label, so a crafted filename cannot escape the directory or collide with
     * another upload. `storePubliclyAs` is deliberately not used: the local
     * disk is `storage/app`, outside the document root, and the only way to
     * read a file back is the permission-gated download route.
     */
    public function attach(ShipmentEvent $event, UploadedFile $file, ?User $user = null): ShipmentEventAttachment
    {
        $path = $file->store("shipment-events/{$event->id}", 'local');

        return $event->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            // Trimmed to the basename so a path in the upload name is discarded.
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 120, ''),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by_user_id' => $user?->id,
        ]);
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

    /**
     * Mark a shipment finished. The timeline is frozen from here: {@see addEvent}
     * refuses while `closed_at` is set. Reversible through {@see reopen}, which
     * anyone holding `shipping.manage` can do and which the activity log records.
     */
    public function close(Shipment $shipment, ?User $user = null): Shipment
    {
        $shipment->forceFill(['closed_at' => now()])->save();

        activity('shipment')
            ->performedOn($shipment)
            ->causedBy($user)
            ->log("Closed shipment {$shipment->reference}");

        return $shipment;
    }

    /**
     * Re-open a closed shipment so a correction can be appended.
     *
     * Deliberately not a delete and not an edit. The entry that was wrong stays
     * on the timeline, the re-open is recorded, and the correcting entry lands
     * after it, so the trail reads as what was believed, when, and what put it
     * right. Callers are expected to close it again afterwards.
     *
     * A reason is required rather than optional: the whole point of leaving the
     * original entry in place is that someone later can tell why it changed.
     */
    public function reopen(Shipment $shipment, string $reason, ?User $user = null): Shipment
    {
        if ($shipment->closed_at === null) {
            return $shipment;
        }

        $closedAt = $shipment->closed_at;
        $shipment->forceFill(['closed_at' => null])->save();

        activity('shipment')
            ->performedOn($shipment)
            ->causedBy($user)
            ->withProperties([
                'reason' => $reason,
                'was_closed_at' => $closedAt?->toDateTimeString(),
            ])
            ->log("Re-opened shipment {$shipment->reference}: {$reason}");

        return $shipment->refresh();
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
