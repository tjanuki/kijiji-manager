<?php

use App\Models\Buyer;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->withoutVite();
});

it('lists only the current user buyers', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Buyer::factory()->count(2)->create(['user_id' => $user->id]);
    Buyer::factory()->create(['user_id' => $other->id]);

    actingAs($user)
        ->get('/buyers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyers/index', false)
            ->has('buyers', 2)
        );
});

it('creates a buyer scoped to the current user', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post('/buyers', [
            'display_name' => 'Sam',
            'phone' => '555-0100',
        ])
        ->assertRedirect();

    expect(Buyer::query()->where('user_id', $user->id)->where('display_name', 'Sam')->exists())->toBeTrue();
});

it('shows a buyer with their inquiries', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get("/buyers/{$buyer->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyers/show', false)
            ->where('buyer.id', $buyer->id)
            ->has('inquiries')
        );
});

it('forbids viewing another user buyer', function () {
    $user = User::factory()->create();
    $other = Buyer::factory()->create();

    actingAs($user)
        ->get("/buyers/{$other->id}")
        ->assertForbidden();
});

it('updates a buyer', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id, 'display_name' => 'Old']);

    actingAs($user)
        ->patch("/buyers/{$buyer->id}", ['display_name' => 'New'])
        ->assertRedirect();

    expect($buyer->fresh()->display_name)->toBe('New');
});

it('forbids updating another user buyer', function () {
    $user = User::factory()->create();
    $other = Buyer::factory()->create();

    actingAs($user)
        ->patch("/buyers/{$other->id}", ['display_name' => 'Hijack'])
        ->assertForbidden();
});
