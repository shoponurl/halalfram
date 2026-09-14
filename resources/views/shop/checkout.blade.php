@php($c = \App\Support\Cents::class)
<x-layouts.shop title="Checkout">
    <h1 class="font-display text-4xl font-semibold">Checkout</h1>
    <p class="mt-2 text-ink-600">Store pickup at 3 Kelly Street, Lansdowne. No account needed.</p>

    <div class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_380px]">
        <form method="post" action="{{ route('checkout.store') }}" class="space-y-5 rounded-3xl bg-white p-6 ring-1 ring-bone-200 sm:p-8">
            @csrf
            {{-- Cross-check only: the server re-prices the cart and rejects the order if this differs --}}
            <input type="hidden" name="expected_hold_cents" value="{{ $quote['hold_cents'] }}" />

            @foreach ([['customer_name', 'Full name', 'text', 'name'], ['customer_email', 'Email (for your receipt and invoice)', 'email', 'email'], ['customer_phone', 'Phone', 'tel', 'tel']] as [$name, $label, $type, $auto])
                <label class="block text-sm font-semibold">
                    {{ $label }}
                    <input name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}" autocomplete="{{ $auto }}" required
                           class="mt-1.5 block h-12 w-full rounded-xl border border-bone-300 bg-bone-50 px-3 text-base focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-100" />
                </label>
            @endforeach
            <label class="block text-sm font-semibold">
                Notes for the butcher (optional)
                <input name="notes" value="{{ old('notes') }}" maxlength="500" class="mt-1.5 block h-12 w-full rounded-xl border border-bone-300 bg-bone-50 px-3 text-base focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-100" />
            </label>

            @include('shop.partials.catch-weight-explainer', ['estimateCents' => $quote['estimated_cents'], 'holdCents' => $quote['hold_cents'], 'tolerance' => $policy['hold_tolerance_pct']])

            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="agree_catch_weight" value="1" required class="mt-1 h-4 w-4 accent-brand-700" @checked(old('agree_catch_weight')) />
                <span>I understand the final price is based on actual weight, and that a hold of {{ $c::format($quote['hold_cents']) }} is placed on my card until my order is weighed.</span>
            </label>

            @if ($paymentsReady)
                <button class="h-12 w-full rounded-full bg-brand-800 text-sm font-bold text-white hover:bg-brand-700">Continue to secure payment</button>
            @else
                <p class="rounded-xl bg-brand-50 p-3 text-sm font-semibold text-brand-700">Online payment isn’t set up yet. Please call (267) 307-3777 to order.</p>
            @endif
        </form>

        <aside class="rounded-3xl bg-white p-6 ring-1 ring-bone-200">
            <h2 class="font-display text-xl font-semibold">Order summary</h2>
            <ul class="mt-4 space-y-3 text-sm">
                @foreach ($quote['lines'] as $line)
                    <li class="flex justify-between gap-3">
                        <span>
                            {{ $line['quantity'] }} × {{ $line['product']->name }}<br>
                            <span class="text-ink-500">~{{ rtrim(rtrim($line['weight']->toDecimal(), '0'), '.') }} lb</span>
                            @if ($line['cut_option'] || $line['offal_option'] || $line['packing_option'])
                                <br><span class="text-ink-500">{{ collect([$line['cut_option']?->name, $line['offal_option']?->name, $line['packing_option']?->name])->filter()->implode(' · ') }}</span>
                            @endif
                        </span>
                        <span class="font-bold tabular-nums">{{ $c::format($line['estimated_cents']) }}</span>
                    </li>
                @endforeach
            </ul>
            @if ($quote['lead_time_days'] > 0)
                <p class="mt-3 text-xs font-semibold text-halal-600">Your options need about {{ $quote['lead_time_days'] }} extra day{{ $quote['lead_time_days'] === 1 ? '' : 's' }} to prepare before pickup.</p>
            @endif
            <dl class="mt-4 space-y-1.5 border-t border-bone-200 pt-4 text-sm">
                <div class="flex justify-between"><dt class="text-ink-600">Estimated total</dt><dd class="font-bold tabular-nums">{{ $c::format($quote['estimated_cents']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-600">Card hold (estimate + {{ $policy['hold_tolerance_pct'] }}%)</dt><dd class="tabular-nums">{{ $c::format($quote['hold_cents']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-600">Store pickup</dt><dd>Free</dd></div>
            </dl>
        </aside>
    </div>
</x-layouts.shop>
