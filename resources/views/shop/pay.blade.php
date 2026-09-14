@php($c = \App\Support\Cents::class)
<x-layouts.shop :title="'Pay for order '.$order->number">
    <x-slot:head>
        {{-- Same release train as the server SDK's API version (2026-08-26.dahlia) --}}
        <script src="https://js.stripe.com/dahlia/stripe.js"></script>
    </x-slot:head>

    <div class="mx-auto max-w-lg">
        <p class="text-xs font-bold uppercase tracking-[.18em] text-ink-500">Order {{ $order->number }}</p>
        <h1 class="mt-2 font-display text-4xl font-semibold">Secure payment</h1>
        <p class="mt-3 text-ink-600">
            We’ll place a hold of <strong>{{ $c::format($order->hold_cents) }}</strong> on your card: your estimate of
            {{ $c::format($order->estimated_cents) }} plus {{ rtrim(rtrim($order->hold_tolerance_pct, '0'), '.') }}%.
            You’re charged for the actual weight once your order is cut.
        </p>

        <form id="payment-form" class="mt-8 rounded-3xl bg-white p-6 ring-1 ring-bone-200">
            <div id="payment-element"></div>
            <p id="payment-message" role="alert" class="mt-4 hidden rounded-xl bg-brand-50 p-3 text-sm font-semibold text-brand-700"></p>
            <button id="submit" class="mt-6 h-12 w-full rounded-full bg-brand-800 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-60">
                Authorize {{ $c::format($order->hold_cents) }} hold
            </button>
            <p class="mt-3 text-center text-xs text-ink-500">Card details are handled by Stripe and never reach our servers.</p>
        </form>
    </div>

    <script>
        (() => {
            const stripe = Stripe(@json($publishableKey));
            const elements = stripe.elements({ clientSecret: @json($clientSecret), appearance: { theme: 'stripe', variables: { colorPrimary: '#C8321A', borderRadius: '12px', fontFamily: 'Manrope, system-ui, sans-serif' } } });
            elements.create('payment', { layout: 'tabs' }).mount('#payment-element');

            const form = document.getElementById('payment-form');
            const button = document.getElementById('submit');
            const message = document.getElementById('payment-message');

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                button.disabled = true;
                message.classList.add('hidden');

                const { error } = await stripe.confirmPayment({ elements, confirmParams: { return_url: @json($returnUrl) } });

                // Only reached when confirmation fails immediately (e.g. declined card); success redirects
                message.textContent = error.message || 'Payment could not be authorized. Please try another card.';
                message.classList.remove('hidden');
                button.disabled = false;
            });
        })();
    </script>
</x-layouts.shop>
