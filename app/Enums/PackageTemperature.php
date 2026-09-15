<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Owner decision (guideline ch. 7, S08): ship frozen by default — it tolerates transit delays far
 * better than chilled — and only chilled where a specific product needs it (App\Models\Product).
 */
enum PackageTemperature: string
{
    case Frozen = 'frozen';
    case Chilled = 'chilled';

    public function label(): string
    {
        return match ($this) {
            self::Frozen => 'Frozen',
            self::Chilled => 'Chilled',
        };
    }
}
