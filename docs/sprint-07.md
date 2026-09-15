# Sprint 07: admin, reports & compliance

**Goal (guideline ch. 6):** the scenario of actually running the business day to day, plus the conditions for opening legally in the US — phone/counter orders, yield/margin/wastage/sales/stock-aging reports, an audit log, USDA/PA consent at checkout, HACCP/inspection records, and privacy/cookie/CCPA/ADA compliance.

## Owner decisions (guideline ch. 7, S07) — recorded 2026-09-15

| Question | Decision | Where it lives |
|---|---|---|
| What did the USDA/PA legal determination conclude? | **USDA-inspected facility.** Full federal inspection — the checkout disclosure and terms page state this plainly; a real establishment number is added by the owner later, never fabricated. | `config('catchweight.usda_establishment_number')`, `resources/views/shop/checkout.blade.php`, `resources/views/shop/legal/terms.blade.php` |
| How should own-farm animal cost be handled for margin/yield reporting, since there's no purchase invoice? | **Manual estimate, always labeled "estimated."** Staff can enter a cost for any animal; own-farm-sourced animals are flagged `(estimated)` everywhere a margin is shown, and never blended in as if they were real, invoiced cost. | `App\Enums\AnimalCostSource`, `Animal::isCostEstimated()`, `App\Filament\Pages\Reports\MarginReport` |
| How should a CCPA deletion request be handled, given it conflicts with recall/traceability record-keeping? | **Anonymize — scrub the customer's name/contact info, keep the traceability link.** An order's PII is scrubbed to a placeholder; its lot/animal/order-item links are untouched, so a recall can still trace forward and back. | `App\Actions\Compliance\AnonymizeCustomerData`, `App\Models\PrivacyRequest` |

## Tasks

| Task | Status | Where |
|---|---|---|
| Phone/counter order entry + customer lookup | ✅ (pickup only, see below) | `App\Filament\Pages\PhoneOrder` |
| Yield report (actual finished-vs-raw weight vs. modeled yield%) | ✅ | `App\Filament\Pages\Reports\YieldReport` |
| Margin report (revenue vs. animal cost, own-farm estimated flag) | ✅ | `App\Filament\Pages\Reports\MarginReport`, `Animal.cost_source`/`cost_cents` |
| Wastage report (spoilage + animal dressing loss) | ✅ | `App\Filament\Pages\Reports\WastageReport` |
| Sales report (revenue by day and by product) | ✅ | `App\Filament\Pages\Reports\SalesReport` |
| Stock-aging report (FEFO worklist by use-by date) | ✅ | `App\Filament\Pages\Reports\StockAgingReport` |
| Audit log UI | ✅ (scoped v1 — see below) | `App\Models\AuditLog`, `App\Filament\Resources\AuditLogs` |
| Read replica + Metabase | ➖ (deferred, disclosed) | see "What's new" below |
| USDA/PA consent language at checkout, recorded | ✅ | `Order.regulatory_consent_at`/`regulatory_consent_version`, `CheckoutRequest` |
| HACCP inspection pack (one-click PDF) | ✅ | `App\Support\InspectionPackPdf`, `App\Filament\Pages\InspectionPack` |
| Privacy policy, terms, cookie notice | ✅ | `resources/views/shop/legal/*`, cookie notice in `components/layouts/shop.blade.php` |
| CCPA data-request form + anonymize action | ✅ | `App\Http\Controllers\Shop\PrivacyRequestController`, `App\Actions\Compliance\AnonymizeCustomerData` |
| ADA/WCAG 2.1 AA pass | ✅ (in-house best-effort — see below) | skip link, `aria-live` totals, alt text, labeled inputs across the storefront |

## Definition of Done: evidence

