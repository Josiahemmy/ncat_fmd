<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ata_chapters', function (Blueprint $table) {
            $table->id();
            // Kept as a string to preserve leading zeros ("00", "05", "21").
            $table->string('chapter_number')->unique();
            $table->string('title');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ata_chapters');
    }
};
