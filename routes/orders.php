<?php

use App\Http\Controllers\Documents\PurchaseOrderController;
use App\Http\Controllers\Documents\RepairOrderController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vendors and Orders (Phase 7, spec §12.4 / §12.5)
|--------------------------------------------------------------------------
| References are minted by DocumentNumberService when an order leaves draft,
| never at creation, so an abandoned draft cannot leave a gap in the series.
| Receiving against a purchase order runs through the SRV, so the ledger stays
| the single source of truth for what actually arrived.
*/

Route::middleware('auth')->group(function () {
    // Vendors
    Route::middleware('can:vendors.view')->group(function () {
        Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::get('vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
    });
    Route::middleware('can:vendors.manage')->group(function () {
        Route::post('vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::put('vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
        Route::delete('vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');
    });

    // Purchase Orders
    Route::middleware('can:orders.create')->group(function () {
        Route::get('orders/purchase/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('orders/purchase', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
    });
    Route::middleware('can:orders.view')->group(function () {
        Route::get('orders/purchase', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('orders/purchase/{purchaseOrder}/pdf', [PdfController::class, 'purchaseOrder'])->name('purchase-orders.pdf');
        Route::get('orders/purchase/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    });
    Route::middleware('can:orders.edit')->group(function () {
        Route::get('orders/purchase/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
        Route::put('orders/purchase/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::post('orders/purchase/{purchaseOrder}/issue', [PurchaseOrderController::class, 'issue'])->name('purchase-orders.issue');
    });
    Route::middleware('can:orders.close')->group(function () {
        Route::post('orders/purchase/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])->name('purchase-orders.close');
        Route::post('orders/purchase/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    });

    // Repair Orders
    Route::middleware('can:orders.create')->group(function () {
        Route::get('orders/repair/create', [RepairOrderController::class, 'create'])->name('repair-orders.create');
        Route::post('orders/repair', [RepairOrderController::class, 'store'])->name('repair-orders.store');
    });
    Route::middleware('can:orders.view')->group(function () {
        Route::get('orders/repair', [RepairOrderController::class, 'index'])->name('repair-orders.index');
        Route::get('orders/repair/{repairOrder}/pdf', [PdfController::class, 'repairOrder'])->name('repair-orders.pdf');
        Route::get('orders/repair/{repairOrder}', [RepairOrderController::class, 'show'])->name('repair-orders.show');
    });
    Route::middleware('can:orders.edit')->group(function () {
        Route::get('orders/repair/{repairOrder}/edit', [RepairOrderController::class, 'edit'])->name('repair-orders.edit');
        Route::put('orders/repair/{repairOrder}', [RepairOrderController::class, 'update'])->name('repair-orders.update');
        Route::post('orders/repair/{repairOrder}/issue', [RepairOrderController::class, 'issue'])->name('repair-orders.issue');
        Route::post('orders/repair/{repairOrder}/at-vendor', [RepairOrderController::class, 'atVendor'])->name('repair-orders.at-vendor');
        Route::post('orders/repair/{repairOrder}/returned', [RepairOrderController::class, 'returned'])->name('repair-orders.returned');
    });
    Route::middleware('can:orders.close')->group(function () {
        Route::post('orders/repair/{repairOrder}/close', [RepairOrderController::class, 'close'])->name('repair-orders.close');
        Route::post('orders/repair/{repairOrder}/cancel', [RepairOrderController::class, 'cancel'])->name('repair-orders.cancel');
    });
});
