# Halal Brothers store — rules for AI assistants and developers

Halal farm e-commerce (Philadelphia, PA): processed meat, whole/half animals, catch-weight pricing, hissa (share) sales, lot traceability.
The business plan is the guideline PDF (`halal-farm-store-business-guideline`, v1.0). This repo is **v1, Sprint 00 onward**.

## Stack

- Laravel 12 · PHP 8.4 · MySQL 8 (InnoDB)
- Storefront: Blade + Tailwind v3. Livewire comes in with Filament and gets used for cart and cut selection from Sprint 01.
- Staff panel: Filament 5 at `/admin`
- Roles: spatie/laravel-permission — six roles in `App\Enums\Role`; the permission matrix lives in `Role::permissions()`
- Testing and quality: Pest 4 (unit, arch/security, feature) · Larastan level 8 · Pint
- Payments later: Stripe (Cashier, `capture_method: manual`) and PayPal Orders v2 · Twilio (A2P 10DLC) · Postmark

## Commands

Local Windows dev uses the portable PHP in `../.tools/php-8.4/php.exe`; Sail (`compose.yaml`) works for anyone with Docker.

```bash
php artisan serve                      # http://127.0.0.1:8000  (admin at /admin)
npm run dev                            # Vite + Tailwind
vendor/bin/pest                        # all tests, including tests/Arch security rules
vendor/bin/phpstan analyse             # Larastan level 8
vendor/bin/pint                        # format
php artisan db:seed                    # 6 roles + one demo account per role (local/staging only)
php artisan staff:create               # real staff accounts (production)
php artisan launch:check               # launch gate: DONE / NOT DONE with proof (docs/runbook.md)
php artisan ops:reconcile              # rebuild every cached balance from its ledger; non-zero exit on mismatch
```

Operating the live system (backups, restore/recall/oversell/load drills, key rotation, alerts) is in `docs/runbook.md`.
A new cached balance over a ledger belongs in `App\Actions\Launch\ReconcileLedgers`; a new production-readiness
requirement belongs in `App\Enums\LaunchGateItem`.

## Non-negotiable rules

Each rule below is a CI failure or a required review, not a style preference.

### Security (guideline ch. 5)

1. **Authorize every action with a Policy.** Filament resources rely on the model's policy. Every new route needs a test that other roles get **403**.
2. **No IDOR.** Load customer-owned records through their owner: `$request->user()->orders()->findOrFail($id)`, never `Order::findOrFail($id)`.
3. **Never take amounts from the client.** Recalculate server-side, e.g. `$order->recalculateTotal()`, and reject mismatches.
4. **No mass assignment.** Every model declares `$fillable`. Never use `$guarded = []`, `unguard()` or `$request->all()`; use `$request->validated()`. Set roles, flags, prices and status explicitly.
5. **Verify webhook signatures.** Use `\Stripe\Webhook::constructEvent($payload, $sig, $secret)`. Never parse unsigned webhook JSON.
6. **Lock rows for stock, shares and money.** Use `DB::transaction()` with `lockForUpdate()`; the base `App\Actions\Action::transaction()` helps.
7. **Raw SQL only with bindings** plus a column allowlist for dynamic sort/filter.
8. **Uploads:** extension allowlist, random filename, private disk (R2), re-encode images.
9. **Logs:** never log whole requests, card data, passwords or tokens.
10. **Production:** `APP_DEBUG=false`, and every dashboard (Horizon, Pulse, Telescope) behind an auth gate.
11. **Rate-limit** login, OTP, coupon and checkout endpoints.
12. **Tests must prove rules:** use `assertForbidden()` or tampered amounts, not just `assertStatus(200)`.

### Money and weight (guideline ch. 4, coding rules)

- **Money is integer cents:** `*_cents` columns with `App\Casts\MoneyCast` (Brick\Money). **Never floats.**
- **Weight is `DECIMAL(10,3)` in pounds:** `App\Casts\WeightCast` → `App\Support\Weight` (bcmath). **Never floats.**
- **Every payment API call sends an idempotency key**, both to Stripe and on our own endpoints.
- **Weight changes are events.** Append a `weight_events` row (who, when, which scale); never overwrite `actual_weight`.
- **Capture locks weight.** After capture, corrections go through a credit note only.
- **Catch-weight holds are estimate × (1 + tolerance).** The default tolerance is 10% (guideline ch. 7, S01).
- **Every pack gets a lot number at the moment it's created.**
- **External calls go through the queue with retries:** Stripe, Twilio, carriers, label printers. Checkout must survive a carrier outage.

### Decisions that are not yours

Guideline chapter 7 lists business questions such as tolerance %, capture timing, cash on delivery and USDA scope. **Stop and ask the owner** when a task depends on one of them. Technical choices (locking strategy, schema shape, ledger vs column) go to the senior developer.

### AI use policy

- Never paste `.env` contents, production data or customer PII into AI tools. Use anonymised fixtures.
- Never give an AI agent production shell or database credentials; staging at most.
- A human checks every new package the AI suggests on Packagist/npm before it's installed.
- Use framework built-ins for security-sensitive code. No home-made hashing, tokens or crypto.

## Code conventions

- Every PHP file has `declare(strict_types=1)` (enforced).
- Domain logic lives in `app/Actions/*` classes with one `handle()`, not in controllers or Filament pages.
- Enums hold fixed business vocabularies (roles, permissions, order states).
- New roles or permissions go in `App\Enums\Role::permissions()` and ship through `RolesAndPermissionsSeeder`, which is idempotent and runs on every deploy.
- Staff are deactivated, never deleted.
