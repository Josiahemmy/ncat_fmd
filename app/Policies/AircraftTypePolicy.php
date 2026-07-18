<?php

namespace App\Policies;

use App\Models\AircraftType;
use App\Models\User;

class AircraftTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('aircraft.view');
    }

    public function view(User $user): bool
    {
        return $user->can('aircraft.view');
    }

    // Types are the fixed fleet set — name/image editable, no create/delete.
    public function update(User $user, AircraftType $type): bool
    {
        return $user->can('aircraft.manage');
    }
}
