<?php

use App\Support\FuelReceiptSourceBackfill;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 5 tracked-debt closeout: link pre-Phase-5 fuel-receipt movements to
 * their SRV via the polymorphic source columns, matching on the reference
 * (srv_number) they already carry. Forward-only — the reference string is left
 * intact, so this is safe to re-run and needs no destructive down().
 */
return new class extends Migration
{
    public function up(): void
    {
        FuelReceiptSourceBackfill::run();
    }

    public function down(): void
    {
        // No-op: the reference string is preserved, so nothing is lost. We do
        // not strip source links on rollback (they may include Phase-5 records).
    }
};
