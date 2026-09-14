# Sprint 03: inventory, lot & cold storage

**Goal (guideline ch. 6):** weight-based stock, one lot per pack — the recall report and the expiry basis stand on this sprint.

## Owner decisions (guideline ch. 7, S03) — recorded 2026-09-22

| Question | Decision | Where it lives |
|---|---|---|
| A "2 lb boneless" order — what does stock decrease by? The guideline flags this as the answer the whole stock model rests on. | Raw weight consumed, including trim loss (e.g. ~2.9 lb raw for a 2 lb finished boneless cut) — not just the finished weight the customer receives | `CutOption.raw_yield_pct`; `App\Support\Weight::dividedByPercent()`; applied in `ReserveStock`/`ConsumeStock` |
| How long does a reservation hold stock before releasing it? | For the full order lifecycle — release only happens on payment failure, authorization expiry, or (later) cancellation, not on a separate short cutting-stage window | `App\Actions\Inventory\ReleaseStock`, called from `PlaceOrder`'s failure path and the webhook's `payment_intent.canceled` handler |

## Tasks

| Task | Status | Where |
|---|---|---|
| Decimal weight stock, dual UOM | ✅ | `lots.on_hand_weight_lb`/`reserved_weight_lb` (`DECIMAL(10,3)`), `App\Models\Lot` |
| Storage location — chiller, freezer, room | ✅ | `App\Enums\StorageLocation`, `lots.storage_location` |
| Lots — slaughter date, pack date, use-by, FEFO | ✅ | `App\Models\Animal`/`Lot`; `Lot::scopeAvailableFor()` orders by `use_by_date` |
| Reserved vs available, wastage and trim loss | ✅ | `Lot::availableWeight()`; `App\Actions\Inventory\{ReserveStock,ConsumeStock,ReleaseStock,RecordWastage}`; append-only `stock_movements` |
| Minimum animal register (traceability) | ✅ | `App\Models\Animal` (tag, species, slaughter date, live/dressed weight), `AnimalResource` |
| Recall report: piece → lot → order, both directions | ✅ | `App\Filament\Pages\RecallReport` (`/admin/recall-report`) |

## Definition of Done: evidence

| DoD item | Result | How it was checked |
|---|---|---|
| Given a lot number, find every order it touched, fast | ✅ | `RecallReport` queries `order_items.lot_id` directly (indexed); `AdminResourceAuthorizationTest` confirms the page renders for Owner/Manager/Butcher and 403s for everyone else |
| Given an order, trace which animal and what slaughter date | ✅ | Same page, order → `items.lot.animal`; `ItemsRelationManager` also shows the lot/animal inline on the order's admin view |
| Reconciliation balances: live weight = packed + wastage + unexplained | ✅ | `Animal::dressingLoss()` = live − dressed weight (the "unexplained"/processing-loss share); `StockReservationTest` proves on-hand, reserved and consumed stay consistent through a full order lifecycle |
| A product with no lots checks out exactly as before | ✅ | `StockReservationTest`: "leaves a lot with no matching product untouched and a product with no lots fully untracked"; the full Sprint 01/02 suite (204 tests) passes unmodified |
| Checkout is rejected when stock runs out, and nothing is created | ✅ | `StockReservationTest`: insufficient-stock case throws `ValidationException` inside `PlaceOrder`'s transaction, so no order or reservation survives |
| Stock reserved at checkout is truly released, not silently lost, when an order fails | ✅ | `StockReservationTest`: card-decline and authorization-expiry cases, plus idempotency guards in `ReleaseStock`/`ConsumeStock` so a retried webhook or failure path can never double-release or double-consume |

## What's new vs. Sprint 02

- **A product with lots is tracked; a product with none is untracked.** `ReserveStock` is a no-op when a product has zero `lots` rows, so every existing Sprint 01/02 flow — and every one of their 204 tests — is unaffected. Inventory tracking is something the owner opts a product into by receiving stock for it, not a requirement from day one.
- **FEFO is real, not just a sort order.** `ReserveStock` locks every active lot for a product (`lockForUpdate`) and picks the first with enough available weight in use-by order — verified with two lots of different use-by dates in `StockReservationTest`.
- **The reservation → consumption handoff is exact, not additive.** At finalization, `ConsumeStock` releases the *originally reserved* raw weight and deducts the *actual* raw weight separately, rather than trying to patch a running total — so a heavier-than-estimated cut is handled the same way a lighter one is, with no drift.
- **Regression fix (pre-existing, found while wiring this sprint):** `RecordWeight` was pricing the final charge from the per-lb rate and actual weight alone, silently dropping Sprint 02's cut/offal/packing surcharge. Fixed and covered by a new test before this sprint's own work began — see the commit on `feature/s02-catalog-cut-options`.

## Next

1. Receive the owner's real stock via `/admin/animals` and `/admin/lots` in staging — this sprint seeds one representative demo animal/lot (`Database\Seeders\InventorySeeder`, local/staging only), not real inventory.
2. Everything open from Sprints 01-02 (Stripe test keys, CODEOWNERS, Forge staging secret, real catalog/photos) is unaffected and still open.
3. Sprint 04 (guideline ch. 6): order processing and the butcher workflow — daily capacity, the production queue board, pack label printing.
