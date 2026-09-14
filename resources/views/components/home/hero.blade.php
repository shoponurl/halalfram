<!-- =========================================================
     3. HERO
     ========================================================= -->
<section id="top" class="relative overflow-hidden bg-gradient-to-b from-bone-50 via-bone-100 to-bone-200/60" aria-labelledby="heroTitle">
  <!-- Ambient colour blobs -->
  <div class="blob -right-20 top-10 h-[420px] w-[420px] bg-brand-200" aria-hidden="true"></div>
  <div class="blob bottom-0 left-1/3 h-[320px] w-[320px] bg-saffron-300" style="animation-delay:-6s" aria-hidden="true"></div>
  <div class="blob -left-24 bottom-10 h-[260px] w-[260px] bg-halal-100" style="animation-delay:-11s" aria-hidden="true"></div>
  <!-- Decorative crescent + lanterns (echoes the Eid reference banner) -->
  <svg class="pointer-events-none absolute -left-24 -top-16 h-[420px] w-[420px] text-brand-800/[.06]" viewBox="0 0 200 200" aria-hidden="true">
    <path fill="currentColor" d="M140 20a90 90 0 1 0 0 160A75 75 0 1 1 140 20z"/>
  </svg>
  <div class="pointer-events-none absolute left-[44%] top-0 hidden gap-10 lg:flex" aria-hidden="true">
    <svg class="sway h-40 w-10 text-saffron-500/70" viewBox="0 0 40 160"><line x1="20" y1="0" x2="20" y2="90" stroke="currentColor" stroke-width="1.5"/><path d="M12 92h16l4 10v26l-4 10H12l-4-10v-26z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 140v8" stroke="currentColor" stroke-width="2"/><circle cx="20" cy="115" r="5" fill="currentColor" opacity=".45"/></svg>
    <svg class="sway h-28 w-8 text-brand-700/40" style="animation-delay:-2s" viewBox="0 0 40 160"><line x1="20" y1="0" x2="20" y2="90" stroke="currentColor" stroke-width="1.5"/><path d="M12 92h16l4 10v26l-4 10H12l-4-10v-26z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="20" cy="115" r="5" fill="currentColor" opacity=".45"/></svg>
  </div>

  <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 pb-16 pt-10 sm:px-6 md:pt-14 lg:grid-cols-[1.05fr_.95fr] lg:gap-8 lg:px-8 lg:pb-24 lg:pt-20">
    <!-- Copy -->
    <div>
      <a href="#services" data-service-link="qurbani" class="rise group/eb inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white/70 py-1 pl-1 pr-3 text-[13px] font-semibold text-brand-700 shadow-sm backdrop-blur hover:bg-white" style="--d:50ms">
        <span class="rounded-full bg-brand-700 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white">New</span>
        Qurbani 2027 pre-booking is open
        <i data-lucide="arrow-right" class="h-3.5 w-3.5 transition-transform group-hover/eb:translate-x-1" aria-hidden="true"></i>
      </a>

      <h1 id="heroTitle" class="mt-6 font-display text-[34px] font-semibold leading-[1.02] tracking-tight text-ink-900 min-[380px]:text-[42px] sm:text-6xl lg:text-[50px] xl:text-[62px] 2xl:text-[68px]">
        <span class="line-mask"><span style="--d:150ms">Hand-slaughtered.</span></span>
        <span class="line-mask"><span style="--d:280ms"><span class="shine-text">Honestly halal.</span></span></span>
        <span class="line-mask"><span style="--d:410ms">Delivered cold.</span></span>
      </h1>

      <p class="rise mt-6 max-w-xl text-lg leading-relaxed text-ink-600" style="--d:600ms">
        Live poultry and zabiha beef, lamb and goat from our Lansdowne market — slaughtered by hand
        every day, butchered to your cut and at your door the same day in 0–4 °C packaging.
      </p>

      <div class="rise mt-8 flex flex-wrap items-center gap-3" style="--d:720ms">
        <a href="#shop" class="press group/cta relative inline-flex h-12 items-center gap-2 overflow-hidden rounded-full bg-brand-800 px-6 text-[15px] font-bold text-white shadow-lift hover:bg-brand-700">
          <span class="absolute inset-y-0 -left-1/2 w-1/3 -skew-x-12 bg-white/20 transition-all duration-700 group-hover/cta:left-[120%]" aria-hidden="true"></span>
          Shop fresh cuts <i data-lucide="arrow-right" class="h-4 w-4 transition-transform group-hover/cta:translate-x-1" aria-hidden="true"></i>
        </a>
        <a href="#services" data-service-link="qurbani" class="inline-flex h-12 items-center gap-2 rounded-full border border-ink-900/15 bg-white px-6 text-[15px] font-bold text-ink-900 hover:border-brand-500 hover:text-brand-700">
          <i data-lucide="moon-star" class="h-4 w-4" aria-hidden="true"></i> Pre-book Qurbani
        </a>
      </div>

      <!-- Delivery ZIP checker -->
      <form id="zipForm" class="rise mt-7 flex max-w-md items-center gap-2 rounded-full bg-white/85 p-1.5 pl-4 shadow-sm ring-1 ring-bone-300 backdrop-blur focus-within:ring-2 focus-within:ring-brand-500" style="--d:780ms" novalidate>
        <i data-lucide="map-pin" class="h-4 w-4 shrink-0 text-brand-600" aria-hidden="true"></i>
        <label for="zipInput" class="sr-only">Your ZIP code</label>
        <input id="zipInput" inputmode="numeric" autocomplete="postal-code" maxlength="5" placeholder="Enter ZIP to check delivery" aria-describedby="zipResult" class="h-9 min-w-0 flex-1 bg-transparent text-sm font-semibold placeholder:font-medium placeholder:text-ink-500 focus:outline-none" />
        <button class="h-9 shrink-0 rounded-full bg-ink-900 px-4 text-xs font-bold text-white hover:bg-ink-700">Check</button>
      </form>
      <p id="zipResult" class="mt-2 min-h-[1.25rem] pl-4 text-[13px] font-semibold" aria-live="polite"></p>

      <dl class="rise mt-6 grid max-w-lg grid-cols-3 gap-4 border-t border-bone-300 pt-6" style="--d:840ms">
        <div><dt class="text-xs font-semibold uppercase tracking-wider text-ink-500">Slaughtered</dt><dd class="mt-1 font-display text-2xl font-semibold">Daily</dd></div>
        <div><dt class="text-xs font-semibold uppercase tracking-wider text-ink-500">Cold chain</dt><dd class="mt-1 font-display text-2xl font-semibold">0–4 °C</dd></div>
        <div><dt class="text-xs font-semibold uppercase tracking-wider text-ink-500">Delivery</dt><dd class="mt-1 font-display text-2xl font-semibold">Same day</dd></div>
      </dl>
    </div>

    <!-- Visual -->
    <div id="heroVisual" class="rise relative mx-auto w-full max-w-[560px] [perspective:1200px]" style="--d:300ms">
      <div id="heroTilt" class="tilt relative aspect-[4/5] overflow-hidden rounded-[2rem] bg-brand-900 shadow-lift sm:aspect-[5/5.4]">
        <img src="https://images.unsplash.com/photo-1603048297172-c92544798d5a?w=1100&q=80&auto=format&fit=crop"
             alt="Two thick-cut raw ribeye steaks on a dark butcher block"
             class="kenburns h-full w-full object-cover" fetchpriority="high" />
        <div class="absolute inset-0 bg-gradient-to-t from-ink-900/60 via-transparent to-transparent"></div>
        <div class="absolute bottom-5 left-5 right-5 text-white">
          <p class="text-xs font-bold uppercase tracking-[.18em] text-saffron-300">Butcher's pick</p>
          <p class="mt-1 font-display text-2xl font-semibold">Grass-fed Ribeye, dry-chilled 48h</p>
        </div>
      </div>

      <!-- Floating card: today's batch -->
      <div class="rise bob absolute -left-3 top-8 flex items-center gap-3 rounded-2xl bg-white/95 p-3 pr-4 shadow-lift backdrop-blur sm:-left-8" style="--d:900ms">
        <span class="relative grid h-10 w-10 place-items-center rounded-full bg-halal-50 text-halal-600">
          <i data-lucide="sunrise" class="h-5 w-5" aria-hidden="true"></i>
          <span class="absolute right-0 top-0 h-2.5 w-2.5 rounded-full bg-halal-500 ring-2 ring-white"><span class="block h-full w-full animate-ping rounded-full bg-halal-500"></span></span>
        </span>
        <span class="text-sm leading-tight">
          <span class="block font-bold">Today's batch</span>
          <span class="text-ink-500">Hand-slaughtered · 6:40 AM</span>
        </span>
      </div>

      <!-- Floating card: temperature -->
      <div class="rise bob absolute -right-2 bottom-24 flex items-center gap-3 rounded-2xl bg-ink-900/90 p-3 pr-4 text-white shadow-lift backdrop-blur sm:-right-6" style="--d:1100ms">
        <span class="grid h-10 w-10 place-items-center rounded-full bg-white/10 text-sky-300">
          <i data-lucide="thermometer-snowflake" class="h-5 w-5" aria-hidden="true"></i>
        </span>
        <span class="text-sm leading-tight">
          <span class="block font-bold"><span id="liveTemp">2.4</span> °C in transit</span>
          <span class="text-white/60">Insulated, sealed box</span>
        </span>
      </div>

      <!-- Rotating Zabiha seal -->
      <div class="absolute -right-4 -top-6 h-28 w-28 sm:-right-8 sm:h-32 sm:w-32" aria-hidden="true">
        <svg viewBox="0 0 120 120" class="spin-slow absolute inset-0 h-full w-full">
          <defs><path id="sealPath" d="M60 60 m-46 0 a46 46 0 1 1 92 0 a46 46 0 1 1 -92 0"/></defs>
          <circle cx="60" cy="60" r="58" fill="#1E4F2E"/>
          <text fill="#FFF8F2" font-size="10.5" font-weight="700" letter-spacing="2.2" font-family="Manrope, sans-serif">
            <textPath href="#sealPath">100% HAND-SLAUGHTERED • ZABIHA HALAL •</textPath>
          </text>
        </svg>
        <span class="absolute inset-[24%] grid place-items-center rounded-full bg-white ring-2 ring-bone-100">
          <span class="brand-mark w-[74%]" data-brand-mark><img src="halal-brothers-logo.jpg" alt="" onerror="brandImgFallback(this)" /></span>
          <span class="mark-fallback place-items-center font-brand text-lg text-brand-600">HB</span>
        </span>
      </div>
    </div>
  </div>
</section>
