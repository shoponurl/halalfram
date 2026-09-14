<!-- =========================================================
     6. SERVICES — Qurbani / Aqiqah / On-Demand request form
     (mirrors the reference service pages, unified into tabs)
     ========================================================= -->
<section id="services" class="mx-auto mt-24 max-w-7xl scroll-mt-24 px-4 sm:px-6 lg:px-8" aria-labelledby="svcTitle">
  <div data-stagger class="grid gap-6 lg:grid-cols-[340px_1fr]">
    <!-- Info card -->
    <aside class="relative overflow-hidden rounded-3xl bg-brand-900 p-7 text-bone-100">
      <svg class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 text-white/[.05]" viewBox="0 0 200 200" aria-hidden="true"><path fill="currentColor" d="M140 20a90 90 0 1 0 0 160A75 75 0 1 1 140 20z"/></svg>
      <!-- Logo sits on its own white tile — the artwork is designed for a white ground -->
      <div class="relative inline-flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-lift">
        <span class="brand-mark w-[58px]" data-brand-mark><img src="halal-brothers-logo.jpg" alt="" onerror="brandImgFallback(this)" /></span>
        <span class="wordmark leading-none">
          <span class="block whitespace-nowrap font-brand text-[17px] text-brand-600">HALAL BROTHERS</span>
          <span class="mt-1 block whitespace-nowrap text-[9px] font-extrabold uppercase tracking-[.16em] text-saffron-600">Live Poultry &amp; Meat</span>
        </span>
      </div>
      <h2 id="svcTitle" class="mt-6 font-display text-3xl font-semibold leading-tight text-white">Qurbani, Aqiqah &amp; custom orders</h2>
      <p class="mt-3 text-sm leading-relaxed text-bone-300">Tell us what you need — our team confirms availability, price and slaughter date within 2 hours during opening times.</p>

      <ul class="mt-7 space-y-3 text-sm [&_a]:inline-block [&_a]:py-0.5">
        <li class="flex gap-3"><i data-lucide="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-saffron-400" aria-hidden="true"></i><a href="https://www.google.com/maps/search/?api=1&amp;query=3+Kelly+Street%2C+Lansdowne%2C+PA+19050" target="_blank" rel="noopener" class="hover:text-white">3 Kelly Street<br />Lansdowne, PA 19050</a></li>
        <li class="flex gap-3"><i data-lucide="phone" class="mt-0.5 h-4 w-4 shrink-0 text-saffron-400" aria-hidden="true"></i><a href="tel:+12673073777" class="hover:text-white">(267) 307-3777</a></li>
        <li class="flex gap-3"><i data-lucide="message-circle" class="mt-0.5 h-4 w-4 shrink-0 text-saffron-400" aria-hidden="true"></i><a href="https://wa.me/12673073777" target="_blank" rel="noopener" class="hover:text-white">WhatsApp +1 267-307-3777</a></li>
      </ul>

      <div class="mt-8 rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
        <p class="flex items-center gap-2 text-sm font-bold text-white"><i data-lucide="hand-heart" class="h-4 w-4 text-saffron-400" aria-hidden="true"></i>Donate a share</p>
        <p class="mt-1 text-[13px] leading-relaxed text-bone-300">We can distribute your Qurbani or Aqiqah meat to partner madrasas and families in need, with photo confirmation.</p>
      </div>
    </aside>

    <!-- Form card -->
    <div class="rounded-3xl bg-white p-6 shadow-card ring-1 ring-bone-200 sm:p-8">
      <div id="svcTabs" role="tablist" aria-label="Service type" class="relative grid grid-cols-3 gap-1 rounded-2xl bg-bone-100 p-1">
        <span id="svcInd" class="svc-ind" aria-hidden="true"></span>
        <button role="tab" id="svc-tab-qurbani"  aria-controls="svcForm" aria-selected="true"  data-svc="qurbani"  class="svc-tab flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold"><i data-lucide="moon-star" class="h-4 w-4" aria-hidden="true"></i><span>Qurbani</span></button>
        <button role="tab" id="svc-tab-aqiqah"   aria-controls="svcForm" aria-selected="false" data-svc="aqiqah"   class="svc-tab flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold" tabindex="-1"><i data-lucide="baby" class="h-4 w-4" aria-hidden="true"></i><span>Aqiqah</span></button>
        <button role="tab" id="svc-tab-ondemand" aria-controls="svcForm" aria-selected="false" data-svc="ondemand" class="svc-tab flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold" tabindex="-1"><i data-lucide="clipboard-list" class="h-4 w-4" aria-hidden="true"></i><span class="hidden sm:inline">On&nbsp;</span><span>Demand</span></button>
      </div>

      <form id="svcForm" role="tabpanel" aria-labelledby="svc-tab-qurbani" class="mt-7" novalidate>
        <h3 id="svcHeading" class="font-display text-2xl font-semibold">Qurbani pre-booking</h3>
        <p id="svcSub" class="mt-1 text-sm text-ink-500">Reserve a whole animal or a 1/7 share. Slaughter on Eid day after Salah, per Sunnah.</p>

        <div class="mt-6 grid gap-4 sm:grid-cols-3">
          <div>
            <label for="fName" class="text-sm font-semibold">Name <span class="text-brand-600" aria-hidden="true">*</span></label>
            <input id="fName" name="name" required autocomplete="name" placeholder="Your name" class="field mt-1.5" />
          </div>
          <div>
            <label for="fEmail" class="text-sm font-semibold">Email</label>
            <input id="fEmail" name="email" type="email" autocomplete="email" placeholder="you@example.com" class="field mt-1.5" />
          </div>
          <div>
            <label for="fPhone" class="text-sm font-semibold">Phone <span class="text-brand-600" aria-hidden="true">*</span></label>
            <input id="fPhone" name="phone" type="tel" required autocomplete="tel" placeholder="(000) 000-0000" class="field mt-1.5" />
          </div>
        </div>

        <!-- Qurbani-only fields -->
        <fieldset data-svc-fields="qurbani" class="mt-4 grid gap-4 sm:grid-cols-3">
          <legend class="sr-only">Qurbani details</legend>
          <div>
            <label for="qAnimal" class="text-sm font-semibold">Animal</label>
            <select id="qAnimal" name="animal" class="field mt-1.5"><option>Goat (whole)</option><option>Sheep (whole)</option><option>Cow — 1/7 share</option><option>Cow (whole)</option></select>
          </div>
          <div>
            <label for="qBudget" class="text-sm font-semibold">Budget</label>
            <select id="qBudget" name="budget" class="field mt-1.5"><option>Select budget</option><option>Under $300</option><option>$300 – $600</option><option>$600 – $1,200</option><option>$1,200+</option></select>
          </div>
          <div>
            <label for="qProcess" class="text-sm font-semibold">Processing</label>
            <select id="qProcess" name="process" class="field mt-1.5"><option>Curry cut, bone-in</option><option>Mixed: curry + mince + steaks</option><option>Keep whole / halves</option><option>Donate all on my behalf</option></select>
          </div>
        </fieldset>

        <!-- Aqiqah-only fields -->
        <fieldset data-svc-fields="aqiqah" class="mt-4 grid gap-4 sm:grid-cols-3" hidden>
          <legend class="sr-only">Aqiqah details</legend>
          <div>
            <label for="aChild" class="text-sm font-semibold">For a</label>
            <select id="aChild" name="child" class="field mt-1.5"><option>Baby boy — 2 goats/sheep</option><option>Baby girl — 1 goat/sheep</option></select>
          </div>
          <div>
            <label for="aDate" class="text-sm font-semibold">Preferred date</label>
            <input id="aDate" name="date" type="date" class="field mt-1.5" />
          </div>
          <div>
            <label for="aProcess" class="text-sm font-semibold">Meat preference</label>
            <select id="aProcess" name="aprocess" class="field mt-1.5"><option>Raw, curry cut</option><option>Cooked (biryani / curry trays)</option><option>Donate to madrasa</option><option>Part keep, part donate</option></select>
          </div>
        </fieldset>

        <!-- On-demand-only fields -->
        <fieldset data-svc-fields="ondemand" class="mt-5" hidden>
          <legend class="text-sm font-semibold">What do you need?</legend>
          <div class="mt-2 flex flex-wrap gap-2">
            <label class="cursor-pointer"><input type="checkbox" name="need" value="Beef" class="peer sr-only" /><span class="need-chip">Beef</span></label>
            <label class="cursor-pointer"><input type="checkbox" name="need" value="Lamb & Mutton" class="peer sr-only" /><span class="need-chip">Lamb &amp; Mutton</span></label>
            <label class="cursor-pointer"><input type="checkbox" name="need" value="Goat" class="peer sr-only" /><span class="need-chip">Goat</span></label>
            <label class="cursor-pointer"><input type="checkbox" name="need" value="Poultry" class="peer sr-only" /><span class="need-chip">Poultry</span></label>
            <label class="cursor-pointer"><input type="checkbox" name="need" value="Offal" class="peer sr-only" /><span class="need-chip">Offal &amp; Bones</span></label>
            <label class="cursor-pointer"><input type="checkbox" name="need" value="Wholesale" class="peer sr-only" /><span class="need-chip">Wholesale / Catering</span></label>
          </div>
        </fieldset>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label for="fLocation" class="text-sm font-semibold">Delivery location</label>
            <input id="fLocation" name="location" autocomplete="street-address" placeholder="Street, city, ZIP" class="field mt-1.5" />
          </div>
          <div>
            <label for="fNote" class="text-sm font-semibold">Note for our butcher</label>
            <input id="fNote" name="note" placeholder="e.g. extra mince, keep the liver, cut size" class="field mt-1.5" />
          </div>
        </div>

        <p id="svcHint" class="mt-4 flex gap-2 rounded-xl bg-saffron-300/20 px-4 py-3 text-[13px] leading-relaxed text-ink-700">
          <i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0 text-saffron-500" aria-hidden="true"></i>
          <span data-hint>A live cow typically yields 52–55% of its live weight as meat. Final price is confirmed after the animal is weighed.</span>
        </p>

        <div class="mt-6 flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-xs text-ink-500">We'll never share your details. Fields marked * are required.</p>
          <button type="submit" class="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-halal-600 px-7 text-sm font-bold text-white hover:bg-halal-700">
            <i data-lucide="send" class="h-4 w-4" aria-hidden="true"></i> <span data-submit-label>Submit request</span>
          </button>
        </div>
      </form>

      <!-- Success state (replaces the form after submit) -->
      <div id="svcSuccess" class="flex flex-col items-center py-14 text-center" tabindex="-1" hidden>
        <svg class="check-draw h-20 w-20" viewBox="0 0 56 56" aria-hidden="true">
          <circle cx="28" cy="28" r="26" fill="none" stroke="#2F7A45" stroke-width="3"/>
          <path d="M16 29l8 8 16-17" fill="none" stroke="#2F7A45" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h3 class="mt-5 font-display text-2xl font-semibold">JazakAllah khair, <span data-success-name>friend</span>!</h3>
        <p class="mt-2 max-w-md text-sm leading-relaxed text-ink-500"><span data-success-what>Your request</span> is in. Our team will call you within 2 hours (during opening times) to confirm the animal, price and date.</p>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
          <button type="button" id="svcAgain" class="inline-flex h-11 items-center gap-2 rounded-full border border-ink-900/15 px-5 text-sm font-bold hover:border-brand-500 hover:text-brand-700"><i data-lucide="rotate-ccw" class="h-4 w-4" aria-hidden="true"></i> Send another request</button>
          <a href="#shop" class="inline-flex h-11 items-center gap-2 rounded-full bg-brand-800 px-5 text-sm font-bold text-white hover:bg-brand-700">Browse fresh cuts</a>
        </div>
      </div>
    </div>
  </div>
</section>
