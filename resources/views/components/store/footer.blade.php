<!-- =========================================================
     8. FOOTER
     ========================================================= -->
<footer class="bg-bone-50" aria-labelledby="footerTitle">
  <h2 id="footerTitle" class="sr-only">Store information</h2>

  <!-- Newsletter band -->
  <div class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
    <div data-reveal class="relative flex flex-col items-start justify-between gap-6 overflow-hidden rounded-3xl bg-brand-800 px-6 py-8 text-white sm:px-10 lg:flex-row lg:items-center">
      <div class="blob -right-10 -top-20 h-60 w-60 bg-saffron-400/60" aria-hidden="true"></div>
      <svg class="pointer-events-none absolute -bottom-20 left-1/2 h-56 w-56 text-white/[.05]" viewBox="0 0 200 200" aria-hidden="true"><path fill="currentColor" d="M140 20a90 90 0 1 0 0 160A75 75 0 1 1 140 20z"/></svg>
      <div class="relative">
        <p class="font-display text-2xl font-semibold sm:text-3xl">Get Friday's fresh-batch list first</p>
        <p class="mt-1 text-sm text-brand-100">Weekly cuts, Qurbani slots and member-only prices. No spam, unsubscribe anytime.</p>
      </div>
      <form id="newsletterForm" class="relative flex w-full max-w-md gap-2" novalidate>
        <label for="nlEmail" class="sr-only">Email address</label>
        <input id="nlEmail" type="email" required autocomplete="email" placeholder="you@example.com" class="h-12 min-w-0 flex-1 rounded-full border-0 bg-white/10 px-5 text-sm text-white placeholder:text-white/60 ring-1 ring-white/20 focus:outline-none focus:ring-2 focus:ring-saffron-400" />
        <button id="nlBtn" class="inline-flex h-12 shrink-0 items-center gap-1.5 rounded-full bg-saffron-400 px-6 text-sm font-extrabold text-ink-900 transition-colors hover:bg-saffron-300">Subscribe</button>
      </form>
    </div>
  </div>

  <div data-stagger class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr] lg:px-8">
    <!-- Brand -->
    <div>
      <!-- Full logo on the light footer ground -->
      <a href="#top" class="inline-block" aria-label="Halal Brothers Live Poultry &amp; Meat — back to top">
        <span class="brand-full w-[230px]" data-brand-mark><img src="halal-brothers-logo.jpg" alt="Halal Brothers Live Poultry &amp; Meat" onerror="brandImgFallback(this)" /></span>
        <span class="wordmark hidden leading-none">
          <span class="block font-brand text-[26px] text-brand-600">HALAL BROTHERS</span>
          <span class="mt-1.5 block text-[11px] font-extrabold uppercase tracking-[.16em] text-saffron-600">Live Poultry &amp; Meat</span>
        </span>
      </a>
      <address class="mt-5 space-y-1.5 text-sm not-italic leading-relaxed text-ink-600 [&_a]:inline-block [&_a]:py-0.5">
        <p class="flex gap-2"><i data-lucide="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" aria-hidden="true"></i><a href="https://www.google.com/maps/search/?api=1&amp;query=3+Kelly+Street%2C+Lansdowne%2C+PA+19050" target="_blank" rel="noopener" class="hover:text-brand-700">3 Kelly Street, Lansdowne, PA 19050</a></p>
        <p class="flex gap-2"><i data-lucide="phone" class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" aria-hidden="true"></i><a href="tel:+12673073777" class="hover:text-brand-700">(267) 307-3777</a></p>
        <p class="flex gap-2"><i data-lucide="message-circle" class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" aria-hidden="true"></i><a href="https://wa.me/12673073777" target="_blank" rel="noopener" class="hover:text-brand-700">WhatsApp +1 267-307-3777</a></p>
      </address>
      <ul class="mt-5 flex gap-2" aria-label="Social media">
        <li><a href="#" aria-label="Facebook"  class="grid h-9 w-9 place-items-center rounded-full bg-white ring-1 ring-bone-300 hover:bg-brand-800 hover:text-white"><i data-lucide="facebook" class="h-4 w-4" aria-hidden="true"></i></a></li>
        <li><a href="#" aria-label="Instagram" class="grid h-9 w-9 place-items-center rounded-full bg-white ring-1 ring-bone-300 hover:bg-brand-800 hover:text-white"><i data-lucide="instagram" class="h-4 w-4" aria-hidden="true"></i></a></li>
        <li><a href="#" aria-label="YouTube"   class="grid h-9 w-9 place-items-center rounded-full bg-white ring-1 ring-bone-300 hover:bg-brand-800 hover:text-white"><i data-lucide="youtube" class="h-4 w-4" aria-hidden="true"></i></a></li>
        <li><a href="https://wa.me/12673073777" target="_blank" rel="noopener" aria-label="WhatsApp"  class="grid h-9 w-9 place-items-center rounded-full bg-white ring-1 ring-bone-300 hover:bg-brand-800 hover:text-white"><i data-lucide="message-circle" class="h-4 w-4" aria-hidden="true"></i></a></li>
      </ul>
    </div>

    <!-- Links -->
    <nav aria-label="Shop links">
      <p class="text-sm font-extrabold uppercase tracking-[.14em] text-ink-900">Shop</p>
      <ul class="mt-3 space-y-0.5 text-sm text-ink-600 [&_a]:inline-block [&_a]:py-1.5">
        <li><a href="#shop" data-jump-cat="beef" class="hover:text-brand-700">Beef</a></li>
        <li><a href="#shop" data-jump-cat="lamb" class="hover:text-brand-700">Lamb &amp; Mutton</a></li>
        <li><a href="#shop" data-jump-cat="poultry" class="hover:text-brand-700">Poultry</a></li>
        <li><a href="#shop" data-jump-cat="specialty" class="hover:text-brand-700">Specialty &amp; Qurbani</a></li>
        <li><a href="#shop" data-jump-cat="marinades" class="hover:text-brand-700">Marinades &amp; BBQ</a></li>
      </ul>
    </nav>
    <nav aria-label="Customer care">
      <p class="text-sm font-extrabold uppercase tracking-[.14em] text-ink-900">Customer care</p>
      <ul class="mt-3 space-y-0.5 text-sm text-ink-600 [&_a]:inline-block [&_a]:py-1.5">
        <li><a href="#/page/delivery" class="hover:text-brand-700">Delivery &amp; cold chain</a></li>
        <li><a href="#faq" class="hover:text-brand-700">FAQ &amp; help</a></li>
        <li><a href="#/page/refund" class="hover:text-brand-700">Refund &amp; freshness policy</a></li>
        <li><a href="#/page/halal" class="hover:text-brand-700">Halal certification</a></li>
        <li><a href="#/account" class="hover:text-brand-700">My orders</a></li>
        <li><a href="#visit" class="hover:text-brand-700">Store location</a></li>
      </ul>
    </nav>

    <!-- Hours -->
    <div>
      <p class="text-sm font-extrabold uppercase tracking-[.14em] text-ink-900">Store hours</p>
      <dl class="mt-4 space-y-2 text-sm">
        <div class="flex justify-between gap-4 border-b border-dashed border-bone-300 pb-2"><dt class="text-ink-600">Mon – Thu</dt><dd class="font-semibold">8:00 AM – 8:00 PM</dd></div>
        <div class="flex justify-between gap-4 border-b border-dashed border-bone-300 pb-2"><dt class="text-ink-600">Friday</dt><dd class="font-semibold">8:00 – 12:30 · 2:30 – 9:00</dd></div>
        <div class="flex justify-between gap-4 border-b border-dashed border-bone-300 pb-2"><dt class="text-ink-600">Sat – Sun</dt><dd class="font-semibold">7:00 AM – 9:00 PM</dd></div>
      </dl>
      <p id="openNow" class="mt-4 inline-flex items-center gap-2 rounded-full bg-halal-50 px-3 py-1.5 text-xs font-bold text-halal-700"></p>
    </div>
  </div>

  <!-- Payments + bottom bar -->
  <div class="border-t border-bone-300">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-5 px-4 py-6 sm:px-6 lg:flex-row lg:px-8">
      <ul class="flex flex-wrap items-center justify-center gap-2" aria-label="Accepted payment methods">
        <li class="pay">VISA</li><li class="pay">Mastercard</li><li class="pay">AMEX</li><li class="pay">Apple Pay</li><li class="pay">G Pay</li><li class="pay">PayPal</li><li class="pay">Cash on delivery</li>
      </ul>
      <div class="flex flex-col items-center gap-1 text-center text-sm text-ink-500 lg:items-end">
        <p>© <span id="year"></span> <strong class="text-ink-900">Halal Brothers Live Poultry &amp; Meat</strong>. All rights reserved.</p>
        <p class="flex items-center gap-3 text-xs [&_a]:py-1.5"><a href="#/page/privacy" class="hover:text-brand-700 hover:underline">Privacy policy</a><span aria-hidden="true">·</span><a href="#/page/terms" class="hover:text-brand-700 hover:underline">Terms of service</a><span aria-hidden="true">·</span><a href="#/page/refund" class="hover:text-brand-700 hover:underline">Refunds</a></p>
      </div>
    </div>
  </div>
</footer>

<!-- Floating cart tab (desktop, echoes the reference's side cart widget) -->
<button id="cartTab" class="fixed right-0 top-1/2 z-30 hidden -translate-y-1/2 flex-col items-center gap-1 rounded-l-2xl bg-halal-600 px-3 py-3 text-white shadow-lift hover:bg-halal-700 2xl:flex" aria-label="Open cart" aria-controls="cartDrawer">
  <i data-lucide="shopping-basket" class="h-5 w-5" aria-hidden="true"></i>
  <span class="text-xs font-bold"><span data-cart-count-plain>0</span> item</span>
  <span class="rounded-md bg-white px-1.5 py-0.5 text-[11px] font-extrabold text-halal-700" data-cart-subtotal>$0.00</span>
</button>
