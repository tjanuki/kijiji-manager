<?php

use App\Models\Item;
use App\Models\ItemPhoto;

it('creates an item photo via factory linked to an item', function () {
    $photo = ItemPhoto::factory()->create();

    expect($photo->item)->toBeInstanceOf(Item::class);
    expect($photo->path)->toBeString();
    expect($photo->position)->toBeGreaterThanOrEqual(0);
});

it('exposes photos via item relationship in position order', function () {
    $item = Item::factory()->create();
    ItemPhoto::factory()->create(['item_id' => $item->id, 'position' => 2]);
    ItemPhoto::factory()->create(['item_id' => $item->id, 'position' => 0]);
    ItemPhoto::factory()->create(['item_id' => $item->id, 'position' => 1]);

    expect($item->photos()->pluck('position')->all())->toBe([0, 1, 2]);
});
