<?php

use App\Enums\PaymentMethod;

it('exposes cash, e-transfer, other', function () {
    expect(array_map(fn ($m) => $m->value, PaymentMethod::cases()))
        ->toBe(['cash', 'e_transfer', 'other']);
});
