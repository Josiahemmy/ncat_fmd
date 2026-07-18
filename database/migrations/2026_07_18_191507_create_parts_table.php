<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('part_number')->unique();
            $table->string('description');
            $table->foreignId('ata_chapter_id')->nullable()->constrained('ata_chapters')->nullOnDelete();
            $table->foreignId('aircraft_type_id')->nullable()->constrained('aircraft_types')->nullOnDelete();
            $table->string('stock_code')->nullable();
            $table->string('ledger_folio')->nullable();
            $table->string('unit_of_issue')->default('EA'); // per AD38 "Unit of Issue"
            $table->decimal('unit_price', 14, 2)->nullable(); // ₦
            $table->string('bin_location')->nullable();
            $table->decimal('min_level', 14, 2)->default(0);
            $table->decimal('max_level', 14, 2)->nullable();
            $table->decimal('reorder_level', 14, 2)->default(0);
            $table->boolean('is_serialized')->default(false);
            $table->boolean('has_shelf_life')->default(false);
            $table->boolean('is_flammable')->default(false); // certification routes to Dope
            $table->boolean('is_fuel')->default(false);      // bulk liquid (litres)
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('description');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
