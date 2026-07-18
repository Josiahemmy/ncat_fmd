<?php

namespace App\Policies;

use App\Models\Aircraft;
use App\Models\User;

class AircraftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('aircraft.view');
    }

    public function view(User $user): bool
    {
        return $user->can('aircraft.view');
    }

    public function create(User $user): bool
    {
        return $user->can('aircraft.manage');
    }

    public function update(User $user, Aircraft $aircraft): bool
    {
        return $user->can('aircraft.manage');
    }

    public function delete(User $user, Aircraft $aircraft): bool
    {
        return $user->can('aircraft.manage');
    }
}
