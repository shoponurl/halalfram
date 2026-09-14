<!-- =========================================================
     9. CART DRAWER (slide-over)
     ========================================================= -->
<div id="cartOverlay" class="fixed inset-0 z-50 bg-ink-900/50 opacity-0 backdrop-blur-[2px]" hidden></div>
<aside id="cartDrawer" role="dialog" aria-modal="true" aria-labelledby="cartTitle"
       class="fixed inset-y-0 right-0 z-[60] flex w-full max-w-[440px] translate-x-full flex-col overflow-y-auto bg-bone-50 shadow-2xl" hidden>
  <header class="sticky top-0 z-10 flex items-center justify-between border-b border-bone-200 bg-bone-50 px-5 py-4">
    <h2 id="cartTitle" class="flex items-center gap-2 font-display text-xl font-semibold">
      Your cart <span class="rounded-full bg-bone-200 px-2 py-0.5 font-sans text-xs font-bold text-ink-600"><span data-cart-count-plain>0</span> items</span>
    </h2>
    <button id="cartClose" class="grid h-10 w-10 place-items-center rounded-full hover:bg-bone-200" aria-label="Close cart">
      <i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i>
    </button>
  </header>

  <!-- Free shipping meter -->
  <div class="border-b border-bone-200 px-5 py-4">
    <p id="shipMsg" class="text-sm font-semibold" aria-live="polite"></p>
    <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-bone-200" role="progressbar" aria-label="Progress to free delivery" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="shipMeter">
      <div id="shipBar" class="h-full w-0 rounded-full bg-gradient-to-r from-brand-600 to-saffron-400"></div>
    </div>
  </div>

  <!-- Items -->
  <div id="cartBody" class="min-h-[208px] flex-1 overflow-y-auto">
    <ul id="cartItems" class="space-y-3 px-5 py-4" aria-label="Cart items"></ul>
    <!-- Upsell: complementary products -->
    <section id="upsell" class="px-5 pb-5" aria-labelledby="upsellTitle">
      <h3 id="upsellTitle" class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-[.14em] text-ink-500"><i data-lucide="sparkles" class="h-3.5 w-3.5 text-saffron-500" aria-hidden="true"></i> Pairs well with</h3>
      <ul id="upsellList" class="no-scrollbar -mx-5 mt-3 flex snap-x gap-3 overflow-x-auto px-5 pb-1"></ul>
    </section>
  </div>

  <!-- Empty state -->
  <div id="cartEmpty" class="flex flex-1 flex-col items-center justify-center px-8 text-center" hidden>
    <span class="grid h-16 w-16 place-items-center rounded-full bg-bone-200 text-ink-500"><i data-lucide="shopping-bag" class="h-7 w-7" aria-hidden="true"></i></span>
    <p class="mt-4 font-display text-xl font-semibold">Your cart is empty</p>
    <p class="mt-1 text-sm text-ink-500">Today's batch was cut at dawn — pick your cuts before they're gone.</p>
    <a href="#shop" data-close-cart class="mt-6 inline-flex h-11 items-center rounded-full bg-brand-800 px-6 text-sm font-bold text-white hover:bg-brand-700">Browse fresh cuts</a>
  </div>

  <!-- Summary -->
  <footer id="cartSummary" class="border-t border-bone-200 bg-white px-5 pb-5 pt-4">
    <label for="slotSelect" class="text-xs font-bold uppercase tracking-[.14em] text-ink-500">Delivery slot</label>
    <select id="slotSelect" class="field mt-1.5 h-10 text-sm"></select>
    <div class="mt-3" data-coupon-slot></div>
    <dl class="mt-4 space-y-1.5 text-sm">
      <div class="flex justify-between"><dt class="text-ink-600">Subtotal</dt><dd class="font-semibold" data-cart-subtotal>$0.00</dd></div>
      <div class="flex justify-between text-halal-700" id="discountRow" hidden><dt>Promo discount</dt><dd class="font-semibold" id="discountAmt"></dd></div>
      <div class="flex justify-between"><dt class="text-ink-600">Cold-chain delivery</dt><dd class="font-semibold" id="shipCost">—</dd></div>
      <div class="flex justify-between border-t border-bone-200 pt-2 text-base"><dt class="font-bold">Total</dt><dd class="font-extrabold" id="cartTotal">$0.00</dd></div>
    </dl>
    <button id="checkoutBtn" class="mt-4 flex h-12 w-full items-center justify-center gap-2 rounded-full bg-brand-800 text-sm font-bold text-white hover:bg-brand-700">
      <i data-lucide="lock" class="h-4 w-4" aria-hidden="true"></i> Secure checkout
    </button>
    <p class="mt-3 flex items-center justify-center gap-1.5 text-xs text-ink-500">
      <i data-lucide="snowflake" class="h-3.5 w-3.5 text-sky-600" aria-hidden="true"></i> Packed with ice packs · kept at 0–4 °C to your door
    </p>
  </footer>
</aside>
