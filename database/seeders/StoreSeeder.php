<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /** The department's four physical stores (spec §4, v1.1). Idempotent. */
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
        ];

        foreach ($stores as $store) {
            Store::updateOrCreate(
                ['slug' => $store['slug']],
                $store + ['is_active' => true],
            );
        }
    }
}
