<?php

namespace Database\Factories;

use App\Models\Aircraft;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        return [
            // Placeholder ref for tests that don't exercise the numbering service;
            // real creation reserves via DocumentNumberService::reserveWorkOrder().
            'wo_ref' => 'FMD/TEST/07/26/'.$this->faker->unique()->numberBetween(1000, 9999),
            'aircraft_id' => Aircraft::factory(),
            'work_type' => 'snag',
            'inspection_type' => null,
            'title' => 'SNAG: '.$this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'status' => 'open',
            'raised_by' => $this->faker->name(),
            'work_date' => now()->toDateString(),
        ];
    }

    public function inspection(): static
    {
        return $this->state(fn () => [
            'work_type' => 'scheduled_inspection',
            'inspection_type' => '100 HRS',
            'title' => '100 HRS INSPECTION',
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed', 'closed_at' => now()]);
    }
}
