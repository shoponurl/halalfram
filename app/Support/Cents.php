<?php

declare(strict_types=1);

namespace App\Support;

/** Display helper for integer-cent amounts (no float division). */
final class Cents
{
    public static function format(?int $cents): string
    {
        if ($cents === null) {
            return '—';
        }
        $sign = $cents < 0 ? '−' : '';
        $abs = abs($cents);

        return $sign.'$'.number_format(intdiv($abs, 100)).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }
}
