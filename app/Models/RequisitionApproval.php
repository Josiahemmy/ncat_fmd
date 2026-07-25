<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per approval level of a requisition's chain, written when the
 * requisition is submitted. The level's name and binding are copied here so the
 * chain a document started with stays intact even if an admin later renames,
 * reorders, deactivates or deletes that level.
 */
class RequisitionApproval extends Model
{
    protected $fillable = [
        'requisition_id', 'approval_level_id', 'cycle', 'sequence', 'level_name',
        'permission_name', 'role_name', 'decision', 'decided_by_user_id', 'decided_at', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'cycle' => 'integer',
            'sequence' => 'integer',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(ApprovalLevel::class, 'approval_level_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->decision === null;
    }

    /** Does this user hold the permission or role this level was bound to? */
    public function isSatisfiedBy(User $user): bool
    {
        if ($this->role_name) {
            return $user->hasRole($this->role_name);
        }

        return $this->permission_name ? $user->can($this->permission_name) : false;
    }
}
