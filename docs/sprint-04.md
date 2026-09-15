# Sprint 04: order processing & the butcher workflow

**Goal (guideline ch. 6):** the work inside the shop. This is where capacity rules take hold — without them, the shop takes more orders than it can actually process.

## Owner decisions (guideline ch. 7, S04) — recorded 2026-09-15

| Question | Decision | Where it lives |
|---|---|---|
| When does the card actually get captured — at weighing, or after QC passes? | **After QC passes.** A QC checklist + temperature log sits between "weighed" and "finalized"; only a pass unlocks `FinalizeOrder`. A fail sends the order back to `QcFailed` for re-cutting and re-weighing — the card is never touched off a weight that might still change. | `App\Enums\OrderStatus` (`QcFailed`/`QcPassed`), `App\Actions\Orders\RecordQcCheck`, `FinalizeOrder`'s status guard |
| What measures the daily processing capacity limit? | **Butcher-minutes**, not weight. Each cut option gets an estimated processing time; a day's budget is a total minutes figure. | `CutOption.estimated_minutes`, `config('catchweight.daily_capacity_minutes')`, `App\Actions\Production\ScheduleOrder` |
| How far in advance can an order be scheduled? | **Never further than the card's ~7-day authorization window** — no re-authorization flow, same call as the Sprint 02 lead-time cap. | `ScheduleOrder::latestAllowedDate()`, reuses `config('catchweight.authorization_fallback_days')` |
| Pack label barcode format? | **GS1-128**, carrying the lot's use-by date (AI 17) and lot number (AI 10) to match Sprint 03's traceability. Flagged for real hardware verification before production use — nothing here has touched a physical printer/scanner. | `App\Support\PackLabelZpl` |
| Live production board via Reverb, or polling? | **Polling for now.** Reverb is a new package and a new process to deploy — CLAUDE.md requires a human sign-off on new dependencies. The board polls every 10 seconds instead, and the guideline itself requires the board to keep working if a websocket drops anyway, so this is the same fallback behavior either way. | `App\Filament\Pages\ProductionBoard` (`->poll('10s')`) |

## Tasks

| Task | Status | Where |
|---|---|---|
| Full state machine, Placed → … | ✅ (in-shop portion; dispatch/delivery states are Sprint 05's) | `App\Enums\OrderStatus` extended with `QcFailed`/`QcPassed` |
| Order cutoff time + daily processing capacity | ✅ | `config('catchweight.order_cutoff_time')`, `App\Models\ProductionDay`, `App\Actions\Production\ScheduleOrder` |
| Daily production queue board | ✅ (polling, not Reverb — owner decision above) | `App\Filament\Pages\ProductionBoard` |
| Weight capture screen | ✅ (carried over from Sprint 01, manual) | `ItemsRelationManager` |
| Pack label print (ZPL, GS1-128) | ✅ | `App\Support\PackLabelZpl`, "Print label" action on `ItemsRelationManager` |
| QC checklist + temperature log | ✅ | `qc_checks` table, `App\Models\QcCheck`, `App\Actions\Orders\RecordQcCheck` |

## Definition of Done: evidence

| DoD item | Result | How it was checked |
|---|---|---|
| Capacity exhausted blocks checkout for that date, with a reason | ✅ | `SchedulingTest`: a fully-booked/closed window throws a `ValidationException` inside `PlaceOrder`'s transaction — nothing is created |
| The butcher can run the whole day from the board | ✅ | `ProductionBoard` lists every order scheduled for a date with its items, minutes and status, with QC-check and finalize actions inline; `AdminResourceAuthorizationTest` confirms it renders for Owner/Manager/Butcher and 403s for everyone else |
| The board keeps working if a websocket drops | ✅ (by construction) | No websocket dependency at all this pass — polling only (owner decision above) |
| Scanning the printed label gives the correct weight and price | ⏳ partial | The ZPL content is correct and tested (`PackLabelZplTest`); actual print/scan hardware behavior is unverified — guideline flags this explicitly, and nothing here has touched a real printer |
| Stock, capacity and slots stay consistent | ✅ | `SchedulingTest` books real orders through `PlaceOrder` (not just the scheduler in isolation) and confirms capacity actually decrements; Sprint 03's stock reservation is untouched and still passes |

## What's new vs. Sprint 03

- **Capture is QC-gated, not weight-gated.** This is a real behavioral change to Sprint 01's `FinalizeOrder`: it now requires `QcPassed` (or `NeedsReview`, unchanged) instead of `Authorized`. Every Sprint 01–03 test that finalized an order was updated to record a passing QC check first — 251 pre-existing tests still pass with that one extra step, nothing else about them changed.
- **A QC failure is a first-class, visible state** (`QcFailed`), not a silent revert — the order stays queryable as "needs re-cutting" until a fresh check passes. QC checks are append-only, same as weight events: a failed check is never edited, only followed by a new one.
- **Scheduling and stock reservation are independent systems that both gate the same checkout.** A product can be out of butcher-minutes capacity for a date while having plenty of raw stock, or vice versa — `PlaceOrder` checks both, in the same transaction, and either can reject the order before anything is written.
- **Regression risk avoided:** `ScheduleOrder`'s capacity check counts live minutes by summing real `order_items.estimated_minutes` for a date rather than a cached running counter, so there's no separate ledger to drift out of sync — same reasoning as Sprint 03's decision to keep `Lot` balances as a cache over the append-only movement log, just without needing the cache at all here since order volume doesn't warrant it yet.

## Next

1. Verify the ZPL label template on real hardware (printer + GS1-128 scanner) before relying on it — the guideline itself calls this out, and nothing in this sprint has touched physical equipment.
2. Tune `config('catchweight.daily_capacity_minutes')` and `order_cutoff_time` to the shop's real staffing — the defaults (480 min/day, 3pm cutoff) are placeholders. Per-date overrides (holidays, extra help) go in via `/admin/production-days`.
3. Everything open from Sprints 01-03 (Stripe test keys, CODEOWNERS, Forge staging secret, real catalog/photos, real animals/lots) is unaffected and still open.
4. Sprint 05 (guideline ch. 6): local delivery and pickup — zip-code service area, delivery slots, driver screen.
