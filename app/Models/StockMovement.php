<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Immutable, append-only ledger row (spec §3.2). Invariant (a): once posted a
 * movement can never be updated or deleted — corrections are counter-movements.
 * The model enforces this itself so no code path (controller, tinker, future
 * developer) can mutate history.
 */
class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'part_id', 'store_id', 'part_batch_id', 'part_serial_id',
        'direction', 'quantity', 'balance_after', 'movement_type',
        'source_type', 'source_id', 'aircraft_id', 'user_id',
        'transfer_group', 'reference', 'remarks', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Stock movements are append-only and cannot be modified. Post a correcting movement instead.');
        });

        static::deleting(function () {
            throw new RuntimeException('Stock movements are append-only and cannot be deleted. Post a correcting movement instead.');
        });
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PartBatch::class, 'part_batch_id');
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(PartSerial::class, 'part_serial_id');
    }

    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
