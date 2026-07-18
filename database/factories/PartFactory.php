<?php

namespace Database\Factories;

use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    protected $model = Part::class;

    public function definition(): array
    {
        return [
            'part_number' => 'P-'.strtoupper($this->faker->unique()->bothify('??###')),
            'description' => $this->faker->words(3, true),
            'unit_of_issue' => 'EA',
            'min_level' => 0,
            'reorder_level' => 0,
            'is_serialized' => false,
            'has_shelf_life' => false,
            'is_flammable' => false,
            'is_fuel' => false,
        ];
    }

    public function serialized(): static
    {
        return $this->state(['is_serialized' => true]);
    }

    public function shelfLife(): static
    {
        return $this->state(['has_shelf_life' => true]);
    }

    public function flammable(): static
    {
        return $this->state(['is_flammable' => true]);
    }

    public function fuel(): static
    {
        return $this->state(['is_fuel' => true, 'unit_of_issue' => 'L']);
    }
}
