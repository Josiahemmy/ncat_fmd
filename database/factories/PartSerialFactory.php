<?php

namespace Database\Factories;

use App\Models\Aircraft;
use App\Models\Part;
use App\Models\PartSerial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartSerial>
 */
class PartSerialFactory extends Factory
{
    protected $model = PartSerial::class;

    public function definition(): array
    {
        return [
            'part_id' => Part::factory()->serialized(),
            'serial_number' => strtoupper($this->faker->unique()->bothify('SN-#####')),
            'status' => 'in_store',
        ];
    }

    public function installed(?Aircraft $aircraft = null): static
    {
        return $this->state(fn () => [
            'status' => 'installed',
            'current_aircraft_id' => $aircraft?->id ?? Aircraft::factory(),
            'current_store_id' => null,
        ]);
    }
}
