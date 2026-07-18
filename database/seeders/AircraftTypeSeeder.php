<?php

namespace Database\Seeders;

use App\Models\AircraftType;
use Illuminate\Database\Seeder;

class AircraftTypeSeeder extends Seeder
{
    /**
     * The 6 fleet types (spec §4). `wo_code` is the canonical token for Work
     * Order refs FMD/{code}/MM/YY/serial — normalising the legacy ledger's
     * inconsistent tokens (TB-9 vs TB-09). Idempotent.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'BARON-58', 'slug' => 'baron-58', 'wo_code' => 'BARON58', 'sort_order' => 1],
            ['name' => 'DA-40NG', 'slug' => 'da-40ng', 'wo_code' => 'DA40NG', 'sort_order' => 2],
            ['name' => 'DA-42NG', 'slug' => 'da-42ng', 'wo_code' => 'DA42NG', 'sort_order' => 3],
            ['name' => 'TB-9', 'slug' => 'tb-9', 'wo_code' => 'TB-9', 'sort_order' => 4],
            ['name' => 'TB-20', 'slug' => 'tb-20', 'wo_code' => 'TB-20', 'sort_order' => 5],
            ['name' => 'TBM-850', 'slug' => 'tbm-850', 'wo_code' => 'TBM850', 'sort_order' => 6],
        ];

        foreach ($types as $type) {
            AircraftType::updateOrCreate(
                ['slug' => $type['slug']],
                $type + ['image_path' => "/aircraft/{$type['slug']}.webp"],
            );
        }
    }
}
