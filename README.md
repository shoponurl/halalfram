# Halal Brothers — farm store (v1)

E-commerce and operations system for Halal Brothers Live Poultry & Meat, Lansdowne / Philadelphia, PA. It covers processed meat, whole and half animals, catch-weight pricing, hissa (share) sales and lot traceability.

This is the v1 build that follows the business guideline, which calls for 10 sprints. The existing plain-PHP site in the parent folder is the demand test (guideline R2) and stays separate.

| | |
|---|---|
| **Stack** | Laravel 12 · PHP 8.4 · MySQL 8 · Blade + Tailwind · Filament 5 · spatie/permission · Pest 4 · Larastan 8 |
| **Storefront** | `/`, the original design ported to Blade components |
| **Staff panel** | `/admin`, six roles, authenticator-app 2FA required |
| **Rules** | [CLAUDE.md](CLAUDE.md), read it before writing code |
| **Workflow** | [CONTRIBUTING.md](CONTRIBUTING.md) covers branches, CI gates and code owners |

## Quick start

```bash
composer install
npm ci && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed        # 6 roles + demo staff (local/staging only)
php artisan serve
```

Demo staff accounts (local and staging only): `owner|manager|front_desk|butcher|driver|accountant@halalbrothers.test`, password `Staging-Only-2026!`. Each is asked to set up 2FA on first sign-in.

Create real staff in production with `php artisan staff:create`.

## Sprint status

- [x] **Sprint 00: foundation and guardrails.** See [docs/sprint-00.md](docs/sprint-00.md) for the Definition of Done evidence.
- [x] **Sprint 01: vertical slice**, catch-weight order → hold → weight → capture → invoice. See [docs/sprint-01.md](docs/sprint-01.md) — the real $1 Stripe test-mode run is still open, pending your test keys.
- [x] **Sprint 02: catalog & cut options.** See [docs/sprint-02.md](docs/sprint-02.md) — real photos and your full catalog still need adding via `/admin/products` in staging.
- [x] **Sprint 03: inventory, lot & cold storage.** See [docs/sprint-03.md](docs/sprint-03.md) — real stock still needs receiving via `/admin/lots` in staging.
- [x] **Sprint 04: order processing & the butcher workflow.** See [docs/sprint-04.md](docs/sprint-04.md) — Reverb (live updates) deliberately deferred; the production board polls instead.
- [x] **Sprint 05: local delivery & pickup.** See [docs/sprint-05.md](docs/sprint-05.md) — real service zones/zips/slots and a real cron/`schedule:work` for the missed-pickup job still need setting up in staging/production.
- [x] **Sprint 06: payment completion & notifications.** See [docs/sprint-06.md](docs/sprint-06.md) — real PayPal/Twilio/Postmark credentials, 10DLC registration, PayPal Vault approval, a PA sales-tax determination and SPF/DKIM/DMARC DNS records still need setting up before launch.
- [x] **Sprint 07: admin, reports & compliance.** See [docs/sprint-07.md](docs/sprint-07.md) — a real USDA establishment number, a read replica/Metabase instance, a professional ADA/WCAG audit and legal review of the privacy/terms/USDA copy still need doing before launch.
- [x] **Sprint 08: nationwide cold-chain shipping.** See [docs/sprint-08.md](docs/sprint-08.md) — real EasyPost credentials, a genuine end-to-end overnight test shipment, and the real packing-rule/ship-blackout-date data still need entering before launch.
- [ ] Sprint 09: hardening & launch
