<!-- =========================================================
     5. PRODUCT GRID
     ========================================================= -->
<section id="shop" class="mx-auto max-w-7xl scroll-mt-28 px-4 pt-20 sm:px-6 lg:px-8" aria-labelledby="shopTitle">
  <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
    <div data-reveal>
      <p class="text-xs font-bold uppercase tracking-[.18em] text-brand-600">Cut fresh this morning</p>
      <h2 id="shopTitle" class="mt-2 font-display text-3xl font-semibold tracking-tight sm:text-4xl">Today's fresh cuts</h2>
      <p id="resultMeta" class="mt-2 text-sm text-ink-500" aria-live="polite"></p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
      <!-- Weight unit (prices update everywhere) -->
      <div class="flex items-center rounded-full border border-bone-300 bg-white p-0.5 text-xs font-bold" role="radiogroup" aria-label="Show prices per">
        <button data-unit="lb" role="radio" aria-checked="true"  class="unit-btn rounded-full px-3 py-2">per lb</button>
        <button data-unit="kg" role="radio" aria-checked="false" class="unit-btn rounded-full px-3 py-2">per kg</button>
      </div>
      <label for="sortSelect" class="text-sm font-semibold text-ink-600">Sort</label>
      <div class="relative">
        <select id="sortSelect" class="h-10 appearance-none rounded-full border border-bone-300 bg-white pl-4 pr-9 text-sm font-semibold focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100">
          <option value="featured">Featured</option>
          <option value="price-asc">Price: low to high</option>
          <option value="price-desc">Price: high to low</option>
          <option value="name">Name A–Z</option>
        </select>
        <i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2" aria-hidden="true"></i>
      </div>
    </div>
  </div>

  <!-- Category tabs (rendered by JS) -->
  <div class="sticky top-[72px] z-30 -mx-4 mt-6 border-b border-bone-300 bg-bone-100/90 px-4 backdrop-blur-lg sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0">
    <div id="catTabs" role="tablist" aria-label="Filter products by category" class="no-scrollbar relative flex gap-2 overflow-x-auto py-3"></div>
  </div>

  <!-- Active search chip -->
  <div id="activeQuery" class="mt-4 flex items-center gap-2 text-sm" hidden>
    <span class="text-ink-500">Showing results for</span>
    <button id="clearQuery" class="inline-flex items-center gap-1.5 rounded-full bg-ink-900 px-3 py-1 font-semibold text-white">
      <span data-query-label></span><i data-lucide="x" class="h-3.5 w-3.5" aria-hidden="true"></i><span class="sr-only">Clear search</span>
    </button>
  </div>

  <div id="productGrid" role="tabpanel" aria-labelledby="tab-all" class="mt-6 grid grid-cols-1 gap-5 min-[480px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"></div>

  <!-- Empty state -->
  <div id="emptyState" class="mt-6 rounded-3xl border border-dashed border-bone-400 bg-white px-6 py-16 text-center" hidden>
    <i data-lucide="search-x" class="mx-auto h-10 w-10 text-ink-500" aria-hidden="true"></i>
    <p class="mt-3 font-display text-xl font-semibold">No cuts match that search</p>
    <p class="mt-1 text-sm text-ink-500">Try "curry cut", "chops" or browse all categories — or send an on-demand request.</p>
  </div>

  <div class="mt-10 flex justify-center">
    <button id="loadMore" class="inline-flex h-12 items-center gap-2 rounded-full border border-ink-900/15 bg-white px-7 text-sm font-bold hover:border-brand-500 hover:text-brand-700">
      Load more cuts <i data-lucide="chevron-down" class="h-4 w-4" aria-hidden="true"></i>
    </button>
  </div>
</section>
