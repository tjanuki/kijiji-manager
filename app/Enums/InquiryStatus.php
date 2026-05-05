<?php

namespace App\Enums;

enum InquiryStatus: string
{
    case New = 'new';
    case Replied = 'replied';
    case Negotiating = 'negotiating';
    case Ghosted = 'ghosted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Replied => 'Replied',
            self::Negotiating => 'Negotiating',
            self::Ghosted => 'Ghosted',
            self::Declined => 'Declined',
        };
    }
}
