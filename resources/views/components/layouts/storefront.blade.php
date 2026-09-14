<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $title ?? 'Halal Brothers Live Poultry & Meat — Zabiha Halal in Lansdowne, PA' }}</title>
    <meta name="description" content="{{ $description ?? 'Halal Brothers Live Poultry & Meat, Lansdowne PA: live poultry and 100% hand-slaughtered zabiha beef, lamb and goat. Fresh daily, delivered cold.' }}" />
    <meta name="theme-color" content="#C8321A" />

    <link rel="icon" href="{{ asset('halal-brothers-logo.jpg') }}" />
    <link rel="apple-touch-icon" href="{{ asset('halal-brothers-logo.jpg') }}" />
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="Halal Brothers Live Poultry &amp; Meat" />
    <meta property="og:title" content="Halal Brothers Live Poultry &amp; Meat — Lansdowne, PA" />
    <meta property="og:description" content="Live poultry and 100% hand-slaughtered zabiha beef, lamb and goat in Lansdowne, PA. Fresh daily, delivered at 0–4 °C." />
    <meta property="og:image" content="{{ asset('halal-brothers-logo.jpg') }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta name="twitter:card" content="summary_large_image" />

    {{-- Local business structured data --}}
    @verbatim
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Store",
      "name": "Halal Brothers Live Poultry & Meat",
      "description": "Live poultry market and hand-slaughtered zabiha halal butcher — chicken, beef, lamb, goat, Qurbani and Aqiqah.",
      "image": "/halal-brothers-logo.jpg",
      "telephone": "+1-267-307-3777",
      "address": { "@type": "PostalAddress", "streetAddress": "3 Kelly Street", "addressLocality": "Lansdowne", "addressRegion": "PA", "postalCode": "19050", "addressCountry": "US" },
      "openingHoursSpecification": [
        { "@type": "OpeningHoursSpecification", "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday"], "opens": "08:00", "closes": "20:00" },
        { "@type": "OpeningHoursSpecification", "dayOfWeek": "Friday", "opens": "08:00", "closes": "12:30" },
        { "@type": "OpeningHoursSpecification", "dayOfWeek": "Friday", "opens": "14:30", "closes": "21:00" },
        { "@type": "OpeningHoursSpecification", "dayOfWeek": ["Saturday", "Sunday"], "opens": "07:00", "closes": "21:00" }
      ],
      "priceRange": "$$"
    }
    </script>
    @endverbatim

    {{-- Fonts: Rye (logo wordmark) + Zilla Slab (display) + Manrope (UI) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Rye&family=Zilla+Slab:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    {{-- Logo file fallback: if the logo can't load, the text wordmark shows on its own --}}
    <script>
        window.brandImgFallback = function () { document.documentElement.classList.add('no-logo-file'); };
    </script>

    {{-- Lucide icons (UMD build, loaded before the deferred storefront module) --}}
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.460.0/dist/umd/lucide.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-bone-100 pb-16 text-ink-900 md:pb-0">
    <x-store.header />

    {{ $slot }}

    <x-store.footer />
    <x-store.cart-drawer />
    <x-store.quick-view />
    <x-store.floating-ui />
</body>
</html>
