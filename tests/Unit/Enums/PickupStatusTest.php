<?php

use App\Enums\PickupStatus;

it('exposes the four pickup states', function () {
    expect(array_map(fn ($s) => $s->value, PickupStatus::cases()))
        ->toBe(['scheduled', 'completed', 'no_show', 'cancelled']);
});

it('returns human labels', function () {
    expect(PickupStatus::Scheduled->label())->toBe('Scheduled');
    expect(PickupStatus::NoShow->label())->toBe('No-show');
});
