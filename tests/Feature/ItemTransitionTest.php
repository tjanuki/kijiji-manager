<?php

use App\Models\Item;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('transitions an owned item along an allowed edge', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->post("/items/{$item->id}/transition", ['to' => 'ready'])
        ->assertRedirect();

    expect($item->fresh()->status->value)->toBe('ready');
});

it('rejects invalid transitions', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->post("/items/{$item->id}/transition", ['to' => 'sold'])
        ->assertSessionHasErrors('to');
});

it('requires kijiji_url when transitioning to listed', function () {
    $user = User::factory()->create();
    $item = Item::factory()->ready()->create(['user_id' => $user->id]);

    actingAs($user)
        ->post("/items/{$item->id}/transition", ['to' => 'listed'])
        ->assertSessionHasErrors('kijiji_url');
});

it('transitions ready -> listed with kijiji_url', function () {
    $user = User::factory()->create();
    $item = Item::factory()->ready()->create(['user_id' => $user->id]);

    actingAs($user)
        ->post("/items/{$item->id}/transition", [
            'to' => 'listed',
            'kijiji_url' => 'https://www.kijiji.ca/v-test',
        ])
        ->assertRedirect();

    $fresh = $item->fresh();
    expect($fresh->status->value)->toBe('listed');
    expect($fresh->kijiji_url)->toBe('https://www.kijiji.ca/v-test');
    expect($fresh->listed_at)->not->toBeNull();
});

it('forbids transitioning another user\'s item', function () {
    $user = User::factory()->create();
    $other = Item::factory()->create();

    actingAs($user)
        ->post("/items/{$other->id}/transition", ['to' => 'ready'])
        ->assertForbidden();
});
