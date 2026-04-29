<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemPhoto>
 */
class ItemPhotoFactory extends Factory
{
    protected $model = ItemPhoto::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'path' => 'items/test/'.fake()->uuid().'.jpg',
            'thumbnail_path' => null,
            'position' => 0,
            'is_primary' => false,
        ];
    }

    public function primary(): self
    {
        return $this->state(['is_primary' => true]);
    }
}
