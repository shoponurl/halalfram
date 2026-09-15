# Sprint 06: payment completion & notifications

**Goal (guideline ch. 6):** finish the payment rails (PayPal, cash on pickup, Apple/Google Pay), add store credit and coupons, and give every order lifecycle event a customer-facing SMS/email notification with dedupe — the storefront has been silently completing orders with no message to the customer at all.

## Owner decisions (guideline ch. 7, S06) — recorded 2026-09-15

| Question | Decision | Where it lives |
|---|---|---|
| How should cash on delivery/pickup work? | **Pickup-only, with a value cap.** No cash for delivery orders (a driver shouldn't carry change or chase payment on the doorstep); capped so a no-show can't leave too much wasted, never-billed product on the books. | `config('catchweight.cod_max_order_cents')` (default $150), `App\Actions\Orders\PlaceOrder`'s cash+delivery rejection |
| How should store credit work? | **Store-use-only, tracked as a liability, no expiry.** Not a refundable cash-equivalent; a balance-as-cache-over-append-only-ledger, the same pattern as Sprint 03's `Lot` quantities. | `App\Models\StoreCreditAccount`, `App\Models\StoreCreditEvent`, `App\Actions\Payments\{IssueStoreCredit,RedeemStoreCredit}` |
| Should a coupon discount the checkout estimate or the final weighed total? | **The final amount the customer actually pays.** Checkout only validates the code is usable; the real discount is computed and locked in at `FinalizeOrder` time, against the actual weighed total, then re-checked under a row lock in case another order used the last redemption slot in between. | `App\Models\Coupon::{assertUsableFor,discountFor}`, `App\Actions\Orders\FinalizeOrder` |
| How should marketing consent be captured? | **Separate opt-ins for transactional vs. marketing.** Order/payment/delivery notifications always send (they're not marketing); a customer must separately opt in to marketing SMS/email, and SMS honors a hard STOP regardless of opt-in status (TCPA). | `orders.marketing_sms_opt_in`/`marketing_email_opt_in`, `App\Models\SmsConsent`, `App\Actions\Sms\HandleTwilioInboundSms` |

## Tasks

| Task | Status | Where |
|---|---|---|
| PayPal Orders v2 (authorize → capture, mirroring the Stripe flow) | ✅ | `App\Payments\PayPalGateway`, `App\Payments\PaymentGatewayFactory`, `Order::gatewayIntentId()` |
| Apple Pay / Google Pay | ✅ (no new code needed — see below) | Stripe Payment Element, `automatic_payment_methods` (Sprint 01) |
| Cash on pickup, capped | ✅ | `App\Actions\Orders\PlaceOrder`, `App\Actions\Orders\RecordCashPayment`, `OrderStatus::AwaitingCashPayment` |
| Store credit: issue, balance, redeem at checkout | ✅ | `App\Models\StoreCreditAccount`, `App\Actions\Payments\{IssueStoreCredit,RedeemStoreCredit}`, `Filament\Resources\StoreCreditAccounts` |
| Coupons: percent/fixed, min order, max redemptions, active window | ✅ | `App\Models\Coupon`, `Filament\Resources\Coupons` |
| Stripe Tax (pluggable, off by default) | ✅ (gated) | `App\Payments\TaxCalculator`/`NullTaxCalculator`, `orders.tax_cents` |
| Stripe Radar | ✅ (Dashboard-only, no code) | n/a |
| Twilio SMS notifications + STOP/START (TCPA) | ✅ | `App\Sms\TwilioSmsGateway`, `App\Http\Controllers\TwilioWebhookController`, `App\Models\SmsConsent` |
| Postmark email notifications | ✅ | `App\Mail\OrderNotificationMail`, `config('services.postmark')` |
| DB-driven notification template manager + per-order dedupe | ✅ | `App\Models\NotificationTemplate`, `App\Models\NotificationLog`, `App\Jobs\DispatchOrderNotification` |

## Definition of Done: evidence

| DoD item | Result | How it was checked |
|---|---|---|
| A PayPal order authorizes on customer return and settles through the same weigh/QC/finalize pipeline as a card order | ✅ | `PayPalCheckoutTest`: place → `confirmAuthorization` → `MarkOrderAuthorized` → `FinalizeOrder` reaches `Completed`, using `payment_reference`/`gatewayIntentId()`, never touching `stripe_payment_intent_id` |
| PayPal overage falls back to a payment link instead of an off-session charge | ✅ | `PayPalCheckoutTest`: `offSessionUnsupported` forces `PaymentMethodNotSupported`, order lands on `AwaitingBalance` with a `balance_payment_url`, no off-session call made |
| Cash orders skip the card hold entirely, cap enforced, delivery rejected | ✅ | `CashOnDeliveryTest`: no `stripe_customer_id`/`stripe_payment_intent_id` at all; a delivery+cash combination and an over-cap order both throw before creating anything |
| A cash order can be marked ready for pickup before the cash is collected, then completed only for the full amount owed | ✅ | `CashOnDeliveryTest`: `MarkReadyForPickup` succeeds at `AwaitingCashPayment`; `RecordCashPayment` rejects an amount tendered below `final_cents`, completes on the full amount |
| A missed pickup on an unpaid cash order writes it off with no refund transaction (nothing was ever charged) | ✅ | `CashOnDeliveryTest`: `refunded_cents` stays 0 and `PaymentGateway::refund()` is never called, unlike the card/PayPal write-off path |
| Store credit applies at checkout, never exceeds the balance, never undercuts the card minimum | ✅ | `StoreCreditTest`: full-balance and partial-balance cases, plus a redeem-more-than-available case capped at the true balance |
| A coupon discounts the final weighed total, not the estimate, and never exceeds the total | ✅ | `CouponTest`: percent and fixed-over-total cases against a 2 lb weighed order; hold amount at checkout is unaffected |
| A coupon can't be redeemed past its max, and a race between two orders for the last slot is caught at finalize | ✅ | `CouponTest`: outright rejection at `PlaceOrder` once maxed; a second test bumps `redeemed_count` between placement and finalize to exercise `FinalizeOrder`'s own re-check, discount comes back 0 |
| A retried notification job or a duplicate trigger sends a message only once per (order, event, channel) | ✅ | `NotificationDedupeTest`: two `dispatchSync` calls for the same event send exactly one SMS and log exactly one row |
| A missing template for an event/channel is skipped silently, not an error | ✅ | `NotificationDedupeTest`: no active template → no send, no log row, no exception |
| SMS to a number that has replied STOP is blocked, even for a transactional event; START unblocks it | ✅ | `NotificationDedupeTest`: `SmsConsent.opted_out_at` blocks the send outright; `HandleTwilioInboundSms` STOP→START round-trip flips `canReceiveSms()` |
| Every role × every new payments-admin route returns the correct 200/403 | ✅ | `AdminResourceAuthorizationTest`: coupons/store-credit-accounts/notification-templates added to the existing matrix, Owner/Manager only |

## What's new vs. Sprint 05

- **A deliberate, disclosed package deviation.** The owner approved installing `srmklive/paypal` alongside `twilio/sdk`, `symfony/postmark-mailer` and `symfony/http-client`. As the senior-developer technical call, I did **not** install it: PayPal Orders v2 is a plain REST API, and building `App\Payments\PayPalGateway` directly on Laravel's `Http` facade keeps it symmetric with `StripeGateway`'s own thin-wrapper style and keeps idempotency/retry handling under our control rather than a third-party client's. The other three packages were installed and are in use.
- **Apple Pay and Google Pay needed zero new code.** Sprint 01's `automatic_payment_methods: {enabled: true}` on the Stripe PaymentIntent, plus the existing Stripe Payment Element in `pay.blade.php`, already surfaces both wallets automatically. What remains is Stripe Dashboard configuration and Apple domain verification — no code gap, just an operational one.
- **PayPal's approval flow is fundamentally different from Stripe's**, and the code reflects that rather than papering over it: Stripe confirms client-side; PayPal requires a redirect to paypal.com and a return trip, handled by a new `PayPalReturnController`. Rather than rename Stripe's `stripe_payment_intent_id`/`stripe_customer_id`/`stripe_payment_method_id` columns into something gateway-neutral (a large, risky rename across dozens of files for a two-gateway system), PayPal gets its own `payment_reference` "rolling pointer" column — it holds whichever id PayPal's *next* call needs (order id → authorization id → capture id) — plus `Order::gatewayIntentId()` to read whichever column is actually live for that order's payment method.
- **Scope cut, disclosed: no PayPal webhook route this sprint.** `PayPalGateway::parseWebhook()` is fully implemented but nothing calls it — authorization relies solely on the customer's own return trip from paypal.com. Known gap: a customer who approves on PayPal but closes the tab before returning leaves the order in `PendingPayment` with no automatic recovery. Webhook wiring is a natural, low-risk Sprint 07+ addition.
- **Notification delivery is logged only on success, deliberately.** `App\Jobs\DispatchOrderNotification` checks `NotificationLog` for an existing row before sending, but only writes the row *after* a send actually succeeds. A transient Twilio/Postmark failure can still retry via the job's own queue backoff without the dedupe check itself blocking the retry — only an actually-delivered message blocks a resend.
- **Store credit's checkout-time floor is simplified on purpose.** Store credit always floors at `minimum_charge_cents`, even for cash orders that have no real card-minimum reason to. This keeps the checkout page's client-side credit math trivially and exactly reproducible against `PlaceOrder`'s server-side tamper cross-check, rather than a payment-method-dependent calculation that could drift and cause spurious "prices changed" rejections.
- **`NotificationLog` is deliberately timestamp-light.** It has a single `sent_at` column (DB-level `useCurrent()`), not Eloquent's usual `created_at`/`updated_at` pair — matching the append-only-ledger pattern used elsewhere (`$timestamps = false`, both `updating`/`deleting` throw).

## Next

1. Real PayPal, Twilio and Postmark credentials, plus Twilio A2P 10DLC campaign registration — none of this ships without it, and none of it should ever be pasted into an AI session (owner adds directly to `.env`/host secrets).
2. PayPal Advanced Vaulting business approval, if off-session overage recharge for PayPal orders is ever wanted — today it correctly falls back to a payment link instead.
3. A PA sales-tax determination is still needed before `NullTaxCalculator` is replaced with a real `TaxCalculator` — the column and hold/final threading are ready, the number is always 0 until then (same gate class as the USDA question).
4. SPF/DKIM/DMARC DNS records for the sending domain, or Postmark notification email lands in spam regardless of code correctness.
5. PayPal webhook handling as defense-in-depth for the abandoned-approval edge case (see above) — `parseWebhook()` is ready, just unwired.
6. Everything open from Sprints 01-05 is unaffected and still open: real Stripe test/live keys, CODEOWNERS, Forge staging secret, real catalog/photos, real animals/lots, production-capacity tuning, GS1-128 label verification on real hardware, Reverb/real-time (still polling by owner decision), the seeded delivery zone/zip/slot map, and the delivery-fee invoice line-item breakout.
7. Sprint 07 (guideline ch. 6): admin, reports & compliance — phone/counter orders, yield/margin/wastage/stock-aging reports, a read replica + Metabase, USDA/PA consent language, HACCP/temperature logs, privacy/cookie/CCPA compliance, and an ADA/WCAG accessibility audit.
