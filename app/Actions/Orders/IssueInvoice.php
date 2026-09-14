<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Action;
use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

/** Gives a settled order its invoice number (once). The PDF itself is rendered on demand from the ledger. */
final class IssueInvoice extends Action
{
    public function handle(Order $order): Invoice
    {
        if (! in_array($order->status, [OrderStatus::Completed, OrderStatus::AwaitingBalance], true)) {
            throw ValidationException::withMessages(['order' => 'An invoice is available once the order has been charged.']);
        }

        return $this->transaction(function () use ($order): Invoice {
            $existing = Invoice::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if ($existing instanceof Invoice) {
                return $existing;
            }

            $invoice = new Invoice;
            $invoice->order_id = $order->id;
            $invoice->issued_at = now();
            $invoice->save();
            $invoice->number = 'INV-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT);
            $invoice->save();

            return $invoice;
        });
    }
}
