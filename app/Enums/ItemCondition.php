<?php

namespace App\Enums;

enum ItemCondition: string
{
    case New = 'new';
    case LikeNew = 'like_new';
    case Good = 'good';
    case Fair = 'fair';
    case ForParts = 'for_parts';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::LikeNew => 'Like new',
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::ForParts => 'For parts / not working',
        };
    }
}
