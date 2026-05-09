<?php

use App\Enums\PaymentMethod;
use App\Models\Buyer;
use App\Models\Pickup;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('updates pickup notes and payment_method', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $pickup = Pickup::factory()->create(['buyer_id' => $buyer->id]);

    actingAs($user)
        ->patch("/pickups/{$pickup->id}", [
            'notes' => 'Use side door',
            'payment_method' => 'e_transfer',
        ])
        ->assertRedirect();

    $fresh = $pickup->fresh();
    expect($fresh->notes)->toBe('Use side door');
    expect($fresh->payment_method)->toBe(PaymentMethod::ETransfer);

    actingAs($user)->get("/pickups/{$pickup->id}")
        ->assertInertia(fn ($page) => $page
            ->hasFlash('toast.type', 'success')
            ->hasFlash('toast.message', 'Pickup updated.')
        );
});

it('forbids updating another user pickup', function () {
    $user = User::factory()->create();
    $foreign = Pickup::factory()->create();

    actingAs($user)
        ->patch("/pickups/{$foreign->id}", ['notes' => 'hijack'])
        ->assertForbidden();
});
