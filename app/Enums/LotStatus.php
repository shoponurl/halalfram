<?php

declare(strict_types=1);

namespace App\Enums;

enum LotStatus: string
{
    case Active = 'active';
    case Depleted = 'depleted';       // on-hand weight reached zero through sales/wastage
    case Withdrawn = 'withdrawn';     // pulled manually — recall or spoilage clearout

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Depleted => 'Depleted',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
