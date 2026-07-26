<?php

namespace Database\Factories;

use App\Models\RepairOrder;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairOrder>
 */
class RepairOrderFactory extends Factory
{
    protected $model = RepairOrder::class;

    public function definition(): array
    {
        return [
            'ro_number' => null,
            'order_date' => now()->toDateString(),
            'vendor_id' => Vendor::factory()->repairOrganization(),
            'aircraft_type_label' => 'DIAMOND DA40G',
            'priority' => 'very_urgent',
            'status' => 'draft',
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'ro_number' => 'NCAT/FMD/RO/TS/03/'.$this->faker->unique()->numberBetween(200, 999),
            'status' => 'issued',
            'issued_at' => now(),
        ]);
    }
}
