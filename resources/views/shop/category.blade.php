@php($c = \App\Support\Cents::class)
<x-layouts.shop :title="$category->name">
    <p class="text-xs font-bold uppercase tracking-[.18em] text-halal-600">{{ $category->species->label() }}</p>
    <h1 class="mt-1 font-display text-4xl font-semibold">{{ $category->name }}</h1>

    @if ($products->isEmpty())
        <p class="mt-6 text-ink-600">No items in this category right now.</p>
    @else
        <ul class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($products as $product)
                <li>
                    <a href="{{ route('products.show', $product) }}" class="block overflow-hidden rounded-3xl bg-white ring-1 ring-bone-200 hover:ring-brand-300">
                        <div class="aspect-square bg-gradient-to-br from-brand-700 to-brand-900">
                            @if ($product->imageUrl())
                                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="h-full w-full object-cover" />
                            @endif
                        </div>
                        <div class="p-4">
                            <h2 class="font-bold">{{ $product->name }}</h2>
                            <p class="mt-1 text-sm text-ink-500">{{ $c::format($product->price_per_lb_cents) }} / lb</p>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.shop>
