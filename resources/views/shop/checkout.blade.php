@php($c = \App\Support\Cents::class)
@php($totalEstimatedCents = $quote['estimated_cents'] + $deliveryFeeCents)
@php($totalHoldCents = $quote['hold_cents'] + $deliveryFeeCents)
<x-layouts.shop title="Checkout">
    <h1 class="font-display text-4xl font-semibold">Checkout</h1>
    <p class="mt-2 text-ink-600">Store pickup at 3 Kelly Street, Lansdowne, or local delivery. No account needed.</p>

    {{-- Fulfilment method — a small GET reload so the total below always matches what's chosen --}}
    <form method="get" action="{{ route('checkout.create') }}" class="mt-6 flex flex-wrap items-end gap-4 rounded-3xl bg-white p-5 ring-1 ring-bone-200">
        <div class="flex gap-4">
            <label class="flex items-center gap-2 text-sm font-semibold">
                <input type="radio" name="fulfilment" value="pickup" class="accent-brand-700" onchange="this.form.requestSubmit()" @checked($fulfilmentMethod === 'pickup') /> Store pickup
            </label>
            <label class="flex items-center gap-2 text-sm font-semibold">
                <input type="radio" name="fulfilment" value="delivery" class="accent-brand-700" onchange="this.form.requestSubmit()" @checked($fulfilmentMethod === 'delivery') /> Local delivery
            </label>
        </div>
        @if ($fulfilmentMethod === 'delivery')
            <label class="text-sm font-semibold">
                Zip code
                <input name="zip" value="{{ $zip }}" maxlength="5" inputmode="numeric" placeholder="19050"
                       class="mt-1.5 block h-10 w-28 rounded-xl border border-bone-300 px-3 text-base focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100" />
            </label>
            <button class="h-10 rounded-full bg-brand-800 px-5 text-sm font-bold text-white hover:bg-brand-700">Check</button>
        @endif
    </form>
    @if ($zipError)
        <p class="mt-3 rounded-xl bg-brand-50 p-3 text-sm font-semibold text-brand-700">{{ $zipError }}</p>
    @endif

    @if ($fulfilmentMethod === 'pickup' || ($fulfilmentMethod === 'delivery' && $deliveryZone))
    <div class="mt-6 grid items-start gap-8 lg:grid-cols-[1fr_380px]">
        <form method="post" action="{{ route('checkout.store') }}" class="space-y-5 rounded-3xl bg-white p-6 ring-1 ring-bone-200 sm:p-8">
            @csrf
            {{-- Cross-check only: the server re-prices the cart and rejects the order if this differs --}}
            <input type="hidden" name="expected_hold_cents" value="{{ $totalHoldCents }}" />
            <input type="hidden" name="fulfilment_method" value="{{ $fulfilmentMethod }}" />

            @foreach ([['customer_name', 'Full name', 'text', 'name'], ['customer_email', 'Email (for your receipt and invoice)', 'email', 'email'], ['customer_phone', 'Phone', 'tel', 'tel']] as [$name, $label, $type, $auto])
                <label class="block text-sm font-semibold">
                    {{ $label }}
                    <input name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}" autocomplete="{{ $auto }}" required
                           class="mt-1.5 block h-12 w-full rounded-xl border border-bone-300 bg-bone-50 px-3 text-base focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-100" />
                </label>
            @endforeach

            @if ($fulfilmentMethod === 'delivery')
                <input type="hidden" name="delivery_zip" value="{{ $zip }}" />
                <input type="hidden" name="delivery_state" value="PA" />
                <label class="block text-sm font-semibold">
                    Street address
                    <input name="delivery_address_line1" value="{{ old('delivery_address_line1') }}" required
                           class="mt-1.5 block h-12 w-full rounded-xl border border-bone-300 bg-bone-50 px-3 text-base focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-100" />
                </label>
                <label class="block text-sm font-semibold">
                    Apt / unit (optional)
                    <input name="delivery_address_line2" value="{{ old('delivery_address_line2') }}"
                           class="mt-1.5 block h-12 w-full rounded-xl border border-bone-300 bg-bone-50 px-3 text-base focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-100" />
                </label>
                <label class="block text-sm font-semibold">
                    City
                    <input name="delivery_city" value="{{ old('delivery_city') }}" required
                           class="mt-1.5 block h-12 w-full rounded-xl border border-bone-300 bg-bone-50 px-3 text-base focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-100" />
                </label>

                <fieldset>
                    <legend class="text-sm font-bold">Delivery window</legend>
                    <div class="mt-2 space-y-1.5">
                        @forelse ($slots as $slot)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="delivery_slot_id" value="{{ $slot->id }}" required class="accent-brand-700" />
                                {{ $slot->label() }}
                            </label>
                        @empty
                            <p class="text-sm text-ink-500">No delivery windows are open right now — please check back soon or choose store pickup.</p>
                        @endforelse
                    </div>
                </fieldset>
            @endif

            <label class="block text-sm font-semibold">
                Notes for the butcher (optional)
                <input name="notes" value="{{ old('notes') }}" maxlength="500" class="mt-1.5 block h-12 w-full rounded-xl border border-bone-300 bg-bone-50 px-3 text-base focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-100" />
            </label>

            @include('shop.partials.catch-weight-explainer', ['estimateCents' => $totalEstimatedCents, 'holdCents' => $totalHoldCents, 'tolerance' => $policy['hold_tolerance_pct']])

            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="agree_catch_weight" value="1" required class="mt-1 h-4 w-4 accent-brand-700" @checked(old('agree_catch_weight')) />
                <span>I understand the final price is based on actual weight, and that a hold of {{ $c::format($totalHoldCents) }} is placed on my card until my order is weighed.</span>
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
                <div class="flex justify-between"><dt class="text-ink-600">Items</dt><dd class="tabular-nums">{{ $c::format($quote['estimated_cents']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-600">{{ $fulfilmentMethod === 'delivery' ? ($deliveryZone->name ?? 'Delivery') : 'Store pickup' }}</dt>
                    <dd>{{ $deliveryFeeCents > 0 ? $c::format($deliveryFeeCents) : 'Free' }}</dd></div>
                <div class="flex justify-between border-t border-bone-200 pt-1.5"><dt class="font-bold text-ink-900">Estimated total</dt><dd class="font-bold tabular-nums">{{ $c::format($totalEstimatedCents) }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-600">Card hold (estimate + {{ $policy['hold_tolerance_pct'] }}%)</dt><dd class="tabular-nums">{{ $c::format($totalHoldCents) }}</dd></div>
            </dl>
        </aside>
    </div>
    @endif
</x-layouts.shop>
