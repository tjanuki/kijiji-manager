<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Received => 'Received',
        };
    }
}
