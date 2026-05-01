<?php

use App\Enums\ItemCondition;
use App\Models\Item;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\ListingDraftRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('renders title from category, item title, and condition', function () {
    $item = Item::factory()->create([
        'title' => 'IKEA Klippan loveseat',
        'category' => 'Furniture',
        'condition' => ItemCondition::Good,
    ]);

    $rendered = app(ListingDraftRenderer::class)->render($item);

    expect($rendered['title'])->toBe('Furniture - IKEA Klippan loveseat - Good');
});

it('omits null category from title', function () {
    $item = Item::factory()->create([
        'title' => 'Misc lamp',
        'category' => null,
        'condition' => ItemCondition::Fair,
    ]);

    $rendered = app(ListingDraftRenderer::class)->render($item);

    expect($rendered['title'])->toBe('Misc lamp - Fair');
});

it('renders description with item description, condition section, and snippets', function () {
    $user = User::factory()->create();
    UserSetting::factory()->create([
        'user_id' => $user->id,
        'snippets' => [
            'pickup' => 'Pickup at front porch, Liberty Village.',
            'payment' => 'Cash or e-transfer.',
        ],
    ]);
    $item = Item::factory()->create([
        'user_id' => $user->id,
        'description' => 'Three years old, no rips.',
        'condition' => ItemCondition::LikeNew,
    ]);

    $rendered = app(ListingDraftRenderer::class)->render($item->fresh()->load('user.settings'));

    expect($rendered['description'])->toBe(
        "Three years old, no rips.\n\n"
        ."Condition: Like new\n\n"
        ."Pickup: Pickup at front porch, Liberty Village.\n\n"
        .'Payment: Cash or e-transfer.'
    );
});

it('skips empty description and missing snippets cleanly', function () {
    $item = Item::factory()->create([
        'description' => null,
        'condition' => ItemCondition::Good,
    ]);

    $rendered = app(ListingDraftRenderer::class)->render($item->fresh()->load('user.settings'));

    expect($rendered['description'])->toBe('Condition: Good');
});

it('skips snippet sections that are empty strings', function () {
    $user = User::factory()->create();
    UserSetting::factory()->create([
        'user_id' => $user->id,
        'snippets' => ['pickup' => '', 'payment' => 'Cash only.'],
    ]);
    $item = Item::factory()->create([
        'user_id' => $user->id,
        'description' => 'Tested working.',
        'condition' => ItemCondition::Good,
    ]);

    $rendered = app(ListingDraftRenderer::class)->render($item->fresh()->load('user.settings'));

    expect($rendered['description'])->toBe(
        "Tested working.\n\nCondition: Good\n\nPayment: Cash only."
    );
});

it('renders cleanly when description is null but snippets are present', function () {
    $user = User::factory()->create();
    UserSetting::factory()->create([
        'user_id' => $user->id,
        'snippets' => [
            'pickup' => 'Front porch.',
            'payment' => 'Cash.',
        ],
    ]);
    $item = Item::factory()->create([
        'user_id' => $user->id,
        'description' => null,
        'condition' => ItemCondition::Good,
    ]);

    $rendered = app(ListingDraftRenderer::class)->render($item->fresh()->load('user.settings'));

    expect($rendered['description'])->toBe(
        "Condition: Good\n\nPickup: Front porch.\n\nPayment: Cash."
    );
});
