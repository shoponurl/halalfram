<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    <title>{{ $title ?? 'Halal Brothers' }} — Halal Brothers</title>
    <meta name="theme-color" content="#C8321A" />
    <link rel="icon" href="{{ asset('halal-brothers-logo.jpg') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Zilla+Slab:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    {{ $head ?? '' }}
</head>
<body class="min-h-screen bg-bone-100 font-sans text-ink-900 antialiased">
    <header class="border-b border-bone-200 bg-bone-50/95">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <img src="{{ asset('halal-brothers-logo.jpg') }}" alt="" class="h-10 w-10 rounded-lg object-cover mix-blend-multiply" />
                <span class="leading-tight">
                    <span class="block font-display text-lg font-bold text-brand-800">Halal Brothers</span>
                    <span class="block text-[11px] font-semibold uppercase tracking-[.16em] text-ink-500">Live poultry &amp; meat</span>
                </span>
            </a>
            <a href="{{ route('cart.show') }}" class="inline-flex h-10 items-center gap-2 rounded-full bg-brand-800 px-4 text-sm font-bold text-white hover:bg-brand-700">
                Cart <span class="rounded-full bg-white/20 px-2 text-xs">{{ app(\App\Support\Cart::class)->count() }}</span>
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        @if (session('status'))
            <p role="status" class="mb-6 rounded-2xl bg-halal-50 px-4 py-3 text-sm font-semibold text-halal-700">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="mb-6 rounded-2xl bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="border-t border-bone-200 py-8 text-center text-xs text-ink-500">
        Halal Brothers · 3 Kelly Street, Lansdowne, PA 19050 · (267) 307-3777
    </footer>
</body>
</html>
