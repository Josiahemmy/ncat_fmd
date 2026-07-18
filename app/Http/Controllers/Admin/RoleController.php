<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::withCount('users')->orderBy('name')->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'users_count' => $r->users_count,
                'permissions' => $r->permissions->pluck('name'),
                'immutable' => $r->name === 'Super Admin',
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'permissionGroups' => Config::get('permissions.groups'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        activity('role')->causedBy($request->user())->performedOn($role)
            ->event('created')->log("Created role {$role->name}");

        return back()->with('success', "Role {$role->name} created.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === 'Super Admin') {
            return back()->with('error', 'The Super Admin role is immutable.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        activity('role')->causedBy($request->user())->performedOn($role)
            ->withProperties(['permissions' => $data['permissions'] ?? []])
            ->event('updated')->log("Updated role {$role->name}");

        return back()->with('success', "Role {$role->name} updated.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === 'Super Admin') {
            return back()->with('error', 'The Super Admin role cannot be deleted.');
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', 'Reassign users before deleting this role.');
        }

        $name = $role->name;
        $role->delete();

        activity('role')->causedBy($request->user())
            ->event('deleted')->log("Deleted role {$name}");

        return back()->with('success', "Role {$name} deleted.");
    }
}
