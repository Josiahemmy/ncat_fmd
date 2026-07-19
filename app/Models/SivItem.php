<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SivItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'siv_id', 'line_no', 'requisition_id', 'part_id', 'description',
        'qty_required', 'qty_issued', 'source_store_id', 'stores_folio',
        'rate', 'amount', 'charging_code', 'part_batch_id', 'serial_ids',
    ];

    protected function casts(): array
    {
        return [
            'qty_required' => 'decimal:2',
            'qty_issued' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'serial_ids' => 'array',
        ];
    }

    public function siv(): BelongsTo
    {
        return $this->belongsTo(Siv::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function sourceStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'source_store_id');
    }
}
