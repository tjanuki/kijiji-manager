<?php

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\User;

it('creates an item via factory belonging to a user', function () {
    $item = Item::factory()->create();

    expect($item->user)->toBeInstanceOf(User::class);
    expect($item->status)->toBe(ItemStatus::Draft);
    expect($item->condition)->toBeInstanceOf(ItemCondition::class);
    expect($item->asking_price_cents)->toBeGreaterThan(0);
});

it('soft-deletes items', function () {
    $item = Item::factory()->create();

    $item->delete();

    expect(Item::find($item->id))->toBeNull();
    expect(Item::withTrashed()->find($item->id))->not->toBeNull();
});

it('exposes a ready factory state', function () {
    $item = Item::factory()->ready()->create();

    expect($item->status)->toBe(ItemStatus::Ready);
});
