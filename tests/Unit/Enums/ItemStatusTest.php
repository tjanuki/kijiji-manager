<?php

use App\Enums\ItemStatus;

it('exposes the six lifecycle states', function () {
    expect(array_map(fn ($s) => $s->value, ItemStatus::cases()))
        ->toBe(['draft', 'ready', 'listed', 'reserved', 'sold', 'withdrawn']);
});

it('reports allowed transitions per the spec', function () {
    expect(ItemStatus::Draft->canTransitionTo(ItemStatus::Ready))->toBeTrue();
    expect(ItemStatus::Ready->canTransitionTo(ItemStatus::Listed))->toBeTrue();
    expect(ItemStatus::Listed->canTransitionTo(ItemStatus::Reserved))->toBeTrue();
    expect(ItemStatus::Reserved->canTransitionTo(ItemStatus::Listed))->toBeTrue();
    expect(ItemStatus::Reserved->canTransitionTo(ItemStatus::Sold))->toBeTrue();
    expect(ItemStatus::Listed->canTransitionTo(ItemStatus::Sold))->toBeTrue();
    expect(ItemStatus::Withdrawn->canTransitionTo(ItemStatus::Draft))->toBeTrue();

    // any -> withdrawn
    foreach (ItemStatus::cases() as $from) {
        if ($from === ItemStatus::Withdrawn) {
            continue;
        }
        expect($from->canTransitionTo(ItemStatus::Withdrawn))->toBeTrue();
    }
});

it('rejects invalid transitions', function () {
    expect(ItemStatus::Draft->canTransitionTo(ItemStatus::Listed))->toBeFalse();
    expect(ItemStatus::Draft->canTransitionTo(ItemStatus::Sold))->toBeFalse();
    expect(ItemStatus::Sold->canTransitionTo(ItemStatus::Listed))->toBeFalse();
    expect(ItemStatus::Sold->canTransitionTo(ItemStatus::Draft))->toBeFalse();
    expect(ItemStatus::Listed->canTransitionTo(ItemStatus::Draft))->toBeFalse();
});
