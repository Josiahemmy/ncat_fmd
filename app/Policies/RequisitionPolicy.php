<?php

namespace App\Policies;

use App\Models\Requisition;
use App\Models\User;
use App\Services\Documents\ApprovalService;

/**
 * The approve/reject gate. Before this phase the route was gated on the fixed
 * `requisitions.approve` permission; now it asks the engine whether the user
 * satisfies whatever the pending level is bound to.
 *
 * Duty segregation (approver ≠ requester) is deliberately NOT checked here. It
 * belongs in the service, which raises a validation error the form can show,
 * and a policy-level refusal would turn that into a bare 403. Keeping it in the
 * service also means a Super Admin cannot bypass it through Gate::before.
 */
class RequisitionPolicy
{
    public function __construct(protected ApprovalService $approvals)
    {
    }

    public function decide(User $user, Requisition $requisition): bool
    {
        return $this->approvals->matchesPendingLevel($user, $requisition);
    }
}
