<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendors (spec §12.4): the suppliers and repair organisations that Purchase
 * Orders and Repair Orders are addressed to.
 *
 * `address` is a single multi-line text column rather than parsed fields
 * because both order forms print it verbatim, one line per line, and the paper
 * makes no attempt at a structured address. Country is kept separate: it is the
 * only part the department filters on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // A vendor that both supplies parts and overhauls them is common, so
            // `both` is a first-class type rather than two vendor records.
            $table->enum('type', ['supplier', 'repair_organization', 'both'])->default('supplier');
            $table->text('address')->nullable();
            $table->string('country')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('contact_person')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
