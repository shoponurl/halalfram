{{-- Expects: $estimateCents, $holdCents, $tolerance --}}
@php($c = \App\Support\Cents::class)
<div class="rounded-2xl bg-saffron-300/25 p-4 text-sm leading-relaxed text-ink-700">
    <p class="font-bold text-ink-900">How weight-based pricing works</p>
    <ul class="mt-2 list-disc space-y-1 pl-5">
        <li>Every piece is weighed after cutting. You pay for the <strong>actual weight</strong>.</li>
        <li>Estimated price: <strong>{{ $c::format($estimateCents) }}</strong>. We place a temporary hold of <strong>{{ $c::format($holdCents) }}</strong> (estimate + {{ $tolerance }}%) on your card. You aren’t charged yet.</li>
        <li>If it weighs less, you pay less and the rest of the hold is released.</li>
        <li>If it weighs a little more, we charge the difference to the same card. If it’s a lot more, we send you a payment link first.</li>
    </ul>
</div>
