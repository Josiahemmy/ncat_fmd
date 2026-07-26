<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id', 'line_no', 'description', 'part_id', 'part_number',
        'qty_to_order', 'qty_received', 'line_status', 'timeline_month', 'timeline_year',
    ];

    protected function casts(): array
    {
        return [
            'qty_to_order' => 'float',
            'qty_received' => 'float',
            'line_no' => 'integer',
            'timeline_month' => 'integer',
            'timeline_year' => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function outstanding(): float
    {
        return max(0, $this->qty_to_order - $this->qty_received);
    }

    public function isFullyReceived(): bool
    {
        return $this->qty_received >= $this->qty_to_order;
    }

    /** "JULY, 2026" as printed in the TIME LINE column. */
    public function timelineLabel(): ?string
    {
        if (! $this->timeline_month || ! $this->timeline_year) {
            return $this->timeline_year ? (string) $this->timeline_year : null;
        }

        return strtoupper(date('F', mktime(0, 0, 0, $this->timeline_month, 1))).', '.$this->timeline_year;
    }
}
