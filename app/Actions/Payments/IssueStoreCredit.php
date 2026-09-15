<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Action;
use App\Models\StoreCreditAccount;
use App\Models\StoreCreditEvent;
use App\Models\User;

/** Staff-initiated credit — goodwill, or a refund the customer preferred to keep in the store. */
final class IssueStoreCredit extends Action
{
    public function handle(string $customerEmail, int $amountCents, string $reason, User $by): StoreCreditAccount
    {
        return $this->transaction(function () use ($customerEmail, $amountCents, $reason, $by): StoreCreditAccount {
            /** @var StoreCreditAccount $account */
            $account = StoreCreditAccount::query()->lockForUpdate()->firstOrCreate(
                ['customer_email' => $customerEmail],
                ['balance_cents' => 0],
            );
            $account->balance_cents += $amountCents;
            $account->save();

            $event = new StoreCreditEvent;
            $event->fill(['customer_email' => $customerEmail, 'type' => 'issue', 'amount_cents' => $amountCents, 'reason' => $reason, 'created_by' => $by->id]);
            $event->save();

            return $account;
        });
    }
}
