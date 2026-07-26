<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * The department's four physical stores (spec §4, v1.1), plus the two
     * holding locations the loan engine posts through (spec §12.7). Idempotent.
     *
     * The loan stores are locations, not rooms. They exist so lending and
     * borrowing are movements in the ledger rather than stock vanishing and
     * reappearing. "Loaned In" carries owned = false, which is the single flag
     * every value and stock-summary query filters on.
     */
    public function run(): void
    {
        $stores = [
            ['name' => 'Quarantine Store', 'slug' => 'quarantine', 'type' => 'quarantine', 'sort_order' => 1,
                'description' => 'Transit intake for newly arrived parts awaiting certification. Stock here is not issuable.'],
            ['name' => 'Bonded Store', 'slug' => 'bonded', 'type' => 'bonded', 'sort_order' => 2,
                'description' => 'Main serviceable store for certified, issuable stock.'],
            ['name' => 'Dope Store', 'slug' => 'dope', 'type' => 'dope', 'sort_order' => 3,
                'description' => 'Flammables store for certified flammable stock.'],
            ['name' => 'Fuel Dump', 'slug' => 'fuel-dump', 'type' => 'fuel', 'sort_order' => 4,
                'description' => 'Aviation fuel (bulk, litres). Received and issued without certification.'],
            ['name' => 'On Loan (Out)', 'slug' => 'on-loan-out', 'type' => Store::LOAN_OUT, 'owned' => true, 'sort_order' => 5,
                'description' => 'NCAT stock currently held by an external party. Still NCAT property, so it still counts '
                    .'towards stock value, but it is not in a physical store and cannot be issued from here.'],
            ['name' => 'Loaned In (Not NCAT Property)', 'slug' => 'loaned-in', 'type' => Store::LOAN_IN, 'owned' => false, 'sort_order' => 6,
                'description' => 'Stock borrowed from another organisation. Excluded from stock value, stock summary and '
                    .'reorder alerts. It can be issued to an aircraft, and is marked as loaned property when it is.'],
        ];

        foreach ($stores as $store) {
            Store::updateOrCreate(
                ['slug' => $store['slug']],
                $store + ['is_active' => true, 'owned' => true],
            );
        }
    }
}
