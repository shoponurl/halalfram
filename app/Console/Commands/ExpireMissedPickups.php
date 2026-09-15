<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Delivery\MarkMissedPickup;
use App\Actions\Delivery\SendPickupReminder;
use App\Enums\FulfilmentStatus;
use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Owner decision (guideline ch. 7, S05): a reminder at pickup_reminder_hours after "ready", then a
 * write-off (partial refund) at pickup_writeoff_hours if still uncollected. Schedule this hourly.
 */
class ExpireMissedPickups extends Command
{
    protected $signature = 'orders:expire-missed-pickups';

    protected $description = 'Sends the one pickup reminder, then writes off orders still uncollected past the deadline (guideline S05)';

    public function handle(SendPickupReminder $remind, MarkMissedPickup $missed): int
    {
        $reminderCutoff = now()->subHours((int) config('catchweight.pickup_reminder_hours'));
        $writeOffCutoff = now()->subHours((int) config('catchweight.pickup_writeoff_hours'));

        $readyOrders = Order::query()->where('fulfilment_status', FulfilmentStatus::ReadyForPickup)->whereNotNull('ready_notified_at')->get();

        $reminded = 0;
        $writtenOff = 0;
        foreach ($readyOrders as $order) {
            if ($order->ready_notified_at === null) {
                continue;   // excluded by the query above; never actually reached
            }
            if ($order->ready_notified_at->lte($writeOffCutoff)) {
                $missed->handle($order);
                $writtenOff++;

                continue;
            }
            if ($order->ready_notified_at->lte($reminderCutoff) && $order->pickup_reminder_sent_at === null) {
                $remind->handle($order);
                $reminded++;
            }
        }

        $this->info("Sent {$reminded} pickup reminder(s), wrote off {$writtenOff} missed pickup(s).");

        return self::SUCCESS;
    }
}
