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

    /**
     * Note what this does NOT guarantee. `Gate::before` in AppServiceProvider
     * returns true for a Super Admin before any policy runs, so for exactly the
     * user most able to do damage this method is never consulted. The rule that
     * the Super Admin role cannot be edited or deleted is enforced in
     * RoleController, which checks the name on the way in and answers with a
     * refusal regardless of who is asking. This clause covers everyone else.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.manage') && $role->name !== 'Super Admin';
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.manage') && $role->name !== 'Super Admin';
    }
}
