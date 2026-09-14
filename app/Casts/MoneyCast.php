<?php

declare(strict_types=1);

namespace App\Casts;

use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Integer-cents column ⇄ Brick\Money\Money (USD). Rule 01: money is never a float.
 * Usage: 'total_cents' => MoneyCast::class
 *
 * @implements CastsAttributes<Money|null, Money|int|null>
 */
final class MoneyCast implements CastsAttributes
{
    public const CURRENCY = 'USD';

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::ofMinor((int) $value, self::CURRENCY);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return match (true) {
            $value === null => null,
            $value instanceof Money => $value->getCurrency()->getCurrencyCode() === self::CURRENCY
                ? $value->getMinorAmount()->toInt()
                : throw new InvalidArgumentException("[{$key}] must be in ".self::CURRENCY.'.'),
            is_int($value) => $value,
            default => throw new InvalidArgumentException("[{$key}] must be Money or integer cents, never a float."),
        };
    }
}
