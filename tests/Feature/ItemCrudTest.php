<?php

use App\Models\Item;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('redirects guests away from the items index', function () {
    get('/items')->assertRedirect('/login');
});

it('shows only the authenticated user\'s items on the index', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Item::factory()->count(3)->create(['user_id' => $user->id]);
    Item::factory()->count(2)->create(['user_id' => $other->id]);

    actingAs($user)
        ->get('/items')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('items/index')
            ->has('items', 3)
        );
});

it('renders the create page for the auth user', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get('/items/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('items/create'));
});

it('validates the store request', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post('/items', [])
        ->assertSessionHasErrors(['title', 'condition', 'asking_price_cents']);
});

it('creates an item in draft status', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->post('/items', [
        'title' => 'Old couch',
        'description' => 'Comfy',
        'condition' => 'good',
        'asking_price_cents' => 12500,
    ]);

    $item = Item::where('title', 'Old couch')->firstOrFail();
    expect($item->status->value)->toBe('draft');
    expect($item->user_id)->toBe($user->id);
    $response->assertRedirect("/items/{$item->id}/edit");

    actingAs($user)->get("/items/{$item->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->hasFlash('toast.type', 'success')
            ->hasFlash('toast.message', 'Item created.')
        );
});

it('shows an item belonging to the auth user', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get("/items/{$item->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('items/show')
            ->where('item.id', $item->id)
        );
});

it('forbids viewing another user\'s item', function () {
    $user = User::factory()->create();
    $otherItem = Item::factory()->create();

    actingAs($user)
        ->get("/items/{$otherItem->id}")
        ->assertForbidden();
});

it('renders the edit page for the owner', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get("/items/{$item->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('items/edit'));
});

it('updates an owned item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create([
        'user_id' => $user->id,
        'title' => 'Old',
        'asking_price_cents' => 1000,
    ]);

    $response = actingAs($user)
        ->patch("/items/{$item->id}", [
            'title' => 'New',
            'condition' => 'good',
            'asking_price_cents' => 2000,
        ]);

    expect($item->fresh()->title)->toBe('New');
    expect($item->fresh()->asking_price_cents)->toBe(2000);
    $response->assertRedirect("/items/{$item->id}/edit");

    actingAs($user)->get("/items/{$item->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->hasFlash('toast.type', 'success')
            ->hasFlash('toast.message', 'Item updated.')
        );
});

it('forbids editing another user\'s item', function () {
    $user = User::factory()->create();
    $otherItem = Item::factory()->create();

    actingAs($user)
        ->patch("/items/{$otherItem->id}", ['title' => 'hacked', 'condition' => 'good', 'asking_price_cents' => 1])
        ->assertForbidden();
});

it('soft-deletes an owned item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    $response = actingAs($user)->delete("/items/{$item->id}");

    expect(Item::find($item->id))->toBeNull();
    expect(Item::withTrashed()->find($item->id))->not->toBeNull();
    $response->assertRedirect('/items');

    actingAs($user)->get('/items')
        ->assertInertia(fn ($page) => $page
            ->hasFlash('toast.type', 'success')
            ->hasFlash('toast.message', 'Item deleted.')
        );
});

it('forbids deleting another user\'s item', function () {
    $user = User::factory()->create();
    $otherItem = Item::factory()->create();

    actingAs($user)
        ->delete("/items/{$otherItem->id}")
        ->assertForbidden();

    expect(Item::find($otherItem->id))->not->toBeNull();
});
