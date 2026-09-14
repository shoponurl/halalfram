@php($c = \App\Support\Cents::class)
<x-layouts.shop :title="$product->name">
    <div class="grid gap-10 md:grid-cols-2">
        <div class="aspect-square overflow-hidden rounded-3xl bg-gradient-to-br from-brand-700 to-brand-900">
            @if ($product->image_path)
                <img src="{{ asset($product->image_path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover" />
            @endif
        </div>

        <div>
            <p class="text-xs font-bold uppercase tracking-[.18em] text-halal-600">Zabiha halal · priced by weight</p>
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

            <form method="post" action="{{ route('cart.add') }}" class="mt-6 flex items-end gap-3">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}" />
                <label class="block text-sm font-semibold">
                    Pieces
                    <input type="number" name="quantity" value="1" min="1" max="{{ \App\Actions\Orders\QuoteCart::MAX_QUANTITY }}" required
                           class="mt-1.5 block h-12 w-24 rounded-xl border border-bone-300 bg-white px-3 text-base font-semibold focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100" />
                </label>
                <button class="h-12 flex-1 rounded-full bg-brand-800 px-6 text-sm font-bold text-white hover:bg-brand-700">Add to cart</button>
            </form>

            <div class="mt-6">
                @include('shop.partials.catch-weight-explainer', ['estimateCents' => $estimateCents, 'holdCents' => $holdCents, 'tolerance' => rtrim(rtrim($product->holdTolerancePct(), '0'), '.')])
            </div>
        </div>
    </div>
</x-layouts.shop>
