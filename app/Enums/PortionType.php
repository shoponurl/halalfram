<?php

declare(strict_types=1);

namespace App\Enums;

/** Whole/Half/Quarter animal variants (guideline ch. 6, Sprint 02): same animal, different yield %. */
enum PortionType: string
{
    case Whole = 'whole';
    case Half = 'half';
    case Quarter = 'quarter';
    case Piece = 'piece';
    case Pack = 'pack';

    public function label(): string
    {
        return match ($this) {
            self::Whole => 'Whole',
            self::Half => 'Half',
            self::Quarter => 'Quarter',
            self::Piece => 'Piece',
            self::Pack => 'Pack',
        };
    }
}
