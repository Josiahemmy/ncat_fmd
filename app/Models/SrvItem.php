<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SrvItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'srv_id', 'part_id', 'purchase_order_line_id', 'line_no', 'quantity', 'supplier_details', 'fol_no',
        'rate', 'amount', 'invoice_no', 'acct_code', 'batch_no', 'batch_year', 'expiry_date', 'serials',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'expiry_date' => 'date',
            'serials' => 'array',
        ];
    }

    public function srv(): BelongsTo
    {
        return $this->belongsTo(Srv::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /** The purchase order line this receipt counts against, when receiving against a PO. */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}
