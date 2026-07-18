<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $users = User::query()
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->with('roles:id,name')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_active' => $u->is_active,
                'must_change_password' => $u->password_change_required,
                'last_login_at' => $u->last_login_at?->toDayDateTimeString(),
                'roles' => $u->roles->pluck('name'),
                'permission_overrides' => $u->getDirectPermissions()->pluck('name'),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
            'roles' => Role::orderBy('name')->pluck('name'),
            'permissionGroups' => Config::get('permissions.groups'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $tempPassword = Str::password(12);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($tempPassword),
            'password_change_required' => true,
            'is_active' => true,
        ]);

        $user->syncRoles($data['roles'] ?? []);

        activity('user')->causedBy($request->user())->performedOn($user)
            ->event('created')->log("Created user {$user->email}");

        // The temp password is shown ONCE to the admin (flash, not stored).
        return back()->with('success', "User {$user->name} created.")
            ->with('generated_password', $tempPassword)
            ->with('generated_for', $user->email);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        // Guard: an admin cannot deactivate their own account.
        $isActive = $request->boolean('is_active');
        if ($user->id === $request->user()->id && ! $isActive) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $isActive,
        ]);

        $user->syncRoles($data['roles'] ?? []);
        $user->syncPermissions($data['permissions'] ?? []);

        activity('user')->causedBy($request->user())->performedOn($user)
            ->withProperties(['roles' => $data['roles'] ?? [], 'is_active' => $isActive])
            ->event('updated')->log("Updated user {$user->email}");

        return back()->with('success', "User {$user->name} updated.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $tempPassword = Str::password(12);

        $user->forceFill([
            'password' => Hash::make($tempPassword),
            'password_change_required' => true,
        ])->save();

        activity('user')->causedBy($request->user())->performedOn($user)
            ->event('password_reset')->log("Reset password for {$user->email}");

        return back()->with('success', "Password reset for {$user->name}.")
            ->with('generated_password', $tempPassword)
            ->with('generated_for', $user->email);
    }
}
