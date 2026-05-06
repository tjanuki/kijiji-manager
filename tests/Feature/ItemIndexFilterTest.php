<?php

use App\Models\Item;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('returns all items with is_stale flag when filter is off', function () {
    $user = User::factory()->create();

    $stale = Item::factory()->stale()->create(['user_id' => $user->id]);
    $fresh = Item::factory()->listed()->create([
        'user_id' => $user->id,
        'listed_at' => now()->subDays(2),
    ]);

    actingAs($user)
        ->get('/items')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('items/index')
            ->has('items', 2)
            ->where('filters.stale', false)
            ->where('stale_count', 1)
            ->has('items.0.is_stale')
            ->has('items.1.is_stale')
        );
});

it('returns only stale items when ?stale=1 is set', function () {
    $user = User::factory()->create();

    $stale = Item::factory()->stale()->create(['user_id' => $user->id]);
    Item::factory()->listed()->create([
        'user_id' => $user->id,
        'listed_at' => now()->subDays(2),
    ]);

    actingAs($user)
        ->get('/items?stale=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('items/index')
            ->has('items', 1)
            ->where('items.0.id', $stale->id)
            ->where('items.0.is_stale', true)
            ->where('filters.stale', true)
            ->where('stale_count', 1)
        );
});

it('stale_count counts only the auth user\'s items', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Item::factory()->stale()->create(['user_id' => $user->id]);
    Item::factory()->stale()->count(3)->create(['user_id' => $other->id]);

    actingAs($user)
        ->get('/items')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('stale_count', 1));
});
