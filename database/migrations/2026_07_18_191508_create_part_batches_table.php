<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->string('batch_number');
            $table->unsignedSmallInteger('batch_year')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('qty_received', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['part_id', 'expiry_date']); // FEFO ordering
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_batches');
    }
};
