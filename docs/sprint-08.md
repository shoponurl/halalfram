# Sprint 08: nationwide cold-chain shipping

**Goal (guideline ch. 6):** sell outside Philadelphia by overnight carrier — but only because the launch-gate condition (guideline ch. 8, risk R1) is now actually met: the USDA determination confirmed in Sprint 07 permits interstate shipping. Per the guideline's own dependency note, this sprint would have been skipped entirely (straight to Sprint 09) if that answer had gone the other way.

## Owner decisions (guideline ch. 7, S08) — recorded 2026-09-15

| Question | Decision | Where it lives |
|---|---|---|
| Can this operation actually ship across state lines? | **Confirmed yes.** The USDA-inspected status from Sprint 07 permits interstate shipping. This unblocked the sprint — see the gate note above. | n/a (a legal confirmation, not a code artifact) |
| Which carrier and service level? | **Overnight only, to start.** Simplest and safest for a perishable shipment — minimizes time outside refrigeration. Two-day (or customer choice) is a later addition once real transit data exists. | `config('catchweight.shipping_service_level')`, `App\Shipping\EasyPostGateway::cheapestOvernightRate()` |
| What do you owe a customer whose order arrives warm? | **Full refund, always.** Never a resend (a second shipment risks the same cold-chain failure) and never a partial credit. | `App\Actions\Shipping\MarkArrivedWarm`, `resources/views/shop/legal/terms.blade.php` |
| Ship chilled or frozen? | **Frozen by default; chilled only where a specific product needs it.** Frozen tolerates transit delays far better. | `Product.requires_chilled_shipping`, `App\Actions\Shipping\PriceShipment` |

## Tasks

| Task | Status | Where |
|---|---|---|
| EasyPost — rate quote, label, tracking webhook | ✅ (hand-built client, see below) | `App\Shipping\EasyPostGateway`, `App\Http\Controllers\EasyPostWebhookController` |
| Packing rules by weight and temperature, in the database | ✅ | `App\Models\PackingRule`, `Filament\Resources\PackingRules` |
| Ship-day calendar — weekday + holiday blackout | ✅ | `App\Actions\Shipping\ComputeShipDate`, `App\Models\ShipBlackoutDate` |
| Address verification | ✅ | `App\Shipping\ShippingGateway::verifyAddress()` (EasyPost `/addresses?verify[]=delivery`) |
| Perishable shipping terms and liability | ✅ | `resources/views/shop/legal/terms.blade.php`, checkout's `agree_perishable_shipping` |

## Definition of Done: evidence

| DoD item | Result | How it was checked |
|---|---|---|
| The shipping rate rides through the estimate, the hold and the final charge | ✅ | `NationwideShippingTest`: `shipping_rate_cents` appears in both `estimated_cents`/`hold_cents` at placement; `FinalizeOrder`'s subtotal sum now includes it too (same rule as S02/S05/S06's charge-component lesson) |
| Cash is never accepted for a shipping order | ✅ | `NationwideShippingTest`: cash + shipping throws, creates nothing |
| A product flagged chilled forces the whole shipment to ship chilled | ✅ | `NationwideShippingTest`: one chilled item in the cart sets `package_temperature = Chilled` for the whole order |
| An order that doesn't fit any packing rule is rejected at checkout, not discovered later | ✅ | `NationwideShippingTest`: a 100 lb order (over every seeded rule's max) throws before creating an order |
| The label is bought only after the customer is actually charged, not at placement | ✅ | `NationwideShippingTest`/`PurchaseShippingLabelTest`: no label exists until `FinalizeOrder` → `SettleOrderPayment` → `complete()` runs; the `AwaitingBalance` (overage) path buys it only once `MarkBalancePaid` later completes it |
| The system itself blocks shipping on the wrong day | ✅ | `PurchaseShippingLabelTest`: on an invalid ship day the job redispatches itself for the next valid one instead of ever calling the carrier; on a valid day it buys immediately |
| A retried/duplicate label-purchase attempt never buys a second label | ✅ | `PurchaseShippingLabelTest`: calling the job again after a label already exists is a no-op |
| A cold-chain failure is always a full refund, and only from a shipped order | ✅ | `MarkArrivedWarmTest`: the full `final_cents` is refunded; marking an order arrived-warm before it has even shipped is rejected |
| EasyPost's tracking webhook marks an order delivered, and ignores an unknown shipment | ✅ | `EasyPostWebhookTest` |
| Every role × every new shipping-admin route returns the correct 200/403 | ✅ | `AdminResourceAuthorizationTest`: packing-rules/ship-blackout-dates (Owner/Manager only) added to the existing matrix |

