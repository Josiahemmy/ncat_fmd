<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('part_batch_id')->nullable()->constrained('part_batches')->nullOnDelete();
            $table->string('serial_number');
            $table->enum('status', ['in_store', 'installed', 'removed_unserviceable', 'at_repair', 'scrapped'])
                ->default('in_store');
            // A serial is in exactly one store/state at a time.
            $table->foreignId('current_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('current_aircraft_id')->nullable()->constrained('aircraft')->nullOnDelete();
            $table->string('position')->nullable(); // zone/position when installed
            $table->timestamps();

            $table->unique(['part_id', 'serial_number']);
            $table->index(['part_id', 'current_store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_serials');
    }
};
