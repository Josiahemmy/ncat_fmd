<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A small key/value store for department-editable document defaults, starting
 * with the NCAT contacts block printed on the order forms. These belong in the
 * database rather than config because the department edits them in
 * Administration without a deploy, and they are not secrets.
 *
 * Not a transactional table: a demo purge leaves it alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
