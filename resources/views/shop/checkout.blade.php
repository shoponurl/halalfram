@php($c = \App\Support\Cents::class)
@php($totalEstimatedCents = $quote['estimated_cents'] + $deliveryFeeCents + $shippingRateCents)
@php($totalHoldCents = $quote['hold_cents'] + $deliveryFeeCents + $shippingRateCents)
@php($creditToApplyCents = min($availableCreditCents, max(0, $totalHoldCents - $policy['minimum_charge_cents'])))
<x-layouts.shop title="Checkout">
    <h1 class="font-display text-4xl font-semibold">Checkout</h1>
    <p class="mt-2 text-ink-600">Store pickup at 3 Kelly Street, Lansdowne, local delivery, or overnight shipping nationwide. No account needed.</p>

    {{-- Fulfilment method — a small GET reload so the total below always matches what's chosen --}}
    <form method="get" action="{{ route('checkout.create') }}" class="mt-6 flex flex-wrap items-end gap-4 rounded-3xl bg-white p-5 ring-1 ring-bone-200">
        <div class="flex gap-4">
            <label class="flex items-center gap-2 text-sm font-semibold">
                <input type="radio" name="fulfilment" value="pickup" class="accent-brand-700" onchange="this.form.requestSubmit()" @checked($fulfilmentMethod === 'pickup') /> Store pickup
            </label>
            @if ($deliveryAvailable)
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="radio" name="fulfilment" value="delivery" class="accent-brand-700" onchange="this.form.requestSubmit()" @checked($fulfilmentMethod === 'delivery') /> Local delivery
                </label>
            @endif
            @if ($shippingReady)
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="radio" name="fulfilment" value="shipping" class="accent-brand-700" onchange="this.form.requestSubmit()" @checked($fulfilmentMethod === 'shipping') /> Ship nationwide (overnight)
                </label>
            @endif
        </div>
        @if ($fulfilmentMethod === 'delivery')
            <label class="text-sm font-semibold">
                Zip code
                <input name="zip" value="{{ $zip }}" maxlength="5" inputmode="numeric" placeholder="19050"
                       class="mt-1.5 block h-10 w-28 rounded-xl border border-bone-300 px-3 text-base focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100" />
            </label>
            <button class="h-10 rounded-full bg-brand-800 px-5 text-sm font-bold text-white hover:bg-brand-700">Check</button>
        @elseif ($fulfilmentMethod === 'shipping')
            <label class="text-sm font-semibold">
                State
                <input name="ship_state" value="{{ $shipState }}" maxlength="2" placeholder="NY"
                       class="mt-1.5 block h-10 w-20 rounded-xl border border-bone-300 px-3 text-base uppercase focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100" />
            </label>
            <label class="text-sm font-semibold">
                Zip code
                <input name="ship_zip" value="{{ $shipZip }}" maxlength="5" inputmode="numeric" placeholder="10001"
                       class="mt-1.5 block h-10 w-28 rounded-xl border border-bone-300 px-3 text-base focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100" />
            </label>
            <button class="h-10 rounded-full bg-brand-800 px-5 text-sm font-bold text-white hover:bg-brand-700">Get shipping rate</button>
        @endif
    </form>
    @if ($zipError)
        <p class="mt-3 rounded-xl bg-brand-50 p-3 text-sm font-semibold text-brand-700">{{ $zipError }}</p>
    @endif
    @if ($shipError)
        <p class="mt-3 rounded-xl bg-brand-50 p-3 text-sm font-semibold text-brand-700">{{ $shipError }}</p>
    @endif

    {{-- Store credit (guideline ch. 7, S06). S09 self-audit SA-01: the balance is only shown after the
         customer proves the email is theirs by following a link sent to it. --}}
    @if ($creditEmail !== '')
        <p class="mt-3 rounded-3xl bg-white p-5 text-sm font-semibold ring-1 ring-bone-200">
            Store credit for {{ $creditEmail }}: <span class="text-halal-600">{{ $c::format($availableCreditCents) }} available</span>
        </p>
    @else
        <form method="post" action="{{ route('store-credit.send') }}" class="mt-3 flex flex-wrap items-end gap-3 rounded-3xl bg-white p-5 ring-1 ring-bone-200">
            @csrf
            <label class="text-sm font-semibold">
                Have store credit? We'll email you a link to use it
                <input name="credit_email" type="email" required placeholder="you@example.com"
                       class="mt-1.5 block h-10 w-56 rounded-xl border border-bone-300 px-3 text-base focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100" />
            </label>
            <button class="h-10 rounded-full bg-bone-200 px-5 text-sm font-bold text-ink-900 hover:bg-bone-300">Email me a link</button>
        </form>
    @endif

    @if ($fulfilmentMethod === 'pickup' || ($fulfilmentMethod === 'delivery' && $deliveryZone) || ($fulfilmentMethod === 'shipping' && $shippingRateCents > 0))
    <div class="mt-6 grid items-start gap-8 lg:grid-cols-[1fr_380px]">
        <form method="post" action="{{ route('checkout.store') }}" id="checkout-form" class="space-y-5 rounded-3xl bg-white p-6 ring-1 ring-bone-200 sm:p-8">
            @csrf
            {{-- Cross-check only: the server re-prices the cart and rejects the order if this differs --}}
            <input type="hidden" name="expected_hold_cents" id="expected_hold_cents" value="{{ $totalHoldCents }}" />
            <input type="hidden" name="fulfilment_method" value="{{ $fulfilmentMethod }}" />

            @foreach ([['customer_name', 'Full name', 'text', 'name'], ['customer_email', 'Email (for your receipt and invoice)', 'email', 'email'], ['customer_phone', 'Phone', 'tel', 'tel']] as [$name, $label, $type, $auto])
                <label class="block text-sm font-semibold">
                    {{ $label }}
                    <input name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}" autocomplete="{{ $auto }}" required
                           class="mt-1.5 block h-12 w-full rounded-xl border border-bone-300 bg-bone-50 px-3 text-base focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-100" />
                </label>
            @endforeach

            @if ($fulfilmentMethod === 'delivery' || $fulfilmentMethod === 'shipping')
                @if ($fulfilmentMethod === 'delivery')
                    <input type="hidden" name="delivery_zip" value="{{ $zip }}" />
                    <input type="hidden" name="delivery_state" value="PA" />
                @else
                    <input type="hidden" name="delivery_zip" value="{{ $shipZip }}" />
                    <input type="hidden" name="delivery_state" value="{{ $shipState }}" />
                @endif
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
            @endif

            @if ($fulfilmentMethod === 'delivery')
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

            <label class="block text-sm font-semibold">
                Coupon code (optional)
                <input name="coupon_code" value="{{ old('coupon_code') }}" maxlength="40" class="mt-1.5 block h-12 w-full rounded-xl border border-bone-300 bg-bone-50 px-3 text-base uppercase focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-100" />
                <span class="mt-1 block text-xs font-normal text-ink-500">Applied to your final total once your order is weighed.</span>
            </label>

            @if ($availableCreditCents > 0)
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" name="apply_store_credit" id="apply_store_credit" value="1" class="mt-1 h-4 w-4 accent-brand-700"
                           data-credit-cents="{{ $creditToApplyCents }}" data-hold-cents="{{ $totalHoldCents }}" @checked(old('apply_store_credit')) />
                    <span>Apply my available store credit ({{ $c::format($availableCreditCents) }}) — up to {{ $c::format($creditToApplyCents) }} off this hold.</span>
                </label>
                <p class="text-xs text-ink-500">Use {{ $creditEmail }} as your email above — store credit is tied to the email it was issued to.</p>
            @endif

            <fieldset>
                <legend class="text-sm font-bold">How would you like to pay?</legend>
                <div class="mt-2 space-y-1.5">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="payment_method" value="card" class="accent-brand-700" @checked(old('payment_method', 'card') === 'card') @disabled(! $cardReady) /> Card (Visa, Mastercard, Amex, Discover)
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="payment_method" value="paypal" class="accent-brand-700" @checked(old('payment_method') === 'paypal') @disabled(! $paypalReady) /> PayPal
                    </label>
                    @if ($fulfilmentMethod === 'pickup')
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="payment_method" value="cash" class="accent-brand-700" @checked(old('payment_method') === 'cash') /> Cash at pickup (orders under {{ $c::format($codMaxCents) }})
                        </label>
                    @endif
                </div>
            </fieldset>

            @if ($fulfilmentMethod === 'shipping')
                {{-- Owner decisions (guideline ch. 7, S08, recorded 2026-09-15): overnight only, full refund if it arrives warm. --}}
                <p class="rounded-xl bg-bone-50 p-3 text-xs text-ink-600">
                    Nationwide orders ship overnight only, frozen unless a product specifically ships chilled. If your order ever arrives warm, we'll issue a full refund — see our <a href="{{ route('legal.terms') }}" class="font-semibold text-brand-700 underline">shipping terms</a> for details.
                </p>
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" name="agree_perishable_shipping" value="1" required class="mt-1 h-4 w-4 accent-brand-700" @checked(old('agree_perishable_shipping')) />
                    <span>I've read the perishable shipping terms above.</span>
                </label>
            @endif
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-sm font-bold">Text/email updates</legend>
                <div class="mt-2 space-y-1.5">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="marketing_sms_opt_in" value="1" class="accent-brand-700" @checked(old('marketing_sms_opt_in')) /> Send me occasional deals by text
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="marketing_email_opt_in" value="1" class="accent-brand-700" @checked(old('marketing_email_opt_in')) /> Send me occasional deals by email
                    </label>
                    <p class="text-xs text-ink-500">Order updates (ready for pickup, out for delivery, receipts) are sent either way — reply STOP to any text to opt out of everything.</p>
                </div>
            </fieldset>

            @include('shop.partials.catch-weight-explainer', ['estimateCents' => $totalEstimatedCents, 'holdCents' => $totalHoldCents, 'tolerance' => $policy['hold_tolerance_pct']])

            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="agree_catch_weight" value="1" required class="mt-1 h-4 w-4 accent-brand-700" @checked(old('agree_catch_weight')) />
                <span>I understand the final price is based on actual weight, and that a hold of {{ $c::format($totalHoldCents) }} is placed until my order is weighed.</span>
            </label>

            {{-- Owner decision (guideline ch. 7, S07, recorded 2026-09-15): USDA-inspected facility. --}}
            <p class="rounded-xl bg-bone-50 p-3 text-xs text-ink-600">
                Halal Brothers Live Poultry &amp; Meat operates under full USDA inspection{{ $usdaEstablishmentNumber ? " (Est. No. {$usdaEstablishmentNumber})" : '' }}.
                All products are processed and sold in compliance with the Federal Meat Inspection Act and Pennsylvania Department of Agriculture requirements.
            </p>
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="agree_regulatory_notice" value="1" required class="mt-1 h-4 w-4 accent-brand-700" @checked(old('agree_regulatory_notice')) />
                <span>I've read the USDA inspection notice above.</span>
            </label>

            @if ($cardReady || $paypalReady)
                <button class="h-12 w-full rounded-full bg-brand-800 text-sm font-bold text-white hover:bg-brand-700">Continue to payment</button>
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
                <div class="flex justify-between"><dt class="text-ink-600">{{ match($fulfilmentMethod) { 'delivery' => $deliveryZone->name ?? 'Delivery', 'shipping' => 'Overnight shipping', default => 'Store pickup' } }}</dt>
                    <dd>{{ ($deliveryFeeCents + $shippingRateCents) > 0 ? $c::format($deliveryFeeCents + $shippingRateCents) : 'Free' }}</dd></div>
                <div class="flex justify-between border-t border-bone-200 pt-1.5"><dt class="font-bold text-ink-900">Estimated total</dt><dd class="font-bold tabular-nums">{{ $c::format($totalEstimatedCents) }}</dd></div>
                @if ($availableCreditCents > 0)
                    <div class="flex justify-between"><dt class="text-ink-600">Store credit</dt><dd id="summary-credit" aria-live="polite" class="tabular-nums">−{{ $c::format(0) }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-ink-600">Hold (estimate + {{ $policy['hold_tolerance_pct'] }}%)</dt><dd id="summary-hold" aria-live="polite" class="tabular-nums">{{ $c::format($totalHoldCents) }}</dd></div>
            </dl>
        </aside>
    </div>

    @if ($availableCreditCents > 0)
        <script>
            (() => {
                const checkbox = document.getElementById('apply_store_credit');
                const expectedHold = document.getElementById('expected_hold_cents');
                const summaryHold = document.getElementById('summary-hold');
                const summaryCredit = document.getElementById('summary-credit');
                const holdCents = Number(checkbox.dataset.holdCents);
                const creditCents = Number(checkbox.dataset.creditCents);
                const formatCents = (cents) => '$' + (cents / 100).toFixed(2);

                const update = () => {
                    const applied = checkbox.checked ? creditCents : 0;
                    expectedHold.value = holdCents - applied;
                    summaryHold.textContent = formatCents(holdCents - applied);
                    summaryCredit.textContent = '−' + formatCents(applied);
                };
                checkbox.addEventListener('change', update);
                update();
            })();
        </script>
    @endif
    @endif
</x-layouts.shop>
