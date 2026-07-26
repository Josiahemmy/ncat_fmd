<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'po_number' => null,
            'order_date' => now()->toDateString(),
            'vendor_id' => Vendor::factory(),
            'aircraft_type_label' => 'DIAMOND DA-40NG/DA-42NG',
            'priority' => 'very_urgent',
            'status' => 'draft',
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'po_number' => 'NCAT/FMD/PO/TS/30/6/'.$this->faker->unique()->numberBetween(300, 999),
            'status' => 'issued',
            'issued_at' => now(),
        ]);
    }
}
