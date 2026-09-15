# Sprint 09: hardening & launch

**Goal (guideline ch. 6):** no new features. Make sure the system is ready for real cards and real customers, and turn the ch. 8 launch gate from a checklist into something that reports **DONE / NOT DONE with proof**.

## Owner decisions

Guideline ch. 7 lists **no owner decisions for Sprint 09**, and ch. 8's launch-gate risks don't block building this sprint (R1 was cleared before S02, and S08 confirmed interstate shipping). Everything here follows the guideline's own recommendations:

- The **soft launch** is store pickup plus a few delivery zips, with no nationwide shipping. Which zips is a config value (`SOFT_LAUNCH_DELIVERY_ZIPS`), not code, so the owner can choose on launch day.
- The **third-party penetration test** and the **restore drill on production** are non-negotiable (ch. 8). The pentest firm is the owner's to hire; [pentest-brief.md](pentest-brief.md) is ready to hand over.

## Tasks

| Task (guideline ch. 6, S09) | Status | Where |
|---|---|---|
| Self-audit of the whole codebase, then fixes + regression tests | ✅ 7 findings (3 High, 3 Medium, 1 Low), all fixed with tests | [security-self-audit.md](security-self-audit.md) |
| Third-party penetration test + fix round | ⏳ **Owner to commission.** Brief written; gate item `pentest_closed` stays NOT DONE until the retest letter | [pentest-brief.md](pentest-brief.md) |
| Load test at 10x normal | ✅ tooling + run: concurrency drill **passed**; HTTP load test built and run, **must be re-run on staging** (see below) | `ops:oversell-drill`, `ops:load-test` |
| Backups + one full restore drill | ✅ tooling + a local drill **passed** (recovery 1.6s); must be repeated on production | `ops:backup`, `ops:restore-drill` |
| Rotate every key, restrict production DB permissions | ✅ grants script, separate migration user, per-key rotation procedure; the rotation itself happens on the server | `deploy/mysql-production-grants.sql`, runbook §5 |
| Soft launch: pickup + a few zip codes | ✅ enforced server-side in `PlaceOrder` | `App\Support\SoftLaunch`, `config/launch.php` |

## Definition of Done: evidence

The launch gate itself is `php artisan launch:check`. Its 29 items combine the Sprint 09 DoD with the ch. 8 gate. The table below is this sprint's DoD.

