<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'must_change_password' => (bool) $user->password_change_required,
                ] : null,
                'roles' => $user ? $user->getRoleNames()->values() : [],
                // Effective permission names. Super Admin (Gate::before bypass)
                // gets the whole catalogue so the UI mirrors its full access.
                'permissions' => $user
                    ? ($user->hasRole('Super Admin')
                        ? $this->allPermissionNames()
                        : $user->getAllPermissions()->pluck('name')->values())
                    : [],
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // One-time temp password shown to an admin after create/reset.
                'generated_password' => fn () => $request->session()->get('generated_password'),
                'generated_for' => fn () => $request->session()->get('generated_for'),
            ],
        ];
    }

    /** @return array<int, string> */
    protected function allPermissionNames(): array
    {
        $names = [];
        foreach (Config::get('permissions.groups', []) as $group) {
            $names = array_merge($names, array_keys($group['permissions']));
        }

        return $names;
    }
}
