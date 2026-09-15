<?php

declare(strict_types=1);

namespace App\Enums;

/** A CCPA request a customer files through the public /privacy/requests form (guideline ch. 7, S07). */
enum PrivacyRequestType: string
{
    case Delete = 'delete';
    case Access = 'access';

    public function label(): string
    {
        return match ($this) {
            self::Delete => 'Delete my data',
            self::Access => 'Access my data',
        };
    }
}
