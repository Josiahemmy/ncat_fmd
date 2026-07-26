<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A consignment on its way from a vendor to NCAT (spec §12.6). The header is
 * mutable admin (carrier, expected date, description); the history is not. See
 * {@see ShipmentEvent} for the append-only rule and the reason for it.
 */
class Shipment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'reference', 'vendor_id', 'source_type', 'source_id', 'description',
        'carrier', 'awb_reference', 'expected_arrival_date',
        'current_status', 'current_status_date', 'arrived_at', 'closed_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'expected_arrival_date' => 'date',
            'current_status_date' => 'date',
            'arrived_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Chronological, with id as the tie-break so several events recorded on the
     * same day keep the order they were entered in. The timeline renders this
     * sequence reversed; the stored order is the truth.
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('event_date')->orderBy('id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** The purchase order or repair order this consignment fulfils, if any. */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** The receipt vouchers raised against this shipment. */
    public function srvs(): HasMany
    {
        return $this->hasMany(Srv::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function hasArrived(): bool
    {
        return $this->arrived_at !== null;
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * Past its expected arrival date and still not here. Derived, never stored,
     * so a shipment cannot look current because no nightly job ran.
     */
    public function isOverdue(): bool
    {
        return $this->expected_arrival_date !== null
            && ! $this->hasArrived()
            && ! $this->isClosed()
            && $this->expected_arrival_date->lt(today());
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue()
            ? (int) round($this->expected_arrival_date->startOfDay()->diffInDays(today()))
            : 0;
    }

    /** Days since the last recorded event, for the in-transit report. */
    public function daysSinceLastEvent(): ?int
    {
        return $this->current_status_date
            ? (int) round($this->current_status_date->startOfDay()->diffInDays(today()))
            : null;
    }

    /** "Purchase Order NCAT/FMD/PO/TS/30/6/309" or null when standalone. */
    public function sourceLabel(): ?string
    {
        return match ($this->source_type) {
            PurchaseOrder::class => 'Purchase Order '.($this->source?->po_number ?? 'draft'),
            RepairOrder::class => 'Repair Order '.($this->source?->ro_number ?? 'draft'),
            default => null,
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reference', 'vendor_id', 'current_status', 'expected_arrival_date', 'arrived_at', 'closed_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('shipment');
    }
}
