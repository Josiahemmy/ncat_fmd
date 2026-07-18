<?php

namespace Database\Seeders;

use App\Models\Aircraft;
use App\Models\AircraftType;
use Illuminate\Database\Seeder;

class AircraftSeeder extends Seeder
{
    /** The 26 registrations across 6 types (spec §4, unchanged). Idempotent. */
    public function run(): void
    {
        $fleet = [
            'da-40ng' => ['5N-CZB', '5N-BZK', '5N-BZG', '5N-BZJ', '5N-BZH', '5N-BZF', '5N-BZI'],
            'da-42ng' => ['5N-BZE', '5N-CZA'],
            'tb-9' => ['5N-CAK', '5N-CBA', '5N-CBD', '5N-CAM', '5N-CBK', '5N-CAQ', '5N-CAL', '5N-CAJ'],
            'tb-20' => ['5N-CBT', '5N-CBU', '5N-CBQ', '5N-CBS', '5N-CBR'],
            'tbm-850' => ['5N-BZA'],
            'baron-58' => ['5N-CAZ', '5N-CAT', '5N-CAG'],
        ];

        foreach ($fleet as $slug => $registrations) {
            $type = AircraftType::where('slug', $slug)->first();
            if (! $type) {
                continue;
            }
            foreach ($registrations as $reg) {
                Aircraft::updateOrCreate(
                    ['registration' => $reg],
                    ['aircraft_type_id' => $type->id, 'status' => 'active'],
                );
            }
        }
    }
}
