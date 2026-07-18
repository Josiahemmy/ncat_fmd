<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        // The Super Admin role is immutable.
        return $user->can('roles.manage') && $role->name !== 'Super Admin';
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.manage') && $role->name !== 'Super Admin';
    }
}
