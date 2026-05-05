<?php

use App\Enums\InquiryStatus;
use App\Models\Buyer;
use App\Models\Inquiry;
use App\Models\Item;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('creates an inquiry against an existing buyer', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->post("/items/{$item->id}/inquiries", [
            'buyer_id' => $buyer->id,
            'message_excerpt' => 'Is it still available?',
            'offered_price_cents' => 4000,
        ])
        ->assertRedirect();

    $inquiry = Inquiry::query()->latest('id')->first();
    expect($inquiry)->not->toBeNull();
    expect($inquiry->item_id)->toBe($item->id);
    expect($inquiry->buyer_id)->toBe($buyer->id);
    expect($inquiry->message_excerpt)->toBe('Is it still available?');
    expect($inquiry->offered_price_cents)->toBe(4000);
    expect($inquiry->status)->toBe(InquiryStatus::New);
    expect($inquiry->received_at)->not->toBeNull();
});

it('quick-creates a buyer when display_name is provided without buyer_id', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->post("/items/{$item->id}/inquiries", [
            'new_buyer' => ['display_name' => 'Jess'],
            'message_excerpt' => 'Will you take $30?',
        ])
        ->assertRedirect();

    $buyer = Buyer::query()->where('user_id', $user->id)->where('display_name', 'Jess')->first();
    expect($buyer)->not->toBeNull();
    expect(Inquiry::query()->where('buyer_id', $buyer->id)->exists())->toBeTrue();
});

it('rejects an inquiry with neither buyer_id nor new_buyer', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->post("/items/{$item->id}/inquiries", [
            'message_excerpt' => 'hi',
        ])
        ->assertSessionHasErrors();
});

it('forbids creating an inquiry on another user item', function () {
    $user = User::factory()->create();
    $other = Item::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->post("/items/{$other->id}/inquiries", [
            'buyer_id' => $buyer->id,
            'message_excerpt' => 'hi',
        ])
        ->assertForbidden();
});

it('forbids creating an inquiry against another user buyer', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    $foreignBuyer = Buyer::factory()->create();

    actingAs($user)
        ->post("/items/{$item->id}/inquiries", [
            'buyer_id' => $foreignBuyer->id,
            'message_excerpt' => 'hi',
        ])
        ->assertSessionHasErrors('buyer_id');
});
