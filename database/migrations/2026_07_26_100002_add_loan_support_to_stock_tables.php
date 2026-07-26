<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema support for loans in both directions (spec §12.7).
 *
 * Lending does not dispose of an asset and borrowing does not acquire one, so
 * both directions are modelled as locations rather than as stock appearing and
 * disappearing:
 *
 *  · Outbound: stock moves Bonded/Dope into "On Loan (Out)", a store NCAT
 *    still owns. The ledger keeps the units, the issuing store's balance drops,
 *    and an unreturned loan is written off as an adjustment out of that store.
 *    That is what makes the write-off visible in the ledger instead of a status
 *    flag nobody reconciles.
 *
 *  · Inbound: stock lands in "Loaned In (Not NCAT Property)", a store carrying
 *    `owned = false`. It is a real location so a borrowed unit can be issued to
 *    an aircraft through the normal SIV path, but every value, stock-summary and
 *    reorder-alert query filters on `owned`, so borrowed stock can never inflate
 *    what NCAT reports as its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // The ownership flag every value/summary query filters on.
            $table->boolean('owned')->default(true)->after('type');
            $table->enum('type', ['quarantine', 'bonded', 'dope', 'fuel', 'general', 'loan_out', 'loan_in'])
                ->default('general')->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('movement_type', [
                'opening_balance', 'receiving', 'certification_transfer', 'transfer',
                'issue', 'fuel_receive', 'fuel_issue', 'adjustment', 'return',
                'loan_out', 'loan_return', 'loan_in', 'loan_in_return',
            ])->change();
        });

        Schema::table('part_serials', function (Blueprint $table) {
            $table->enum('status', ['in_store', 'installed', 'removed_unserviceable', 'at_repair', 'scrapped', 'on_loan'])
                ->default('in_store')->change();
            // Borrowed property. Survives being installed on an aircraft, which
            // is exactly when the marking matters most.
            $table->boolean('is_loaned')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('part_serials', function (Blueprint $table) {
            $table->dropColumn('is_loaned');
            $table->enum('status', ['in_store', 'installed', 'removed_unserviceable', 'at_repair', 'scrapped'])
                ->default('in_store')->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('movement_type', [
                'opening_balance', 'receiving', 'certification_transfer', 'transfer',
                'issue', 'fuel_receive', 'fuel_issue', 'adjustment', 'return',
            ])->change();
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('owned');
            $table->enum('type', ['quarantine', 'bonded', 'dope', 'fuel', 'general'])
                ->default('general')->change();
        });
    }
};
