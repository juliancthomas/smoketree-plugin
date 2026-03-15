# Auto-Renewal System

## What It Does

Auto-renewal allows members who pay with a credit card or bank account (Stripe) to have their membership automatically renewed at the start of each new season. Members who pay by check, Zelle, cash, or payment plan are **not eligible** for auto-renewal — they must go through the manual renewal flow in the portal.

## How It Works (Season Lifecycle)

1. **During the current season** — Active members who paid via Stripe can opt in to auto-renewal from the Member Portal profile page, or during registration/renewal checkout.
2. **Admin runs Season Reset** — All active members become inactive. Auto-renewal preferences are preserved (unless the admin explicitly checks the "Clear auto-renewal" box).
3. **Cron job runs** — The auto-renewal cron finds all inactive members who have `auto_renewal_enabled = 1`, a Stripe-compatible payment type (`card` or `bank_account`), and a saved Stripe customer ID. It charges their saved payment method off-session and moves them directly back to active.
4. **Everyone else** — Members without auto-renewal must visit the portal and go through the renewal flow manually (`inactive → pending → active`).

## Who Is Eligible

All three conditions must be true:

| Requirement | Why |
|---|---|
| Payment type is `card` or `bank_account` | Only Stripe supports off-session charges |
| Has a `stripe_customer_id` | Needed to charge their saved payment method |
| `auto_renewal_enabled = 1` | Member must explicitly opt in |

Members who paid by check, Zelle, or cash will never see the auto-renewal opt-in during checkout, and the toggle is disabled on their profile page.

## Where Members Opt In

- **Registration form** — The auto-renewal agreement section appears when a Stripe payment type (card or bank account) is selected. It shows the agreement text from the `stsrc_auto_renewal_text` ACF field and requires acknowledgment.
- **Renewal form** — Same behavior: the auto-renewal section appears only for card/ACH and includes the agreement text. Opting in saves the preference to the member record.
- **Member Portal profile** — Active members can toggle auto-renewal on or off at any time. The toggle is disabled if they don't have a saved Stripe payment method.

## Admin Controls

### Season Reset (Members List page)

The "Start Season Reset" button moves all active members to inactive. Two optional checkboxes:

- **Clear auto-renewal opt-in** (unchecked by default) — If checked, resets `auto_renewal_enabled = 0` for all members. Leave unchecked so that Stripe members who opted in will be auto-renewed by the cron job.
- **Reset guest pass balances** — Zeros out guest pass ledger balances.

### Member Edit page

Admins can manually toggle the auto-renewal checkbox on any member's edit page. A warning appears if the member doesn't have a Stripe-compatible payment method or saved customer ID.

## Auto-Renewal Agreement Text

The agreement text shown during registration and renewal checkout is managed via the ACF option field `stsrc_auto_renewal_text` (WYSIWYG editor). It can also be edited from the plugin Settings page under "Auto-Renewal Agreement."

## Status Transitions

| Scenario | Path |
|---|---|
| Season reset | `active` → `inactive` |
| Manual renewal (all payment types) | `inactive` → `pending` → `active` |
| Auto-renewal (Stripe only) | `inactive` → `active` (instant off-session charge) |

Auto-renewal skips the `pending` state because the Stripe off-session charge confirms immediately — there is no waiting period.

## Cron Schedule

Two cron hooks are registered:

- `stsrc_auto_renewal_send_notifications` — Sends reminder emails to eligible members before the renewal date (configurable lead time, default 7 days).
- `stsrc_auto_renewal_process` — Attempts off-session Stripe charges on the renewal date itself.

The season renewal date is configured via the `stsrc_season_renewal_date` ACF option field.
