<!-- Skip link -->
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow-lift">Skip to content</a>

<!-- =========================================================
     1. ANNOUNCEMENT BAR
     ========================================================= -->
<!-- Logo-colour strip: red-orange → orange -->
<div class="brand-gradient h-1" aria-hidden="true"></div>
<div class="bg-ink-900 text-bone-100 text-[12px] sm:text-[13px]" role="region" aria-label="Store announcements">
  <div class="mx-auto flex h-10 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
    <div class="hidden lg:flex items-center gap-2 whitespace-nowrap text-bone-300">
      <i data-lucide="clock-3" class="h-4 w-4 text-saffron-400" aria-hidden="true"></i>
      <span id="countdown" aria-live="off">Order by <strong class="text-white">2 PM</strong> for same-day delivery</span>
    </div>

    <!-- Rotating messages -->
    <div class="h-6 flex-1 overflow-hidden text-center md:flex-none" aria-live="off">
      <ul class="ticker">
        <li class="flex h-6 items-center justify-center gap-2"><i data-lucide="truck" class="h-4 w-4 text-saffron-400" aria-hidden="true"></i>Free cold-chain delivery on orders over <strong class="text-white">$99</strong></li>
        <li class="flex h-6 items-center justify-center gap-2"><i data-lucide="moon-star" class="h-4 w-4 text-saffron-400" aria-hidden="true"></i>Qurbani 2027 pre-booking is now open</li>
        <li class="flex h-6 items-center justify-center gap-2"><i data-lucide="snowflake" class="h-4 w-4 text-saffron-400" aria-hidden="true"></i>Packed at 0–4 °C · Never frozen</li>
      </ul>
    </div>

    <a href="#/page/halal" class="hidden sm:inline-flex items-center gap-1.5 whitespace-nowrap py-1 font-semibold text-white hover:text-saffron-300">
      <i data-lucide="badge-check" class="h-4 w-4 text-halal-100" aria-hidden="true"></i>
      View Halal Certificate
      <i data-lucide="arrow-up-right" class="h-3.5 w-3.5" aria-hidden="true"></i>
    </a>
  </div>
</div>

<!-- =========================================================
     2. STICKY HEADER
     ========================================================= -->
