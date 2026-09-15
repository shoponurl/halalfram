# Staff guide

Everyday use of the staff panel at **`/admin`**, by role. Short on purpose: print the page for your role and keep it by your station.

**Everyone:**

- Sign in with your own email and password, then the 6-digit code from your authenticator app. **Never share an account.** Every weight, payment and refund is recorded under the name of whoever did it.
- Lost your phone? Sign in with one of the **recovery codes** you saved when you set up 2FA, then set up the app again on your new phone from your profile. Lost the codes too? The Owner confirms it's really you, and the developer runs `php artisan staff:reset-2fa you@… --reason="…"` on the server. That's recorded in the audit log.
- Left the business? Your account is deactivated, never deleted, so the history stays accurate.
- If something looks wrong with money (a charge that doesn't match the scale ticket, a refund you didn't do), **don't try to fix it yourself**. Tell the Owner or Manager.

---

## Butcher

**Your screen: Production board**, today's orders in queue order, with the butcher-minutes each one takes.

1. **Open** the order and print the **Cutting sheet**: cut, offal and packing instructions for each piece.
2. Cut and pack. For each item, go to *Items & weights* → **Record weight**, then type the actual weight from the scale, in lb, exactly as shown.
   - Made a mistake? Record the weight again with a **note saying why**. The old number is kept, not erased.
3. **Print label** for each pack. The label carries the lot number and use-by date. **Every pack gets a label before it leaves the table.**
4. **QC check:** tick the checklist, enter the temperature, and choose Pass or Fail.
   - **Fail** needs a note. The order goes back for re-cutting and can't be charged until it passes.
5. Stock that spoiled or was trimmed away (not for any order): *Lots* → **Record wastage**, with a reason.

**Once an order is charged, its weights are locked.** A correction after that point goes through the Manager as a credit note.

## Front desk

**Your screens: Orders, Production board, and Phone / counter order.**

**Charging an order (after the butcher's QC pass):**

- **Finalize & charge card** charges the actual weighed price. Most orders just capture from the hold the customer placed at checkout.
- A little over the estimate is charged automatically. Well over, and the customer gets a payment link by text/email for the rest; the order shows "Awaiting balance" until they pay.
- **Well under the estimate** stops for a Manager to approve. That check exists to catch a typo on the scale.

**Pickup:**

1. **Mark ready for pickup** texts the customer.
2. When they collect: **Mark picked up**.
3. **Cash on pickup orders:** **Record cash payment**, and enter what they handed you.
4. Not collected? The system sends one reminder, then writes the order off automatically with a partial refund. Nothing for you to do.

**Phone or walk-in orders:** *Phone / counter order* → look the customer up by phone number → add items → place it. It's pickup-only for now.

**Invoices:** **Invoice PDF** on any charged order.

**Customer says their order arrived warm** (nationwide shipping): **Mark arrived warm — issue refund** and write down what they told you. This **always refunds the full amount**, and we never re-ship.

## Driver

**Your screen: Today's deliveries** (built for your phone).

1. Tap **Start delivery** when you leave. The customer is texted.
2. At the door, tap **Delivered** and enter the customer's code, **or** take a photo of the order at the door.
3. Nobody home: **No one home**, with a quick note. The shop decides on a re-delivery or refund.

Call a customer by tapping their phone number.

## Manager

Everything the front desk and butcher can do, plus:

- **Approve underweight & charge**, when an order came in well under estimate. Check the scale ticket first.
- **Store credit:** *Store credit* → **Issue credit** (email, amount, reason). Customers use it online by requesting a link to that email at checkout.
- **Catalog, stock, delivery zones and slots, packing rules, ship blackout dates** are all in the left menu. Changes apply immediately, no developer needed.
- **Reports:** yield, margin, wastage, sales, stock aging. Margins on our own farm's animals are labeled **estimated**.
- **Privacy requests (CCPA):** *Privacy requests*. "Delete" requests anonymize the customer's name and contact details but keep the lot link, which food-safety law requires.
- **Inspector visit:** **Inspection pack** produces the HACCP and temperature record as a PDF.
- **Recall:** see the runbook, §8.

## Owner

Everything above, plus staff accounts (*Users*), roles, and the **Audit log** (who changed a role, took cash, issued credit, signed off a launch item, and when).

Before launch, and after anything big, the developer runs `php artisan launch:check` with you. Items needing your signature (the pentest retest letter, a real SMS test, the day the front desk and butcher ran the system on their own) are signed off with `php artisan launch:attest`.

---

## The dry-run day (launch gate item: *staff_dry_run*)

Before real customers, the front desk and butcher run one full day on the system **with no developer in the room**:

- 5+ online orders across pickup, cash on pickup, and (if soft launch includes it) delivery
- 2 phone orders
- Every order weighed, QC'd (at least one deliberate QC fail), charged and handed over
- One over-estimate order that needs a payment link, and one under-estimate order that needs Manager approval
- One recall lookup on a lot used that day

Write down every place someone got stuck or confused. Anything that stops a sale gets fixed before launch. Then the Owner signs it off:
`php artisan launch:attest staff_dry_run --by=owner@… --evidence="dry run 2027-01-12: list of issues in <doc>, all fixed"`
