<?php

namespace App\Http\Middleware;

use App\Services\Dashboard\DashboardService;
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
            // Live stock alerts for the notification bell (count + list).
            'alerts' => fn () => $this->alerts($user),
            // Sidebar work-queue badges (approvals for approvers, quarantine for certifiers).
            'badges' => fn () => $this->badges($user),
        ];
    }

    /**
     * Sidebar badge counts, gated by permission so each role only sees its own
     * queue. Sourced from the cached dashboard aggregates — no per-request
     * query cost.
     *
     * @return array<string, int>
     */
    protected function badges($user): array
    {
        if (! $user) {
            return [];
        }

        $counts = app(DashboardService::class)->aggregates()['alerts'];
        $badges = [];

        if ($user->can('requisitions.approve')) {
            $badges['approvals'] = $counts['requisitions_pending'];
        }

        if ($user->can('quarantine.certify')) {
            $badges['quarantine'] = $counts['quarantine'];
        }

        return $badges;
    }

    /**
     * Live, grouped, permission-filtered alerts for the notification bell.
     * The heavy computation is done once per 60s in DashboardService and
     * busted on posting — the shared cost here is a few permission checks.
     *
     * @return array<string, mixed>
     */
    protected function alerts($user): array
    {
        return app(DashboardService::class)->sharedAlerts($user);
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
