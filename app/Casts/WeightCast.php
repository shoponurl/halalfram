<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Weight;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * DECIMAL(10,3) column ⇄ App\Support\Weight. Usage: 'actual_weight' => WeightCast::class
 *
 * @implements CastsAttributes<Weight|null, Weight|string|null>
 */
final class WeightCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Weight
    {
        return $value === null ? null : Weight::pounds((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof Weight => $value->toDecimal(),
            is_string($value) => Weight::pounds($value)->toDecimal(),
            default => throw new InvalidArgumentException("[{$key}] must be a Weight or decimal string, never a float."),
        };
    }
}
