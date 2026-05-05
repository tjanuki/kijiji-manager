<?php

use App\Actions\SchedulePickup;
use App\Enums\ItemStatus;
use App\Enums\PickupStatus;
use App\Models\Buyer;
use App\Models\Item;
use App\Models\Pickup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('schedules a pickup and reserves attached items', function () {
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
        notes: 'Saturday around 2pm',
    );

    expect($pickup->status)->toBe(PickupStatus::Scheduled);
    expect($pickup->notes)->toBe('Saturday around 2pm');
    expect($pickup->items)->toHaveCount(2);
    expect($itemA->fresh()->status)->toBe(ItemStatus::Reserved);
    expect($itemB->fresh()->status)->toBe(ItemStatus::Reserved);
    $pivot = $pickup->items->firstWhere('id', $itemA->id)->pivot;
    expect($pivot->agreed_price_cents)->toBe(2500);
});

it('rolls back when one of the items cannot transition', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $listed = Item::factory()->listed()->create(['user_id' => $user->id]);
    $draft = Item::factory()->create(['user_id' => $user->id, 'status' => ItemStatus::Draft]);

    expect(fn () => app(SchedulePickup::class)->handle(
        buyer: $buyer,
        items: [
            ['item_id' => $listed->id, 'agreed_price_cents' => 100],
            ['item_id' => $draft->id, 'agreed_price_cents' => 100],
        ],
    ))->toThrow(InvalidArgumentException::class);

    expect($listed->fresh()->status)->toBe(ItemStatus::Listed);
    expect(Pickup::query()->count())->toBe(0);
    expect(DB::table('pickup_items')->count())->toBe(0);
});

it('creates a pickup via POST /pickups', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $itemA = Item::factory()->listed()->create(['user_id' => $user->id]);
    $itemB = Item::factory()->listed()->create(['user_id' => $user->id]);

    \Pest\Laravel\actingAs($user)
        ->post('/pickups', [
            'buyer_id' => $buyer->id,
            'notes' => 'Front porch Saturday',
            'items' => [
                ['item_id' => $itemA->id, 'agreed_price_cents' => 2500],
                ['item_id' => $itemB->id, 'agreed_price_cents' => 1500],
            ],
        ])
        ->assertRedirect();

    $pickup = Pickup::query()->latest('id')->first();
    expect($pickup->items)->toHaveCount(2);
    expect($itemA->fresh()->status)->toBe(ItemStatus::Reserved);
});

it('rejects a pickup against another user buyer', function () {
    $user = User::factory()->create();
    $foreignBuyer = Buyer::factory()->create();
    $item = Item::factory()->listed()->create(['user_id' => $user->id]);

    \Pest\Laravel\actingAs($user)
        ->post('/pickups', [
            'buyer_id' => $foreignBuyer->id,
            'items' => [['item_id' => $item->id, 'agreed_price_cents' => 100]],
        ])
        ->assertSessionHasErrors('buyer_id');
});

it('rejects a pickup including another user item', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $foreignItem = Item::factory()->listed()->create();

    \Pest\Laravel\actingAs($user)
        ->post('/pickups', [
            'buyer_id' => $buyer->id,
            'items' => [['item_id' => $foreignItem->id, 'agreed_price_cents' => 100]],
        ])
        ->assertSessionHasErrors();
});

it('returns a validation error when scheduling against an item not in listed status', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $draft = Item::factory()->create(['user_id' => $user->id, 'status' => ItemStatus::Draft]);

    \Pest\Laravel\actingAs($user)
        ->post('/pickups', [
            'buyer_id' => $buyer->id,
            'items' => [['item_id' => $draft->id, 'agreed_price_cents' => 100]],
        ])
        ->assertSessionHasErrors('items');

    expect(Pickup::query()->count())->toBe(0);
});

it('keeps the existing items/{item} show page rendering after schedule CTA is added', function () {
    $user = User::factory()->create();
    $item = Item::factory()->listed()->create(['user_id' => $user->id]);
    Buyer::factory()->create(['user_id' => $user->id]);

    \Pest\Laravel\actingAs($user)
        ->get("/items/{$item->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('items/show', false)
            ->has('buyers')
        );
});
