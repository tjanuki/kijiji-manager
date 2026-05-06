<?php

use App\Enums\ItemStatus;
use App\Models\Inquiry;
use App\Models\Item;

it('returns listed items past the cutoff with no recent inquiries', function () {
    $stale = Item::factory()->stale()->create();
    $fresh = Item::factory()->listed()->create(['listed_at' => now()->subDays(5)]);

    $results = Item::stale()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($stale->id);
});

it('excludes listed items with a recent inquiry', function () {
    $cold = Item::factory()->stale()->create();
    $warm = Item::factory()->stale()->create();

    Inquiry::factory()->create([
        'item_id' => $warm->id,
        'last_contact_at' => now()->subDays(2),
    ]);

    $ids = Item::stale()->pluck('id')->all();

    expect($ids)->toContain($cold->id);
    expect($ids)->not->toContain($warm->id);
});

it('includes listed items whose inquiries all went cold', function () {
    $item = Item::factory()->stale()->create();

    Inquiry::factory()->create([
        'item_id' => $item->id,
        'last_contact_at' => now()->subDays(10),
    ]);

    expect(Item::stale()->pluck('id')->all())->toContain($item->id);
});

it('excludes non-listed items even if dates qualify', function (ItemStatus $status) {
    Item::factory()->create([
        'status' => $status->value,
        'listed_at' => now()->subDays(30),
    ]);

    expect(Item::stale()->count())->toBe(0);
})->with([
    ItemStatus::Draft,
    ItemStatus::Ready,
    ItemStatus::Reserved,
    ItemStatus::Sold,
    ItemStatus::Withdrawn,
]);

it('includes items listed exactly on the cutoff boundary', function () {
    $item = Item::factory()->listed()->create([
        'listed_at' => now()->subDays(Item::STALE_DAYS_LISTED),
    ]);

    expect(Item::stale()->pluck('id')->all())->toContain($item->id);
});
