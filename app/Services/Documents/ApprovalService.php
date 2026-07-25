<?php

namespace App\Services\Documents;

use App\Models\ApprovalLevel;
use App\Models\ApprovalWorkflow;
use App\Models\Requisition;
use App\Models\RequisitionApproval;
use App\Models\User;
use App\Notifications\RequisitionAwaitingApproval;
use App\Notifications\RequisitionDecided;
use App\Notifications\RequisitionReadyForIssue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The requisition approval engine (spec §12.1).
 *
 * A submitted requisition carries its own chain: one `requisition_approvals` row
 * per active level, written at submission with the level's name and binding
 * copied in. Everything the engine decides is read from those rows, so an admin
 * who renames, reorders, deactivates or deletes a level changes only what new
 * submissions will do. Documents already in flight are untouched.
 *
 * The `status` column keeps its original vocabulary: a requisition stays
 * `submitted` until the final level approves, at which point it becomes
 * `approved` and therefore issuable. With the seeded single-level default this
 * is byte-for-byte the pre-migration behaviour.
 */
class ApprovalService
{
    public const DOCUMENT_TYPE = 'requisition';

    /** The level binding used when no workflow configuration can be found. */
    public const FALLBACK_PERMISSION = 'requisitions.approve';

    public function activeWorkflow(string $documentType = self::DOCUMENT_TYPE): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::where('document_type', $documentType)
            ->where('is_active', true)
            ->with('activeLevels')
            ->orderBy('id')
            ->first();
    }

    /**
     * Called when a requisition is submitted (or re-submitted after rejection).
     * Writes a fresh chain and notifies whoever can act on the first level.
     */
    public function onSubmitted(Requisition $requisition): void
    {
        $cycle = $this->nextCycle($requisition);
        $this->materialise($requisition, $cycle);

        $requisition->forceFill(['submitted_at' => $requisition->submitted_at ?? now()])->save();

        if ($pending = $this->pending($requisition)) {
            $this->notifyLevelActors($requisition, $pending);
        }
    }

    /**
     * The level a requisition currently sits at: the lowest undecided row of its
     * newest cycle. Null once the chain is finished or was rejected.
     *
     * Rows are materialised on demand so requisitions that reached `submitted`
     * without passing through the engine (demo seeds, factories, data written
     * before this phase) still resolve correctly.
     */
    public function pending(Requisition $requisition): ?RequisitionApproval
    {
        if ($requisition->status !== 'submitted') {
            return null;
        }

        $cycle = $this->currentCycle($requisition);

        if ($cycle === 0) {
            $this->materialise($requisition, 1);
            $cycle = 1;
        }

        return RequisitionApproval::where('requisition_id', $requisition->id)
            ->where('cycle', $cycle)
            ->whereNull('decision')
            ->orderBy('sequence')
            ->first();
    }

    /** Does this user hold what the pending level requires? Ignores duty segregation. */
    public function matchesPendingLevel(?User $user, Requisition $requisition): bool
    {
        if (! $user) {
            return false;
        }

        return (bool) $this->pending($requisition)?->isSatisfiedBy($user);
    }

    /**
     * The full test for showing and enabling the approve/reject controls: the
     * user satisfies the pending level and did not raise the requisition.
     */
    public function canAct(?User $user, Requisition $requisition): bool
    {
        return $this->matchesPendingLevel($user, $requisition)
            && $user->id !== $requisition->requested_by_user_id;
    }

    /**
     * Record an approval at the pending level. Approving the final level makes
     * the requisition issuable.
     */
    public function approve(Requisition $requisition, User $user, ?string $remarks = null): RequisitionApproval
    {
        return DB::transaction(function () use ($requisition, $user, $remarks) {
            $pending = $this->assertActionable($requisition, $user);

            $pending->update([
                'decision' => 'approve',
                'decided_by_user_id' => $user->id,
                'decided_at' => now(),
                'remarks' => $remarks ?: null,
            ]);

            $next = $this->pending($requisition->refresh());

            if ($next) {
                $this->notifyLevelActors($requisition, $next);
            } else {
                $requisition->update([
                    'status' => 'approved',
                    'approved_by_user_id' => $user->id,
                    'approved_at' => now(),
                    'approval_remarks' => $remarks ?: null,
                ]);

                $this->notifyIssuers($requisition);
            }

            activity('requisition')->causedBy($user)->performedOn($requisition)
                ->event('approved')
                ->log("Approved requisition {$requisition->requisition_no} at level {$pending->level_name}");

            $this->notifyRequester($requisition, $pending, $next !== null);

            return $pending;
        });
    }

    /** Reject at the pending level. This ends the chain; the reason is mandatory. */
    public function reject(Requisition $requisition, User $user, string $remarks): RequisitionApproval
    {
        return DB::transaction(function () use ($requisition, $user, $remarks) {
            $pending = $this->assertActionable($requisition, $user);

            $pending->update([
                'decision' => 'reject',
                'decided_by_user_id' => $user->id,
                'decided_at' => now(),
                'remarks' => $remarks,
            ]);

            $requisition->update([
                'status' => 'rejected',
                'approved_by_user_id' => $user->id,
                'rejected_at' => now(),
                'approval_remarks' => $remarks,
            ]);

            activity('requisition')->causedBy($user)->performedOn($requisition)
                ->event('rejected')
                ->log("Rejected requisition {$requisition->requisition_no} at level {$pending->level_name}");

            $this->notifyRequester($requisition->refresh(), $pending, false);

            return $pending;
        });
    }

    /**
     * The decision trail for the detail page: every level of every cycle, with
     * the pending one flagged.
     *
     * @return array<int, array<string, mixed>>
     */
    public function trail(Requisition $requisition): array
    {
        $pendingId = $this->pending($requisition)?->id;

        return $requisition->approvals()->with('decidedBy:id,name')->get()
            ->map(fn (RequisitionApproval $a) => [
                'id' => $a->id,
                'cycle' => $a->cycle,
                'sequence' => $a->sequence,
                'level_name' => $a->level_name,
                'binding' => $a->role_name ? "Role: {$a->role_name}" : "Permission: {$a->permission_name}",
                'decision' => $a->decision,
                'decided_by' => $a->decidedBy?->name,
                'decided_at' => $a->decided_at?->toDayDateTimeString(),
                'remarks' => $a->remarks,
                'is_pending' => $a->id === $pendingId,
            ])
            ->all();
    }

    /** Are any requisitions part-way through a chain right now? Drives the admin warning. */
    public function inFlightCount(): int
    {
        return Requisition::where('status', 'submitted')->count();
    }

    /**
     * Could this user ever decide anything under the current configuration? Used
     * to decide whether approval surfaces (sidebar badge, dashboard card) belong
     * to them at all. With the seeded default level this is exactly the old
     * `can('requisitions.approve')` test.
     */
    public function canApproveAnyLevel(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $levels = $this->activeWorkflow()?->activeLevels ?? collect();

        if ($levels->isEmpty()) {
            return $user->can(self::FALLBACK_PERMISSION);
        }

        return $levels->contains(fn (ApprovalLevel $l) => $l->role_name
            ? $user->hasRole($l->role_name)
            : $user->can($l->permission_name ?? self::FALLBACK_PERMISSION));
    }

    /**
     * How many requisitions are waiting on this specific user right now: the
     * pending level of the newest cycle matches one of their bindings and they
     * did not raise it.
     */
    public function pendingForCount(User $user): int
    {
        $roles = $user->getRoleNames()->all();
        $permissions = $user->hasRole('Super Admin')
            ? null   // Gate::before grants everything; role-bound levels still apply.
            : $user->getAllPermissions()->pluck('name')->all();

        return RequisitionApproval::query()
            ->whereNull('decision')
            ->whereHas('requisition', fn ($q) => $q->where('status', 'submitted')
                ->where(fn ($w) => $w->whereNull('requested_by_user_id')
                    ->orWhere('requested_by_user_id', '!=', $user->id)))
            // Newest cycle only, and only its lowest undecided level.
            ->whereRaw('cycle = (select max(c.cycle) from requisition_approvals c where c.requisition_id = requisition_approvals.requisition_id)')
            ->whereRaw('sequence = (select min(s.sequence) from requisition_approvals s where s.requisition_id = requisition_approvals.requisition_id and s.cycle = requisition_approvals.cycle and s.decision is null)')
            ->where(function ($q) use ($roles, $permissions) {
                $q->when($roles, fn ($w) => $w->whereIn('role_name', $roles));

                if ($permissions === null) {
                    $q->orWhereNotNull('permission_name');
                } elseif ($permissions) {
                    $q->orWhereIn('permission_name', $permissions);
                }
            })
            ->distinct()
            ->count('requisition_id');
    }

    /**
     * Users who can act on a level, capped to holders of its bound role or
     * permission. The requester is excluded: they could never act anyway.
     */
    public function actorsFor(RequisitionApproval|ApprovalLevel $level, ?int $excludeUserId = null): Collection
    {
        if ($level->role_name) {
            if (! Role::where(['name' => $level->role_name, 'guard_name' => 'web'])->exists()) {
                return new Collection;
            }

            $query = User::role($level->role_name);
        } else {
            $permission = $level->permission_name ?? self::FALLBACK_PERMISSION;

            if (! Permission::where(['name' => $permission, 'guard_name' => 'web'])->exists()) {
                return new Collection;
            }

            $query = User::permission($permission);
        }

        return $query->where('is_active', true)
            ->when($excludeUserId, fn ($q, $id) => $q->whereKeyNot($id))
            ->get();
    }

    /**
     * Give a requisition whose status was written directly (demo seeds, imports)
     * the chain a real submission would have produced, including a decision on
     * the final level when the status says it was already decided. Notifies
     * nobody: these are historic or synthetic records.
     */
    public function backfillChain(Requisition $requisition): void
    {
        $this->materialise($requisition, 1);

        $decision = match ($requisition->status) {
            'rejected' => 'reject',
            'approved', 'issued', 'closed' => 'approve',
            default => null,
        };

        if ($decision === null) {
            return;
        }

        $rows = RequisitionApproval::where('requisition_id', $requisition->id)
            ->where('cycle', 1)->orderBy('sequence')->get();

        // A rejection stops at the first level; an approval had to clear them all.
        $decided = $decision === 'reject' ? $rows->take(1) : $rows;

        foreach ($decided as $row) {
            $row->update([
                'decision' => $decision,
                'decided_by_user_id' => $requisition->approved_by_user_id,
                'decided_at' => ($decision === 'reject' ? $requisition->rejected_at : $requisition->approved_at)
                    ?? $requisition->created_at,
                'remarks' => $requisition->approval_remarks,
            ]);
        }
    }

    /** Write one pending row per active level of the current configuration. */
    protected function materialise(Requisition $requisition, int $cycle): void
    {
        $levels = $this->activeWorkflow()?->activeLevels ?? collect();

        $rows = $levels->values()->map(fn (ApprovalLevel $level, int $i) => [
            'approval_level_id' => $level->id,
            'cycle' => $cycle,
            'sequence' => $i + 1,
            'level_name' => $level->name,
            'permission_name' => $level->permission_name,
            'role_name' => $level->role_name,
        ]);

        // A configuration with no usable level would leave the requisition
        // permanently unapprovable, so fall back to the original single gate.
        if ($rows->isEmpty()) {
            $rows = collect([[
                'approval_level_id' => null,
                'cycle' => $cycle,
                'sequence' => 1,
                'level_name' => 'Approval',
                'permission_name' => self::FALLBACK_PERMISSION,
                'role_name' => null,
            ]]);
        }

        foreach ($rows as $row) {
            RequisitionApproval::firstOrCreate(
                ['requisition_id' => $requisition->id, 'cycle' => $row['cycle'], 'sequence' => $row['sequence']],
                $row + ['requisition_id' => $requisition->id],
            );
        }
    }

    /** Highest cycle already written for this requisition, or 0 if there is none. */
    protected function currentCycle(Requisition $requisition): int
    {
        return (int) RequisitionApproval::where('requisition_id', $requisition->id)->max('cycle');
    }

    /**
     * A re-submission after a rejection opens a new cycle; re-submitting a chain
     * that is still open keeps the one it has.
     */
    protected function nextCycle(Requisition $requisition): int
    {
        $cycle = $this->currentCycle($requisition);

        if ($cycle === 0) {
            return 1;
        }

        $closed = RequisitionApproval::where('requisition_id', $requisition->id)
            ->where('cycle', $cycle)
            ->where('decision', 'reject')
            ->exists();

        return $closed ? $cycle + 1 : $cycle;
    }

    /** @throws ValidationException */
    protected function assertActionable(Requisition $requisition, User $user): RequisitionApproval
    {
        abort_unless($requisition->status === 'submitted', 422, 'Only a submitted requisition can be decided.');

        $pending = $this->pending($requisition);

        abort_if($pending === null, 422, 'This requisition has no level awaiting a decision.');

        if ($requisition->requested_by_user_id === $user->id) {
            throw ValidationException::withMessages([
                'approval' => 'You cannot approve a requisition you raised.',
            ]);
        }

        if (! $pending->isSatisfiedBy($user)) {
            throw ValidationException::withMessages([
                'approval' => "This requisition is awaiting {$pending->level_name}, which you cannot act on.",
            ]);
        }

        return $pending;
    }

    protected function notifyLevelActors(Requisition $requisition, RequisitionApproval $pending): void
    {
        $actors = $this->actorsFor($pending, $requisition->requested_by_user_id);

        Notification::send($actors, new RequisitionAwaitingApproval($requisition, $pending));
    }

    protected function notifyRequester(Requisition $requisition, RequisitionApproval $decided, bool $moreLevelsRemain): void
    {
        $requester = $requisition->requestedBy;

        if (! $requester || $requester->id === $decided->decided_by_user_id) {
            return;
        }

        $requester->notify(new RequisitionDecided($requisition, $decided, $moreLevelsRemain));
    }

    /** Fully approved: the store officers who will raise the SIV need to know. */
    protected function notifyIssuers(Requisition $requisition): void
    {
        if (! Permission::where(['name' => 'issues.post', 'guard_name' => 'web'])->exists()) {
            return;
        }

        $issuers = User::permission('issues.post')->where('is_active', true)->get();

        Notification::send($issuers, new RequisitionReadyForIssue($requisition));
    }

    /**
     * Permission names a level may be bound to. Approval is meaningless for
     * permissions nobody would gate a decision on, but the department may want
     * any of them, so the full catalogue is offered.
     *
     * @return array<int, string>
     */
    public function bindablePermissions(): array
    {
        return Permission::orderBy('name')->pluck('name')->all();
    }
}
