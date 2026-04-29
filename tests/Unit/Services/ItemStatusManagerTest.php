<?php

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Services\ItemStatusManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('transitions draft to ready and persists', function () {
    $item = Item::factory()->create(['status' => ItemStatus::Draft]);

    app(ItemStatusManager::class)->transition($item, ItemStatus::Ready);

    expect($item->fresh()->status)->toBe(ItemStatus::Ready);
});

it('sets listed_at when transitioning to listed', function () {
    $item = Item::factory()->ready()->create();

    app(ItemStatusManager::class)->transition($item, ItemStatus::Listed, [
        'kijiji_url' => 'https://www.kijiji.ca/v-test',
    ]);

    $fresh = $item->fresh();
    expect($fresh->status)->toBe(ItemStatus::Listed);
    expect($fresh->kijiji_url)->toBe('https://www.kijiji.ca/v-test');
    expect($fresh->listed_at)->not->toBeNull();
});

it('sets sold_at when transitioning to sold', function () {
    $item = Item::factory()->listed()->create();

    app(ItemStatusManager::class)->transition($item, ItemStatus::Sold);

    expect($item->fresh()->sold_at)->not->toBeNull();
});

it('sets withdrawn_at when transitioning to withdrawn', function () {
    $item = Item::factory()->ready()->create();

    app(ItemStatusManager::class)->transition($item, ItemStatus::Withdrawn);

    expect($item->fresh()->withdrawn_at)->not->toBeNull();
});

it('throws when transitioning along a disallowed edge', function () {
    $item = Item::factory()->create(['status' => ItemStatus::Draft]);

    app(ItemStatusManager::class)->transition($item, ItemStatus::Sold);
})->throws(InvalidArgumentException::class);

it('requires kijiji_url when transitioning to listed', function () {
    $item = Item::factory()->ready()->create();

    app(ItemStatusManager::class)->transition($item, ItemStatus::Listed);
})->throws(InvalidArgumentException::class, 'kijiji_url');
