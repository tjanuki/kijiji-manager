<?php

use App\Enums\ItemStatus;
use App\Models\Inquiry;
use App\Models\Item;

it('flags a listed item with no inquiries past the cutoff as stale', function () {
    $item = Item::factory()->stale()->create();

    expect($item->isStale())->toBeTrue();
});

it('does not flag a recently listed item as stale', function () {
    $item = Item::factory()->listed()->create([
        'listed_at' => now()->subDays(13),
    ]);

    expect($item->isStale())->toBeFalse();
});

it('does not flag a listed item with a recent inquiry as stale', function () {
    $item = Item::factory()->stale()->create();
    Inquiry::factory()->create([
        'item_id' => $item->id,
        'last_contact_at' => now()->subDays(3),
    ]);

    expect($item->fresh()->isStale())->toBeFalse();
});

it('flags a listed item whose inquiries all went cold as stale', function () {
    $item = Item::factory()->stale()->create();
    Inquiry::factory()->create([
        'item_id' => $item->id,
        'last_contact_at' => now()->subDays(8),
    ]);

    expect($item->fresh()->isStale())->toBeTrue();
});

it('does not flag non-listed items as stale even if dates qualify', function (ItemStatus $status) {
    $item = Item::factory()->create([
        'status' => $status->value,
        'listed_at' => now()->subDays(30),
    ]);

    expect($item->isStale())->toBeFalse();
})->with([
    ItemStatus::Draft,
    ItemStatus::Ready,
    ItemStatus::Reserved,
    ItemStatus::Sold,
    ItemStatus::Withdrawn,
]);

it('treats an item listed exactly on the cutoff day as stale', function () {
    $item = Item::factory()->listed()->create([
        'listed_at' => now()->subDays(Item::STALE_DAYS_LISTED),
    ]);

    expect($item->isStale())->toBeTrue();
});

it('treats an inquiry contacted exactly on the cutoff day as recent (not stale)', function () {
    $item = Item::factory()->stale()->create();
    Inquiry::factory()->create([
        'item_id' => $item->id,
        'last_contact_at' => now()->subDays(Item::STALE_INQUIRY_WINDOW_DAYS),
    ]);

    expect($item->fresh()->isStale())->toBeFalse();
});