## What's new vs. Sprint 07

- **A real package arriving cold, with a temperature logger, is a physical test — not something this session can perform.** Same category as Sprint 01's live $1 Stripe transaction and Sprint 04's GS1-128 label-on-real-hardware verification: the code path is built and tested end-to-end with a fake carrier, but an actual overnight shipment has to happen once real EasyPost credentials exist.
- **Another hand-built gateway, same reasoning as Sprint 06's PayPalGateway.** `App\Shipping\EasyPostGateway` calls EasyPost's REST API directly via Laravel's `Http` facade rather than pulling in an SDK — kept symmetric with `StripeGateway`/`PayPalGateway`'s own thin-wrapper style, and avoids a new Composer dependency needing sign-off for what is, again, a plain JSON API.
- **"Overnight" is defined operationally, not by carrier-specific service names.** Rather than hardcode "FedEx First Overnight" or similar, `EasyPostGateway::cheapestOvernightRate()` takes the cheapest rate EasyPost quotes with a 1-day estimated transit, across whichever carriers the account has enabled. This means the code needs no changes if the owner adds or drops a carrier in the EasyPost dashboard later.
- **Nationwide shipping deliberately reuses the local-delivery address columns** (`delivery_address_line1`/`_city`/`_state`/`_zip`) rather than adding a parallel set — the two fulfilment methods never overlap on one order, and `Order::fullDeliveryAddress()` already worked for both with no change.
- **The shipping rate charged to the customer is fixed at checkout time and never re-quoted at fulfilment**, even though the real EasyPost label (bought once the actual weight is known) can come back at a slightly different real cost. `Order.shipping_actual_rate_cents` records that real figure separately for reconciliation; the customer is never charged a moving target, matching how the product price itself already works within its own catch-weight tolerance. A significant, systematic gap between quoted and actual shipping cost would show up in that reconciliation column — worth revisiting once real volume exists.
- **Two more self-caught bugs, both found by the quality gate before any test ran:**
  - A `Weight` value object (this app's bcmath type) can't be a bare Livewire component property — carried over as a lesson from Sprint 07's `WastageReport` bug, deliberately avoided here by never storing one directly on a Page.
  - An orphaned second `/** */` docblock immediately following a real one on `ShippingGateway::buyOvernightLabel()` silently disconnected the `@param` array-shape types from the method — PHP (and PHPStan) only associates a docblock with the *next* line of code, so the second, undocumented block "won by proximity." Larastan caught the resulting `missingType.iterableValue` before this ever became a real bug, but the lesson generalizes: two consecutive `/** */` blocks above one declaration is always a mistake, not extra documentation.
- **Cash-on-delivery's existing "no cash for delivery" rejection in `PlaceOrder` was extended, not duplicated** — the same `if ($paymentMethod === 'cash' && ($isDelivery || $isShipping))` guard now covers both non-pickup fulfilment methods, rather than adding a second near-identical check.

## Next

1. Real EasyPost API + webhook credentials, and a genuine end-to-end overnight test shipment with a temperature logger — nothing here ships until that happens.
2. The real Lansdowne-area packing rules (box sizes, dry-ice weights per weight band) need entering via `/admin/packing-rules` — nothing is seeded beyond what tests create.
3. Federal holidays and any other no-ship dates need entering via `/admin/ship-blackout-dates` before launch — the Monday-Thursday rule alone doesn't know about Thanksgiving.
4. Revisit two-day (or customer-choice) service levels once real overnight cost/volume data exists.
5. Everything open from Sprints 01-07 is unaffected and still open: real Stripe/PayPal/Twilio/Postmark credentials, 10DLC registration, a PA sales-tax determination, SPF/DKIM/DMARC records, CODEOWNERS, Forge staging secret, real catalog/inventory, GS1-128 hardware verification, the delivery zone/slot/scheduler setup, a real USDA establishment number, and a professional ADA/WCAG audit.
6. Sprint 09 (guideline ch. 6): hardening & launch — a third-party penetration test, full backup/restore drill, load testing at 10x normal traffic, key rotation, a soft launch limited to pickup and a few zip codes.
