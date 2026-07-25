<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AircraftController;
use App\Http\Controllers\Admin\AircraftTypeController;
use App\Http\Controllers\Admin\ApprovalWorkflowController;
use App\Http\Controllers\Admin\AtaChapterController;
use App\Http\Controllers\Admin\DocumentCounterController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administration routes (/admin)
|--------------------------------------------------------------------------
| Every action is permission-gated with the `can:` middleware, matching the
| server-side policies. The sidebar hides what these enforce.
*/

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Users
    Route::middleware('can:users.view')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
    });
    Route::middleware('can:users.manage')->group(function () {
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset');
    });

    // Roles & permissions
    Route::middleware('can:roles.view')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    });
    Route::middleware('can:roles.manage')->group(function () {
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    // Fleet (aircraft + types)
    Route::middleware('can:aircraft.view')->group(function () {
        Route::get('fleet', [AircraftController::class, 'index'])->name('fleet.index');
    });
    Route::middleware('can:aircraft.manage')->group(function () {
        Route::post('aircraft', [AircraftController::class, 'store'])->name('aircraft.store');
        Route::put('aircraft/{aircraft}', [AircraftController::class, 'update'])->name('aircraft.update');
        Route::delete('aircraft/{aircraft}', [AircraftController::class, 'destroy'])->name('aircraft.destroy');
        Route::put('aircraft-types/{type}', [AircraftTypeController::class, 'update'])->name('types.update');
    });

    // Stores
    Route::middleware('can:stores.view')->group(function () {
        Route::get('stores', [StoreController::class, 'index'])->name('stores.index');
    });
    Route::middleware('can:stores.manage')->group(function () {
        Route::post('stores', [StoreController::class, 'store'])->name('stores.store');
        Route::put('stores/{store}', [StoreController::class, 'update'])->name('stores.update');
    });

    // ATA chapters
    Route::middleware('can:ata.view')->group(function () {
        Route::get('ata-chapters', [AtaChapterController::class, 'index'])->name('ata.index');
    });
    Route::middleware('can:ata.manage')->group(function () {
        Route::post('ata-chapters', [AtaChapterController::class, 'store'])->name('ata.store');
        Route::put('ata-chapters/{chapter}', [AtaChapterController::class, 'update'])->name('ata.update');
        Route::delete('ata-chapters/{chapter}', [AtaChapterController::class, 'destroy'])->name('ata.destroy');
    });

    // Document counters
    Route::middleware('can:counters.view')->group(function () {
        Route::get('counters', [DocumentCounterController::class, 'index'])->name('counters.index');
    });
    Route::middleware('can:counters.manage')->group(function () {
        Route::put('counters/{counter}', [DocumentCounterController::class, 'update'])->name('counters.update');
    });

    // Approval workflow (ordered levels bound to a permission or a role)
    Route::middleware('can:approvals.manage')->group(function () {
        Route::get('approval-workflow', [ApprovalWorkflowController::class, 'index'])->name('approvals.index');
        Route::put('approval-workflow/{workflow}', [ApprovalWorkflowController::class, 'update'])->name('approvals.update');
    });

    // Activity log
    Route::middleware('can:audit.view')->group(function () {
        Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');
    });
});
