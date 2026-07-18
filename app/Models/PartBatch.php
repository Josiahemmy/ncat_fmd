<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'part_id', 'batch_number', 'batch_year', 'expiry_date', 'qty_received',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'qty_received' => 'decimal:2',
            'batch_year' => 'integer',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }
}
