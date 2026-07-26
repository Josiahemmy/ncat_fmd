<?php

use App\Http\Controllers\Logistics\LoanController;
use App\Http\Controllers\Logistics\ShipmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shipping and Loans (Phase 8, spec §12.6 / §12.7 / §12.8)
|--------------------------------------------------------------------------
| Note what is absent from the shipping block: no PUT or DELETE for an event.
| The timeline is append-only, so there is no route that could edit it even if
| a caller went looking for one. Corrections are new events.
|
| Loan write-off is gated on `stock.adjust` inside the controller rather than
| here, because it posts a ledger adjustment and must answer to the same
| permission as every other adjustment.
*/

Route::middleware('auth')->group(function () {
    // Shipping
    Route::middleware('can:shipping.view')->group(function () {
        Route::get('shipments', [ShipmentController::class, 'index'])->name('shipments.index');
        Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])
            ->whereNumber('shipment')->name('shipments.show');
    });
    Route::middleware('can:shipping.manage')->group(function () {
        Route::get('shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
        Route::post('shipments', [ShipmentController::class, 'store'])->name('shipments.store');
        Route::put('shipments/{shipment}', [ShipmentController::class, 'update'])->name('shipments.update');
        Route::post('shipments/{shipment}/events', [ShipmentController::class, 'addEvent'])->name('shipments.events.store');
        Route::post('shipments/{shipment}/close', [ShipmentController::class, 'close'])->name('shipments.close');
    });
    Route::middleware('can:receiving.post')->group(function () {
        Route::get('shipments/{shipment}/srv', [ShipmentController::class, 'createSrv'])->name('shipments.srv');
    });

    // Loans, both directions
    Route::middleware('can:loans.view')->group(function () {
        Route::get('loans', [LoanController::class, 'index'])->name('loans.index');
        Route::get('loans/{loan}', [LoanController::class, 'show'])->whereNumber('loan')->name('loans.show');
    });
    Route::middleware('can:loans.manage')->group(function () {
        Route::post('loans/outbound', [LoanController::class, 'storeOutbound'])->name('loans.outbound.store');
        Route::post('loans/inbound', [LoanController::class, 'storeInbound'])->name('loans.inbound.store');
        Route::post('loans/{loan}/return', [LoanController::class, 'recordReturn'])->name('loans.return');
        Route::post('loans/{loan}/install', [LoanController::class, 'install'])->name('loans.install');
        Route::post('loans/{loan}/write-off', [LoanController::class, 'writeOff'])->name('loans.write-off');
    });
});
