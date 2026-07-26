<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase Orders and Repair Orders (spec §12.5). Two header tables and two
 * line tables rather than one polymorphic pair: the forms share a letterhead
 * and a priority block but nothing else. A PO line orders a quantity against a
 * timeline; an RO line sends one specific serial away for an action. Merging
 * them would mean half the columns are null on every row.
 *
 * See forms_reference/Purchase Order.png and forms_reference/Repair Order.png.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            // Null while draft. The reference is minted at issue so an abandoned
            // draft cannot burn a serial, matching the no-gaps rule everywhere else.
            $table->string('po_number')->nullable()->unique();
            $table->date('order_date');
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('aircraft_type_label')->nullable();       // AIRCRAFT TYPE: ...
            $table->enum('priority', ['aog', 'very_urgent', 'for_inventory'])->nullable();
            $table->enum('status', ['draft', 'issued', 'partially_received', 'received', 'closed', 'cancelled'])
                ->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('vendor_id');
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->unsignedInteger('line_no')->default(1);          // S/NO.
            $table->text('description')->nullable();                 // DESCRIPTION
            // Free text, not a foreign key: POs routinely order parts that are
            // not in the catalogue yet. `part_id` is set when one was picked.
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->string('part_number')->nullable();               // PART NUMBER
            $table->decimal('qty_to_order', 12, 2)->default(0);      // QTY TO ORDER
            $table->decimal('qty_received', 12, 2)->default(0);      // accumulated by SRVs
            $table->string('line_status')->nullable();               // STATUS (NEW / OH / ...)
            $table->unsignedTinyInteger('timeline_month')->nullable(); // TIME LINE
            $table->unsignedSmallInteger('timeline_year')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'line_no']);
        });

        Schema::create('repair_orders', function (Blueprint $table) {
            $table->id();
            $table->string('ro_number')->nullable()->unique();
            $table->date('order_date');
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('aircraft_type_label')->nullable();
            $table->enum('priority', ['aog', 'very_urgent', 'for_inventory'])->nullable();
            $table->enum('status', ['draft', 'issued', 'at_vendor', 'returned', 'closed', 'cancelled'])
                ->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('vendor_id');
        });

        Schema::create('repair_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->unsignedInteger('line_no')->default(1);          // S/N.
            $table->text('description')->nullable();                 // DESCRIPTION
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->string('part_number')->nullable();               // PART NUMBER
            // The tracked serial when one was picked; `serial_no` always carries
            // the printed text so a free-text line still renders on the form.
            $table->foreignId('part_serial_id')->nullable()->constrained('part_serials')->nullOnDelete();
            $table->string('serial_no')->nullable();                 // SERIAL NO.
            // The requisition whose removal section sent this unit away, so the
            // detail page can walk back from the order to the removal.
            $table->foreignId('requisition_id')->nullable()->constrained('requisitions')->nullOnDelete();
            $table->decimal('qty', 12, 2)->default(1);               // QTY
            $table->string('action')->nullable();                    // ACTION (OVERHAUL / REPAIR / ...)
            $table->enum('disposition', ['serviceable', 'scrapped'])->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('disposition_note')->nullable();
            $table->timestamps();

            $table->index(['repair_order_id', 'line_no']);
            $table->index('part_serial_id');
        });

        // Receiving against a PO (spec §12.5). The free-text LPO field stays: it
        // records the paper reference, which is not always one of ours.
        Schema::table('srvs', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->after('lpo_or_petty_cash_ref')
                ->constrained('purchase_orders')->nullOnDelete();
        });

        // Which PO line an SRV line was received against, so quantities
        // accumulate on the right line rather than being matched by part number.
        Schema::table('srv_items', function (Blueprint $table) {
            $table->foreignId('purchase_order_line_id')->nullable()->after('part_id')
                ->constrained('purchase_order_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('srv_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_line_id');
        });

        Schema::table('srvs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
        });

        Schema::dropIfExists('repair_order_lines');
        Schema::dropIfExists('repair_orders');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
