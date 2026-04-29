<?php

namespace Database\Factories;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['Furniture', 'Electronics', 'Kitchen', 'Books']),
            'condition' => fake()->randomElement(ItemCondition::cases())->value,
            'asking_price_cents' => fake()->numberBetween(1000, 50000),
            'floor_price_cents' => fake()->numberBetween(500, 1000),
            'location_in_house' => fake()->randomElement(['Garage shelf A', 'Basement closet', 'Living room']),
            'status' => ItemStatus::Draft->value,
            'notes' => null,
        ];
    }

    public function ready(): self
    {
        return $this->state(['status' => ItemStatus::Ready->value]);
    }

    public function listed(): self
    {
        return $this->state([
            'status' => ItemStatus::Listed->value,
            'kijiji_url' => 'https://www.kijiji.ca/v-'.fake()->uuid(),
            'listed_at' => now()->subDays(2),
        ]);
    }
}
