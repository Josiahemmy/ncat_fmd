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
 * Repair Order header (spec §12.5). The vendor must be able to repair, and
 * issuing one sends its serials to `at_repair`. Marking it returned is where
 * each unit gets a disposition and the loop back through Quarantine closes.
 */
class RepairOrder extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const ISSUED_STATUSES = ['issued', 'at_vendor', 'returned', 'closed'];

    public const PRIORITIES = ['aog', 'very_urgent', 'for_inventory'];

    /** The column default is not read back after create(), so state it here too. */
    protected $attributes = ['status' => 'draft'];

    protected $fillable = [
        'ro_number', 'order_date', 'vendor_id', 'aircraft_type_label', 'priority', 'status',
        'issued_at', 'issued_by_user_id', 'returned_at', 'closed_at', 'cancelled_at', 'cancel_reason',
        'created_by_user_id', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'issued_at' => 'datetime',
            'returned_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RepairOrderLine::class)->orderBy('line_no');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isLocked(): bool
    {
        return ! $this->isDraft();
    }

    /** Units are away and can be booked back in. */
    public function isAwaitingReturn(): bool
    {
        return in_array($this->status, ['issued', 'at_vendor'], true);
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
            ->logOnly(['ro_number', 'status', 'vendor_id', 'priority'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('repair_order');
    }
}
