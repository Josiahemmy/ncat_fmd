<?php

namespace Database\Factories;

use App\Models\Siv;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Siv>
 */
class SivFactory extends Factory
{
    protected $model = Siv::class;

    public function definition(): array
    {
        return [
            'siv_number' => str_pad((string) $this->faker->unique()->numberBetween(294, 9999), 4, '0', STR_PAD_LEFT),
            'requisition_for' => $this->faker->words(2, true),
            'issued_by' => $this->faker->name(),
            'status' => 'draft',
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => ['status' => 'posted', 'posted_at' => now()]);
    }
}
