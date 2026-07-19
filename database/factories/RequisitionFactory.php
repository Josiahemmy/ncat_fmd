<?php

namespace Database\Factories;

use App\Models\Requisition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requisition>
 */
class RequisitionFactory extends Factory
{
    protected $model = Requisition::class;

    public function definition(): array
    {
        return [
            'requisition_no' => (string) $this->faker->unique()->numberBetween(1000, 9999),
            'full_description' => $this->faker->words(3, true),
            'part_no' => 'P-'.strtoupper($this->faker->bothify('??###')),
            'status' => 'draft',
            'requisition_date' => now()->toDateString(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => 'submitted']);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved', 'approved_at' => now()]);
    }

    public function issued(): static
    {
        return $this->state(fn () => ['status' => 'issued', 'approved_at' => now(), 'issued_at' => now()]);
    }
}
