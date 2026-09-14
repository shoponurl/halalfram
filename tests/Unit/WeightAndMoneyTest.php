<?php

declare(strict_types=1);

use App\Casts\MoneyCast;
use App\Casts\WeightCast;
use App\Support\Weight;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;

it('keeps weights exact to three decimals without floats', function () {
    expect(Weight::pounds('0.1')->plus(Weight::pounds('0.2'))->toDecimal())->toBe('0.300')
        ->and(Weight::pounds('2.0004')->toDecimal())->toBe('2.000')
        ->and(Weight::pounds('2.0005')->toDecimal())->toBe('2.001')
        ->and(Weight::kilograms('1')->toDecimal())->toBe('2.205');
});

it('computes weight variance against an estimate', function () {
    expect(Weight::pounds('2.300')->variancePercentFrom(Weight::pounds('2.000')))->toBe('15.000')
        ->and(Weight::pounds('1.800')->variancePercentFrom(Weight::pounds('2.000')))->toBe('-10.000');
});

it('rejects float weights', function () {
    $cast = new WeightCast;
    expect(fn () => $cast->set(model(), 'actual_weight', 2.3, []))->toThrow(InvalidArgumentException::class);
});

it('stores money as integer cents and rejects floats', function () {
    $cast = new MoneyCast;
    expect($cast->set(model(), 'total_cents', Money::of('26.40', 'USD'), []))->toBe(2640)
        ->and($cast->get(model(), 'total_cents', 2640, [])->getAmount()->__toString())->toBe('26.40')
        ->and(fn () => $cast->set(model(), 'total_cents', 26.40, []))->toThrow(InvalidArgumentException::class);
});

function model(): Model
{
    return new class extends Model {};
}
