<x-layouts.shop title="Terms of service">
    <h1 class="font-display text-4xl font-semibold">Terms of service</h1>
    <p class="mt-2 text-sm text-ink-500">Last updated September 15, 2026</p>

    <div class="prose prose-sm mt-8 max-w-none space-y-6 text-ink-700">
        <section>
            <h2 class="font-display text-xl font-semibold text-ink-900">Catch-weight pricing</h2>
            <p>Prices are quoted per pound against an estimated weight. When you check out, we place a temporary card hold at estimate + {{ config('catchweight.hold_tolerance_pct') }}% to cover normal weight variation. Once your order is actually weighed, we charge (or refund) the difference between the hold and the real total — see the notice on the checkout page for the exact numbers on your order.</p>
        </section>
        <section>
            <h2 class="font-display text-xl font-semibold text-ink-900">USDA inspection</h2>
            <p>Halal Brothers Live Poultry &amp; Meat operates under full USDA inspection{{ config('catchweight.usda_establishment_number') ? ' (Est. No. '.config('catchweight.usda_establishment_number').')' : '' }}. All products are processed and sold in compliance with the Federal Meat Inspection Act and Pennsylvania Department of Agriculture requirements.</p>
        </section>
        <section>
            <h2 class="font-display text-xl font-semibold text-ink-900">Pickup and delivery</h2>
            <p>Store pickup is at 3 Kelly Street, Lansdowne, PA. Local delivery is offered only to the zip codes listed at checkout. A missed pickup or a failed delivery is handled per the policy shown on your order page.</p>
        </section>
        <section>
            <h2 class="font-display text-xl font-semibold text-ink-900">Payment</h2>
            <p>We accept card, PayPal, and — for pickup orders under {{ \App\Support\Cents::format((int) config('catchweight.cod_max_order_cents')) }} — cash at pickup. Card and PayPal payments are processed by Stripe and PayPal directly; we never see or store your full card number.</p>
        </section>
        <section>
            <h2 class="font-display text-xl font-semibold text-ink-900">Contact</h2>
            <p>Questions about an order? Call (267) 307-3777 or email us using the address on your order confirmation.</p>
        </section>
    </div>
</x-layouts.shop>
