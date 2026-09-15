<?php

declare(strict_types=1);

use App\Actions\Shipping\ComputeShipDate;
use App\Models\ShipBlackoutDate;
use Illuminate\Support\Carbon;

/*
 * Owner decision (guideline ch. 7, S08): ship Monday-Thursday only, so an overnight shipment never
 * arrives on a weekend with no one home. Guideline DoD: "the system itself blocks shipping on the
 * wrong day."
 */

it('ships on a Monday-Thursday date and never a weekend', function () {
    $saturday = Carbon::parse('2026-09-19');   // a Saturday
    expect($saturday->dayOfWeekIso)->toBe(6);

    $date = app(ComputeShipDate::class)->handle($saturday);

    expect($date->dayOfWeekIso)->toBeLessThanOrEqual(4)
        ->and($date->gte($saturday))->toBeTrue();
});

it('skips a Monday-Thursday date that is in the blackout table', function () {
    $monday = Carbon::parse('2026-09-21');   // a Monday
    expect($monday->dayOfWeekIso)->toBe(1);
    ShipBlackoutDate::query()->create(['date' => $monday->toDateString(), 'reason' => 'Federal holiday']);

    $date = app(ComputeShipDate::class)->handle($monday);

    expect($date->toDateString())->not->toBe($monday->toDateString())
        ->and($date->gt($monday))->toBeTrue();
});

it('accepts a Monday-Thursday date with no blackout as-is', function () {
    $tuesday = Carbon::parse('2026-09-22');
    expect($tuesday->dayOfWeekIso)->toBe(2);

    $date = app(ComputeShipDate::class)->handle($tuesday);

    expect($date->toDateString())->toBe($tuesday->toDateString());
});
