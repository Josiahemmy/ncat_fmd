<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work Orders — mirrors the department's Work Order Ledger Log (xlsx).
 * Register columns: S/NO, DATE, A/C REG, TYPES OF INSPECTIONS, JOB/WORK ORDER
 * REF, RAISED BY, QUALITY CONTROL CHECK BY, RECORD'S UPDATED BY, FILED BY.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('wo_ref')->unique();          // FMD/{wo_code}/{MM}/{YY}/{serial}
            $table->foreignId('aircraft_id')->constrained('aircraft')->cascadeOnDelete();
            $table->enum('work_type', ['snag', 'scheduled_inspection', 'other']);
            // Preset (50/100/200/1000 HRS, ANNUAL) or free text — scheduled inspections only.
            $table->string('inspection_type')->nullable();
            $table->string('title');                       // ledger "TYPES OF INSPECTIONS" cell
            $table->text('description')->nullable();        // full snag / job detail
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            // Ledger shows hand-written names — free text, with an optional user link.
            $table->string('raised_by');
            $table->foreignId('raised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('qc_checked_by')->nullable();
            $table->string('records_updated_by')->nullable();
            $table->string('filed_by')->nullable();
            $table->date('work_date');                     // ledger DATE (raised)
            $table->timestamp('closed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('work_type');
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
