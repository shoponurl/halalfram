<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Models\Order;
use App\Models\User;

/**
 * Orders are created only by checkout (phone/counter orders arrive in Sprint 07) and never deleted.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewOrders->value);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can(Permission::ViewOrders->value);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return false;
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /** Butchers, managers and owners weigh items while the order still accepts weights. */
    public function recordWeight(User $user, Order $order): bool
    {
        return $user->can(Permission::RecordWeights->value) && $order->status->acceptsWeights() && $order->weights_locked_at === null;
    }

    /** Charging the card is a front-desk/manager decision, not the butcher's — and only once QC passes. */
    public function finalize(User $user, Order $order): bool
    {
        return $user->can(Permission::ManageOrders->value)
            && in_array($order->status, [OrderStatus::QcPassed, OrderStatus::NeedsReview], true);
    }

    /** Whoever weighs the order can also QC it, once every item has a weight. */
    public function recordQcCheck(User $user, Order $order): bool
    {
        return $user->can(Permission::RecordWeights->value) && $order->status->acceptsQcCheck() && $order->allItemsWeighed();
    }

    /** Releasing a large underweight capture needs a manager or owner (owner policy S01). */
    public function approveUnderweight(User $user, Order $order): bool
    {
        return $user->can(Permission::ApproveAdjustments->value) && $order->status === OrderStatus::NeedsReview;
    }

    /** The butcher's cutting sheet is only useful once the hold is placed and weighing can start. */
    public function printCuttingSheet(User $user, Order $order): bool
    {
        return $user->can(Permission::RecordWeights->value) && $order->status !== OrderStatus::PendingPayment;
    }

    /** Guideline ch. 6, Sprint 05: pickup/delivery status only moves once the order is actually paid. */
    public function manageFulfilment(User $user, Order $order): bool
    {
        return $user->can(Permission::ManageDeliveries->value) && in_array($order->status, [OrderStatus::Completed, OrderStatus::AwaitingBalance], true);
    }
}
