<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WorkOrder extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'wo_ref', 'aircraft_id', 'work_type', 'inspection_type', 'title', 'description',
        'status', 'raised_by', 'raised_by_user_id', 'qc_checked_by', 'records_updated_by',
        'filed_by', 'work_date', 'closed_at', 'remarks', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function aircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class);
    }

    public function raisedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function requisitions(): HasMany
    {
        return $this->hasMany(Requisition::class);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /** Requisitions on this WO that are not yet closed — drives the close warning. */
    public function openRequisitionsCount(): int
    {
        return $this->requisitions()->where('status', '!=', 'closed')->count();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'wo_ref', 'aircraft_id', 'work_type', 'inspection_type', 'title',
                'status', 'raised_by', 'qc_checked_by', 'records_updated_by', 'filed_by',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('work_order');
    }
}
