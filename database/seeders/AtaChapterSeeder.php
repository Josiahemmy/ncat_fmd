<?php

namespace Database\Seeders;

use App\Models\AtaChapter;
use Illuminate\Database\Seeder;

class AtaChapterSeeder extends Seeder
{
    /** Standard ATA 100 chapters commonly used in GA maintenance. Idempotent. */
    public function run(): void
    {
        $chapters = [
            '00' => 'General',
            '05' => 'Time Limits / Maintenance Checks',
            '06' => 'Dimensions & Areas',
            '07' => 'Lifting & Shoring',
            '08' => 'Levelling & Weighing',
            '09' => 'Towing & Taxiing',
            '10' => 'Parking, Mooring, Storage & Return to Service',
            '11' => 'Placards & Markings',
            '12' => 'Servicing',
            '20' => 'Standard Practices - Airframe',
            '21' => 'Air Conditioning',
            '22' => 'Auto Flight',
            '23' => 'Communications',
            '24' => 'Electrical Power',
            '25' => 'Equipment / Furnishings',
            '26' => 'Fire Protection',
            '27' => 'Flight Controls',
            '28' => 'Fuel',
            '29' => 'Hydraulic Power',
            '30' => 'Ice & Rain Protection',
            '31' => 'Indicating / Recording Systems',
            '32' => 'Landing Gear',
            '33' => 'Lights',
            '34' => 'Navigation',
            '35' => 'Oxygen',
            '36' => 'Pneumatic',
            '37' => 'Vacuum',
            '38' => 'Water / Waste',
            '39' => 'Electrical / Electronic Panels & Components',
            '49' => 'Airborne Auxiliary Power',
            '51' => 'Standard Practices & Structures',
            '52' => 'Doors',
            '53' => 'Fuselage',
            '54' => 'Nacelles / Pylons',
            '55' => 'Stabilizers',
            '56' => 'Windows',
            '57' => 'Wings',
            '61' => 'Propellers / Propulsion',
            '71' => 'Power Plant',
            '72' => 'Engine',
            '73' => 'Engine Fuel & Control',
            '74' => 'Ignition',
            '75' => 'Air (Engine)',
            '76' => 'Engine Controls',
            '77' => 'Engine Indicating',
            '78' => 'Exhaust',
            '79' => 'Oil',
            '80' => 'Starting',
            '91' => 'Charts',
            '92' => 'Electrical System Installation',
        ];

        foreach ($chapters as $number => $title) {
            AtaChapter::updateOrCreate(
                ['chapter_number' => $number],
                ['title' => $title],
            );
        }
    }
}
