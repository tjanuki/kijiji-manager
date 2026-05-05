<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case ETransfer = 'e_transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::ETransfer => 'E-transfer',
            self::Other => 'Other',
        };
    }
}
