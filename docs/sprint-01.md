# Sprint 01: catch-weight vertical slice

**Goal (guideline ch. 6):** one product, end-to-end: order → hold → weight → capture → invoice. The riskiest part of the whole project, proven first.

## Owner decisions (guideline ch. 7, S01) — recorded 2026-09-14

| Question | Decision | Where it lives |
|---|---|---|
| Hold tolerance | 10% (`hold = estimate × 1.10`) | `config/catchweight.php` → `hold_tolerance_pct` |
| Weight above the hold | Auto-charge the saved card up to estimate **+25%**; above that, send a payment link | `overage_autocharge_pct`; decided in `App\Support\SettlementPlan` |
| Weight far below the estimate | A manager must approve before capture when the actual total is more than **20%** below the estimate | `underweight_review_pct`; `payments.approve_adjustments` permission, Manager/Owner only |
| Card minimum | 50¢ — below it, the hold is cancelled and the balance is written off | `minimum_charge_cents` |

These are snapshotted onto every `orders` row at checkout, so a later policy change never rewrites past orders.

## Tasks

| Task | Status | Where |
|---|---|---|
| `products` — price_per_lb_cents, est_weight, tolerance_pct, uom | ✅ | `database/migrations/2026_09_15_000100_create_products_table.php`, `App\Models\Product` |
| `orders` / `order_items` — two-amount model | ✅ | `2026_09_15_000200_create_orders_tables.php`, `App\Models\Order`/`OrderItem` |
| `weight_events` — append-only | ✅ | `App\Models\WeightEvent` (throws on update/delete); `App\Actions\Orders\RecordWeight` |
| Cart + checkout + Stripe `capture_method: manual` | ✅ | `App\Http\Controllers\Shop\*`, `App\Payments\StripeGateway`, `App\Actions\Orders\PlaceOrder` |
| Admin: weight entry → recompute → capture / partial refund | ✅ | `ItemsRelationManager` (weigh), `ViewOrder` page actions (finalize / approve), `App\Jobs\SettleOrderPayment` |
| Invoice PDF — estimate + adjustment lines | ✅ | `App\Support\InvoicePdf`, `resources/views/invoices/pdf.blade.php`, barryvdh/laravel-dompdf 3.1 |

## Definition of Done: evidence

| DoD item | Result | How it was checked |
|---|---|---|
| A real $1 test transaction runs the full path in staging | ⏳ **Blocked on your Stripe test keys.** Every step is proven against `App\Payments\FakePaymentGateway` (138 Pest tests) and against the actual Stripe SDK's request/response shapes (`StripeGateway`, `stripe/stripe-php` 21.3). A `staging-test-pack` ($1) product is seeded by `CatalogSeeder` for local/staging so the first real run needs no extra setup. |
| Extra capture (over) and partial refund (under) both work | ✅ | `CatchWeightVerticalSliceTest`: over-hold auto-charge, over-ceiling payment link, and a declined off-session charge all falling back to a link; under-floor review → manager approval → capture of the lower amount |
| Tampering the amount in the browser is rejected | ✅ | `PlaceOrderTest` (action level) + `CheckoutSecurityTest` (HTTP level): a mismatched `expected_hold_cents`, or any of `amount`/`price`/`hold_cents`/`total`/`estimated_cents` in the POST body, is rejected before an order is saved |
| Stripe's authorization window is verified in writing | ✅ | Stripe docs (fetched 2026-09-14): online card holds are valid **7 days** (Visa/Mastercard/Amex/Discover, customer-initiated). `authorization_expires_at` is set from the charge's real `capture_before`, with the 7-day figure only as a fallback (`config('catchweight.authorization_fallback_days')`). `FinalizeOrder` refuses to finalize a lapsed hold. |

## What's proven vs. what's still a fake

- **Proven for real:** the storefront HTTP flow (home → product → cart → checkout) against the real Laravel router, session, CSRF and rate limiter; the webhook signature check against HMAC-signed payloads; every settlement branch's arithmetic (bcmath, no floats — rule 01).
- **Still against the fake gateway:** the actual network calls to Stripe (`PaymentIntents`, off-session charges, Checkout Sessions for the balance link). `StripeGateway` is written directly from the Stripe API/PHP SDK docs and used nowhere else, so swapping the container binding is the only change staging needs.

## Next: give the app your Stripe test keys

1. Add to `.env` (never commit it): `STRIPE_KEY=pk_test_…`, `STRIPE_SECRET=sk_test_…`, `STRIPE_WEBHOOK_SECRET=whsec_…` (from `stripe listen --forward-to localhost:8010/stripe/webhook` locally, or the Dashboard webhook for staging).
2. `php artisan serve`, add the `staging-test-pack` ($1) product to the cart, and check out with Stripe's `4242 4242 4242 4242` test card.
3. Weigh it in `/admin/orders/{number}` as a Butcher, then finalize as a Front desk/Manager — watch the payment ledger in the order's **Payment ledger** tab and the corresponding PaymentIntent in the Stripe Dashboard.
