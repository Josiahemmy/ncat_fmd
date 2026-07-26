<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'name' => strtoupper($this->faker->unique()->company()),
            'type' => 'supplier',
            'address' => implode("\n", [
                strtoupper($this->faker->company()).',',
                $this->faker->streetAddress().',',
                $this->faker->city().',',
                strtoupper($this->faker->country()).'.',
            ]),
            'country' => $this->faker->country(),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'contact_person' => $this->faker->name(),
            'is_active' => true,
        ];
    }

    public function repairOrganization(): static
    {
        return $this->state(['type' => 'repair_organization']);
    }

    public function both(): static
    {
        return $this->state(['type' => 'both']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function demo(): static
    {
        return $this->state(['is_demo' => true]);
    }
}
