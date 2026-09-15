<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Action;
use App\Models\StoreCreditAccount;
use App\Models\StoreCreditEvent;

/**
 * Locks the customer's cached balance (same pattern as Sprint 03's Lot quantities) so two concurrent
 * orders can never both spend the same store credit.
 */
final class RedeemStoreCredit extends Action
{
    /** Redeems up to $requestedCents of the customer's balance; returns how much was actually applied. */
    public function handle(string $customerEmail, int $requestedCents, ?int $orderId = null): int
    {
        if ($requestedCents <= 0) {
            return 0;
        }

        return $this->transaction(function () use ($customerEmail, $requestedCents, $orderId): int {
            /** @var StoreCreditAccount|null $account */
            $account = StoreCreditAccount::query()->whereKey($customerEmail)->lockForUpdate()->first();
            if ($account === null || $account->balance_cents <= 0) {
                return 0;
            }
            $applied = min($requestedCents, $account->balance_cents);

            $account->balance_cents -= $applied;
            $account->save();

            $event = new StoreCreditEvent;
            $event->fill(['customer_email' => $customerEmail, 'type' => 'redeem', 'amount_cents' => -$applied, 'order_id' => $orderId]);
            $event->save();

            return $applied;
        });
    }
}
