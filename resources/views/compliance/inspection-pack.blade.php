@php($lb = fn ($w) => $w === null ? '—' : rtrim(rtrim($w->toDecimal(), '0'), '.').' lb')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inspection pack — {{ $from->toDateString() }} to {{ $to->toDateString() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #16181B; }
        h1 { font-size: 18px; margin: 0; color: #C8321A; }
        h2 { font-size: 13px; margin: 20px 0 6px; color: #16181B; }
        .muted { color: #5B6168; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { text-align: left; font-size: 8px; text-transform: uppercase; color: #5B6168; border-bottom: 1px solid #DCC3AE; padding: 4px 3px; }
        td { padding: 5px 3px; border-bottom: 1px solid #F2DDCC; vertical-align: top; }
    </style>
</head>
<body>
    <h1>Inspection pack</h1>
    <p class="muted">{{ $from->toDateString() }} to {{ $to->toDateString() }} · Halal Brothers Live Poultry &amp; Meat, 3 Kelly Street, Lansdowne, PA 19050 · generated {{ now()->timezone('America/New_York')->format('M j, Y g:i A') }}</p>

    <h2>Animals ({{ $animals->count() }})</h2>
    <table>
        <thead><tr><th>Tag</th><th>Species</th><th>Slaughter date</th><th>Live weight</th><th>Dressed weight</th></tr></thead>
        <tbody>
            @forelse ($animals as $animal)
                <tr>
                    <td>{{ $animal->tag_id }}</td>
                    <td>{{ $animal->species->label() }}</td>
                    <td>{{ $animal->slaughter_date->toDateString() }}</td>
                    <td>{{ $lb($animal->live_weight_lb) }}</td>
                    <td>{{ $lb($animal->dressed_weight_lb) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">None in this range.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Lots ({{ $lots->count() }})</h2>
    <table>
        <thead><tr><th>Lot</th><th>Product</th><th>Animal</th><th>Storage</th><th>Pack date</th><th>Use-by</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($lots as $lot)
                <tr>
                    <td>{{ $lot->lot_number }}</td>
                    <td>{{ $lot->product->name }}</td>
                    <td>{{ $lot->animal?->tag_id ?? '—' }}</td>
                    <td>{{ $lot->storage_location->label() }}</td>
                    <td>{{ $lot->pack_date->toDateString() }}</td>
                    <td>{{ $lot->use_by_date->toDateString() }}</td>
                    <td>{{ $lot->status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">None in this range.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>QC / temperature checks ({{ $qcChecks->count() }})</h2>
    <table>
        <thead><tr><th>Date</th><th>Order</th><th>Result</th><th>Temperature (°F)</th><th>Inspector</th></tr></thead>
        <tbody>
            @forelse ($qcChecks as $check)
                <tr>
                    <td>{{ $check->created_at->toDateString() }}</td>
                    <td>{{ $check->order->number }}</td>
                    <td>{{ $check->passed ? 'Passed' : 'Failed' }}</td>
                    <td>{{ $check->temperature_f ?? '—' }}</td>
                    <td>{{ $check->inspector->name }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">None in this range.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
