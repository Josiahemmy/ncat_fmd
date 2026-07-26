<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loans in both directions (spec §12.7). One table rather than two: the
 * counterparty, the item, the due date and the return are the same shape in
 * both directions, and every list and report wants them together. What differs
 * is which columns apply, which the direction discriminates.
 *
 * `status` is on_loan | returned | written_off. Overdue is NOT stored: it is
 * derived from due_date against today, so a loan cannot sit in the database
 * claiming to be current because no scheduled job ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->enum('direction', ['out', 'in']);

            // The counterparty. A known vendor when there is one; the free-text
            // pair covers organisations that are not suppliers (another college,
            // an operator, a visiting maintenance team).
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('party_name')->nullable();
            $table->string('party_contact')->nullable();

            // The item. Inbound loans may reference a part that is not in the
            // catalogue at all, which is what item_description carries.
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->foreignId('part_serial_id')->nullable()->constrained('part_serials')->nullOnDelete();
            $table->foreignId('part_batch_id')->nullable()->constrained('part_batches')->nullOnDelete();
            $table->text('item_description')->nullable();
            $table->string('serial_text')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);

            // Outbound only: the store the units left. Same rule as issuing, so
            // Bonded or Dope. The return posts back to this store, not to
            // whichever store the user happens to pick months later.
            $table->foreignId('from_store_id')->nullable()->constrained('stores')->nullOnDelete();

            // loaned_at for an outbound loan, received_at for an inbound one.
            // One column because every list, report and overdue check treats it
            // identically; the UI labels it per direction.
            $table->date('started_at');
            $table->date('due_date')->nullable();

            $table->enum('status', ['on_loan', 'returned', 'written_off'])->default('on_loan');
            $table->date('returned_at')->nullable();
            $table->text('return_condition')->nullable();

            $table->timestamp('written_off_at')->nullable();
            $table->foreignId('written_off_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('write_off_reason')->nullable();

            // Inbound only: the aircraft a borrowed unit is currently fitted to,
            // so the parts-on-aircraft view can mark it as loaned property.
            $table->foreignId('installed_aircraft_id')->nullable()->constrained('aircraft')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['direction', 'status']);
            $table->index('due_date');
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
