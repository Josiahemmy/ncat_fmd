<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a shipment's history. Append-only, for the same reason the stock
 * ledger is: the record of what was believed, and when, is evidence. A wrong
 * event is corrected by recording a superseding one with a note explaining it,
 * not by rewriting history.
 *
 * The model enforces this rather than trusting callers to behave: `updating`
 * and `deleting` both throw, so there is no model path to an edit even if a
 * route were added by mistake.
 */
class ShipmentEvent extends Model
{
    use HasFactory;

    /** No updated_at: there is no update. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'shipment_id', 'status', 'event_date', 'note', 'is_arrival', 'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'is_arrival' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \App\Exceptions\Shipping\ShipmentEventImmutableException(
                'A shipment event cannot be edited. Record a superseding event with a note instead.',
            );
        });

        static::deleting(function (): never {
            throw new \App\Exceptions\Shipping\ShipmentEventImmutableException(
                'A shipment event cannot be deleted. Record a superseding event with a note instead.',
            );
        });
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