<header id="siteHeader" class="sticky top-0 z-40 border-b border-bone-200 bg-bone-50/90 backdrop-blur-xl transition-shadow">
  <!-- Reading-progress bar -->
  <div id="scrollProgress" class="pointer-events-none absolute inset-x-0 bottom-[-1px] h-[2px] origin-left bg-gradient-to-r from-brand-700 via-brand-500 to-saffron-400" style="transform: scaleX(0)" aria-hidden="true"></div>
  <div class="mx-auto flex h-[72px] max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">

    <!-- Mobile menu toggle -->
    <button id="menuBtn" class="-ml-1 grid h-10 w-10 shrink-0 place-items-center rounded-full hover:bg-bone-200 lg:hidden" aria-label="Open menu" aria-expanded="false" aria-controls="mobileMenu">
      <i data-lucide="menu" class="h-5 w-5" aria-hidden="true"></i>
    </button>

    <!-- Logo lockup: animal mark (cropped from the logo file) + live-text wordmark -->
    <a href="#top" class="flex min-w-0 shrink-0 items-center gap-2.5" aria-label="Halal Brothers Live Poultry &amp; Meat — home">
      <span class="brand-mark hidden w-[46px] min-[360px]:block sm:w-[60px]" data-brand-mark>
        <img src="halal-brothers-logo.jpg" alt="" onerror="brandImgFallback(this)" />
      </span>
      <span class="wordmark block leading-none">
        <span class="block whitespace-nowrap font-brand text-[16px] tracking-[.02em] text-brand-600 sm:text-[19px] lg:text-[17px] xl:text-[19px]">HALAL BROTHERS</span>
        <span class="mt-1 block whitespace-nowrap text-[8.5px] font-extrabold uppercase tracking-[.16em] text-saffron-600 sm:text-[10px]">Live Poultry &amp; Meat</span>
      </span>
    </a>

    <!-- Primary nav -->
    <nav class="ml-4 hidden lg:block xl:ml-6" aria-label="Primary">
      <ul class="flex items-center gap-0 whitespace-nowrap text-[13.5px] font-semibold text-ink-700 xl:gap-1 xl:text-[14px] [&_a]:px-2.5 xl:[&_a]:px-3">
        <li><a href="#shop" data-spy="shop" class="navlink block rounded-full px-3 py-2 hover:text-brand-700">Shop</a></li>
        <li><a href="#services" data-spy="services" data-service-link="qurbani" class="navlink block rounded-full px-3 py-2 hover:text-brand-700">
          Qurbani <span class="ml-0.5 inline-block h-1.5 w-1.5 -translate-y-2 rounded-full bg-brand-500 align-middle" aria-hidden="true"><span class="block h-full w-full animate-ping rounded-full bg-brand-500"></span></span>
        </a></li>
        <li><a href="#services" data-spy="services" data-service-link="aqiqah" class="navlink block rounded-full px-3 py-2 hover:text-brand-700">Aqiqah</a></li>
        <li><a href="#services" data-spy="services" data-service-link="ondemand" class="navlink block rounded-full px-3 py-2 hover:text-brand-700">On Demand</a></li>
        <li><a href="#standard" data-spy="standard" class="navlink block rounded-full px-3 py-2 hover:text-brand-700">Our Standard</a></li>
      </ul>
    </nav>

    <!-- Search (desktop) -->
    <div class="relative ml-auto hidden xl:block xl:w-60 2xl:w-80" id="searchWrap">
      <label for="searchInput" class="sr-only">Search cuts and products</label>
      <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500" aria-hidden="true"></i>
      <input id="searchInput" type="search" autocomplete="off" placeholder="Search cuts — ribeye, chops…"
             class="h-11 w-full rounded-full border border-bone-300 bg-white pl-10 pr-12 text-sm placeholder:text-ink-500/80 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100"
             role="combobox" aria-expanded="false" aria-controls="searchPanel" aria-autocomplete="list" />
      <kbd class="pointer-events-none absolute right-3 top-1/2 hidden -translate-y-1/2 rounded-md border border-bone-300 bg-bone-100 px-1.5 text-[11px] font-semibold text-ink-500 xl:block">/</kbd>

      <!-- Search dropdown: quick-cut filters + live results -->
      <div id="searchPanel" class="absolute left-0 right-0 top-[calc(100%+8px)] z-50 rounded-2xl border border-bone-200 bg-white p-4 shadow-lift" hidden>
        <p class="mb-2 text-[11px] font-bold uppercase tracking-[.14em] text-ink-500">Quick cuts</p>
        <div class="flex flex-wrap gap-2" id="quickCuts"></div>
        <div id="searchResults" class="mt-4 border-t border-bone-200 pt-3" role="listbox" aria-label="Matching products"></div>
      </div>
    </div>

    <!-- Header actions -->
    <div class="ml-auto flex shrink-0 items-center gap-1 xl:ml-2">
      <!-- Search icon below 1280 px (phones use the bottom bar's Search) -->
      <button id="mobileSearchBtn" class="hidden h-10 w-10 place-items-center rounded-full hover:bg-bone-200 sm:grid xl:hidden" aria-label="Search" aria-expanded="false" aria-controls="mobileSearch">
        <i data-lucide="search" class="h-5 w-5" aria-hidden="true"></i>
      </button>

      <a href="#/wishlist" class="relative hidden sm:grid h-10 w-10 place-items-center rounded-full hover:bg-bone-200" aria-label="Wishlist" data-wish-link>
        <i data-lucide="heart" class="h-5 w-5" aria-hidden="true"></i>
        <span data-wish-count class="absolute -right-0.5 -top-0.5 grid h-4 min-w-[16px] place-items-center rounded-full bg-brand-600 px-1 text-[10px] font-extrabold text-white" hidden>0</span>
      </a>
      <a href="#/account" class="hidden sm:grid h-10 w-10 place-items-center rounded-full hover:bg-bone-200" aria-label="My account and orders">
        <i data-lucide="user-round" class="h-5 w-5" aria-hidden="true"></i>
      </a>

      <!-- Cart trigger -->
      <button id="cartBtn" class="relative inline-flex h-11 items-center gap-2 rounded-full bg-brand-800 pl-3.5 pr-4 text-sm font-bold text-white shadow-sm hover:bg-brand-700" aria-label="Open cart, 0 items" aria-haspopup="dialog" aria-controls="cartDrawer">
        <i data-lucide="shopping-bag" class="h-[18px] w-[18px]" aria-hidden="true"></i>
        <span class="hidden sm:inline" data-cart-subtotal>$0.00</span>
        <span data-cart-count class="absolute -right-1 -top-1 grid h-5 min-w-[20px] place-items-center rounded-full bg-saffron-400 px-1 text-[11px] font-extrabold text-ink-900 ring-2 ring-bone-50">0</span>
      </button>
    </div>
  </div>

  <!-- Mobile search row -->
  <div id="mobileSearch" class="border-t border-bone-200 px-4 py-3 sm:px-6 lg:px-8 xl:hidden" hidden>
    <label for="mobileSearchInput" class="sr-only">Search cuts and products</label>
    <div class="relative">
      <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500" aria-hidden="true"></i>
      <input id="mobileSearchInput" type="search" placeholder="Search cuts…" class="h-11 w-full rounded-full border border-bone-300 bg-white pl-10 pr-4 text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100" />
    </div>
    <div class="no-scrollbar mt-3 flex gap-2 overflow-x-auto" id="quickCutsMobile"></div>
  </div>

  <!-- Mobile menu -->
  <nav id="mobileMenu" class="border-t border-bone-200 bg-bone-50 lg:hidden" aria-label="Mobile" hidden>
    <ul class="mx-auto grid max-w-7xl gap-1 px-4 py-4 text-[15px] font-semibold">
      <li><a href="#shop" class="flex items-center justify-between rounded-xl px-3 py-3 hover:bg-bone-200">Shop all cuts <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i></a></li>
      <li><a href="#services" data-service-link="qurbani" class="flex items-center justify-between rounded-xl px-3 py-3 text-brand-600 hover:bg-bone-200">Qurbani pre-booking <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i></a></li>
      <li><a href="#services" data-service-link="aqiqah" class="flex items-center justify-between rounded-xl px-3 py-3 hover:bg-bone-200">Aqiqah <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i></a></li>
      <li><a href="#services" data-service-link="ondemand" class="flex items-center justify-between rounded-xl px-3 py-3 hover:bg-bone-200">On-demand request <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i></a></li>
      <li><a href="#standard" class="flex items-center justify-between rounded-xl px-3 py-3 hover:bg-bone-200">Our Zabiha standard <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i></a></li>
      <li class="mt-2 grid grid-cols-2 gap-2">
        <a href="#/wishlist" class="flex items-center gap-2 rounded-xl bg-white px-3 py-3 text-sm ring-1 ring-bone-200"><i data-lucide="heart" class="h-4 w-4 text-brand-600" aria-hidden="true"></i>Wishlist</a>
        <a href="#/account" class="flex items-center gap-2 rounded-xl bg-white px-3 py-3 text-sm ring-1 ring-bone-200"><i data-lucide="receipt" class="h-4 w-4 text-brand-600" aria-hidden="true"></i>My orders</a>
        <a href="#faq" class="flex items-center gap-2 rounded-xl bg-white px-3 py-3 text-sm ring-1 ring-bone-200"><i data-lucide="circle-help" class="h-4 w-4 text-brand-600" aria-hidden="true"></i>FAQ</a>
        <a href="#visit" class="flex items-center gap-2 rounded-xl bg-white px-3 py-3 text-sm ring-1 ring-bone-200"><i data-lucide="map-pin" class="h-4 w-4 text-brand-600" aria-hidden="true"></i>Visit us</a>
      </li>
      <li class="mt-2 flex items-center justify-between rounded-xl bg-white px-3 py-3 ring-1 ring-bone-200">
        <span class="text-sm text-ink-600">Weight unit</span>
        <span class="flex items-center rounded-full border border-bone-300 p-0.5 text-xs font-bold" role="radiogroup" aria-label="Weight unit">
          <button data-unit="lb" role="radio" aria-checked="true"  class="unit-btn rounded-full px-3 py-1.5">lb</button>
          <button data-unit="kg" role="radio" aria-checked="false" class="unit-btn rounded-full px-3 py-1.5">kg</button>
        </span>
      </li>
    </ul>
  </nav>
</header>
