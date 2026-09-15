<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Action;
use App\Actions\Inventory\ConsumeStock;
use App\Enums\OrderStatus;
use App\Jobs\SettleOrderPayment;
use App\Models\Order;
use App\Models\User;
use App\Support\SettlementPlan;
use Illuminate\Validation\ValidationException;

/**
 * QC-passed lines → decide what the card should be charged (owner policy S01) and hand off to the
 * queue. Owner decision (guideline ch. 7, S04): capture only ever happens after QC passes, never
 * straight off the scale, so a failed check can never leave a wrong-weight charge to undo.
 */
final class FinalizeOrder extends Action
{
    public function __construct(private readonly ConsumeStock $consumeStock) {}

    public function handle(Order $order, User $by): SettlementPlan
    {
        // DB::transaction() only returns once the commit has actually happened (or rolls back and rethrows),
        // so dispatching the job after it returns — rather than via ->afterCommit() from inside the closure —
        // guarantees a queue worker on a real async driver never reads the pre-Settling row. It also means
        // the dispatch isn't silently dropped when a test wraps the whole case in one outer transaction.
        /** @var array{0: SettlementPlan, 1: bool} $result */
        $result = $this->transaction(function () use ($order, $by): array {
            /** @var Order $locked */
            $locked = Order::query()->with('items')->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [OrderStatus::QcPassed, OrderStatus::NeedsReview], true)) {
                throw ValidationException::withMessages(['order' => "Order {$locked->number} can’t be finalized while it is “{$locked->status->label()}”."]);
            }
            if (! $locked->allItemsWeighed()) {
                throw ValidationException::withMessages(['order' => 'Weigh every item before finalizing.']);
            }
            if ($locked->authorization_expires_at !== null && $locked->authorization_expires_at->isPast()) {
                throw ValidationException::withMessages(['order' => 'The card hold has expired — contact the customer to pay again.']);
            }

            $final = (int) $locked->items->sum(fn ($item) => (int) $item->final_cents);
            $plan = SettlementPlan::for(
                estimatedCents: $locked->estimated_cents,
                holdCents: $locked->hold_cents,
                finalCents: $final,
                overageAutochargePct: $locked->overage_autocharge_pct,
                underweightReviewPct: $locked->underweight_review_pct,
                underweightApproved: $locked->underweight_approved_at !== null,
                minimumChargeCents: (int) config('catchweight.minimum_charge_cents'),
            );

            $locked->final_cents = $final;

            if ($plan->action === SettlementPlan::REVIEW) {
                $locked->status = OrderStatus::NeedsReview;
                $locked->save();
                $order->setRawAttributes($locked->getAttributes(), true);

                return [$plan, false];
            }

            $locked->status = OrderStatus::Settling;
            $locked->weights_locked_at = now();
            $locked->finalized_by = $by->id;
            $locked->save();
            $order->setRawAttributes($locked->getAttributes(), true);

            // Weights are locked now — convert each item's reservation into an actual consumption
            // (guideline ch. 6, Sprint 03). A no-op for items not under inventory tracking.
            foreach ($locked->items as $item) {
                $this->consumeStock->handle($item);
            }

            return [$plan, true];
        });

        [$plan, $shouldSettle] = $result;

        if ($shouldSettle) {
            SettleOrderPayment::dispatch($order->id);
        }

        return $plan;
    }
}