| DoD item | Result | How it was checked |
|---|---|---|
| A staff member can look up a returning customer by phone and place a pickup order for them | ✅ | `PhoneOrderTest`: `lookupCustomer()` prefills name/email from the customer's most recent order; `submit()` places a real order through `PlaceOrder` |
| Phone/counter order entry is reachable only by Owner/Manager/Front desk | ✅ | `AdminResourceAuthorizationTest`: `orders.manage` role matrix on `/admin/phone-order` |
| A lot's actual margin shows up in the report, and an own-farm animal's margin is visibly flagged as an estimate | ✅ | `MarginReportTest`: a purchased-cost animal's margin is unflagged; an own-farm animal's margin carries `isCostEstimated() === true`; an animal with no recorded cost appears in the separate "no cost" bucket, not blended into margin figures |
| The yield report compares actual finished weight against the product's modeled yield% | ✅ | `YieldReportTest`: a 100%-actual (no cut option) result correctly compares against a set `Product.yield_pct` |
| Reports, the audit log, and compliance pages are all reachable only by the correct roles | ✅ | `AdminResourceAuthorizationTest`: `reports.view` (Owner/Manager/Accountant) for the five reports + audit log; `compliance.manage` (Owner/Manager only) for privacy requests + the inspection pack |
| Checkout shows the USDA/PA disclosure and won't submit without it; the order records which version was agreed to | ✅ | `RegulatoryConsentTest`: omitting `agree_regulatory_notice` fails validation; a placed order's `regulatory_consent_at`/`_version` are set to the current `config('catchweight.regulatory_notice_version')` |
| An inspection pack for a date range compiles animals, lots, and QC/temperature checks into one PDF | ✅ (by construction — see `App\Support\InspectionPackPdf`) | `InspectionPack`'s header action form + PDF template; not covered by an automated test this sprint (PDF rendering, same as the invoice/cutting-sheet precedent) |
| A CCPA delete request scrubs the customer's PII but never breaks the order/lot/animal traceability chain | ✅ | `PrivacyRequestTest`: after `AnonymizeCustomerData`, the order's name/email/phone are scrubbed but its `order_items` (and therefore lot/animal links) are untouched |
| Role changes, cash payments, store-credit issuance and staff logins are all logged, and the log can't be edited or deleted after the fact | ✅ | `AuditLogTest`: each event writes exactly one `AuditLog` row with the right causer; `update()`/`delete()` on an existing row both throw |
| Every role × every new report/compliance/order route returns the correct 200/403 | ✅ | `AdminResourceAuthorizationTest`: three new route groups added to the existing matrix |

## What's new vs. Sprint 06

