@php($c = \App\Support\Cents::class)
<x-layouts.shop title="Your cart">
    <h1 class="font-display text-4xl font-semibold">Your cart</h1>

    @if ($quote['lines'] === [])
        <p class="mt-6 text-ink-600">Your cart is empty.</p>
    @else
        <ul class="mt-8 divide-y divide-bone-200 rounded-3xl bg-white ring-1 ring-bone-200">
            @foreach ($quote['lines'] as $line)
                <li class="flex flex-wrap items-center gap-4 p-5">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('products.show', $line['product']) }}" class="font-bold hover:text-brand-700">{{ $line['product']->name }}</a>
                        <p class="text-sm text-ink-500">~{{ rtrim(rtrim($line['weight']->toDecimal(), '0'), '.') }} lb · {{ $c::format($line['product']->price_per_lb_cents) }}/lb</p>
                        @if ($line['cut_option'] || $line['offal_option'] || $line['packing_option'])
                            <p class="mt-1 text-xs text-ink-500">
                                {{ collect([$line['cut_option']?->name, $line['offal_option']?->name, $line['packing_option']?->name])->filter()->implode(' · ') }}
                            </p>
                        @endif
                        @if ($line['lead_time_days'] > 0)
                            <p class="mt-0.5 text-xs font-semibold text-halal-600">Needs about {{ $line['lead_time_days'] }} extra day{{ $line['lead_time_days'] === 1 ? '' : 's' }} to prepare</p>
                        @endif
                    </div>
                    <form method="post" action="{{ route('cart.update', $line['line_key']) }}" class="flex items-center gap-2">
                        @csrf @method('patch')
                        <label class="sr-only" for="qty-{{ $line['line_key'] }}">Pieces</label>
                        <input id="qty-{{ $line['line_key'] }}" type="number" name="quantity" value="{{ $line['quantity'] }}" min="1" max="{{ \App\Actions\Orders\QuoteCart::MAX_QUANTITY }}" class="h-10 w-20 rounded-xl border border-bone-300 px-2" />
                        <button class="text-sm font-semibold text-brand-700 hover:underline">Update</button>
                    </form>
                    <p class="w-24 text-right font-extrabold tabular-nums">{{ $c::format($line['estimated_cents']) }}</p>
                    <form method="post" action="{{ route('cart.remove', $line['line_key']) }}">
                        @csrf @method('delete')
                        <button class="text-sm text-ink-500 hover:text-brand-700" aria-label="Remove {{ $line['product']->name }}">Remove</button>
                    </form>
                </li>
            @endforeach
        </ul>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
            <p class="text-sm text-ink-600">Estimated total <strong class="text-lg text-ink-900">{{ $c::format($quote['estimated_cents']) }}</strong> · card hold {{ $c::format($quote['hold_cents']) }}</p>
            <a href="{{ route('checkout.create') }}" class="inline-flex h-12 items-center rounded-full bg-brand-800 px-8 text-sm font-bold text-white hover:bg-brand-700">Checkout</a>
        </div>
    @endif
</x-layouts.shop>
