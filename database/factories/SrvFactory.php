<?php

namespace Database\Factories;

use App\Models\Srv;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Srv>
 */
class SrvFactory extends Factory
{
    protected $model = Srv::class;

    public function definition(): array
    {
        return [
            'srv_number' => str_pad((string) $this->faker->unique()->numberBetween(200, 9999), 4, '0', STR_PAD_LEFT),
            'srv_date' => now()->toDateString(),
            // Requires StoreSeeder in the test (there is no Store factory).
            'destination_store_id' => fn () => Store::where('type', 'quarantine')->value('id'),
            'supplier' => $this->faker->company(),
            'status' => 'draft',
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => ['status' => 'posted', 'posted_at' => now()]);
    }
}