| DoD item | Result | How it was checked |
|---|---|---|
| Every Critical and High from the pentest closed | ⏳ NOT DONE | Needs the external test. Tracked as `pentest_closed` |
| A full restore from backup has been done | ✅ locally / ⏳ production | `ops:restore-drill` on the local dev DB, a real `mysqldump` → GnuPG → decrypt → import into a scratch DB: migrations complete, reconciliation clean, 20 orders and 3 lots restored, **recovery time 1.6s**. `BackupRestoreDrillTest` runs the same end to end in CI (Ubuntu has all three binaries), including a wrong-passphrase failure. |
| No oversell under concurrency | ✅ | `ops:oversell-drill`: **25 separate PHP processes checking out at the same instant for 10 units of stock → exactly 10 placed, 15 refused as out of stock, 0 errors, lot reserved 10.000 of 10.000 lb, ledger reconciles.** Run twice, the second time with today's production-day row deleted first to force the "new day" path. |
| Front desk and butcher ran the system themselves for a day | ⏳ NOT DONE | A person-in-the-room task. The checklist for the day is in [staff-guide.md](staff-guide.md#the-dry-run-day-launch-gate-item-staff_dry_run); signed off as `staff_dry_run` |
| Monitoring and alerting reach a real person (ch. 8) | ✅ built / ⏳ production | `alerts` log channel (Slack webhook and/or email, deduplicated, never throws); `/up` now fails when the database is down; `ops:test-alert` + `alert_reached_person` sign-off. `OperationalDrillsTest` |
| Reconciliation matches on production data (ch. 8) | ✅ built / ⏳ production | `ops:reconcile` rebuilds order money, lot stock, store credit and weights from their append-only ledgers; scheduled daily; alerts on a mismatch. `ReconcileLedgersTest` corrupts each cached number in turn and checks it's caught |
| Recall drill within 30 seconds on production data (ch. 8) | ✅ locally (12 ms) / ⏳ production | `ops:recall-drill`. `OperationalDrillsTest` |
| Production DB user can't DROP or ALTER (ch. 8) | ✅ built / ⏳ production | `launch:check` reads `SHOW GRANTS` live; correctly NOT DONE on the local root user (`LaunchGateTest`) |
| Runbook and staff guide written (ch. 8) | ✅ | [runbook.md](runbook.md), [staff-guide.md](staff-guide.md) |
| Every staff account has 2FA; no development accounts (ch. 8) | ✅ built | `launch:check` names any active staff without 2FA and any active `@halalbrothers.test` demo account |

## The load test, honestly

`ops:load-test` ran against a local `php artisan serve`: **400 requests at concurrency 20 → 2 req/s, p50 3.6s, p95 6.7s, 0 errors. FAIL against a 20 req/s target.** Single requests take about 0.3s here, `/up` included, so the time is framework boot on this machine (Windows, no opcache, debug mode, nothing cached). PHP's built-in server also ignores `PHP_CLI_SERVER_WORKERS` on Windows and serves one request at a time. **The number says nothing about production capacity.** The gate item stays NOT DONE until the command is run against staging (nginx + PHP-FPM + opcache, `php artisan optimize`) with the owner's real normal traffic as `--normal-rps`. What the local run did prove is that the tool works, and that nothing errored under 20× concurrency.

## What's new vs. Sprint 08

- **The self-audit found a real money bug that ~490 passing tests didn't:** store credit was effectively bearer-by-email (SA-01). And the webhooks bug (SA-02) was *hidden by* the tests: Laravel turns CSRF off under unit tests, so the Twilio and EasyPost webhook tests passed while the production routes would have returned 419. The regression test enforces CSRF for real.
- **Launch gate as code.** `LaunchGateItem` (29 items) + `EvaluateLaunchGate` + `launch:check`. There are three kinds of proof:
  - **automated:** evaluated live
  - **drill:** recorded by the drill command, must be a recent pass in the same environment
  - **manual:** signed off by a named staff member with `launch:attest`, recorded in the audit log

  Automated items and drills *can't* be signed off; they have to actually pass. Evidence is an append-only table (`launch_gate_evidence`), so a later failed drill supersedes an earlier pass. The cold-chain test shipment is automatically "not needed for this launch" while shipping is switched off. That's the guideline's "smallest launch that doesn't need this yet", as code.
- **No new packages.** Backups use the `mysqldump`/`mysql` binaries and GnuPG through Laravel's `Process` facade (vetted tools, no home-made crypto). The load test uses Laravel's own `Http::pool`. Alerts use Monolog's bundled Slack handler and Laravel Mail. Error tracking with a Sentry-style tool (guideline M19) would be a new package, so it's left as the owner's call; alerts on every `Log::critical` plus the `/up` monitor cover "reaches a real person" without it.
- **The database user split is real, not just documented.** A `mysql_migrate` connection is used by the deploy script's `migrate`, by backups and by restore drills. The site's own user only has SELECT/INSERT/UPDATE/DELETE. Locally both fall back to the same root user, so nothing changes for development.
- **Investigated and ruled out:** a suspected race in `ScheduleOrder`'s `firstOrCreate` on a new production day. Laravel 12 already falls back to `createOrFirst`, and the oversell drill with the day row deleted confirmed 0 errors. Recorded in the audit so nobody re-investigates it.
- **Self-caught while writing the docs:** the staff guide originally said "the Owner can reset your 2FA". No such function existed, so a staff member who lost both their phone and recovery codes would have been locked out permanently. Added `staff:reset-2fa` (server shell only, a reason is required, audited) with tests. Also removed an "or let the scale fill it in" line describing a scale integration that doesn't exist.
- **A `gpg` gotcha on Windows only:** Git for Windows' `gpg` leaves a `gpg-agent` holding the parent shell's output pipe, so a terminal waits after the command has finished. The PHP side is unaffected (the test suite completes in 12s). Documented in the runbook; Linux servers don't behave this way.

## Next

**Before launch (owner / on the server):**

1. Commission the third-party pentest ([pentest-brief.md](pentest-brief.md)), fix every Critical/High, get the retest letter.
2. On production: apply `deploy/mysql-production-grants.sql`, set the Sprint 09 environment block, rotate every key (runbook §5), configure alerts, scheduler, queue worker and uptime monitor. Then `ops:backup` → `ops:restore-drill` → `ops:reconcile` → `ops:recall-drill`.
3. `ops:load-test` against staging with real normal traffic numbers.
4. The staff dry-run day.
5. Everything open from Sprints 01–08: live Stripe/PayPal/Twilio/Postmark/EasyPost credentials, 10DLC, SPF/DKIM/DMARC, PA sales-tax determination, USDA establishment number, professional WCAG audit, legal review, real catalog/stock/zones/packing rules, GS1-128 hardware check, cold-chain test shipment (only once shipping is switched on).
6. Walk through `php artisan launch:check` with the owner until everything is DONE.

**Deliberately deferred:** a full script CSP (needs nonces across the storefront and Filament), re-encoding uploaded images (new package), a "withdraw lot" button for recalls (today: record the remaining weight as wastage, see runbook §8), and error tracking à la Sentry (new package, the owner's call).

Per the guideline's v2 timeline (ch. 9), what follows launch is **stabilization (Feb–Mar 2027): no new features, only fixing what real customers break.**
