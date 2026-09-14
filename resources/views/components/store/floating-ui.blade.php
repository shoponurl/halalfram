<!-- =========================================================
     12. MOBILE BOTTOM NAV (thumb-reachable)
     ========================================================= -->
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-bone-200 bg-bone-50/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl md:hidden" aria-label="Quick navigation">
  <ul class="grid grid-cols-5 text-[11px] font-bold text-ink-600">
    <li><a href="#top" data-bnav="top" class="bnav flex h-16 flex-col items-center justify-center gap-1"><i data-lucide="house" class="h-5 w-5" aria-hidden="true"></i>Home<span class="dot h-1 w-1 rounded-full bg-brand-600"></span></a></li>
    <li><a href="#shop" data-bnav="shop" class="bnav flex h-16 flex-col items-center justify-center gap-1"><i data-lucide="layout-grid" class="h-5 w-5" aria-hidden="true"></i>Shop<span class="dot h-1 w-1 rounded-full bg-brand-600"></span></a></li>
    <li><button type="button" id="bnavSearch" class="bnav flex h-16 w-full flex-col items-center justify-center gap-1"><i data-lucide="search" class="h-5 w-5" aria-hidden="true"></i>Search<span class="dot h-1 w-1 rounded-full bg-brand-600"></span></button></li>
    <li><a href="#services" data-bnav="services" data-service-link="qurbani" class="bnav flex h-16 flex-col items-center justify-center gap-1"><i data-lucide="moon-star" class="h-5 w-5" aria-hidden="true"></i>Qurbani<span class="dot h-1 w-1 rounded-full bg-brand-600"></span></a></li>
    <li><button type="button" id="bnavCart" class="bnav relative flex h-16 w-full flex-col items-center justify-center gap-1" aria-label="Open cart" aria-controls="cartDrawer">
      <span class="relative"><i data-lucide="shopping-bag" class="h-5 w-5" aria-hidden="true"></i><span data-cart-count class="absolute -right-2.5 -top-2 grid h-4 min-w-[16px] place-items-center rounded-full bg-brand-700 px-1 text-[10px] font-extrabold text-white">0</span></span>
      <span data-cart-subtotal>$0.00</span>
    </button></li>
  </ul>
</nav>

<!-- Back to top with scroll-progress ring -->
<button id="toTop" class="fixed bottom-20 right-4 z-30 grid h-12 w-12 translate-y-4 place-items-center rounded-full bg-white text-ink-900 opacity-0 shadow-lift ring-1 ring-bone-200 transition-all duration-300 hover:text-brand-700 md:bottom-6 2xl:right-24" aria-label="Back to top" tabindex="-1">
  <svg class="absolute inset-0 h-full w-full -rotate-90" viewBox="0 0 48 48" aria-hidden="true">
    <circle cx="24" cy="24" r="22" fill="none" stroke="#FBEDE2" stroke-width="2.5"/>
    <circle id="toTopRing" cx="24" cy="24" r="22" fill="none" stroke="#B02914" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="138.2" stroke-dashoffset="138.2"/>
  </svg>
  <i data-lucide="arrow-up" class="relative h-5 w-5" aria-hidden="true"></i>
</button>

<!-- WhatsApp chat button — hidden until CONFIG.whatsapp is set in the script -->
<a id="waBtn" href="#" target="_blank" rel="noopener" class="fixed bottom-20 left-4 z-30 grid h-12 w-12 place-items-center rounded-full bg-[#25D366] text-white shadow-lift transition hover:scale-105 md:bottom-6" aria-label="Chat with us on WhatsApp" hidden>
  <i data-lucide="message-circle" class="h-6 w-6" aria-hidden="true"></i>
</a>

<!-- Sticky buy bar (product page; appears once the main Add button scrolls away) -->
<div id="pdpBar" class="fixed inset-x-0 bottom-16 z-30 translate-y-[160%] border-t border-bone-200 bg-white/95 shadow-[0_-12px_32px_-16px_rgba(22,24,27,.35)] backdrop-blur-xl md:bottom-0" aria-label="Quick add" inert></div>

<!-- Toasts -->
<div id="toasts" class="pointer-events-none fixed inset-x-0 bottom-20 z-[70] flex flex-col items-center gap-2 px-4 md:bottom-6" role="status" aria-live="polite"></div>
