<?php

use App\Enums\PaymentStatus;
use App\Enums\PickupStatus;
use App\Models\Buyer;
use App\Models\Item;
use App\Models\Pickup;
use App\Models\User;

it('belongs to a buyer and pivots to items with agreed prices', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $itemA = Item::factory()->listed()->create(['user_id' => $user->id]);
    $itemB = Item::factory()->listed()->create(['user_id' => $user->id]);

    $pickup = Pickup::factory()->create(['buyer_id' => $buyer->id]);
    $pickup->items()->attach([
        $itemA->id => ['agreed_price_cents' => 2500],
        $itemB->id => ['agreed_price_cents' => 4000],
    ]);

    expect($pickup->buyer->id)->toBe($buyer->id);
    expect($pickup->items)->toHaveCount(2);
    expect($pickup->items->first()->pivot->agreed_price_cents)->toBeInt();
    expect($pickup->status)->toBe(PickupStatus::Scheduled);
    expect($pickup->payment_status)->toBe(PaymentStatus::Pending);
});

it('exposes pickups from the item side', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $item = Item::factory()->listed()->create(['user_id' => $user->id]);
    $pickup = Pickup::factory()->create(['buyer_id' => $buyer->id]);
    $pickup->items()->attach($item->id, ['agreed_price_cents' => 1000]);

    expect($item->fresh()->pickups)->toHaveCount(1);
    expect($item->fresh()->pickups->first()->id)->toBe($pickup->id);
});

it('soft deletes', function () {
    $pickup = Pickup::factory()->create();

    $pickup->delete();

    expect(Pickup::query()->find($pickup->id))->toBeNull();
    expect(Pickup::withTrashed()->find($pickup->id))->not->toBeNull();
});
