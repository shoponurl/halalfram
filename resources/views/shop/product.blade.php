@php($c = \App\Support\Cents::class)
<x-layouts.shop :title="$product->name">
    <div class="grid gap-10 md:grid-cols-2">
        <div class="aspect-square overflow-hidden rounded-3xl bg-gradient-to-br from-brand-700 to-brand-900">
            @if ($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="h-full w-full object-cover" />
            @endif
        </div>

        <div>
            <p class="text-xs font-bold uppercase tracking-[.18em] text-halal-600">
                Zabiha halal · priced by weight
                @if ($product->category)
                    · {{ $product->category->name }}
                @endif
            </p>
            <h1 class="mt-2 font-display text-4xl font-semibold leading-tight">{{ $product->name }}</h1>
            @if ($product->description)
                <p class="mt-3 text-lg text-ink-600">{{ $product->description }}</p>
            @endif

            <p class="mt-6 flex items-baseline gap-2">
                <span class="text-3xl font-extrabold tabular-nums">{{ $c::format($product->price_per_lb_cents) }}</span>
                <span class="text-sm font-semibold text-ink-500">/ lb</span>
            </p>
            <p class="mt-1 text-sm text-ink-600">
                About {{ rtrim(rtrim($product->estimated_weight_lb->toDecimal(), '0'), '.') }} lb each · estimated <strong>{{ $c::format($estimateCents) }}</strong> per piece
            </p>

            <form method="post" action="{{ route('cart.add') }}" class="mt-6 space-y-5">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}" />

                @if ($cutOptions->isNotEmpty())
                    <fieldset>
                        <legend class="text-sm font-bold">Cut</legend>
                        <div class="mt-2 space-y-1.5">
                            <label class="flex items-center gap-2 text-sm"><input type="radio" name="cut_option_id" value="" checked class="accent-brand-700" /> Standard — no extra charge</label>
                            @foreach ($cutOptions as $option)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="radio" name="cut_option_id" value="{{ $option->id }}" class="accent-brand-700" />
                                    {{ $option->name }}
                                    @if ($option->extra_price_cents > 0) <span class="text-ink-500">(+{{ $c::format($option->extra_price_cents) }}/piece)</span> @endif
                                    @if ($option->extra_lead_time_days > 0) <span class="text-ink-500">· +{{ $option->extra_lead_time_days }}d</span> @endif
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                @if ($offalOptions->isNotEmpty())
                    <fieldset>
                        <legend class="text-sm font-bold">Offal</legend>
                        <div class="mt-2 space-y-1.5">
                            <label class="flex items-center gap-2 text-sm"><input type="radio" name="offal_option_id" value="" checked class="accent-brand-700" /> Keep as usual — no extra charge</label>
                            @foreach ($offalOptions as $option)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="radio" name="offal_option_id" value="{{ $option->id }}" class="accent-brand-700" />
                                    {{ $option->name }}
                                    @if ($option->extra_price_cents > 0) <span class="text-ink-500">(+{{ $c::format($option->extra_price_cents) }}/piece)</span> @endif
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                @if ($packingOptions->isNotEmpty())
                    <fieldset>
                        <legend class="text-sm font-bold">Packing</legend>
                        <div class="mt-2 space-y-1.5">
                            <label class="flex items-center gap-2 text-sm"><input type="radio" name="packing_option_id" value="" checked class="accent-brand-700" /> Standard — no extra charge</label>
                            @foreach ($packingOptions as $option)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="radio" name="packing_option_id" value="{{ $option->id }}" class="accent-brand-700" />
                                    {{ $option->name }}
                                    @if ($option->surcharge_cents > 0) <span class="text-ink-500">(+{{ $c::format($option->surcharge_cents) }}/piece)</span> @endif
                                    @if ($option->extra_lead_time_days > 0) <span class="text-ink-500">· +{{ $option->extra_lead_time_days }}d</span> @endif
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <div class="flex items-end gap-3">
                    <label class="block text-sm font-semibold">
                        Pieces
                        <input type="number" name="quantity" value="1" min="1" max="{{ \App\Actions\Orders\QuoteCart::MAX_QUANTITY }}" required
                               class="mt-1.5 block h-12 w-24 rounded-xl border border-bone-300 bg-white px-3 text-base font-semibold focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100" />
                    </label>
                    <button class="h-12 flex-1 rounded-full bg-brand-800 px-6 text-sm font-bold text-white hover:bg-brand-700">Add to cart</button>
                </div>
            </form>

            <div class="mt-6">
                @include('shop.partials.catch-weight-explainer', ['estimateCents' => $estimateCents, 'holdCents' => $holdCents, 'tolerance' => rtrim(rtrim($product->holdTolerancePct(), '0'), '.')])
            </div>
        </div>
    </div>
</x-layouts.shop>
