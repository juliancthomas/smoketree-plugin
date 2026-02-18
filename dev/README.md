# Local Stripe Testing

Tools for testing Stripe payments locally using [Stripe CLI](https://stripe.com/docs/stripe-cli).

## Prerequisites

- Stripe CLI installed (`stripe --version`)
- Stripe CLI already authenticated (run `stripe login` if needed)
- LocalWP site running at `smoketree-ai.local`

## Quick Start

### Step 1 – Start the webhook listener

**Windows (CMD/PowerShell):**
```
dev\stripe-listen.bat
```

**Git Bash / WSL:**
```bash
bash dev/stripe-listen.sh
```

You'll see output like:
```
> Ready! Your webhook signing secret is whsec_abc123... (^C to quit)
```

**Copy that `whsec_...` secret** — you'll need it in Step 2.

---

### Step 2 – Configure the plugin (one-time)

Go to **WP Admin → Smoketree Settings** and fill in:

| Field | Value |
|-------|-------|
| Test Mode | ✅ Checked |
| Test Publishable Key | `pk_test_51QhBzUG...` (from Stripe Dashboard) |
| Test Secret Key | `sk_test_51QhBzUG...` (from Stripe Dashboard) |
| Test Webhook Secret | `whsec_...` (from the CLI output in Step 1) |

Save settings.

> **Note:** The `whsec_...` secret changes each time you restart `stripe listen`. Update it in the plugin settings whenever you restart the listener.

---

### Step 3 – Run the webhook test runner

With the listener running, open a second terminal and run:

```bash
bash dev/stripe-test-webhooks.sh
```

This opens an interactive menu to fire any of the 5 most-used webhook flows with real member/customer metadata pre-filled:

```
 1)  checkout.session.completed  →  registration       ($300)
 2)  checkout.session.completed  →  balance_payment    ($100)
 3)  payment_intent.succeeded
 4)  checkout.session.completed  →  guest_pass         (1 × $5)
 5)  payment_intent.payment_failed
 6)  checkout.session.completed  →  extra_member       ($50)
 7)  Run all 5 in sequence
 8)  Cleanup / Reset test data
```

The **Cleanup** option (8) resets member 13's status back to `pending`, removes test payment log rows, deletes the test extra member, and restores balance_owed — so you can re-run tests cleanly.

---

## Stripe Test Cards

Use these card numbers on the Stripe Checkout page:

| Scenario | Card Number |
|----------|-------------|
| Success | `4242 4242 4242 4242` |
| Requires auth | `4000 0025 0000 3155` |
| Declined | `4000 0000 0000 9995` |
| Insufficient funds | `4000 0000 0000 9995` |

Use any future expiry date, any 3-digit CVC, and any ZIP.

---

## Webhook Endpoint

The plugin registers webhooks at:
```
https://smoketree-ai.local/wp-json/stripe/v1/webhook
```

Events handled:
- `checkout.session.completed` → registration, balance payment, guest pass, extra member
- `payment_intent.succeeded` → updates payment log status
- `payment_intent.payment_failed` → failure handling and notifications

---

## Troubleshooting

**`stripe listen` fails with SSL error:**
The `--skip-verify` flag is already included in the scripts to handle LocalWP's self-signed cert.

**Webhook returns 401/403:**
Make sure the `whsec_...` from the current CLI session is saved in plugin settings.

**Events not forwarding:**
Confirm LocalWP is running and `https://smoketree-ai.local` loads in your browser.

**Re-authenticate Stripe CLI:**
```bash
stripe login
```
