<?php

use App\Actions\SchedulePickup;
use App\Models\Buyer;
use App\Models\Item;
use App\Models\Pickup;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->withoutVite();
});

it('lists scheduled pickups for the current user only', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $item = Item::factory()->listed()->create(['user_id' => $user->id]);

    app(SchedulePickup::class)->handle(
        buyer: $buyer,
        items: [['item_id' => $item->id, 'agreed_price_cents' => 1000]],
    );

    // Pickup that belongs to another user's buyer — must not appear
    $foreignPickup = Pickup::factory()->create();
    expect($foreignPickup->buyer->user_id)->not->toBe($user->id);

    actingAs($user)
        ->get('/pickups')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pickups/index', false)
            ->has('pickups', 1)
        );
});

it('shows a pickup the user owns', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $pickup = Pickup::factory()->create(['buyer_id' => $buyer->id]);

    actingAs($user)
        ->get("/pickups/{$pickup->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pickups/show', false)
            ->where('pickup.id', $pickup->id)
        );
});

it('forbids viewing another user pickup', function () {
    $user = User::factory()->create();
    $foreignPickup = Pickup::factory()->create();

    actingAs($user)
        ->get("/pickups/{$foreignPickup->id}")
        ->assertForbidden();
});
