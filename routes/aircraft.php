<?php

use App\Http\Controllers\AircraftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Aircraft experience (Phase 4)
|--------------------------------------------------------------------------
| The flagship fleet grid → registrations → Aircraft Workspace. The workspace
| is bound by registration for clean, memorable URLs (/aircraft/5N-XYZ) while
| the admin fleet CRUD keeps its id binding untouched.
*/

Route::middleware('auth')->group(function () {
    Route::middleware('can:aircraft.view')->group(function () {
        Route::get('aircraft-types', [AircraftController::class, 'index'])->name('aircraft-types');
        Route::get('aircraft/{aircraft:registration}', [AircraftController::class, 'show'])->name('aircraft.show');
    });
});
