<x-layouts.shop title="Shop by category">
    <h1 class="font-display text-4xl font-semibold">Shop by category</h1>

    <ul class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($categories as $category)
            <li>
                <a href="{{ route('categories.show', $category) }}" class="block rounded-3xl bg-white p-6 ring-1 ring-bone-200 hover:ring-brand-300">
                    <p class="text-xs font-bold uppercase tracking-[.18em] text-halal-600">{{ $category->species->label() }}</p>
                    <h2 class="mt-1 font-display text-xl font-semibold">{{ $category->name }}</h2>
                    <p class="mt-2 text-sm text-ink-500">{{ $category->products_count }} item{{ $category->products_count === 1 ? '' : 's' }}</p>
                </a>
            </li>
        @endforeach
    </ul>
</x-layouts.shop>
