<?php

use App\Http\Controllers\Stock\FuelController;
use App\Http\Controllers\Stock\OpeningBalanceController;
use App\Http\Controllers\Stock\PartController;
use App\Http\Controllers\Stock\StockPostingController;
use App\Http\Controllers\Stock\StoresController;
use App\Http\Controllers\Stock\TallyController;
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
        Route::get('fuel', [FuelController::class, 'index'])->name('fuel.index');
    });
    Route::middleware('can:fuel.post')->group(function () {
        Route::post('fuel/receive', [FuelController::class, 'receive'])->name('fuel.receive');
        Route::post('fuel/issue', [FuelController::class, 'issue'])->name('fuel.issue');
    });

    // Tally cards (AD38)
    Route::middleware('can:tally.view')->group(function () {
        Route::get('tally-cards', [TallyController::class, 'index'])->name('tally-cards.index');
        Route::get('tally-cards/{part}/pdf', [\App\Http\Controllers\PdfController::class, 'tally'])->name('tally-cards.pdf');
        Route::get('tally-cards/{part}', [TallyController::class, 'show'])->name('tally-cards.show');
    });

    // Opening balances (digitising the paper tally cards) — gated by stock.adjust
    Route::middleware('can:stock.adjust')->group(function () {
        Route::get('opening-balances', [OpeningBalanceController::class, 'index'])->name('opening-balances.index');
        Route::get('opening-balances/template', [OpeningBalanceController::class, 'template'])->name('opening-balances.template');
        Route::post('opening-balances', [OpeningBalanceController::class, 'store'])->name('opening-balances.store');
        Route::post('opening-balances/preview', [OpeningBalanceController::class, 'preview'])->name('opening-balances.preview');
        Route::post('opening-balances/import', [OpeningBalanceController::class, 'import'])->name('opening-balances.import');
    });

    // Postings (each gated by its own permission; all funnel through StockService)
    Route::middleware('throttle:posting')->group(function () {
        Route::post('stock/certify', [StockPostingController::class, 'certify'])
            ->name('stock.certify')->middleware('can:quarantine.certify');
        Route::post('stock/transfer', [StockPostingController::class, 'transfer'])
            ->name('stock.transfer')->middleware('can:stock.transfer');
        Route::post('stock/adjust', [StockPostingController::class, 'adjust'])
            ->name('stock.adjust')->middleware('can:stock.adjust');
    });
});
