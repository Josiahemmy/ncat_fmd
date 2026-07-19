<?php

use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
    Route::get('/search', SearchController::class)->name('search');

    // First-login forced password change (exempt from EnsurePasswordChanged).
    Route::get('/password/change', [ForcePasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('/password/change', [ForcePasswordChangeController::class, 'update'])->name('password.change.update');

    // Profile (account) management.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    | Module placeholders — the full modules land in later phases (see the
    | design spec §9). Each renders a branded "Coming in next phase" page so
    | the sidebar is fully navigable today.
    */
    $modules = [
        ['reports', 'Reports', 'Phase 5'],
    ];

    foreach ($modules as [$slug, $label, $phase]) {
        Route::get("/{$slug}", fn () => Inertia::render('Placeholder', [
            'module' => $label,
            'phase' => $phase,
        ]))->name($slug);
    }
});

require __DIR__.'/aircraft.php';
require __DIR__.'/stock.php';
require __DIR__.'/documents.php';
require __DIR__.'/admin.php';
require __DIR__.'/auth.php';
