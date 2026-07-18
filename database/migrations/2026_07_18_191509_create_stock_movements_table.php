<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The immutable, append-only ledger. Every stock change is a row here;
     * balances are derived (and mirrored in stock_balances for fast reads).
     * There is deliberately no updated_at concept of "editing" a movement —
     * corrections are posted as counter-movements.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('part_batch_id')->nullable()->constrained('part_batches')->nullOnDelete();
            $table->foreignId('part_serial_id')->nullable()->constrained('part_serials')->nullOnDelete();

            $table->enum('direction', ['in', 'out']);
            $table->decimal('quantity', 14, 2); // always positive; direction carries the sign
            $table->decimal('balance_after', 14, 2); // running balance for this part in this store

            $table->enum('movement_type', [
                'opening_balance', 'receiving', 'certification_transfer', 'transfer',
                'issue', 'fuel_receive', 'fuel_issue', 'adjustment', 'return',
            ]);

            // Polymorphic link to the source document (SRV, SIV, requisition, …).
            $table->nullableMorphs('source');

            $table->foreignId('aircraft_id')->nullable()->constrained('aircraft')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Links the two legs of a transfer/certification so they reconcile.
            $table->uuid('transfer_group')->nullable();
            $table->string('reference')->nullable(); // voucher no / free ref for the tally
            $table->text('remarks')->nullable();

            $table->timestamp('posted_at')->useCurrent();
            $table->timestamps();

            $table->index(['part_id', 'store_id', 'id']); // tally + latest-balance lookups
            $table->index('movement_type');
            $table->index('transfer_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
