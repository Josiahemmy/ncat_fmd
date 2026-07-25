<?php

use App\Http\Controllers\Documents\RequisitionController;
use App\Http\Controllers\Documents\SivController;
use App\Http\Controllers\Documents\SrvController;
use App\Http\Controllers\Documents\WorkOrderController;
use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Paper documents — Work Orders, Requisitions, SRV, SIV (Phase 3)
|--------------------------------------------------------------------------
| Every ledger-posting path funnels through StockService; documents never
| write to stock_movements directly. Numbers are reserved from the
| row-locked document_counters via DocumentNumberService.
*/

Route::middleware('auth')->group(function () {
    // Work Orders
    Route::middleware('can:work_orders.view')->group(function () {
        Route::get('work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
        Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');
    });
    Route::middleware('can:work_orders.create')->group(function () {
        Route::post('work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
    });
    Route::middleware('can:work_orders.edit')->group(function () {
        Route::get('work-orders/{workOrder}/edit', [WorkOrderController::class, 'edit'])->name('work-orders.edit');
        Route::put('work-orders/{workOrder}', [WorkOrderController::class, 'update'])->name('work-orders.update');
    });

    // Requisitions (Aircraft Spare Parts Requisition Sheet — one voucher per unit)
    Route::middleware('can:requisitions.view')->group(function () {
        Route::get('requisitions', [RequisitionController::class, 'index'])->name('requisitions.index');
    });
    Route::middleware('can:requisitions.create')->group(function () {
        Route::get('requisitions/create', [RequisitionController::class, 'create'])->name('requisitions.create');
        Route::post('requisitions', [RequisitionController::class, 'store'])->name('requisitions.store');
    });
    Route::middleware('can:requisitions.view')->group(function () {
        Route::get('requisitions/{requisition}/pdf', [PdfController::class, 'requisition'])->name('requisitions.pdf');
        Route::get('requisitions/{requisition}', [RequisitionController::class, 'show'])->name('requisitions.show');
    });
    Route::middleware('can:requisitions.create')->group(function () {
        Route::put('requisitions/{requisition}', [RequisitionController::class, 'update'])->name('requisitions.update');
        Route::post('requisitions/{requisition}/submit', [RequisitionController::class, 'submit'])->name('requisitions.submit');
        Route::post('requisitions/{requisition}/removal', [RequisitionController::class, 'completeRemoval'])->name('requisitions.removal');
    });
    // Gated by the approval engine rather than a fixed permission: the user must
    // satisfy whatever role or permission the requisition's pending level is
    // bound to. With the seeded default level that is `requisitions.approve`.
    Route::middleware('can:decide,requisition')->group(function () {
        Route::post('requisitions/{requisition}/approve', [RequisitionController::class, 'approve'])->name('requisitions.approve');
        Route::post('requisitions/{requisition}/reject', [RequisitionController::class, 'reject'])->name('requisitions.reject');
    });

    // Receiving — Store Receipt Voucher (SRV)
    Route::middleware('can:receiving.view')->group(function () {
        Route::get('receiving', [SrvController::class, 'index'])->name('receiving.index');
    });
    Route::middleware('can:receiving.post')->group(function () {
        Route::get('receiving/create', [SrvController::class, 'create'])->name('receiving.create');
        Route::post('receiving', [SrvController::class, 'store'])->name('receiving.store');
    });
    Route::middleware('can:receiving.view')->group(function () {
        Route::get('receiving/{srv}/pdf', [PdfController::class, 'srv'])->name('receiving.pdf');
        Route::get('receiving/{srv}', [SrvController::class, 'show'])->name('receiving.show');
    });
    Route::middleware('can:receiving.post')->group(function () {
        Route::put('receiving/{srv}', [SrvController::class, 'update'])->name('receiving.update');
        Route::post('receiving/{srv}/post', [SrvController::class, 'post'])->name('receiving.post');
    });

    // Issuing — Store Issue Voucher (SIV)
    Route::middleware('can:issues.view')->group(function () {
        Route::get('issuing', [SivController::class, 'index'])->name('issuing.index');
    });
    Route::middleware('can:issues.post')->group(function () {
        Route::get('issuing/create', [SivController::class, 'create'])->name('issuing.create');
        Route::post('issuing', [SivController::class, 'store'])->name('issuing.store');
    });
    Route::middleware('can:issues.view')->group(function () {
        Route::get('issuing/{siv}/pdf', [PdfController::class, 'siv'])->name('issuing.pdf');
        Route::get('issuing/{siv}', [SivController::class, 'show'])->name('issuing.show');
    });
    Route::middleware('can:issues.post')->group(function () {
        Route::put('issuing/{siv}', [SivController::class, 'update'])->name('issuing.update');
        Route::post('issuing/{siv}/post', [SivController::class, 'post'])->name('issuing.post');
    });
});
