<?php

use App\Enums\InquiryStatus;
use App\Models\Buyer;
use App\Models\Inquiry;
use App\Models\Item;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('updates status, offered price, and last_contact_at', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $inquiry = Inquiry::factory()->create([
        'item_id' => $item->id,
        'buyer_id' => $buyer->id,
        'status' => InquiryStatus::New->value,
    ]);

    $before = $inquiry->last_contact_at;

    actingAs($user)
        ->patch("/inquiries/{$inquiry->id}", [
            'status' => 'negotiating',
            'offered_price_cents' => 5500,
        ])
        ->assertRedirect();

    $fresh = $inquiry->fresh();
    expect($fresh->status)->toBe(InquiryStatus::Negotiating);
    expect($fresh->offered_price_cents)->toBe(5500);
    expect($fresh->last_contact_at->greaterThan($before))->toBeTrue();

    actingAs($user)->get("/items/{$item->id}")
        ->assertInertia(fn ($page) => $page
            ->hasFlash('toast.type', 'success')
            ->hasFlash('toast.message', 'Inquiry updated.')
        );
});

it('appends a negotiation note to the log when provided', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $inquiry = Inquiry::factory()->create([
        'item_id' => $item->id,
        'buyer_id' => $buyer->id,
        'negotiation_log' => null,
    ]);

    actingAs($user)
        ->patch("/inquiries/{$inquiry->id}", [
            'negotiation_note' => 'Countered with $80',
        ])
        ->assertRedirect();

    $log = $inquiry->fresh()->negotiation_log;
    expect($log)->toBeArray()->toHaveCount(1);
    expect($log[0]['note'])->toBe('Countered with $80');
    expect($log[0]['at'])->not->toBeEmpty();
});

it('forbids updating another user inquiry', function () {
    $user = User::factory()->create();
    $foreign = Inquiry::factory()->create();

    actingAs($user)
        ->patch("/inquiries/{$foreign->id}", ['status' => 'replied'])
        ->assertForbidden();
});
