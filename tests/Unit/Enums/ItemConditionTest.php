<?php

use App\Enums\ItemCondition;

it('exposes the expected condition cases', function () {
    expect(ItemCondition::cases())
        ->toHaveCount(5)
        ->and(array_map(fn ($c) => $c->value, ItemCondition::cases()))
        ->toBe(['new', 'like_new', 'good', 'fair', 'for_parts']);
});

it('returns a human label for each case', function () {
    expect(ItemCondition::Good->label())->toBe('Good');
    expect(ItemCondition::LikeNew->label())->toBe('Like new');
    expect(ItemCondition::ForParts->label())->toBe('For parts / not working');
});
