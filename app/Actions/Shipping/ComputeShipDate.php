<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Actions\Action;
use App\Models\ShipBlackoutDate;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Owner decision (guideline ch. 7, S08): ship Monday-Thursday only, so an overnight shipment never
 * arrives on a weekend with no one home — plus federal holidays and other blackout dates
 * (App\Models\ShipBlackoutDate). Guideline DoD: "the system itself blocks shipping on the wrong day."
 */
final class ComputeShipDate extends Action
{
    /** @throws ValidationException if no valid ship date exists within the lookahead window */
    public function handle(?Carbon $from = null): Carbon
    {
        $date = ($from ?? Carbon::today())->copy();
        $limit = Carbon::today()->addDays((int) config('catchweight.authorization_fallback_days'));

        while ($date->lte($limit)) {
            if ($this->isValidShipDate($date)) {
                return $date;
            }
            $date = $date->copy()->addDay();
        }

        throw ValidationException::withMessages([
            'cart' => 'We don\'t have a valid ship date coming up — please call us to arrange nationwide shipping.',
        ]);
    }

    public function isValidShipDate(Carbon $date): bool
    {
        /** @var list<int> $allowedWeekdays */
        $allowedWeekdays = config('catchweight.ship_weekdays');
        if (! in_array($date->dayOfWeekIso, $allowedWeekdays, true)) {
            return false;
        }

        return ! ShipBlackoutDate::query()->where('date', $date->toDateString())->exists();
    }
}
