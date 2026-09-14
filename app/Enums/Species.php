<?php

declare(strict_types=1);

namespace App\Enums;

/** The species groupings a category belongs to (guideline ch. 6, Sprint 02). */
enum Species: string
{
    case Beef = 'beef';
    case Goat = 'goat';
    case Lamb = 'lamb';
    case Chicken = 'chicken';
    case Duck = 'duck';
    case Turkey = 'turkey';
    case Processed = 'processed';

    public function label(): string
    {
        return match ($this) {
            self::Beef => 'Beef',
            self::Goat => 'Goat',
            self::Lamb => 'Lamb',
            self::Chicken => 'Chicken',
            self::Duck => 'Duck',
            self::Turkey => 'Turkey',
            self::Processed => 'Processed / deli',
        };
    }
}
