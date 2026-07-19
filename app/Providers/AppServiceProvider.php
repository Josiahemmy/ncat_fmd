<?php

namespace App\Providers;

use App\Models\Requisition;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use App\Services\Dashboard\DashboardService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Super Admin bypasses every ability check. Keeps policies authoritative
        // while guaranteeing the administrator always has full access.
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        // Audit authentication + record last login.
        Event::listen(Login::class, function (Login $event) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            activity('auth')->causedBy($event->user)->event('login')->log('Signed in');
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                activity('auth')->causedBy($event->user)->event('logout')->log('Signed out');
            }
        });

        // Keep the cached dashboard aggregates fresh: any ledger posting or
        // document status change invalidates the 60s cache so the alert panel,
        // KPIs and the shared notification-bell counts never go stale.
        $bust = fn () => app(DashboardService::class)->bust();
        StockMovement::created($bust);
        Requisition::saved($bust);
        WorkOrder::saved($bust);
    }
}
