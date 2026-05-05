<?php

namespace Database\Factories;

use App\Enums\InquiryStatus;
use App\Models\Buyer;
use App\Models\Inquiry;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inquiry>
 */
class InquiryFactory extends Factory
{
    protected $model = Inquiry::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'buyer_id' => Buyer::factory(),
            'message_excerpt' => fake()->sentence(8),
            'status' => InquiryStatus::New->value,
            'offered_price_cents' => fake()->optional()->numberBetween(500, 30000),
            'received_at' => now()->subHours(fake()->numberBetween(1, 72)),
            'last_contact_at' => now()->subHours(fake()->numberBetween(0, 24)),
            'negotiation_log' => null,
        ];
    }
}
