<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks demo-mode state (Builder Prompt #7). A single row records whether the
 * app is currently showing seeded demo data, and snapshots the document-counter
 * values taken at seed time so `demo:purge` can restore them exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_states', function (Blueprint $table) {
            $table->id();
            $table->boolean('active')->default(false);
            // { series => next_number } captured before the demo advanced them.
            $table->json('counters_snapshot')->nullable();
            $table->timestamp('seeded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_states');
    }
};
