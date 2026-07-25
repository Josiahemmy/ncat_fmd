<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SIV header's "Requisition for" becomes a picker over fully approved
 * requisitions (spec §12.2). The link is the source of truth for the printed
 * "Ordered by" name and request date, so it needs a real foreign key rather than
 * the free-text label alone. `requisition_for` stays as the rendered caption and
 * still takes free text on standalone vouchers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sivs', function (Blueprint $table) {
            $table->foreignId('requisition_id')->nullable()->after('siv_number')
                ->constrained('requisitions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sivs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requisition_id');
        });
    }
};
