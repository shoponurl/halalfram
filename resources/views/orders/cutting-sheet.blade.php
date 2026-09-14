@php($lb = fn ($w) => $w === null ? '—' : rtrim(rtrim($w->toDecimal(), '0'), '.').' lb')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cutting sheet — {{ $order->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #16181B; }
        h1 { font-size: 20px; margin: 0; color: #C8321A; }
        .muted { color: #5B6168; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { text-align: left; font-size: 9px; text-transform: uppercase; color: #5B6168; border-bottom: 1px solid #DCC3AE; padding: 6px 4px; }
        td { padding: 8px 4px; border-bottom: 1px solid #F2DDCC; vertical-align: top; }
        .item-name { font-weight: bold; font-size: 13px; }
        .options { margin-top: 4px; }
        .options span { display: inline-block; background: #FFF8F2; border: 1px solid #DCC3AE; border-radius: 4px; padding: 2px 6px; margin: 2px 4px 0 0; font-size: 11px; }
        .box { background: #FFF8F2; padding: 10px; margin-top: 18px; }
    </style>
</head>
<body>
    <h1>Cutting sheet — {{ $order->number }}</h1>
    <p class="muted">
        {{ $order->customer_name }}
        @if ($order->lead_time_days > 0) · needs about {{ $order->lead_time_days }} extra day{{ $order->lead_time_days === 1 ? '' : 's' }} to prepare @endif
    </p>

    <table>
        <thead><tr><th>Piece</th><th>Qty</th><th>Est. weight</th><th>Instructions</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td class="item-name">{{ $item->product_name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $lb($item->estimated_weight_lb) }} / piece</td>
                    <td>
                        @if (! $item->cut_option_name && ! $item->offal_option_name && ! $item->packing_option_name)
                            <span class="muted">Standard — no special instructions</span>
                        @else
                            <div class="options">
                                @if ($item->cut_option_name) <span>Cut: {{ $item->cut_option_name }}</span> @endif
                                @if ($item->offal_option_name) <span>Offal: {{ $item->offal_option_name }}</span> @endif
                                @if ($item->packing_option_name) <span>Pack: {{ $item->packing_option_name }}</span> @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="box">
        Whose name: <strong>{{ $order->customer_name }}</strong> · Order {{ $order->number }} · {{ $order->created_at->timezone('America/New_York')->format('M j, Y') }}
    </div>
</body>
</html>
