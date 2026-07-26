<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files hung off a shipment event (Phase 9, item 2). An airway bill, a customs
 * release note and an agent's invoice are what a timeline entry actually
 * refers to, so the entry should carry them.
 *
 * The file itself lives under `storage/app`, never `public/`: on cPanel shared
 * hosting anything under the document root is fetchable by anyone who guesses
 * the URL, and a customs document is not public. `path` is therefore a private
 * disk path served through a permission-gated controller. `original_name`
 * exists because the stored name is generated. The clerk's own filename is
 * kept for display and for the download filename only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_event_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_event_id')->constrained('shipment_events')->cascadeOnDelete();
            // The disk is stored so a later move off local storage does not
            // strand rows written before the move.
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('shipment_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_event_attachments');
    }
};
