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
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-brand-800 focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-white">Skip to content</a>
    <header class="border-b border-bone-200 bg-bone-50/95">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <img src="{{ asset('halal-brothers-logo.jpg') }}" alt="Halal Brothers logo" class="h-10 w-10 rounded-lg object-cover mix-blend-multiply" />
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

    <main id="main-content" class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
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
        <p>Halal Brothers · 3 Kelly Street, Lansdowne, PA 19050 · (267) 307-3777</p>
        <p class="mt-2">
            <a href="{{ route('legal.privacy') }}" class="underline hover:text-ink-700">Privacy policy</a>
            · <a href="{{ route('legal.terms') }}" class="underline hover:text-ink-700">Terms</a>
        </p>
    </footer>

    {{-- CCPA/cookie notice (guideline ch. 7, S07): this site sets only the essential session/cart
         cookie — no third-party analytics or ad trackers — so one dismissible notice is all that's
         needed, no granular category toggles. --}}
    <div id="cookie-notice" hidden class="fixed inset-x-4 bottom-4 z-40 mx-auto flex max-w-2xl flex-wrap items-center gap-3 rounded-2xl bg-ink-900 px-5 py-4 text-sm text-white shadow-lg sm:inset-x-auto sm:right-4">
        <p class="flex-1">We use only the essential cookies needed to run your cart and checkout — no ad trackers. See our <a href="{{ route('legal.privacy') }}" class="underline">privacy policy</a>.</p>
        <button id="cookie-notice-dismiss" type="button" class="h-9 shrink-0 rounded-full bg-white px-4 text-xs font-bold text-ink-900 hover:bg-bone-100">Got it</button>
    </div>
    <script>
        (() => {
            const KEY = 'cookie-notice-dismissed';
            const notice = document.getElementById('cookie-notice');
            try {
                if (!localStorage.getItem(KEY)) notice.hidden = false;
            } catch (e) { notice.hidden = false; }
            document.getElementById('cookie-notice-dismiss').addEventListener('click', () => {
                notice.hidden = true;
                try { localStorage.setItem(KEY, '1'); } catch (e) {}
            });
        })();
    </script>
</body>
</html>
