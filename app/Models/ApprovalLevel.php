<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ApprovalLevel extends Model
{
    use LogsActivity;

    protected $fillable = [
        'approval_workflow_id', 'sequence', 'name', 'permission_name', 'role_name', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sequence' => 'integer'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    /** A level is bound by exactly one of the two: 'permission' or 'role'. */
    public function bindingType(): string
    {
        return $this->role_name ? 'role' : 'permission';
    }

    public function bindingLabel(): string
    {
        return $this->role_name ?? (string) $this->permission_name;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['sequence', 'name', 'permission_name', 'role_name', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('approval_workflow');
    }
}
