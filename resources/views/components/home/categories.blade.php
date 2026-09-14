<!-- =========================================================
     4. CATEGORY CAROUSEL
     ========================================================= -->
<section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8" aria-labelledby="catTitle">
  <div class="flex items-end justify-between gap-4">
    <div data-reveal>
      <p class="text-xs font-bold uppercase tracking-[.18em] text-brand-600">Shop by category</p>
      <h2 id="catTitle" class="mt-2 font-display text-3xl font-semibold tracking-tight sm:text-4xl">From our butcher's counter</h2>
    </div>
    <div class="hidden gap-2 sm:flex">
      <button id="catPrev" class="grid h-11 w-11 place-items-center rounded-full border border-bone-300 bg-white hover:border-brand-500 hover:text-brand-700 disabled:opacity-40" aria-label="Previous categories">
        <i data-lucide="chevron-left" class="h-5 w-5" aria-hidden="true"></i>
      </button>
      <button id="catNext" class="grid h-11 w-11 place-items-center rounded-full border border-bone-300 bg-white hover:border-brand-500 hover:text-brand-700 disabled:opacity-40" aria-label="Next categories">
        <i data-lucide="chevron-right" class="h-5 w-5" aria-hidden="true"></i>
      </button>
    </div>
  </div>

  <!-- Cards are rendered by JS from CATEGORIES -->
  <ul id="catTrack" class="no-scrollbar -mx-4 mt-8 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-px-4 px-4 pb-2 sm:-mx-6 sm:scroll-px-6 sm:px-6 lg:mx-0 lg:px-0 lg:scroll-px-0" aria-label="Categories"></ul>
  <!-- Carousel scroll progress -->
  <div class="mx-auto mt-5 h-1 w-40 overflow-hidden rounded-full bg-bone-300 lg:hidden" aria-hidden="true">
    <div id="catProgress" class="h-full w-1/3 rounded-full bg-brand-700 transition-transform duration-150"></div>
  </div>
</section>
