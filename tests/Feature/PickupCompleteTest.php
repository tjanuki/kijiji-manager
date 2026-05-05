<?php

use App\Actions\CompletePickup;
use App\Actions\SchedulePickup;
use App\Enums\ItemStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PickupStatus;
use App\Models\Buyer;
use App\Models\Item;
use App\Models\Pickup;
use App\Models\User;

it('completes a pickup, marks payment received, and sells all items', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $itemA = Item::factory()->listed()->create(['user_id' => $user->id]);
    $itemB = Item::factory()->listed()->create(['user_id' => $user->id]);

    $pickup = app(SchedulePickup::class)->handle(
        buyer: $buyer,
        items: [
            ['item_id' => $itemA->id, 'agreed_price_cents' => 2500],
            ['item_id' => $itemB->id, 'agreed_price_cents' => 1500],
        ],
    );

    app(CompletePickup::class)->handle($pickup, PaymentMethod::Cash);

    $pickup->refresh();
    expect($pickup->status)->toBe(PickupStatus::Completed);
    expect($pickup->payment_status)->toBe(PaymentStatus::Received);
    expect($pickup->payment_method)->toBe(PaymentMethod::Cash);
    expect($pickup->completed_at)->not->toBeNull();

    expect($itemA->fresh()->status)->toBe(ItemStatus::Sold);
    expect($itemA->fresh()->sold_at)->not->toBeNull();
    expect($itemB->fresh()->status)->toBe(ItemStatus::Sold);
});

it('rejects completing a pickup that is not scheduled', function () {
    $pickup = Pickup::factory()->completed()->create();

    expect(fn () => app(CompletePickup::class)->handle($pickup, PaymentMethod::Cash))
        ->toThrow(InvalidArgumentException::class);
});

it('completes a pickup via POST /pickups/{pickup}/complete', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $item = Item::factory()->listed()->create(['user_id' => $user->id]);

    $pickup = app(SchedulePickup::class)->handle(
        buyer: $buyer,
        items: [['item_id' => $item->id, 'agreed_price_cents' => 2000]],
    );

    \Pest\Laravel\actingAs($user)
        ->post("/pickups/{$pickup->id}/complete", ['payment_method' => 'cash'])
        ->assertRedirect();

    expect($pickup->fresh()->status)->toBe(PickupStatus::Completed);
    expect($item->fresh()->status)->toBe(ItemStatus::Sold);
});

it('forbids completing another user pickup', function () {
    $user = User::factory()->create();
    $foreignPickup = Pickup::factory()->create();

    \Pest\Laravel\actingAs($user)
        ->post("/pickups/{$foreignPickup->id}/complete", ['payment_method' => 'cash'])
        ->assertForbidden();
});

it('returns a validation error when completing an already-completed pickup', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $pickup = Pickup::factory()->completed()->create(['buyer_id' => $buyer->id]);

    \Pest\Laravel\actingAs($user)
        ->post("/pickups/{$pickup->id}/complete", ['payment_method' => 'cash'])
        ->assertSessionHasErrors('payment_method');
});
