<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_order_id', 'line_no', 'description', 'part_id', 'part_number',
        'part_serial_id', 'serial_no', 'requisition_id', 'qty', 'action',
        'disposition', 'returned_at', 'disposition_note',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'line_no' => 'integer',
            'returned_at' => 'datetime',
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /** The tracked serial, when the line was raised from one rather than typed. */
    public function partSerial(): BelongsTo
    {
        return $this->belongsTo(PartSerial::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function isTracked(): bool
    {
        return $this->part_serial_id !== null;
    }

    public function isReturned(): bool
    {
        return $this->disposition !== null;
    }
}
