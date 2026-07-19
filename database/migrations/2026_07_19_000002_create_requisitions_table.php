<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aircraft Spare Parts Requisition Sheet — paper-exact, ONE VOUCHER PER UNIT
 * (no quantity: each voucher requisitions a single unit). Field numbers below
 * map to the printed form in forms_reference/Requisition Sheet.png.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_no')->unique();               // (A) NO.
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();

            // Header — REQUIRED FOR
            $table->foreignId('aircraft_id')->nullable()->constrained('aircraft')->nullOnDelete();
            $table->string('aircraft_reg')->nullable();               // (1) AIRCRAFT REG (captured text)
            $table->string('engine_serial_no')->nullable();           // (2) ENGINE SER. NO.
            $table->string('position')->nullable();                   // (3) POSITION
            $table->string('authorised_by')->nullable();              // (4) AUTHORISED BY
            $table->string('supply_source')->nullable();              // (C) SUPPLY SOURCE

            // Part identification block
            $table->string('full_description');                       // (5) FULL DESCRIPTION
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->string('part_no')->nullable();                    // (6) PART NO (free-text fallback)
            $table->string('stock_code')->nullable();                 // (7) STOCK CODE
            $table->string('serial_number')->nullable();              // (8) SERIAL NUMBER
            $table->string('batch_no')->nullable();                   // (9) BATCH NO
            $table->string('batch_year')->nullable();                 // (9) YEAR
            $table->string('bin_bal_line_no')->nullable();            // (10) BIN / BAL LINE NO.

            // Issuance (11–12)
            $table->string('issued_by')->nullable();                  // (11) SERVICEABLE UNIT ISSUED BY (STOREMAN)
            $table->string('unit_serviced_by')->nullable();           // (12) UNIT SERVICED BY
            $table->date('unit_serviced_date')->nullable();           // (12) DATE

            // Status flow: draft → submitted → approved/rejected → issued → closed
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'issued', 'closed'])->default('draft');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('approval_remarks')->nullable();
            $table->timestamp('issued_at')->nullable();

            // Removal information (13–20) — completed by aircraft technician, post-issue
            $table->string('serial_no_removed')->nullable();          // (13) SERIAL NO REMOVED
            $table->string('removal_zone')->nullable();               // (14) ZONE
            $table->string('unit_changed_by')->nullable();            // (15) UNIT CHANGED BY (BLOCK CAPITALS)
            $table->text('reason_for_removal')->nullable();           // (16) REASON FOR REMOVAL
            $table->string('removal_signature')->nullable();          // (17) SIGNATURE
            $table->date('removal_date')->nullable();                 // (17) DATE
            $table->string('repair_facility')->nullable();            // (18) REPAIR FACILITY / WORK SHOP
            $table->date('date_sent')->nullable();                    // (19) DATE SENT
            $table->string('repair_order_ref')->nullable();           // (20) REPAIR ORDER (IF APPLICABLE)
            $table->foreignId('removed_serial_id')->nullable()->constrained('part_serials')->nullOnDelete();
            $table->timestamp('removal_completed_at')->nullable();

            $table->date('requisition_date')->nullable();             // (B) DATE
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitions');
    }
};
