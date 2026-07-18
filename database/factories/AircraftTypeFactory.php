<?php

namespace Database\Factories;

use App\Models\AircraftType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AircraftType>
 */
class AircraftTypeFactory extends Factory
{
    protected $model = AircraftType::class;

    public function definition(): array
    {
        $name = strtoupper($this->faker->unique()->bothify('AC-###'));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'wo_code' => str_replace('-', '', $name),
            'sort_order' => 0,
        ];
    }
}
