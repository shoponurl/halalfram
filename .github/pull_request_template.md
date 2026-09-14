## What & why

<!-- One or two sentences. Link the sprint task. -->

## Security checklist (CLAUDE.md)

- [ ] Every new route / Filament resource has a Policy, and a test proves other roles get 403
- [ ] Customer-owned records are looked up through the owner, never by raw id (IDOR)
- [ ] No `$guarded = []`, `$request->all()`, or user input inside raw SQL
- [ ] Amounts and prices are recalculated on the server; money is integer cents, weight is `DECIMAL(10,3)`
- [ ] Stock, shares and money changes run in a transaction with `lockForUpdate()`
- [ ] Payment calls use idempotency keys; external calls go through the queue
- [ ] No secrets, customer PII or production data in code, fixtures, logs or AI prompts
- [ ] New composer/npm packages were checked on Packagist/npm by a human (slopsquatting)

## How I tested

<!-- Commands run, screenshots for UI changes. -->
