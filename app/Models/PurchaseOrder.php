<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Purchase Order header (spec §12.5). A draft carries no number; the reference
 * is minted at issue. From `issued` on, the commercial fields are frozen: what
 * the vendor was sent is what the record says. Corrections go through cancel
 * and re-raise, which leaves both documents in the audit trail.
 */
class PurchaseOrder extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /** Statuses in which the vendor already holds a copy of the paper. */
    public const ISSUED_STATUSES = ['issued', 'partially_received', 'received', 'closed'];

    public const PRIORITIES = ['aog', 'very_urgent', 'for_inventory'];

    /** The column default is not read back after create(), so state it here too. */
    protected $attributes = ['status' => 'draft'];

    protected $fillable = [
        'po_number', 'order_date', 'vendor_id', 'aircraft_type_label', 'priority', 'status',
        'issued_at', 'issued_by_user_id', 'closed_at', 'cancelled_at', 'cancel_reason',
        'created_by_user_id', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'issued_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_no');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function srvs(): HasMany
    {
        return $this->hasMany(Srv::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Frozen commercially: lines and vendor can no longer change. */
    public function isLocked(): bool
    {
        return ! $this->isDraft();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** Open to receiving: issued and not yet fully received, closed or cancelled. */
    public function isReceivable(): bool
    {
        return in_array($this->status, ['issued', 'partially_received'], true);
    }

    public function priorityLabel(): ?string
    {
        return match ($this->priority) {
            'aog' => 'A.O.G',
            'very_urgent' => 'Very Urgent',
            'for_inventory' => 'For inventory',
            default => null,
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['po_number', 'status', 'vendor_id', 'priority'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('purchase_order');
    }
}
