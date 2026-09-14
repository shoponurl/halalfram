<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Action;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\SettlementPlan;
use Illuminate\Validation\ValidationException;

/**
 * A manager confirms that a large underweight is real (not a typo) and releases the capture.
 * Authorization (payments.approve_adjustments) is checked by the caller's policy.
 */
final class ApproveUnderweight extends Action
{
    public function __construct(private readonly FinalizeOrder $finalize) {}

    public function handle(Order $order, User $manager): SettlementPlan
    {
        $this->transaction(function () use ($order, $manager): void {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== OrderStatus::NeedsReview) {
                throw ValidationException::withMessages(['order' => 'Only orders waiting for review can be approved.']);
            }
            $locked->underweight_approved_by = $manager->id;
            $locked->underweight_approved_at = now();
            $locked->save();
        });

        return $this->finalize->handle($order->refresh(), $manager);
    }
}
