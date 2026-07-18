<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Part extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'part_number', 'description', 'ata_chapter_id', 'aircraft_type_id',
        'stock_code', 'ledger_folio', 'unit_of_issue', 'unit_price', 'bin_location',
        'min_level', 'max_level', 'reorder_level',
        'is_serialized', 'has_shelf_life', 'is_flammable', 'is_fuel', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'min_level' => 'decimal:2',
            'max_level' => 'decimal:2',
            'reorder_level' => 'decimal:2',
            'is_serialized' => 'boolean',
            'has_shelf_life' => 'boolean',
            'is_flammable' => 'boolean',
            'is_fuel' => 'boolean',
        ];
    }

    public function ataChapter(): BelongsTo
    {
        return $this->belongsTo(AtaChapter::class);
    }

    public function aircraftType(): BelongsTo
    {
        return $this->belongsTo(AircraftType::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(PartBatch::class);
    }

    public function serials(): HasMany
    {
        return $this->hasMany(PartSerial::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    /** On-hand total across all stores (derived from the balance cache). */
    public function totalOnHand(): float
    {
        return (float) $this->balances()->sum('quantity');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['part_number', 'description', 'min_level', 'max_level', 'reorder_level', 'unit_price'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('part');
    }
}
