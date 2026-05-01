<?php

use App\Enums\ItemCondition;
use App\Models\Item;
use App\Models\User;
use App\Models\UserSetting;

use function Pest\Laravel\actingAs;

it('passes a rendered listing_draft prop on item show', function () {
    $user = User::factory()->create();
    UserSetting::factory()->create([
        'user_id' => $user->id,
        'snippets' => ['pickup' => 'Side door.', 'payment' => 'Cash.'],
    ]);
    $item = Item::factory()->create([
        'user_id' => $user->id,
        'title' => 'Klippan loveseat',
        'category' => 'Furniture',
        'description' => 'Good shape.',
        'condition' => ItemCondition::Good,
    ]);

    actingAs($user)
        ->get("/items/{$item->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('items/show')
            ->where('listing_draft.title', 'Furniture - Klippan loveseat - Good')
            ->where('listing_draft.description', "Good shape.\n\nCondition: Good\n\nPickup: Side door.\n\nPayment: Cash.")
        );
});

it('does not leak user.settings into the item prop', function () {
    $user = User::factory()->create();
    UserSetting::factory()->create([
        'user_id' => $user->id,
        'snippets' => ['pickup' => 'Secret pickup info', 'payment' => 'Secret payment info'],
    ]);
    $item = Item::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get("/items/{$item->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('items/show')
            ->missing('item.user')
        );
});
