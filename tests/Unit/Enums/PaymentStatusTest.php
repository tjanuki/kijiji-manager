<?php

use App\Enums\PaymentStatus;

it('exposes pending and received', function () {
    expect(array_map(fn ($s) => $s->value, PaymentStatus::cases()))
        ->toBe(['pending', 'received']);
});
