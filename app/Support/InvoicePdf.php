<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Invoice = original estimate lines + one weight-adjustment line per item + what was actually charged.
 * Rendered from the order ledger each time, so it can never drift from the payment records.
 */
final class InvoicePdf
{
    public function render(Order $order, Invoice $invoice): DomPdf
    {
        $order->loadMissing(['items', 'transactions']);

        $adjustments = $order->items->map(function (OrderItem $item): array {
            $actual = $item->actual_weight_lb;

            return [
                'name' => $item->product_name,
                'estimated_weight' => $item->estimated_weight_lb->toDecimal(),
                'actual_weight' => $actual?->toDecimal(),
                'difference_weight' => $actual?->minus($item->estimated_weight_lb)->toDecimal(),
                'difference_cents' => $item->final_cents === null ? null : $item->final_cents - $item->estimated_cents,
            ];
        });

        return Pdf::loadView('invoices.pdf', [
            'order' => $order,
            'invoice' => $invoice,
            'adjustments' => $adjustments,
            'adjustmentTotalCents' => $order->final_cents === null ? 0 : $order->final_cents - $order->estimated_cents,
        ])->setPaper('letter');
    }
}
