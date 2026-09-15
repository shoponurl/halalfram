# Runbook

How to launch, run, and recover the Halal Brothers store. Written for whoever is on call, whether that's the developer or the owner at 7 a.m. with a customer on the phone. Each section says **what to do**, not how the code works.

**The one command to remember:** `php artisan launch:check`. It lists every launch-gate item as DONE or NOT DONE, with the proof behind each.

---

## 1. Going live, step by step

Do these in order on the production server. None of them involve pasting a secret into chat, a ticket, or an AI tool. Secrets go straight into Forge's environment editor.

1. **Database users.** Generate two long random passwords on the server, put them into `deploy/mysql-production-grants.sql`, run it as the MySQL admin, then delete the edited copy. Set `DB_USERNAME=halal_app`, `DB_MIGRATE_USERNAME=halal_migrate`, and both passwords.
2. **Environment:** `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`, `QUEUE_CONNECTION=database` (or redis), `MAIL_MAILER=postmark`, and `TRUSTED_PROXIES` if a load balancer or Cloudflare sits in front.
3. **Fresh keys for production, never the development ones (§5):** a new `APP_KEY`, live Stripe keys plus the webhook secret, PayPal live credentials, Twilio, Postmark and EasyPost.
4. **Alerts (§3):** `LOG_STACK=daily,alerts`, plus `LOG_SLACK_WEBHOOK_URL` and/or `ALERT_EMAIL`. Run `php artisan ops:test-alert` and confirm a real person got it.
5. **Backups (§4):** set `BACKUP_PASSPHRASE` (also save it in the owner's password manager), run `php artisan ops:backup`, then `php artisan ops:restore-drill`.
6. **Scheduler and queue worker:** in Forge, add the scheduler (`php artisan schedule:run` every minute) and a queue worker daemon. Without the scheduler there are no nightly backups, no reconciliation, and no missed-pickup handling.
7. **Uptime monitor:** point an external monitor (Forge heartbeats, UptimeRobot, Better Stack) at `https://…/up` every minute, alerting the same person. `/up` fails when the database is down, not just when PHP is.
8. **Staff:** create real accounts with `php artisan staff:create`. Each person enrolls 2FA on first sign-in. **Deactivate every `@halalbrothers.test` demo account.** If someone later loses both their phone and recovery codes, confirm who they are in person, then run `php artisan staff:reset-2fa <email> --reason="…"` (audited).
9. **Soft launch (§2):** `SOFT_LAUNCH=true`, `SOFT_LAUNCH_DELIVERY_ZIPS=19050` (or whichever few zips).
10. **Drills on production data:** `php artisan ops:reconcile` and `php artisan ops:recall-drill`.
11. **Manual sign-offs** for each remaining item (pentest, SAQ-A, 10DLC, inbox test, WCAG, legal review, staff dry run and so on):
    `php artisan launch:attest <item> --by=owner@… --evidence="where the proof is"`
12. `php artisan launch:check` shows **all DONE**. Open the doors.

If something is NOT DONE, the guideline's question isn't "can we launch anyway?" It's **"what is the smallest launch that doesn't need this yet?"** For example, shipping stays off until the cold-chain test shipment has happened.

## 2. Soft launch → full launch

| Stage | Setting | Customers see |
|---|---|---|
| Soft launch | `SOFT_LAUNCH=true`, `SOFT_LAUNCH_DELIVERY_ZIPS=` (empty) | Store pickup only |
| + one delivery area | `SOFT_LAUNCH_DELIVERY_ZIPS=19050,19018` | Pickup, plus delivery to those zips (they must also be in an active zone in `/admin/delivery-zones`) |
| Full launch | `SOFT_LAUNCH=false` | Pickup, every active delivery zone, and nationwide shipping (if EasyPost is configured) |

These are config changes, not deploys. After editing the environment, run `php artisan config:cache`. Checkout enforces the rules on the server, not just in the page.

## 3. Responding to an alert

Alerts are `Log::critical` events sent to Slack or email. The subject line says what failed.

| Alert | What it means | What to do |
|---|---|---|
| **Order settlement failed after retries** | A card capture or charge failed 5 times, often a Stripe outage. The order stays in "Settling". | Check [status.stripe.com](https://status.stripe.com). Open the order in `/admin/orders`; its payment ledger shows what did and didn't happen. Don't re-charge by hand in the Stripe dashboard: once Stripe recovers, `php artisan queue:failed` finds the job and `php artisan queue:retry <id>` re-runs it. It's idempotent, so nothing already captured is charged twice. |
| **Fulfilment refund failed** | A refund (missed pickup, failed delivery, arrived warm) didn't go through. | Refund manually in the Stripe/PayPal dashboard, and note the refund id on the order. Tell the customer. |
| **Purchasing a shipping label failed** / **no packing rule** | EasyPost is down, or the order's weight fits no packing rule. | Check `/admin/packing-rules` for a gap in the weight bands. The job retries; if it's still failing next morning, buy the label in EasyPost directly. |
| **Order notification job failed** | An SMS or email couldn't be sent after 5 tries. | Check the Twilio/Postmark status pages. Call the customer if the message was time-sensitive (ready for pickup, delivery today). |
| **Ledger reconciliation found mismatches** | A cached balance (money captured, stock on hand, store credit, weight) disagrees with its ledger. **Something wrote data outside the normal actions.** | Don't "fix" the number. Run `php artisan ops:reconcile` to see every mismatch, then check `/admin/audit-logs` around that time. Treat it as a possible security incident (§7) until explained. |
| **Database backup failed** | The nightly `ops:backup` didn't produce a file. | Usually disk space or a changed database password. Fix it and run `php artisan ops:backup` by hand the same day. |
| **`/up` monitor down** | The site or its database is unreachable. | Check Forge's server status → the MySQL service → disk space. If the database is gone, go to §4. |

## 4. Backups and restoring

- **Nightly at 02:30** the scheduler runs `ops:backup`: `mysqldump`, then GnuPG AES-256 encryption, into `storage/app/private/backups/`, keeping the newest 14 (`BACKUP_KEEP`).
- **Copy them off the server.** A backup on the same disk as the database doesn't survive losing the server. Use Forge's backup feature or an `rclone`/`rsync` cron job to object storage (S3/R2/Spaces) with its own credentials, and turn on the bucket's versioning or object lock.
- **The passphrase lives in two places:** the server environment and the owner's password manager. If both are lost, the backups are unrecoverable by design.

**Monthly restore drill** (the launch gate counts a pass for 30 days):

```bash
php artisan ops:restore-drill
```

This decrypts the newest backup, restores it into the separate `halal_restore_drill` database, checks every migration is present, reconciles every ledger on the restored copy, reports the recovery time, then drops the scratch database.

**Real recovery (the live database is lost or corrupted):**

1. `php artisan down` (maintenance mode).
2. Get the newest good `.sql.gpg` onto the server (from off-site storage if the server itself was lost).
3. `gpg --decrypt --output restore.sql halal-YYYY-MM-DD_HHMMSS.sql.gpg` (it asks for the passphrase).
4. Recreate the database, then `mysql -u halal_migrate -p halal_farm_store < restore.sql`, then **delete `restore.sql`**.
5. `php artisan migrate --force --database=mysql_migrate` (in case the backup predates a deploy), then `php artisan ops:reconcile`.
6. **Anything since the backup is missing.** Get today's payments from the Stripe/PayPal dashboards, and call customers whose orders are gone. `php artisan up`.

Recovery time from the local drill (2026-09-15, a 14 KB rehearsal database) was 1.6s. Real production numbers get recorded by each drill in `launch:check`.

> **Windows only (development):** `gpg` from Git for Windows leaves a `gpg-agent` running that holds the parent shell's output open, so a terminal can look "stuck" after the command has already finished. Send the output to a file, or end `gpg-agent` afterwards. Linux servers don't do this.

## 5. Rotating keys

The launch gate needs **every production key to be different from anything used in development**, and any key that may have been exposed rotated immediately.

| Key | How to rotate | Watch out for |
|---|---|---|
| `APP_KEY` | `php artisan key:generate --show` → put the **old** key in `APP_PREVIOUS_KEYS` and the new one in `APP_KEY` → `php artisan config:cache`. | Staff 2FA secrets and encrypted cookies use this key. **Without `APP_PREVIOUS_KEYS`, every staff member is locked out of `/admin`.** Customers are logged out of carts either way. Remove the previous key once staff have signed in again, after about a week. |
| Stripe secret + webhook secret | Roll the key in the Stripe dashboard (it allows an overlap period), update `STRIPE_SECRET`, re-reveal the webhook signing secret into `STRIPE_WEBHOOK_SECRET`. | Update both before the overlap ends. |
| PayPal | Create a new REST app secret, update `PAYPAL_CLIENT_SECRET`, delete the old one. | |
| Twilio | Create a secondary auth token, swap it into `TWILIO_AUTH_TOKEN`, promote it in Twilio. | The STOP webhook's signature check uses this token: swap both in the same minute. |
| Postmark, EasyPost | New server/API token, update the environment, revoke the old one. EasyPost: also regenerate the webhook secret. | |
| Database passwords | `ALTER USER 'halal_app'@'localhost' IDENTIFIED BY '…'`, update the environment, `php artisan config:cache`, restart PHP-FPM and the queue workers. | Do `halal_migrate` separately; it's only used on deploys and backups. |
| `BACKUP_PASSPHRASE` | New passphrase for future backups. **Keep the old one** in the password manager for as long as backups made with it still exist. | |

After any rotation: `php artisan config:cache`, restart queue workers (`php artisan queue:restart`), and place one test order.

## 6. Deploying and rolling back

- Deploys run `deploy/forge-deploy-script.sh`: install, build, migrate (as `halal_migrate`), sync roles, cache, restart queues.
- **Never deploy during opening hours before Eid.** Keep deploys small, and put new features behind a flag (guideline ch. 9).
- **Rolling back code:** redeploy the previous commit in Forge.
- **Rolling back a migration:** every migration has a `down()`, but a rollback on real data can lose data. Take `php artisan ops:backup` right before any deploy that changes the schema, and test the rollback on a restored copy first (`ops:restore-drill --keep` leaves one to practise on).

## 7. Suspected security incident

1. **Contain:** deactivate the affected staff account in `/admin/users` (never delete it; the audit trail needs it) and rotate any key that might be exposed (§5).
2. **Preserve:** run `php artisan ops:backup` now, before changing any data. Export `/admin/audit-logs`.
3. **Assess:** run `php artisan ops:reconcile` to see whether money, stock or credit changed. Check the Stripe dashboard for unexpected refunds or charges.
4. **Notify:** if customer personal data was exposed, Pennsylvania's breach-notification law (73 P.S. §2301 et seq.) may require telling affected customers. Talk to the lawyer before sending anything.

## 8. Product recall

1. `/admin` → **Recall report**, then type the lot number. It lists every order that lot touched, with the customer's phone number. The same lookup from the command line is `php artisan ops:recall-drill <lot>`.
2. Call every customer on the list. Note each call on the order. **Don't fulfil any open order on the list**: its stock is already reserved from the recalled lot.
3. In `/admin/lots`, use **Record wastage** for the lot's entire remaining on-hand weight, with the note "Recall". That takes it to zero, so nothing more can be sold from it. (There's no separate "withdraw lot" button yet; see sprint-09.md, Next.)
4. The **Inspection pack** page produces the HACCP and temperature record for the inspector.

## 9. Scheduled jobs (reference)

| Job | When | If it stops running |
|---|---|---|
| `orders:expire-missed-pickups` | hourly | Uncollected orders never get their reminder or write-off |
| `ops:backup` | 02:30 daily | No new backups, and the alert says so |
| `ops:reconcile` | 06:00 daily | Silent ledger drift goes unnoticed |
