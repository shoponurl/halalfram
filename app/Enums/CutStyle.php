<?php

declare(strict_types=1);

namespace App\Enums;

/** Structured cut styles (guideline M03) — never free text, so the butcher never has to guess. */
enum CutStyle: string
{
    case CurryCut = 'curry_cut';
    case Steak = 'steak';
    case Chops = 'chops';
    case Roast = 'roast';
    case Boneless = 'boneless';
    case BoneIn = 'bone_in';
    case Mince = 'mince';
    case Whole = 'whole';

    public function label(): string
    {
        return match ($this) {
            self::CurryCut => 'Curry cut',
            self::Steak => 'Steak',
            self::Chops => 'Chops',
            self::Roast => 'Roast',
            self::Boneless => 'Boneless',
            self::BoneIn => 'Bone-in',
            self::Mince => 'Mince',
            self::Whole => 'Whole (uncut)',
        };
    }
}
