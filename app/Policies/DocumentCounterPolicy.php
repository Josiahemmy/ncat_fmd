<?php

namespace App\Policies;

use App\Models\DocumentCounter;
use App\Models\User;

class DocumentCounterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('counters.view');
    }

    public function view(User $user): bool
    {
        return $user->can('counters.view');
    }

    // Counters are seeded per series — value editable, no create/delete.
    public function update(User $user, DocumentCounter $counter): bool
    {
        return $user->can('counters.manage');
    }
}
