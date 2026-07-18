<?php

namespace Database\Factories;

use App\Models\Aircraft;
use App\Models\AircraftType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Aircraft>
 */
class AircraftFactory extends Factory
{
    protected $model = Aircraft::class;

    public function definition(): array
    {
        return [
            'registration' => '5N-'.strtoupper($this->faker->unique()->lexify('???')),
            'aircraft_type_id' => AircraftType::factory(),
            'status' => 'active',
        ];
    }
}
