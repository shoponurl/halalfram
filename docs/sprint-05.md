# Sprint 05: local delivery & pickup

**Goal (guideline ch. 6):** everything needed to start selling within Philadelphia — nationwide shipping comes later. Pickup and local delivery both need a real lifecycle from "paid" to "in the customer's hands," not just an order status.

## Owner decisions (guideline ch. 7, S05) — recorded 2026-09-15

| Question | Decision | Where it lives |
|---|---|---|
| How should the delivery service area be defined? | **Zip code list.** A zone owns a flat fee; zips are assigned to a zone. A checkout zip that isn't in any zone is rejected with a friendly message, and pickup is always offered as a fallback. | `App\Models\DeliveryZone`, `App\Models\ServiceZip`, `App\Actions\Delivery\ReserveDeliverySlot::zoneForZip()` |
| What should the delivery charge structure be? | **Flat fee per zone.** No distance/weight-based pricing yet. | `DeliveryZone.flat_fee_cents` |
| What happens when a driver arrives and no one is home? | **Return to store → one free re-attempt → then refund minus the delivery fee.** The order goes back to `DeliveryFailed` (not resolved) after the first miss; a second miss writes it off as `Returned` and refunds everything except the delivery fee, since the trip was still made. | `App\Actions\Delivery\MarkDeliveryFailed`, `config('catchweight.delivery_max_attempts')` (2) |
| If a customer books pickup and no one comes to collect it? | **Hold until end of next business day, one reminder, then a partial refund.** A reminder fires once past `pickup_reminder_hours`; past `pickup_writeoff_hours` the order is written off with an `pickup_writeoff_refund_pct` refund (goods already cut/packed aren't free to redo). | `App\Console\Commands\ExpireMissedPickups`, `App\Actions\Delivery\{SendPickupReminder,MarkMissedPickup}` |
| How long can a delivered order safely sit outside refrigeration? | **2 hours** (FDA/USDA "danger zone" rule). Used to size delivery slot windows and as a documented constraint on routing, not yet enforced by code (no live driver-tracking exists to measure it against). | `config('catchweight.cold_chain_max_hours')`, `DeliverySeeder`'s 2-hour slot windows |

## Tasks

| Task | Status | Where |
|---|---|---|
| Zip-code service area + per-zone flat fee | ✅ | `DeliveryZone`, `ServiceZip`, `Filament\Resources\DeliveryZones` |
| Delivery slots with real driver capacity | ✅ | `DeliverySlot`, `Filament\Resources\DeliverySlots`, capacity checked against live bookings in `ReserveDeliverySlot` |
| Checkout collects fulfilment method, address, slot | ✅ | `CheckoutRequest`, `CheckoutController`, `resources/views/shop/checkout.blade.php` |
| Delivery fee rides through estimate, hold and final charge | ✅ | `PlaceOrder`, `FinalizeOrder` |
| Pickup lifecycle: ready → picked up | ✅ | `App\Actions\Delivery\{MarkReadyForPickup,MarkPickedUp}` |
| Delivery lifecycle: out for delivery → delivered/failed, with OTP or photo proof | ✅ | `App\Actions\Delivery\{MarkOutForDelivery,MarkDelivered,MarkDeliveryFailed,RescheduleDelivery}` |
| Missed-pickup reminder + write-off automation | ✅ | `App\Console\Commands\ExpireMissedPickups` (hourly schedule) |
| Partial/full refunds for write-offs | ✅ | `App\Jobs\RefundFulfilment`, `PaymentGateway::refund()` (Stripe + fake) |
| Driver-facing screen with no price data | ✅ | `App\Filament\Pages\DriverRoute` |
| Customer-facing delivery/pickup status | ✅ | `resources/views/shop/order.blade.php` |
| Append-only delivery event history | ✅ | `delivery_events` table, `App\Models\DeliveryEvent`, `DeliveryEventsRelationManager` |

## Definition of Done: evidence

| DoD item | Result | How it was checked |
|---|---|---|
| A delivery order outside the service area is rejected, nothing created | ✅ | `DeliveryCheckoutTest`: unknown zip throws a `ValidationException` inside `PlaceOrder`'s transaction, `Order::count()` stays 0 |
| Slot capacity is real, checked against actual bookings | ✅ | `DeliveryCheckoutTest`: a capacity-1 slot rejects a second order for that slot |
| The delivery fee is actually charged, not just quoted | ✅ | `DeliveryCheckoutTest` (fee in estimate/hold) + `FulfilmentTest` (orders reach `Completed`/paid with the fee included) — this closed a real bug, see below |
| No one home → one free re-attempt → refund minus fee on the second failure | ✅ | `FulfilmentTest`'s re-attempt test: first failure leaves `DeliveryFailed` with no refund call; second failure sets `Returned`→`Refunded` and refunds `final_cents - delivery_fee_cents` |
| Proof of delivery is OTP or photo, and a wrong OTP is rejected | ✅ | `FulfilmentTest`: correct OTP, wrong OTP (throws), and photo-only paths all covered; a `MarkDelivered` call with neither is rejected |
| Missed pickup: one reminder, then a write-off with a partial refund | ✅ | `MissedPickupTest` (uses `travelTo` to cross both deadlines): no action before either deadline, exactly one reminder (idempotent on a second run), then a `pickup_writeoff_refund_pct` refund past the write-off window |
| Driver screen never queries price columns | ✅ (by construction) | `DriverRoute`'s table query selects only customer/address/status columns; `Driver` role has `deliveries.manage` but not `orders.view`, so no other price-showing admin page is reachable either — confirmed in `AdminResourceAuthorizationTest` |
| Fulfilment status can't move before the order is actually paid | ✅ | `FulfilmentTest`: `manageFulfilment` policy returns false and every action throws for a `Placed` order |
| Every role × every new route returns the correct 200/403 | ✅ | `AdminResourceAuthorizationTest`: delivery-zones/-slots (Owner/Manager only) and `/admin/driver-route` (Owner/Manager/Driver only) added to the existing matrix |

## What's new vs. Sprint 04

- **A second, independent status axis.** `FulfilmentStatus` tracks pickup/delivery progress separately from the existing payment `OrderStatus`, the same way Sprint 04 kept QC as an independent gate ahead of `FinalizeOrder`. Fulfilment actions are gated by a new `OrderPolicy::manageFulfilment`, which requires the order to already be `Completed`/`AwaitingBalance` — a driver or front-desk action can never race ahead of payment.
- **Regression found and fixed: the delivery fee wasn't actually being charged.** `FinalizeOrder` summed only `order_items.final_cents` — the delivery fee, an order-level charge, was silently dropped from the amount that gets captured, even though it was correctly included in the estimate and the card hold. This is the same class of bug as Sprint 03's cut/offal/packing-surcharge regression: every new order-level charge component has to be threaded through *both* the initial hold *and* the final settlement sum, or it leaks. Caught by `FulfilmentTest`'s delivery scenarios failing at "the order isn't paid yet," not by inspection. Fixed in `App\Actions\Orders\FinalizeOrder`.
- **The delivery OTP is deliberately stored in plaintext**, not hashed like `public_token_hash`. It's a short-lived confirmation code that must be shown back to the customer and read aloud to a driver — a bearer credential model doesn't fit here, so `Order::matchesDeliveryOtp()` does a constant-time plain comparison instead of a hash comparison.
- **Refunds are a new payment primitive.** `PaymentGateway::refund()` (Stripe + fake) and `App\Jobs\RefundFulfilment` follow the same `Cache::lock` + idempotency-key-guarded `PaymentTransaction` pattern as Sprint 04's `SettleOrderPayment`, so a queue retry can never double-refund. `Order::totalChargedCents()` now nets out `refunded_cents`.
- **Automatic vs. manual by design, matching the two guideline scenarios.** Missed pickup is fully clock-driven (`ExpireMissedPickups`, hourly schedule) because "no one collected it" is genuinely time-based. A failed delivery is driver-reported, not clock-driven — there's no automation guessing whether someone was home.
- **The driver-facing page is a bespoke Filament `Page`, not a scoped `OrderResource` view**, specifically so price columns are never queried into it at all. Combined with the `Driver` role lacking `orders.view`, drivers have no reachable admin page that shows a price.

## Next

1. Replace the seeded starter delivery zone/zips/slots (`DeliverySeeder`) with the real Lansdowne-area service map, fees and driver capacity before launch.
2. Configure a real scheduler (`php artisan schedule:work` or system cron → `schedule:run`) in staging/production so `orders:expire-missed-pickups` actually fires hourly — nothing runs it automatically outside of `artisan`.
3. The delivery fee doesn't yet appear as its own line item on the printed invoice (`resources/views/invoices/pdf.blade.php`) — it's included in the total correctly but not broken out. Low priority, deferred.
4. Everything open from Sprints 01-04 is unaffected and still open: real Stripe test/live keys, CODEOWNERS, Forge staging secret, real catalog/photos, real animals/lots, production-capacity tuning, GS1-128 label verification on real hardware, and Reverb/real-time (still polling everywhere by owner decision, repeated again this sprint implicitly by not introducing it).
5. Sprint 06 (guideline ch. 6): customer notifications (SMS/email) — pickup-ready, out-for-delivery, delivered receipts. `SendPickupReminder` already has a comment noting it's a manual call/text today because no messaging channel exists yet.
