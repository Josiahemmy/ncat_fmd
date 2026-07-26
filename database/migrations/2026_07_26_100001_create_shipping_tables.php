<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping (spec §12.6). A shipment tracks one consignment from a vendor to
 * NCAT. Its history is an append-only event log, deliberately shaped like the
 * stock ledger: a posted event is never edited or deleted, a mistake is
 * corrected by recording a superseding event. `current_status` on the header
 * is a denormalised copy of the latest event so the list can filter and sort
 * without a correlated subquery per row.
 *
 * The source document is a morph rather than two nullable foreign keys: a
 * shipment carries either a purchase order, a repair order, or nothing at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            // Minted at creation from its own counter series. A shipment has no
            // draft state, so there is no reference to withhold.
            $table->string('reference')->unique();
            $table->foreignId('vendor_id')->constrained('vendors');
            // Purchase order, repair order, or standalone.
            $table->nullableMorphs('source');
            $table->text('description')->nullable();
            $table->string('carrier')->nullable();
            $table->string('awb_reference')->nullable();
            $table->date('expected_arrival_date')->nullable();
            // Denormalised from the latest event (see ShipmentService::refresh).
            $table->string('current_status')->nullable();
            $table->date('current_status_date')->nullable();
            // Set by the event that marks arrival at NCAT. Until it is set the
            // shipment can go overdue; once set it never can.
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('vendor_id');
            $table->index('current_status');
            $table->index('expected_arrival_date');
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            // Free text. The admin list suggests values, it does not constrain them.
            $table->string('status');
            $table->date('event_date');
            $table->text('note')->nullable();
            // Marks "this event is the consignment landing at NCAT". Kept as an
            // explicit flag rather than inferred from the status text, so a
            // renamed or free-typed status cannot silently stop closing shipments.
            $table->boolean('is_arrival')->default(false);
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Deliberately created_at only. There is no update path to stamp.
            $table->timestamp('created_at')->useCurrent();

            // Timeline ordering: by event date, then by insertion order so
            // several events recorded on the same day keep the sequence they
            // were entered in.
            $table->index(['shipment_id', 'event_date', 'id']);
        });

        // Which SRV(s) fulfilled a shipment. One column rather than a pivot: an
        // SRV belongs to at most one shipment, a shipment can produce several.
        Schema::table('srvs', function (Blueprint $table) {
            $table->foreignId('shipment_id')->nullable()->after('purchase_order_id')
                ->constrained('shipments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('srvs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipment_id');
        });

        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipments');
    }
};
