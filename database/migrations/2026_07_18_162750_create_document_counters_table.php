<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            // series: work_order | requisition | srv | siv
            $table->string('series')->unique();
            $table->string('label');
            $table->string('prefix')->nullable();
            // The NEXT number to be issued. Admin-editable (permission-gated,
            // audit-logged) to continue the department's paper sequences.
            $table->unsignedBigInteger('next_number')->default(1);
            // Zero-pad width for display (e.g. 4 → "0293"). 0 = no padding.
            $table->unsignedTinyInteger('padding')->default(0);
            $table->boolean('confirmed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_counters');
    }
};
