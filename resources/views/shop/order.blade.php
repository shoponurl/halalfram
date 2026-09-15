@php($c = \App\Support\Cents::class)
@php($s = \App\Enums\OrderStatus::class)
@php($fs = \App\Enums\FulfilmentStatus::class)
<x-layouts.shop :title="'Order '.$order->number">
    <p class="text-xs font-bold uppercase tracking-[.18em] text-ink-500">Order {{ $order->number }}</p>
    <h1 class="mt-2 font-display text-4xl font-semibold">{{ $order->status->label() }}</h1>
    @if ($order->scheduled_date)
        <p class="mt-1 text-sm text-ink-500">Scheduled for {{ $order->scheduled_date->format('l, M j') }}</p>
    @endif

    <div class="mt-3 max-w-2xl text-ink-600">
        @switch($order->status)
            @case($s::PendingPayment)
                <p>We haven’t received your card authorization yet. <a class="font-semibold text-brand-700 underline" href="{{ route('checkout.pay', $order) }}">Continue to payment</a>.</p>
                @break
            @case($s::Authorized)
            @case($s::QcFailed)
            @case($s::QcPassed)
            @case($s::NeedsReview)
            @case($s::Settling)
                <p>JazakAllah khair, {{ strtok($order->customer_name, ' ') }}! A hold of {{ $c::format($order->hold_cents) }} is on your card. We’ll charge the actual weight once your order is cut, and call {{ $order->customer_phone }} when it’s ready for pickup.</p>
                @break
            @case($s::AwaitingBalance)
                <p>Your order weighed more than the estimate. We charged {{ $c::format($order->captured_cents) }}. Please pay the remaining <strong>{{ $c::format($order->balance_due_cents) }}</strong>.</p>
                @if ($order->balance_payment_url)
                    <a href="{{ $order->balance_payment_url }}" class="mt-4 inline-flex h-12 items-center rounded-full bg-brand-800 px-8 text-sm font-bold text-white hover:bg-brand-700">Pay {{ $c::format($order->balance_due_cents) }}</a>
                @endif
                @break
            @case($s::Completed)
                <p>Paid in full: {{ $c::format($order->totalChargedCents()) }}. Your invoice shows the estimate and the weight adjustment.</p>
                @break
            @default
                <p>Please call (267) 307-3777 if you have any questions about this order.</p>
        @endswitch
    </div>

    <div class="mt-8 overflow-x-auto rounded-3xl bg-white ring-1 ring-bone-200">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wide text-ink-500">
                <tr><th class="px-5 py-3">Item</th><th>Estimated</th><th>Actual weight</th><th class="px-5 text-right">Price</th></tr>
            </thead>
            <tbody class="divide-y divide-bone-200">
                @foreach ($order->items as $item)
                    <tr>
                        <td class="px-5 py-3 font-semibold">
                            {{ $item->quantity }} × {{ $item->product_name }}
                            @if ($item->cut_option_name || $item->offal_option_name || $item->packing_option_name)
                                <br><span class="text-xs font-normal text-ink-500">{{ collect([$item->cut_option_name, $item->offal_option_name, $item->packing_option_name])->filter()->implode(' · ') }}</span>
                            @endif
                        </td>
                        <td>{{ rtrim(rtrim($item->estimated_weight_lb->toDecimal(), '0'), '.') }} lb · {{ $c::format($item->estimated_cents) }}</td>
                        <td>{{ $item->actual_weight_lb ? rtrim(rtrim($item->actual_weight_lb->toDecimal(), '0'), '.').' lb' : 'Not weighed yet' }}</td>
                        <td class="px-5 text-right font-bold tabular-nums">{{ $c::format($item->final_cents ?? $item->estimated_cents) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <dl class="mt-6 ml-auto max-w-sm space-y-1.5 text-sm">
        <div class="flex justify-between"><dt class="text-ink-600">Estimated</dt><dd class="tabular-nums">{{ $c::format($order->estimated_cents) }}</dd></div>
        <div class="flex justify-between"><dt class="text-ink-600">Card hold</dt><dd class="tabular-nums">{{ $c::format($order->hold_cents) }}</dd></div>
        @if ($order->final_cents !== null)
            <div class="flex justify-between border-t border-bone-200 pt-2 text-base"><dt class="font-bold">Actual total</dt><dd class="font-extrabold tabular-nums">{{ $c::format($order->final_cents) }}</dd></div>
        @endif
    </dl>

    @if (in_array($order->status, [$s::Completed, $s::AwaitingBalance], true))
        <p class="mt-6 text-right"><a href="{{ route('orders.invoice', $order) }}" class="font-semibold text-brand-700 underline">Download invoice (PDF)</a></p>
    @endif

    @if ($order->fulfilment_status !== $fs::AwaitingFulfilment || $order->fulfilment === 'delivery')
        <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-bone-200">
            <h2 class="font-display text-xl font-semibold">{{ $order->fulfilment === 'delivery' ? 'Delivery' : 'Pickup' }}</h2>
            <p class="mt-1 text-ink-600">{{ $order->fulfilment_status->label() }}</p>

            @if ($order->fulfilment === 'delivery')
                <p class="mt-2 text-sm text-ink-500">{{ $order->fullDeliveryAddress() }}</p>
                @if ($order->deliverySlot)
                    <p class="text-sm text-ink-500">{{ $order->deliverySlot->label() }}</p>
                @endif
            @endif

            @if ($order->fulfilment_status === $fs::OutForDelivery && $order->delivery_otp)
                <p class="mt-4 rounded-xl bg-halal-50 p-3 text-sm font-semibold text-halal-700">
                    Give this code to the driver when your order arrives: <span class="text-lg tracking-widest">{{ $order->delivery_otp }}</span>
                </p>
            @endif

            @if ($order->fulfilment_status === $fs::DeliveryFailed)
                <p class="mt-4 text-sm text-brand-700">We tried to deliver and missed you — we'll be in touch to reschedule, or call (267) 307-3777.</p>
            @endif

            @if (in_array($order->fulfilment_status, [$fs::Returned, $fs::MissedPickup, $fs::Refunded], true))
                <p class="mt-4 text-sm text-ink-600">
                    This order wasn't completed.
                    {{ $order->refunded_cents > 0 ? 'A refund of '.$c::format($order->refunded_cents).' has been issued.' : 'A refund is being processed.' }}
                </p>
            @endif
        </div>
    @endif
</x-layouts.shop>
