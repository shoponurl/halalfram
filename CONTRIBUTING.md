# Contributing

## Branch strategy (trunk-based)

| Branch | Purpose | Deploys to |
|---|---|---|
| `main` | Always releasable. Protected: no direct pushes. | **Staging**, automatically after CI passes (`.github/workflows/deploy-staging.yml`) |
| `feature/<sprint>-<short-name>` | One task, e.g. `feature/s01-catch-weight-capture` | — |
| `fix/<short-name>` | Bug fixes | — |
| tag `vX.Y.Z` | A release someone has approved on staging | **Production**, triggered manually in Forge |

**Rules**

1. Branch from `main` and keep branches short-lived (days, not weeks).
2. Open a PR and fill in the security checklist in the template.
3. A PR can merge only when:
   - all CI jobs are green (Pint, Larastan 8, Pest, gitleaks, audits, semgrep), and
   - code owners have approved changes to protected paths (`.github/CODEOWNERS`).
4. Squash-merge into `main`.
5. **Code freeze:** no production deploys during the two weeks before Eid (guideline ch. 9).

## GitHub settings to enable (repo admin)

Settings → Branches → add a rule for `main` with these enabled:
- Require a pull request before merging (1 approval)
- Require review from Code Owners
- Require status checks: `Pint (code style)`, `Larastan (level 8)`, `Pest (unit, arch/security rules, feature)`, `gitleaks (no secrets in commits)`, `composer audit + npm audit`, `semgrep (injection, mass assignment, unsafe functions)`
- Require branches to be up to date · Do not allow bypassing

Settings → Secrets and variables → Actions: add `FORGE_STAGING_DEPLOY_URL`.

## Local setup

```bash
composer install
npm ci && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve
```

With Docker, use `./vendor/bin/sail up -d` and prefix the commands with `sail`.
