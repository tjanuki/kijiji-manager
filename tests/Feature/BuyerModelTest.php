<?php

use App\Models\Buyer;
use App\Models\User;

it('creates a buyer scoped to a user via the factory', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);

    expect($buyer->user_id)->toBe($user->id);
    expect($buyer->user->is($user))->toBeTrue();
});

it('soft-deletes a buyer', function () {
    $buyer = Buyer::factory()->create();
    $buyer->delete();

    expect(Buyer::query()->find($buyer->id))->toBeNull();
    expect(Buyer::withTrashed()->find($buyer->id))->not->toBeNull();
});
