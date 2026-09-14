# Sprint 02: catalog & cut options

**Goal (guideline ch. 6):** move from one test product to the whole shop — structured cut, offal and packing choices per species, never free text, so the butcher never has to guess.

**Launch gate (ch. 8, risk R1):** the guideline requires a USDA/PA legal answer before this sprint starts. The owner confirmed that answer is in hand (recorded 2026-09-22) before work began.

## Owner decision (guideline ch. 7, S02) — recorded 2026-09-22

| Question | Decision | Where it lives |
|---|---|---|
| Cut/packing options add butcher processing lead time — what happens if that risks outliving the card hold? | Cap lead time to the hold window: checkout refuses any cut+packing combination needing more than the configured max | `config/catchweight.php` → `max_lead_time_days` (4 days, under the 7-day hold); enforced in `App\Actions\Orders\QuoteCart` |

## Tasks

| Task | Status | Where |
|---|---|---|
| Category tree & species, Whole/Half/Quarter variants with yield % | ✅ | `categories` table, `App\Models\Category`; `products.category_id`/`portion_type`/`yield_pct` |
| Structured cut option set per species (never free text) | ✅ | `cut_options` table, `App\Models\CutOption`, `App\Enums\CutStyle` |
| Offal selection per species | ✅ | `offal_options` table, `App\Models\OffalOption` |
| Packing options + surcharge + extra lead time | ✅ | `packing_options` table, `App\Models\PackingOption` |
| Butcher cutting sheet PDF | ✅ | `App\Support\CuttingSheetPdf`, `resources/views/orders/cutting-sheet.blade.php`, "Cutting sheet" action on the order's admin page |
| Product photo + Filament catalog CRUD | ✅ | `ProductForm` (`FileUpload`, public disk), `CategoryResource`, `CutOptionResource`, `OffalOptionResource`, `PackingOptionResource` |

## Definition of Done: evidence

| DoD item | Result | How it was checked |
|---|---|---|
| Staging login works, all 6 roles active | ✅ | Unchanged from Sprint 00/01 — no auth changes this sprint |
| `guarded = []` fails CI | ✅ | Unchanged Sprint 00 arch rule; every new model (`Category`, `CutOption`, `OffalOption`, `PackingOption`) declares `$fillable` |
| A fake API key in a commit is blocked by gitleaks | ✅ | Ran gitleaks on the staged diff before commit |
| Pages match the design | ✅ | New category/product pages reuse `x-layouts.shop` and the existing Tailwind classes verbatim; the marketing homepage (`x-home.*`, pixel-matched to the original design) is untouched |
| A real $1 transaction still runs the full path in staging | ⏳ (carried over from Sprint 01) | Still blocked on the owner's Stripe test keys; nothing in this sprint touches the payment path itself |
| Choosing cut + offal + packing correctly increases the price | ✅ | `CatchWeightOptionsTest`: base price + cut + offal + packing surcharges, each multiplied by quantity, summed once; verified live over HTTP (`/products/goat-whole` → add to cart → cart shows $230.25 for 899¢/lb × 25 lb + $4.00 cut + $1.50 packing) |
| Cutting sheet prints so the butcher can understand | ✅ | `CuttingSheetPdfTest`; lines with no options print "Standard — no special instructions" instead of a blank field, so nothing is ever ambiguous |

## What's new vs. Sprint 01

- **Cart lines now carry options.** `App\Support\CartLine`/`Cart` key a line by product **and** its chosen cut/offal/packing, so the same product with two different cuts is two cart lines. Sprint 01's plain `productId => quantity` shape still works untouched — 138 of Sprint 01's tests pass unmodified against the new `QuoteCart`/`PlaceOrder`, which normalize both shapes.
- **Options are snapshotted onto the order item** (name + price), the same append-only guarantee Sprint 01 gave the policy percentages — renaming or repricing an option later never rewrites a placed order (`CatchWeightOptionsTest`).
- **Lead-time cap is a real checkout rule**, not just a UI hint: `QuoteCart` rejects a cart line before it can ever reach `PlaceOrder` if the chosen options would need more processing time than the card hold can cover.
- **Real HTTP-boundary tamper tests** (`CartOptionsSecurityTest`) mirror `CheckoutSecurityTest`'s pattern: an option id from another category, or a product whose category doesn't offer options at all, is rejected with 422 before it ever reaches the cart.

## Next

1. Add the real catalog and photos via `/admin/categories`, `/admin/cut-options`, `/admin/offal-options`, `/admin/packing-options` and `/admin/products` in staging — this sprint ships a representative starter set (`Database\Seeders\CategorySeeder`/`CatalogSeeder`), not your actual products.
2. The Sprint 01 Stripe test-keys and CI/CODEOWNERS/Forge items from `docs/sprint-01.md` are still open and unaffected by this sprint.
3. Sprint 03 (guideline ch. 6): inventory, lot numbers and cold storage.
