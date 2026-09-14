<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An exact weight in pounds, stored as DECIMAL(10,3). Rule 01: never a float.
 * All arithmetic uses bcmath on decimal strings.
 */
final class Weight implements JsonSerializable, Stringable
{
    private const SCALE = 3;

    private const LB_PER_KG = '2.20462262';

    /** @param numeric-string $pounds */
    private function __construct(private readonly string $pounds) {}

    public static function pounds(string|int $pounds): self
    {
        return new self(self::round(self::decimal($pounds)));
    }

    public static function kilograms(string|int $kilograms): self
    {
        return new self(self::round(bcmul(self::decimal($kilograms), self::LB_PER_KG, 10)));
    }

    /** @return numeric-string */
    private static function decimal(string|int $input): string
    {
        $value = (string) $input;
        if (! is_numeric($value) || ! preg_match('/^-?\d{1,7}(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Invalid weight [{$value}] — pass a decimal string, never a float.");
        }

        return $value;
    }

    public static function zero(): self
    {
        return new self('0.000');
    }

    public function plus(self $other): self
    {
        return new self(bcadd($this->pounds, $other->pounds, self::SCALE));
    }

    public function minus(self $other): self
    {
        return new self(bcsub($this->pounds, $other->pounds, self::SCALE));
    }

    /** Percentage difference from an estimate, e.g. "10.000" for +10 %. */
    public function variancePercentFrom(self $estimate): string
    {
        if (bccomp($estimate->pounds, '0', self::SCALE) === 0) {
            throw new InvalidArgumentException('Estimate weight must be greater than zero.');
        }

        return self::round(bcmul(bcdiv(bcsub($this->pounds, $estimate->pounds, 10), $estimate->pounds, 10), '100', 10));
    }

    public function compareTo(self $other): int
    {
        return bccomp($this->pounds, $other->pounds, self::SCALE);
    }

    public function isPositive(): bool
    {
        return bccomp($this->pounds, '0', self::SCALE) === 1;
    }

    /** The decimal string for the DECIMAL(10,3) column. */
    public function toDecimal(): string
    {
        return $this->pounds;
    }

    public function __toString(): string
    {
        return $this->pounds.' lb';
    }

    public function jsonSerialize(): string
    {
        return $this->pounds;
    }

    /**
     * Half-up rounding to 3 decimals without floats.
     *
     * @param  numeric-string  $value
     * @return numeric-string
     */
    private static function round(string $value): string
    {
        $offset = '0.'.str_repeat('0', self::SCALE).'5';

        return str_starts_with($value, '-')
            ? bcsub($value, $offset, self::SCALE)
            : bcadd($value, $offset, self::SCALE);
    }
}
