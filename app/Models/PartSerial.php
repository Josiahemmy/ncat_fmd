<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartSerial extends Model
{
    use HasFactory;

    protected $fillable = [
        'part_id', 'part_batch_id', 'serial_number', 'status', 'is_loaned',
        'current_store_id', 'current_aircraft_id', 'position',
    ];

    protected function casts(): array
    {
        return [
            'is_loaned' => 'boolean',
        ];
    }

    /** Borrowed property, whichever store or aircraft it currently sits on. */
    public function isLoanedProperty(): bool
    {
        return (bool) $this->is_loaned;
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PartBatch::class, 'part_batch_id');
    }

    public function currentStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'current_store_id');
    }

    public function currentAircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class, 'current_aircraft_id');
    }
}