- **Read replica + Metabase, deferred by design, disclosed here.** Standing up a MySQL read replica and an external Metabase instance is production infrastructure the owner needs to provision (a hosting/cost decision, not a code one). All five reports instead query the primary database directly through plain Filament Pages — correct today, and a reasonable place to point a replica connection at later without changing any report's logic, once that infrastructure exists.
- **A real, if narrow, bug in the audit log — found by its own test, not by inspection.** `Illuminate\Database\Eloquent\Concerns\HasAttributes` already declares a real `protected $changes` property on every Eloquent model, for its own dirty-tracking. Naming the audit log's own JSON column `changes` meant `$log->changes = [...]` resolved to that *real PHP property* instead of Eloquent's attribute-casting `__set()` magic — the column silently stayed `null` on every insert, with no error anywhere. Renamed the column (and everywhere it's referenced) to `meta`. Worth remembering: never name an Eloquent model attribute `changes` (or presumably `attributes`, `original`, `exists`, or any other real property `Model`/`HasAttributes` already declares).
- **Two more self-caught bugs, same well-worn lesson from Sprints 03/05.** A `PrivacyRequest`'s `status` column has a DB-level `->default('pending')`, but `status` isn't (and shouldn't be) mass-assignable — so the public request form now builds the model explicitly (`new PrivacyRequest; ...; $privacyRequest->status = PrivacyRequestStatus::Pending; ->save()`) rather than trusting the DB default to appear in a freshly-created, unrefreshed in-memory instance. Caught in `PrivacyRequestTest` before it ever reached production, exactly the "assign every such column explicitly, even when it equals the DB default" rule from Sprints 03 and 05.
- **A Livewire-specific lesson, new this sprint: a Filament Page's public properties must be Livewire-"synth"-able.** `App\Support\Weight` (this app's bcmath value object) has no registered Livewire synth, so a raw `public Weight $totalWastageWeight` on `WastageReport` crashed every request to that page with a 500 the moment Livewire tried to serialize it — caught immediately by `AdminResourceAuthorizationTest`'s own role matrix, which actually loads every new page. Fixed by storing the pre-formatted decimal string instead. A `Collection` of plain arrays containing an Eloquent model (as `MarginReport.$rows` does) is fine — Livewire recursively finds the model and uses its own well-supported model synth; a bare custom value object anywhere in a public property is not.
- **Own-farm costing is schema-agnostic between the two "no margin" options the owner didn't pick.** `Animal.cost_source`/`cost_cents` are recorded the same way regardless of which of the three guideline options got chosen — only `MarginReport`'s query/labeling logic encodes the actual decision (manual estimate, always flagged). Switching to "purchased-lots-only" later would be a report-query change, not a schema migration.
- **Phone/counter orders are pickup-only, disclosed.** The full storefront checkout flow's delivery-zone/slot picker is real UI complexity; replicating it inside an internal staff tool wasn't worth it for v1. A phone customer wanting delivery still needs the web checkout (or a staff member reading them through it). This mirrors the sprint's disclosed-scope-cut pattern from every prior sprint (S05's Reverb, S06's PayPal webhook).
- **The yield report's "actual vs. modeled" comparison is honestly limited, and says so in its own description text.** For an item with a cut option, `consumed_raw_weight_lb` is *derived from* that cut's own `raw_yield_pct` formula (there's no independently-measured raw weight anywhere in the schema), so actual and modeled will always match exactly for those rows — not a bug, just not the variance-detection tool it might look like at a glance. It's genuinely useful for items with no cut option, compared against `Product.yield_pct` (e.g. a Half/Quarter share).
- **The audit log is a deliberately scoped v1**, not a blanket log of every model's every change: staff role/active changes, staff logins, cash payments, and store-credit issuance. Catalog/coupon/notification-template edits are not yet audited — a natural, low-risk follow-up once it's clear which of those the owner actually wants tracked.

## Next

1. A real USDA establishment number, once assigned, for the checkout notice and terms page (`USDA_ESTABLISHMENT_NUMBER` env var — currently blank, and the copy reads fine either way).
2. A read replica and Metabase instance, once the owner has decided on hosting for it — every report already queries the primary DB directly and needs no code change to point at a replica later.
3. A real ADA/WCAG 2.1 AA audit by a professional or automated tool (axe, Lighthouse) — this sprint's pass (skip link, `aria-live` on dynamic totals, alt text, associated labels) is a solid in-house baseline, not a certified audit.
4. Legal review of the privacy policy, terms and USDA/PA checkout notice copy — written to be accurate and specific to this business, but not drafted or reviewed by a lawyer.
5. Decide whether to extend the audit log to catalog/coupon/notification-template edits, and whether CCPA "access" requests should generate an automated data export rather than a manual one.
6. Everything open from Sprints 01-06 is unaffected and still open: real Stripe/PayPal/Twilio/Postmark credentials, 10DLC registration, PayPal Vault approval, a PA sales-tax determination, SPF/DKIM/DMARC records, CODEOWNERS, Forge staging secret, real catalog/inventory, GS1-128 hardware verification, and the delivery zone/slot/scheduler setup.
7. Sprint 08 (guideline ch. 6): nationwide cold-chain shipping — gated entirely on whether the USDA determination (now confirmed: USDA-inspected) permits interstate shipping in practice; if not, v1 stops at eight sprints and Sprint 08 is skipped in favor of hardening (Sprint 09).
