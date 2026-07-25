<?php

namespace App\Http\Middleware;

use App\Http\Controllers\NotificationController;
use App\Services\Dashboard\DashboardService;
use App\Services\Documents\ApprovalService;
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
            // Event notifications from the database channel (approval decisions,
            // low stock). A separate path from `alerts`, which stays computed.
            'notices' => fn () => $this->notices($user),
            // Sidebar work-queue badges (approvals for approvers, quarantine for certifiers).
            'badges' => fn () => $this->badges($user),
            // Demo-mode banner flag (Builder Prompt #7) — cheap cached read.
            'demo_mode' => fn () => app(\App\Services\Demo\DemoMode::class)->isActive(),
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

        // Whether this user can approve at all depends on the configured levels,
        // not a fixed permission. The badge then counts only what is actually
        // waiting on them, and skips the query entirely when nothing is pending.
        $approvals = app(ApprovalService::class);

        if ($approvals->canApproveAnyLevel($user)) {
            $badges['approvals'] = $counts['requisitions_pending'] > 0
                ? $approvals->pendingForCount($user)
                : 0;
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

    /**
     * Unread event notifications for the bell: a count plus the newest few.
     *
     * @return array<string, mixed>
     */
    protected function notices($user): array
    {
        if (! $user) {
            return ['count' => 0, 'items' => []];
        }

        return [
            'count' => $user->unreadNotifications()->count(),
            'items' => $user->unreadNotifications()
                ->latest()->limit(NotificationController::BELL_LIMIT)->get()
                ->map(fn ($n) => NotificationController::present($n))
                ->values()
                ->all(),
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
