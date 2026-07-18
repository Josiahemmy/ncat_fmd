<?php

namespace Database\Seeders;

use App\Models\DocumentCounter;
use Illuminate\Database\Seeder;

class DocumentCounterSeeder extends Seeder
{
    /**
     * Per-series running numbers to continue the paper sequences.
     *
     * PROVISIONAL — admin-editable and flagged `confirmed=false`. Values are
     * derived from the ground-truth forms, NOT the prompt's prose estimate:
     *   · Work Order: ledger's latest is FMD/DA40NG/07/26/**1343** → next 1344
     *     (the prompt/spec said ~1338/1339 — forms win; flagged for the dept).
     *   · Requisition sample 1001 → next 1002.
     *   · SIV sample 0293 (4-digit) → next 0294.
     *   · SRV sample 0201 (4-digit) → next 0202.
     *
     * Idempotent — never resets a value the department has since adjusted:
     * only creates rows that don't exist yet.
     */
    public function run(): void
    {
        $counters = [
            [
                'series' => 'work_order', 'label' => 'Work Order Serial', 'prefix' => null,
                'next_number' => 1344, 'padding' => 0, 'confirmed' => false,
                'notes' => 'Continues FMD/{TYPE}/MM/YY/{serial}. Ledger latest = 1343. Confirm exact next number with the department before Phase 3.',
            ],
            [
                'series' => 'requisition', 'label' => 'Requisition No.', 'prefix' => null,
                'next_number' => 1002, 'padding' => 0, 'confirmed' => false,
                'notes' => 'Aircraft Spare Parts Requisition Sheet. Sample 1001. Confirm with department.',
            ],
            [
                'series' => 'srv', 'label' => 'Store Receipt Voucher No.', 'prefix' => null,
                'next_number' => 202, 'padding' => 4, 'confirmed' => false,
                'notes' => 'SRV printed 4-digit. Sample 0201. Confirm with department.',
            ],
            [
                'series' => 'siv', 'label' => 'Store Issue Voucher No.', 'prefix' => null,
                'next_number' => 294, 'padding' => 4, 'confirmed' => false,
                'notes' => 'SIV printed 4-digit. Sample 0293. Confirm with department.',
            ],
        ];

        foreach ($counters as $counter) {
            DocumentCounter::firstOrCreate(
                ['series' => $counter['series']],
                $counter,
            );
        }
    }
}
