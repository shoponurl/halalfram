@php($c = \App\Support\Cents::class)
@php($lb = fn (?string $w) => $w === null ? '—' : rtrim(rtrim($w, '0'), '.').' lb')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #16181B; }
        h1 { font-size: 22px; margin: 0; color: #C8321A; }
        h2 { font-size: 13px; margin: 22px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 9px; text-transform: uppercase; color: #5B6168; border-bottom: 1px solid #DCC3AE; padding: 6px 4px; }
        td { padding: 6px 4px; border-bottom: 1px solid #F2DDCC; vertical-align: top; }
        .r { text-align: right; }
        .muted { color: #5B6168; }
        .totals td { border: 0; padding: 3px 4px; }
        .grand td { font-weight: bold; font-size: 13px; border-top: 1px solid #16181B; }
        .box { background: #FFF8F2; padding: 10px; margin-top: 18px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="border:0">
                <h1>Halal Brothers</h1>
                <div class="muted">Live Poultry &amp; Meat · 3 Kelly Street, Lansdowne, PA 19050 · (267) 307-3777</div>
            </td>
            <td class="r" style="border:0">
                <strong style="font-size:16px">INVOICE</strong><br>
                {{ $invoice->number }}<br>
                <span class="muted">Issued {{ $invoice->issued_at->timezone('America/New_York')->format('M j, Y') }}</span><br>
                <span class="muted">Order {{ $order->number }} · {{ $order->created_at->timezone('America/New_York')->format('M j, Y') }}</span>
            </td>
        </tr>
    </table>

    <p><strong>Bill to:</strong> {{ $order->customer_name }} · {{ $order->customer_email }} · {{ $order->customer_phone }}<br>
        <span class="muted">Fulfilment: store pickup</span></p>

    <h2>Original order (estimated)</h2>
    <table>
        <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Price / lb</th><th class="r">Est. weight</th><th class="r">Estimated</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>
                        {{ $item->product_name }}
                        @if ($item->cut_option_name || $item->offal_option_name || $item->packing_option_name)
                            <br><span class="muted">{{ collect([$item->cut_option_name, $item->offal_option_name, $item->packing_option_name])->filter()->implode(' · ') }}</span>
                        @endif
                    </td>
                    <td class="r">{{ $item->quantity }}</td>
                    <td class="r">{{ $c::format($item->price_per_lb_cents) }}</td>
                    <td class="r">{{ $lb($item->estimated_weight_lb->toDecimal()) }}</td>
                    <td class="r">{{ $c::format($item->estimated_cents) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Weight adjustments</h2>
    <table>
        <thead><tr><th>Item</th><th class="r">Estimated</th><th class="r">Actual</th><th class="r">Difference</th><th class="r">Adjustment</th></tr></thead>
        <tbody>
            @foreach ($adjustments as $a)
                <tr>
                    <td>{{ $a['name'] }}</td>
                    <td class="r">{{ $lb($a['estimated_weight']) }}</td>
                    <td class="r">{{ $lb($a['actual_weight']) }}</td>
                    <td class="r">{{ $a['difference_weight'] === null ? '—' : (str_starts_with($a['difference_weight'], '-') ? '' : '+').$lb($a['difference_weight']) }}</td>
                    <td class="r">{{ $a['difference_cents'] === null ? '—' : ($a['difference_cents'] >= 0 ? '+' : '').$c::format($a['difference_cents']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="width: 55%; margin-left: 45%; margin-top: 12px;">
        <tr><td>Estimated subtotal</td><td class="r">{{ $c::format($order->estimated_cents) }}</td></tr>
        <tr><td>Weight adjustment</td><td class="r">{{ ($adjustmentTotalCents >= 0 ? '+' : '').$c::format($adjustmentTotalCents) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="r">{{ $c::format($order->final_cents) }}</td></tr>
        @if ($order->written_off_cents > 0)
            <tr><td class="muted">Waived (below card minimum)</td><td class="r muted">−{{ $c::format($order->written_off_cents) }}</td></tr>
        @endif
    </table>

    <h2>Payments</h2>
    <table>
        <thead><tr><th>Date</th><th>Description</th><th class="r">Amount</th></tr></thead>
        <tbody>
            @foreach ($order->transactions->where('status', 'succeeded') as $t)
                @continue(in_array($t->type->value, ['authorization', 'balance_link', 'write_off', 'cancel'], true))
                <tr>
                    <td>{{ $t->created_at->timezone('America/New_York')->format('M j, Y g:i A') }}</td>
                    <td>{{ ['capture' => 'Card payment (captured from hold)', 'extra_charge' => 'Card payment (weight above hold)', 'balance_paid' => 'Balance payment link', 'refund' => 'Refund'][$t->type->value] ?? $t->type->value }}</td>
                    <td class="r">{{ $t->type->value === 'refund' ? '−'.$c::format($t->amount_cents) : $c::format($t->amount_cents) }}</td>
                </tr>
            @endforeach
            <tr><td></td><td><strong>Total paid</strong></td><td class="r"><strong>{{ $c::format($order->totalChargedCents()) }}</strong></td></tr>
            @if ($order->balance_due_cents > 0)
                <tr><td></td><td><strong>Balance due</strong></td><td class="r"><strong>{{ $c::format($order->balance_due_cents) }}</strong></td></tr>
            @endif
        </tbody>
    </table>

    <div class="box">
        Meat is sold by weight. Your card was authorized for the estimate plus {{ rtrim(rtrim($order->hold_tolerance_pct, '0'), '.') }}% and charged for the actual weight after cutting; any unused part of the hold was released by your bank.
        100% zabiha halal, hand-slaughtered.
    </div>
</body>
</html>
