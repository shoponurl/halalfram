# Sprint 00: foundation and guardrails

**Goal (guideline ch. 6):** no features. Build a structure where AI-written code is safe by default, and where CI catches mistakes.

## Tasks

| Task | Status | Where |
|---|---|---|
| Laravel 12 + Sail, repo, branch strategy | ✅ Done | Laravel 12.69 on PHP 8.4; `compose.yaml` (Sail: PHP 8.4, MySQL 8.4, Redis, Mailpit); [CONTRIBUTING.md](../CONTRIBUTING.md) |
| CLAUDE.md security rules + base classes and traits | ✅ Done | [CLAUDE.md](../CLAUDE.md), `App\Support\Weight`, `App\Casts\WeightCast`, `App\Casts\MoneyCast`, `App\Actions\Action` |
| GitHub Actions: Pint, Larastan L8, Pest, gitleaks, composer audit | ✅ Written, ⏳ not yet run on GitHub | `.github/workflows/ci.yml` (also runs npm audit and semgrep) |
| CODEOWNERS: required review on payment and Policy paths | ✅ Written, ⏳ needs real GitHub team | `.github/CODEOWNERS` |
| Auth + 2FA + spatie/permission, 6 roles | ✅ Done | Filament 5 login with required authenticator-app 2FA and recovery codes; `App\Enums\Role`/`Permission`; `RolesAndPermissionsSeeder`; Owner-only Staff screen |
| Existing design → Blade + Tailwind components | ✅ Done | `resources/views/components/{layouts,store,home}`, `resources/css/storefront.css`, `resources/js/storefront.js` |
| Forge staging server + auto deploy | ⏳ Needs your Forge account | `.github/workflows/deploy-staging.yml`, `deploy/forge-deploy-script.sh` |

## Definition of Done: evidence

| DoD item | Result | How it was checked (2026-09-14, local) |
|---|---|---|
| Sign-in works on staging and all 6 roles are active | ✅ Local / ⏳ staging | All 6 roles seeded. Pest checks each role reaches the panel; customers and deactivated staff get 403; the staff-management route matrix (6 roles × 3 routes) returns 200 only for Owner. Tested in a browser: password → forced 2FA setup (QR + recovery codes) → panel; the next sign-in asks for the 6-digit code, and skipping to /admin redirects to login. |
| Writing `$guarded = []` on purpose fails CI | ✅ | Added a model with `$guarded = []`: `tests/Arch/SecurityRulesTest` failed with "Gap 03 mass assignment" and "Every model needs an explicit $fillable". Removing it passed (14/14). Semgrep rule `eloquent-guarded-empty` also flags it. |
| A fake API key in a commit is blocked by gitleaks | ✅ | gitleaks 8.30.1 (checksum verified) on a scratch repo with `.gitleaks.toml`: found the `stripe-access-token` default rule and the custom `laravel-app-key` rule, exit 1. Every folder that gets committed scans clean. |
| The `/` page matches your design | ✅ | Laravel `/` compared with the original `index.html` in same-size iframes: 1,080 vs 1,080 elements, 30 computed properties each. The only differences are gradients the minifier wrote without redundant 0%/100% stops, plus one 1/255 colour rounding. No console errors. |

## Quality gates (local run)

- Pest: 56 tests (unit money/weight, 14 arch/security rules, 38 feature auth/authorization)
- Larastan level 8: no errors
- Pint: passes (`declare_strict_types`, `strict_comparison`)
- Semgrep (7 project rules): config valid; a test file with 7 deliberate mistakes gets 7 detections; the app scans clean

## Decisions made in this sprint (technical, for the senior developer)

| Guideline said | Chosen | Why |
|---|---|---|
| Filament 3 | **Filament 5.8** | Filament 3 is out of active support by Sept 2026. Filament 4+ has **built-in required 2FA** (TOTP + recovery codes), which Sprint 00 needs. |
| Livewire 3 | **Livewire 4** | Required by Filament 5 |
| PHP 8.3+ | **PHP 8.4** | Current Pest/Symfony need ≥ 8.4.1; Forge supports it |
| Pest (latest) | **Pest 4.7** | Pest 5 requires Laravel 13 |
| Tailwind (skeleton ships v4) | **Tailwind v3.4** for the storefront | Same engine as the verified design, so there's zero visual drift. Filament ships its own CSS. |
| — | Staff can't be deleted, only deactivated; the system keeps at least one active Owner | Orders, weights and audit entries must keep pointing at a real person |
| — | Money = integer cents (Brick\Money 0.11, the newest version compatible with Laravel 12's brick/math) | Rule 01 |

## Open items before Sprint 00 counts as fully done

1. **GitHub repository.** Create it, push, add the branch protection from CONTRIBUTING.md, and replace `@halal-brothers/senior-devs` in CODEOWNERS.
2. **Watch the first CI run go green on GitHub.** The jobs haven't run on GitHub Actions yet; each tool was run locally.
3. **Forge staging.** Create a server (us-east), add the site, set `FORGE_STAGING_DEPLOY_URL`, then run `php artisan migrate --seed` once to get the 6 roles and demo accounts.
