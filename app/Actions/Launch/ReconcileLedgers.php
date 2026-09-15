<?php

declare(strict_types=1);

namespace App\Actions\Launch;

use App\Actions\Action;
use App\Enums\PaymentTransactionType;
use App\Enums\StockMovementType;
use App\Models\Lot;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\StoreCreditAccount;
use App\Support\Weight;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Guideline ch. 8 launch gate: "reconciliation matches on production data". Every cached balance in
 * this app sits over an append-only ledger (rule 04); this re-derives each one from its ledger and
 * reports anything that disagrees. Read-only — it never "fixes" a number, because a mismatch means
 * something wrote money or stock outside the actions, and that needs a human to find out what.
 *
 * Checks: order money columns vs payment_transactions, lot on-hand/reserved vs stock_movements,
 * store-credit balances vs store_credit_events, and each weighed item vs its latest weight event.
 * Not checked: written_off_cents — a cash order written off at a missed pickup (S05) has no ledger row.
 */
final class ReconcileLedgers extends Action
{
    /** @return list<string> one line per mismatch; empty means every ledger reconciles */
    public function handle(): array
    {
        return [...$this->orders(), ...$this->lots(), ...$this->storeCredit(), ...$this->weights()];
    }

    /** @return list<string> */
    private function orders(): array
    {
        $mismatches = [];

        Order::query()->select(['id', 'number', 'captured_cents', 'extra_charged_cents', 'balance_paid_cents', 'refunded_cents'])
            ->chunkById(500, function (Collection $orders) use (&$mismatches): void {
                /** @var Collection<int, Order> $orders */
                $sums = PaymentTransaction::query()
                    ->whereIn('order_id', $orders->pluck('id'))
                    ->where('status', PaymentTransaction::SUCCEEDED)
                    ->groupBy('order_id', 'type')
                    ->get(['order_id', 'type', DB::raw('SUM(amount_cents) as total')])
                    ->groupBy('order_id');

                foreach ($orders as $order) {
                    $ledger = fn (PaymentTransactionType ...$types): int => (int) ($sums->get($order->id)?->whereIn('type', $types)->sum('total') ?? 0);

                    $expected = [
                        'captured_cents' => $ledger(PaymentTransactionType::Capture, PaymentTransactionType::CashReceived),
                        'extra_charged_cents' => $ledger(PaymentTransactionType::ExtraCharge),
                        'balance_paid_cents' => $ledger(PaymentTransactionType::BalancePaid),
                        'refunded_cents' => $ledger(PaymentTransactionType::Refund),
                    ];
                    foreach ($expected as $column => $fromLedger) {
                        if ((int) $order->{$column} !== $fromLedger) {
                            $mismatches[] = "Order {$order->number}: {$column} is {$order->{$column}} but the payment ledger adds up to {$fromLedger}";
                        }
                    }
                }
            });

        return $mismatches;
    }

    /** @return list<string> */
    private function lots(): array
    {
        $mismatches = [];

        Lot::query()->select(['id', 'lot_number', 'on_hand_weight_lb', 'reserved_weight_lb'])
            ->chunkById(500, function (Collection $lots) use (&$mismatches): void {
                /** @var Collection<int, Lot> $lots */
                $sums = DB::table('stock_movements')
                    ->whereIn('lot_id', $lots->pluck('id'))
                    ->groupBy('lot_id', 'type')
                    ->get(['lot_id', 'type', DB::raw('SUM(weight_lb) as total')])
                    ->groupBy('lot_id');

                foreach ($lots as $lot) {
                    $ledger = function (StockMovementType $type) use ($sums, $lot): Weight {
                        $row = $sums->get($lot->id)?->firstWhere('type', $type->value);

                        return $row === null ? Weight::zero() : Weight::pounds((string) $row->total);
                    };

                    $onHand = $ledger(StockMovementType::Received)->minus($ledger(StockMovementType::Consumed))->minus($ledger(StockMovementType::Wastage));
                    $reserved = $ledger(StockMovementType::Reserved)->minus($ledger(StockMovementType::Released));

                    if ($lot->on_hand_weight_lb->compareTo($onHand) !== 0) {
                        $mismatches[] = "Lot {$lot->lot_number}: on hand is {$lot->on_hand_weight_lb} but the stock ledger adds up to {$onHand}";
                    }
                    if ($lot->reserved_weight_lb->compareTo($reserved) !== 0) {
                        $mismatches[] = "Lot {$lot->lot_number}: reserved is {$lot->reserved_weight_lb} but the stock ledger adds up to {$reserved}";
                    }
                }
            });

        return $mismatches;
    }

    /** @return list<string> */
    private function storeCredit(): array
    {
        $ledger = DB::table('store_credit_events')->groupBy('customer_email')
            ->pluck(DB::raw('SUM(amount_cents)'), 'customer_email');

        $mismatches = [];
        foreach (StoreCreditAccount::query()->get(['customer_email', 'balance_cents']) as $account) {
            $fromLedger = (int) ($ledger[$account->customer_email] ?? 0);
            if ($account->balance_cents !== $fromLedger) {
                // Account key only, never the email itself — this output lands in logs and alert emails.
                $mismatches[] = 'Store credit account #'.substr(hash('sha256', $account->customer_email), 0, 10).": balance is {$account->balance_cents} but the credit ledger adds up to {$fromLedger}";
            }
        }

        return $mismatches;
    }

    /** @return list<string> */
    private function weights(): array
    {
        $latestEvents = DB::table('weight_events')->select(DB::raw('MAX(id)'))->groupBy('order_item_id');

        return array_values(DB::table('weight_events')
            ->join('order_items', 'order_items.id', '=', 'weight_events.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('weight_events.id', $latestEvents)
            ->where(fn ($q) => $q->whereNull('order_items.actual_weight_lb')->orWhereColumn('order_items.actual_weight_lb', '!=', 'weight_events.weight_lb'))
            ->get(['orders.number', 'order_items.id', 'order_items.actual_weight_lb', 'weight_events.weight_lb'])
            ->map(fn (object $row): string => "Order {$row->number}, item #{$row->id}: actual weight is ".($row->actual_weight_lb ?? 'empty')." lb but its latest weight event says {$row->weight_lb} lb")
            ->all());
    }
}
