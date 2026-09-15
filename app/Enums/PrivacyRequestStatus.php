<?php

declare(strict_types=1);

namespace App\Enums;

enum PrivacyRequestStatus: string
{
    case Pending = 'pending';
    case Fulfilled = 'fulfilled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Fulfilled => 'Fulfilled',
        };
    }
}
