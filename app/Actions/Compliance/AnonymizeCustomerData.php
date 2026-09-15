<?php

declare(strict_types=1);

namespace App\Actions\Compliance;

use App\Actions\Action;
use App\Enums\PrivacyRequestStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PrivacyRequest;
use App\Models\User;

/**
 * Owner decision (guideline ch. 7, S07): a CCPA deletion request is fulfilled by anonymizing, never
 * hard-deleting — USDA/PA traceability requires every lot stay linked to the orders it went into.
 * This scrubs personally-identifying fields on the customer's orders; it does not touch
 * StoreCreditAccount/SmsConsent (keyed by the very email/phone being asked to be forgotten) — a
 * disclosed gap, see docs/sprint-07.md.
 */
final class AnonymizeCustomerData extends Action
{
    public const SCRUBBED_NAME = 'Deleted customer';

    public const SCRUBBED_EMAIL = 'deleted-customer@removed.invalid';

    public function handle(PrivacyRequest $request, User $by): PrivacyRequest
    {
        return $this->transaction(function () use ($request, $by): PrivacyRequest {
            Order::query()
                ->where('customer_email', $request->customer_email)
                ->lockForUpdate()
                ->get()
                ->each(function (Order $order): void {
                    $order->customer_name = self::SCRUBBED_NAME;
                    $order->customer_email = self::SCRUBBED_EMAIL;
                    $order->customer_phone = '';
                    $order->delivery_address_line1 = $order->delivery_address_line1 !== null ? '' : null;
                    $order->delivery_address_line2 = null;
                    $order->delivery_city = $order->delivery_city !== null ? '' : null;
                    $order->delivery_zip = $order->delivery_zip !== null ? '' : null;
                    $order->notes = null;
                    $order->save();
                });

            $request->status = PrivacyRequestStatus::Fulfilled;
            $request->fulfilled_by = $by->id;
            $request->fulfilled_at = now();
            $request->save();

            AuditLog::record(
                'privacy_request.fulfilled',
                "Anonymized orders for {$request->customer_email}",
                $request,
            );

            return $request;
        });
    }
}
