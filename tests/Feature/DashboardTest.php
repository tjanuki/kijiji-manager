<?php

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('shows status counts and recent items for the auth user', function () {
    $user = User::factory()->create();

    Item::factory()->count(2)->create(['user_id' => $user->id, 'status' => ItemStatus::Draft]);
    Item::factory()->count(1)->create(['user_id' => $user->id, 'status' => ItemStatus::Listed]);
    Item::factory()->create();   // other user — must not be counted

    actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('counts.draft', 2)
            ->where('counts.listed', 1)
            ->has('recentItems', 3)
        );
});
