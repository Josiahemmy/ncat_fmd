<?php

use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes — NCAT FMD Inventory
|--------------------------------------------------------------------------
| This is an internal tool: the root simply forwards into the app. Guests
| hitting an auth-protected route are redirected to the login screen.
*/

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Global typeahead (grouped, permission-filtered) — JSON for the topbar.
    Route::get('/search', SearchController::class)->middleware('throttle:search')->name('search');

    // Event notifications (database channel). Every signed-in user has their own
    // queue, so these need no extra permission.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'read'])->name('notifications.read');

    // First-login forced password change (exempt from EnsurePasswordChanged).
    Route::get('/password/change', [ForcePasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('/password/change', [ForcePasswordChangeController::class, 'update'])->name('password.change.update');

    // Profile (account) management.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Reports module (spec §7 module 10) — filterable screens + PDF/CSV export.
    Route::middleware('can:reports.view')->group(function () {
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports');
        Route::get('/reports/{report}/export', [ReportsController::class, 'export'])->name('reports.export');
        Route::get('/reports/{report}', [ReportsController::class, 'show'])->name('reports.show');
    });
});

require __DIR__.'/aircraft.php';
require __DIR__.'/stock.php';
require __DIR__.'/documents.php';
require __DIR__.'/orders.php';
require __DIR__.'/admin.php';
require __DIR__.'/auth.php';
