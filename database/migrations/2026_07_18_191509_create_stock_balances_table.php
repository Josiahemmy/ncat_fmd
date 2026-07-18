<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-part per-store on-hand summary, maintained INSIDE the same
     * transaction as each movement (row-locked). This is the row the engine
     * locks (lockForUpdate) to serialise concurrent postings and guarantee a
     * balance can never be read or written wrong, even mid-transaction.
     * It is a derived cache of stock_movements, never the source of truth.
     */
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->decimal('quantity', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['part_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
