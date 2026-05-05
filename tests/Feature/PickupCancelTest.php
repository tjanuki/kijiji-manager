<?php

use App\Actions\CancelPickup;
use App\Actions\SchedulePickup;
use App\Enums\ItemStatus;
use App\Enums\PickupStatus;
use App\Models\Buyer;
use App\Models\Item;
use App\Models\Pickup;
use App\Models\User;

it('cancels a pickup and returns items to listed', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $item = Item::factory()->listed()->create(['user_id' => $user->id]);
    $originalListedAt = $item->listed_at;

    $pickup = app(SchedulePickup::class)->handle(
        buyer: $buyer,
        items: [['item_id' => $item->id, 'agreed_price_cents' => 1000]],
    );

    app(CancelPickup::class)->handle($pickup, PickupStatus::Cancelled);

    $pickup->refresh();
    expect($pickup->status)->toBe(PickupStatus::Cancelled);
    expect($pickup->cancelled_at)->not->toBeNull();

    $fresh = $item->fresh();
    expect($fresh->status)->toBe(ItemStatus::Listed);
    expect($fresh->listed_at->equalTo($originalListedAt))->toBeTrue();
});

it('marks no_show and still returns items to listed', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $item = Item::factory()->listed()->create(['user_id' => $user->id]);

    $pickup = app(SchedulePickup::class)->handle(
        buyer: $buyer,
        items: [['item_id' => $item->id, 'agreed_price_cents' => 1000]],
    );

    app(CancelPickup::class)->handle($pickup, PickupStatus::NoShow);

    expect($pickup->fresh()->status)->toBe(PickupStatus::NoShow);
    expect($item->fresh()->status)->toBe(ItemStatus::Listed);
});

it('rejects a non-cancelling status', function () {
    $pickup = Pickup::factory()->create();

    expect(fn () => app(CancelPickup::class)->handle($pickup, PickupStatus::Completed))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects cancelling a pickup that is not scheduled', function () {
    $pickup = Pickup::factory()->completed()->create();

    expect(fn () => app(CancelPickup::class)->handle($pickup, PickupStatus::Cancelled))
        ->toThrow(InvalidArgumentException::class);
});

it('cancels a pickup via POST /pickups/{pickup}/cancel', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $item = Item::factory()->listed()->create(['user_id' => $user->id]);

    $pickup = app(SchedulePickup::class)->handle(
        buyer: $buyer,
        items: [['item_id' => $item->id, 'agreed_price_cents' => 1000]],
    );

    \Pest\Laravel\actingAs($user)
        ->post("/pickups/{$pickup->id}/cancel", ['to' => 'no_show'])
        ->assertRedirect();

    expect($pickup->fresh()->status)->toBe(PickupStatus::NoShow);
    expect($item->fresh()->status)->toBe(ItemStatus::Listed);
});
