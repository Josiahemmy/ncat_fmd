<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store Issue Voucher (SIV) — goods outward. Header + line items.
 * Draft → posted (irreversible): posting funnels `issue` movements OUT of
 * Bonded / Dope only (FEFO batch, specific serials) via StockService.
 * Lines may be pulled from approved requisitions (link + feedback) or be
 * standalone consumable lines. See forms_reference/Store Issue Voucher.png.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sivs', function (Blueprint $table) {
            $table->id();
            $table->string('siv_number')->unique();               // STORE ISSUE VOUCHER NO
            $table->string('requisition_for')->nullable();        // REQUISITION FOR
            $table->string('ordered_by')->nullable();             // ORDERED BY (name)
            $table->date('ordered_by_date')->nullable();
            $table->string('school_section')->nullable();         // SCHOOL/SECTION
            $table->string('approved_by')->nullable();            // APPROVED BY (name on form)
            $table->date('approved_by_date')->nullable();
            $table->string('entered_by')->nullable();             // ENTERED BY
            $table->date('entered_by_date')->nullable();
            $table->string('issued_by')->nullable();              // ISSUED BY
            $table->date('issued_by_date')->nullable();
            $table->string('received_by')->nullable();            // RECEIVED BY
            $table->date('received_by_date')->nullable();
            $table->string('remark')->nullable();                 // REMARK
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('siv_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siv_id')->constrained('sivs')->cascadeOnDelete();
            $table->unsignedInteger('line_no')->default(1);       // ITEM NO
            $table->foreignId('requisition_id')->nullable()->constrained('requisitions')->nullOnDelete();
            $table->foreignId('part_id')->constrained('parts');
            $table->string('description')->nullable();            // DESCRIPTION
            $table->decimal('qty_required', 12, 2);               // QUANTITY REQUIRED (FIG)
            $table->decimal('qty_issued', 12, 2)->default(0);     // QUANTITY ISSUED
            $table->foreignId('source_store_id')->constrained('stores'); // Bonded / Dope
            $table->string('stores_folio')->nullable();           // STORES FOLIO
            $table->decimal('rate', 12, 2)->nullable();           // RATE
            $table->decimal('amount', 14, 2)->nullable();         // AMOUNT (₦ / K)
            $table->string('charging_code')->nullable();          // Charging Code
            $table->foreignId('part_batch_id')->nullable()->constrained('part_batches')->nullOnDelete();
            $table->json('serial_ids')->nullable();               // chosen serials (serialized parts)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siv_items');
        Schema::dropIfExists('sivs');
    }
};
