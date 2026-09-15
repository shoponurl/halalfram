# Security self-audit (Sprint 09)

**Scope:** the whole v1 codebase as of `feature/s08-nationwide-shipping` (289 app files, ~14.6k lines): every public route and controller, all three webhooks, checkout and store credit, uploads, session/cookie/proxy/header configuration, logging, the staff panel's authentication and middleware, raw SQL, and the concurrency paths for stock and scheduling.

**Method:** a line-by-line review against CLAUDE.md's 12 security rules and the guideline's ch. 5 gap list, plus two live drills (`ops:oversell-drill`, `ops:restore-drill`) run against a real MariaDB instance, not just the test suite.

**What this is not:** a substitute for the third-party penetration test. Guideline ch. 8 is explicit that an AI-built system can't find what its own author didn't think of, and that's the whole reason an outside team is non-negotiable. See [pentest-brief.md](pentest-brief.md).

## Findings

Severity uses the same scale a pentest report would: **Critical** = exploitable now for money, data or control; **High** = exploitable with little effort, or breaks a launch-critical path in production; **Medium** = needs a precondition or leaks less; **Low** = hardening.

| ID | Severity | Finding | Fix | Regression test |
|---|---|---|---|---|
| SA-01 | **High** | **Anyone could read and spend another customer's store credit.** Checkout looked the balance up by an email typed into `?credit_email=`, and `PlaceOrder` redeemed credit for whatever `customer_email` was posted. Knowing someone's email was enough to see their balance and use it on your own order. | Store credit now needs proof of email ownership. The customer asks for a link, it goes to that inbox (single-purpose token, 30-minute TTL, stored hashed in cache), and following it marks the email as verified in that browser session. The balance is only shown for the verified email, and checkout rejects `apply_store_credit` unless the order's email matches it. The link form answers identically whether or not credit exists, so it can't be used to enumerate balances, and it's rate-limited per IP and per email. | `tests/Feature/Security/StoreCreditOwnershipTest.php` (6 tests) |
| SA-02 | **High** | **Twilio's STOP webhook and EasyPost's tracking webhook would have returned 419 in production.** Only `stripe/webhook` was excluded from CSRF. Laravel skips CSRF under unit tests, so every existing webhook test passed while the real routes were unreachable. The effect would have been that STOP never unsubscribes anyone (a TCPA exposure) and shipped orders never get marked delivered. | Both routes added to the CSRF exclusions. They're already authenticated by their own signatures (Twilio `RequestValidator`, EasyPost HMAC). | `ProductionHardeningTest`: a CSRF middleware subclass that doesn't skip under tests proves all three webhooks get past CSRF while `/checkout` and `/privacy/requests` still get 419 |
| SA-03 | **High** | **SVG uploads allowed, a stored-XSS path into the staff panel.** Filament's `->image()` accepts `image/*`, which includes `image/svg+xml`. A product photo is served from the same origin (`/storage/products/...`), so an SVG with embedded script runs with the session of any staff member who opens it. It needs a catalog-manager account, but that's exactly the account-takeover escalation a pentest looks for. | Every `FileUpload` is now restricted to `image/jpeg`, `image/png` and `image/webp`. | `tests/Arch/SecurityRulesTest.php`: every `FileUpload::make` must carry the allowlist |
| SA-04 | Medium | **Delivery-proof photos (a customer's front door) were on the public disk**, web-served from `/storage/delivery-proof/...`. Filenames are random, so this isn't enumerable, but it breaks rule 8 (private disk) and is personal data. | Moved to the private `local` disk. | Same arch test: any `proof_photo_path` upload must use `disk('local')` |
| SA-05 | Medium | **No trusted-proxy configuration.** Behind a load balancer or CDN, every visitor shares the proxy's IP, so the 5-per-minute checkout limiter would apply to the whole site at once (a self-inflicted outage at launch). Twilio's signature would also be checked against an `http://` URL it never signed. | `config/trustedproxy.php` reads `TRUSTED_PROXIES` from the environment. Laravel's `TrustProxies` middleware already consults it, so no code change was needed. | `ProductionHardeningTest`: forwarded headers are ignored by default and honored only from a configured proxy |
| SA-06 | Medium | **No security headers.** Checkout and `/admin` could be framed (clickjacking). There was no explicit `Referrer-Policy`, even though guest order links carry their access token in `?token=`. No `nosniff`, no HSTS. | New `SecurityHeaders` middleware on the web group and the Filament panel: `X-Frame-Options: SAMEORIGIN`, `frame-ancestors 'self'`, `nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, and HSTS on HTTPS requests. | `ProductionHardeningTest` (storefront and `/admin/login`) |
| SA-07 | Low | **The Slack log channel's level came from `LOG_LEVEL`**, so `LOG_LEVEL=debug` in production would have posted every debug line, including context arrays, to Slack. | Its own `LOG_SLACK_LEVEL`, defaulting to `critical`. | `ProductionHardeningTest` |

**Summary: 0 Critical, 3 High, 3 Medium, 1 Low. All closed, each with a regression test.**

## Investigated and ruled out

These are recorded so nobody spends time on them again, and so the pentesters know they were looked at.

- **Scheduling race on a new production day.** `ScheduleOrder` calls `ProductionDay::firstOrCreate()` on a table whose primary key is the date, which looks like it could throw a duplicate-key error when two checkouts race on a new day. It doesn't: Laravel 12's `firstOrCreate` falls back to `createOrFirst`, which catches the unique violation and re-reads the row. Checkouts for the same product also queue behind the product row lock before scheduling runs. The oversell drill confirmed this with 25 concurrent checkouts, 0 errors, including one run with today's production-day row deliberately removed first.
- **Order IDOR.** Every customer-facing order route (`orders.show`, `orders.invoice`, `checkout.pay`, PayPal return/cancel) goes through `AuthorizesOrderAccess`: a constant-time token hash comparison, or the signed-in owner, and otherwise 404. `orders.balance-paid` is a static thank-you page with no order data.
- **Client-supplied amounts.** `CheckoutRequest` prohibits every money field. `PlaceOrder` re-prices the cart under row locks and rejects any mismatch with the displayed hold.
- **Raw SQL.** The only raw expressions (`SalesReport`, `ReconcileLedgers`) are constant strings with no interpolated input. The existing arch rule enforces this.
- **Webhook replay.** Stripe events are deduplicated by event id inside the same transaction as their effects. EasyPost updates are idempotent state transitions. Notifications are deduplicated per order, event and channel.
- **Staff authentication.** Filament's login is rate-limited, 2FA is required for every role, deactivated staff can't enter the panel, and `AuthenticateSession` invalidates other sessions on a password change.

## Carried to the pentest and to after launch

- **A full script Content-Security-Policy.** The storefront (the checkout total script, Stripe.js) and Filament/Livewire both rely on inline scripts, so a real CSP needs nonces, which is too risky to retrofit in the same sprint as launch. Only `frame-ancestors` is set today. Ask the pentesters to rate this.
- **Re-encoding uploaded images** (rule 8). The allowlist closes the SVG hole, but a polyglot JPEG is still stored byte-for-byte. Re-encoding needs an image library, which is a new-package decision.
- **Key rotation of `APP_KEY`** must use `APP_PREVIOUS_KEYS`. Staff 2FA secrets are encrypted with it, so a rotation without the previous key locks every staff member out. See runbook §5.
