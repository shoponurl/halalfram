<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/** Converts a typed dollar amount ("3.49") to integer cents without floats. */
final class DollarInput
{
    public static function toCents(string $dollars): int
    {
        $value = trim(str_replace(['$', ','], '', $dollars));
        if (! preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/', $value, $m)) {
            throw new InvalidArgumentException("Invalid dollar amount [{$dollars}].");
        }

        return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    public static function fromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
