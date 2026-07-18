<?php

use App\Http\Controllers\Stock\PartController;
use App\Http\Controllers\Stock\StockPostingController;
use App\Http\Controllers\Stock\StoresController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Parts catalogue & stores module (Phase 2)
|--------------------------------------------------------------------------
| All stock mutations go through StockService via StockPostingController —
| no controller writes to stock_movements directly.
*/

Route::middleware('auth')->group(function () {
    // Parts catalogue
    Route::middleware('can:parts.view')->group(function () {
        Route::get('parts', [PartController::class, 'index'])->name('parts.index');
        Route::get('parts/{part}', [PartController::class, 'show'])->name('parts.show');
    });
    Route::middleware('can:parts.manage')->group(function () {
        Route::post('parts', [PartController::class, 'store'])->name('parts.store');
        Route::put('parts/{part}', [PartController::class, 'update'])->name('parts.update');
        Route::delete('parts/{part}', [PartController::class, 'destroy'])->name('parts.destroy');
    });

    // Stores module
    Route::middleware('can:stores.view')->group(function () {
        Route::get('stores', [StoresController::class, 'index'])->name('stores.index');
        Route::get('stores/{store}', [StoresController::class, 'show'])->name('stores.show');
    });

    // Postings (each gated by its own permission; all funnel through StockService)
    Route::post('stock/certify', [StockPostingController::class, 'certify'])
        ->name('stock.certify')->middleware('can:quarantine.certify');
    Route::post('stock/transfer', [StockPostingController::class, 'transfer'])
        ->name('stock.transfer')->middleware('can:stock.transfer');
    Route::post('stock/adjust', [StockPostingController::class, 'adjust'])
        ->name('stock.adjust')->middleware('can:stock.adjust');
});
