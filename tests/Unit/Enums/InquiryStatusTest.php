<?php

use App\Enums\InquiryStatus;

it('exposes all five statuses with labels', function () {
    expect(InquiryStatus::cases())->toHaveCount(5);
    expect(InquiryStatus::New->value)->toBe('new');
    expect(InquiryStatus::Replied->value)->toBe('replied');
    expect(InquiryStatus::Negotiating->value)->toBe('negotiating');
    expect(InquiryStatus::Ghosted->value)->toBe('ghosted');
    expect(InquiryStatus::Declined->value)->toBe('declined');
});

it('returns a human label for each status', function () {
    expect(InquiryStatus::New->label())->toBe('New');
    expect(InquiryStatus::Replied->label())->toBe('Replied');
    expect(InquiryStatus::Negotiating->label())->toBe('Negotiating');
    expect(InquiryStatus::Ghosted->label())->toBe('Ghosted');
    expect(InquiryStatus::Declined->label())->toBe('Declined');
});
