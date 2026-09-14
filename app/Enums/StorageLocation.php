<?php

declare(strict_types=1);

namespace App\Enums;

/** Cold-chain storage areas (guideline ch. 6, Sprint 03). */
enum StorageLocation: string
{
    case Chiller = 'chiller';
    case Freezer = 'freezer';
    case Room = 'room';

    public function label(): string
    {
        return match ($this) {
            self::Chiller => 'Chiller',
            self::Freezer => 'Freezer',
            self::Room => 'Room temperature',
        };
    }
}
