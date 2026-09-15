<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Actions\Action;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductionDay;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Finds the earliest production day with enough butcher-minutes for a new order (owner decisions,
 * guideline ch. 7 S04): orders placed after the daily cutoff queue for the next day, and a day is
 * never booked past what the card's authorization window can cover — no re-authorization flow
 * (same call as the Sprint 02 lead-time cap).
 */
final class ScheduleOrder extends Action
{
    /** @var list<OrderStatus> statuses that no longer hold a place in the day's capacity */
    private const RELEASED_STATUSES = [OrderStatus::PaymentFailed, OrderStatus::AuthorizationExpired, OrderStatus::Cancelled];

    public function handle(int $neededMinutes): Carbon
    {
        $date = $this->firstCandidateDate();
        $limit = $this->latestAllowedDate();

        while ($date->lte($limit)) {
            $found = $this->transaction(function () use ($date, $neededMinutes): bool {
                $day = ProductionDay::query()->firstOrCreate(['date' => $date->toDateString()]);
                /** @var ProductionDay $locked */
                $locked = ProductionDay::query()->whereKey($day->getKey())->lockForUpdate()->firstOrFail();

                if (! $locked->is_open) {
                    return false;
                }

                $used = (int) OrderItem::query()
                    ->whereHas('order', fn ($q) => $q->whereDate('scheduled_date', $date->toDateString())->whereNotIn('status', self::RELEASED_STATUSES))
                    ->sum('estimated_minutes');

                return ($used + $neededMinutes) <= $locked->capacityMinutes();
            });

            if ($found) {
                return $date;
            }

            $date = $date->copy()->addDay();
        }

        throw ValidationException::withMessages([
            'cart' => "We don't have processing capacity for this order in the next few days. Please reduce the quantity, choose fewer time-consuming options, or call us for a custom order.",
        ]);
    }

    private function firstCandidateDate(): Carbon
    {
        $today = Carbon::today();
        $cutoff = (string) config('catchweight.order_cutoff_time');

        return now()->format('H:i') < $cutoff ? $today : $today->copy()->addDay();
    }

    private function latestAllowedDate(): Carbon
    {
        return Carbon::today()->addDays((int) config('catchweight.authorization_fallback_days'));
    }
}
