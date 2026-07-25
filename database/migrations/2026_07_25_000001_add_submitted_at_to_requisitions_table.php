<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The SIV "Ordered by" block stamps the request date from the requisition, so
 * the moment of submission has to be a first-class column rather than inferred
 * from created_at. Existing rows get created_at as their best-known value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('requested_by_user_id');
        });

        DB::table('requisitions')
            ->whereNot('status', 'draft')
            ->update(['submitted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }
};
