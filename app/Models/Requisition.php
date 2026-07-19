<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Requisition extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'requisition_no', 'work_order_id',
        'aircraft_id', 'aircraft_reg', 'engine_serial_no', 'position', 'authorised_by', 'supply_source',
        'full_description', 'part_id', 'part_no', 'stock_code', 'serial_number', 'batch_no', 'batch_year', 'bin_bal_line_no',
        'issued_by', 'unit_serviced_by', 'unit_serviced_date',
        'status', 'requested_by_user_id', 'approved_by_user_id', 'approved_at', 'rejected_at', 'approval_remarks', 'issued_at',
        'serial_no_removed', 'removal_zone', 'unit_changed_by', 'reason_for_removal', 'removal_signature',
        'removal_date', 'repair_facility', 'date_sent', 'repair_order_ref', 'removed_serial_id', 'removal_completed_at',
        'requisition_date',
    ];

    protected function casts(): array
    {
        return [
            'unit_serviced_date' => 'date',
            'removal_date' => 'date',
            'date_sent' => 'date',
            'requisition_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'issued_at' => 'datetime',
            'removal_completed_at' => 'datetime',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function removedSerial(): BelongsTo
    {
        return $this->belongsTo(PartSerial::class, 'removed_serial_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['requisition_no', 'status', 'part_id', 'part_no', 'approved_by_user_id', 'work_order_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('requisition');
    }
}
