<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store Receipt Voucher (SRV) — goods inward. Header + line items.
 * Draft → posted (irreversible): posting funnels `receive` movements into the
 * destination store (Quarantine by default; Fuel Dump for fuel) via StockService.
 * See forms_reference/Store Receipt Voucher.png.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('srvs', function (Blueprint $table) {
            $table->id();
            $table->string('srv_number')->unique();                 // No:
            $table->date('srv_date');                               // DATE
            $table->foreignId('destination_store_id')->constrained('stores'); // "into the ___ store"
            $table->string('supplier')->nullable();
            $table->string('lpo_or_petty_cash_ref')->nullable();    // LPO / Petty Cash Voucher No.
            $table->string('head_of_receiving_dept')->nullable();   // Head Of Receiving Dept.
            $table->string('storekeeper')->nullable();              // STOREKEEPER
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('srv_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('srv_id')->constrained('srvs')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts');
            $table->unsignedInteger('line_no')->default(1);         // ITEM No.
            $table->decimal('quantity', 12, 2);                     // QUANTITY (FIG)
            $table->string('supplier_details')->nullable();         // SUPPLIERS & DETAILS OF MATERIALS
            $table->string('fol_no')->nullable();                   // FOL NO
            $table->decimal('rate', 12, 2)->nullable();             // RATE
            $table->decimal('amount', 14, 2)->nullable();           // AMOUNT (₦ / K)
            $table->string('invoice_no')->nullable();               // INVOICE
            $table->string('acct_code')->nullable();                // ACCT CODE
            $table->string('batch_no')->nullable();                 // batch capture
            $table->string('batch_year')->nullable();
            $table->date('expiry_date')->nullable();
            $table->json('serials')->nullable();                    // captured serial numbers (serialized parts)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('srv_items');
        Schema::dropIfExists('srvs');
    }
};
