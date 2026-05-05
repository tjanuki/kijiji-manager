<?php

use App\Enums\InquiryStatus;
use App\Models\Buyer;
use App\Models\Inquiry;
use App\Models\Item;
use App\Models\User;

it('attaches an inquiry to an item and a buyer', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);

    $inquiry = Inquiry::factory()->create([
        'item_id' => $item->id,
        'buyer_id' => $buyer->id,
    ]);

    expect($inquiry->item->is($item))->toBeTrue();
    expect($inquiry->buyer->is($buyer))->toBeTrue();
});

it('casts status to InquiryStatus and negotiation_log to array', function () {
    $inquiry = Inquiry::factory()->create([
        'status' => 'negotiating',
        'negotiation_log' => [['note' => 'offered $80', 'at' => now()->toIso8601String()]],
    ]);

    expect($inquiry->status)->toBe(InquiryStatus::Negotiating);
    expect($inquiry->negotiation_log)->toBeArray()->toHaveCount(1);
});

it('exposes inquiries via Item->inquiries() and buyers via User->buyers()', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    Inquiry::factory()->count(2)->create([
        'item_id' => $item->id,
        'buyer_id' => $buyer->id,
    ]);

    expect($item->inquiries()->count())->toBe(2);
    expect($user->buyers()->count())->toBe(1);
});
