<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PickupStatus;
use App\Models\Buyer;
use App\Models\Pickup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pickup>
 */
class PickupFactory extends Factory
{
    protected $model = Pickup::class;

    public function definition(): array
    {
        return [
            'buyer_id' => Buyer::factory(),
            'status' => PickupStatus::Scheduled->value,
            'notes' => fake()->optional()->sentence(),
            'payment_method' => null,
            'payment_status' => PaymentStatus::Pending->value,
        ];
    }

    public function completed(): self
    {
        return $this->state([
            'status' => PickupStatus::Completed->value,
            'payment_method' => PaymentMethod::Cash->value,
            'payment_status' => PaymentStatus::Received->value,
            'completed_at' => now(),
        ]);
    }
}
