# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Smoketree Plugin is a WordPress membership management plugin for a swim club. It handles member registration, Stripe payments, guest passes, auto-renewal, and email campaigns. Entry point: [smoketree-plugin.php](smoketree-plugin.php).

## Build & Deployment

There are no npm/Composer build steps in the root. The plugin is deployed as a zip:

```powershell
# Windows
powershell -ExecutionPolicy Bypass -File build-zip.ps1
```

GitHub Actions (`.github/workflows/release.yml`) also builds a zip on release.

## Testing

### E2E Tests (Playwright)

```bash
cd e2e
npm install
npm test                    # Run all 141 tests (headless)
npm run test:headed         # Run with visible browser
npm run test:ui             # Interactive Playwright UI
npm run test:auth           # Auth suite only (16 tests)
npm run test:registration   # Registration suite (37 tests)
npm run test:portal         # Member portal suite (24 tests)
npm run test:family         # Family members (11 tests)
npm run test:extra          # Extra members (11 tests)
npm run test:guest          # Guest passes (12 tests)
npm run test:balance        # Balance payment (11 tests)

# Seed test data
npm run seed                # Creates 4 membership types, 5 test members, 2 access codes
npm run seed:reset          # Clean up test data
```

E2E tests require LocalWP running with the plugin active. See [e2e/README.md](e2e/README.md) for full setup.

### Stripe Webhook Testing

```bash
bash dev/stripe-listen.sh           # Start Stripe CLI webhook listener
bash dev/stripe-test-webhooks.sh    # Interactive runner for 5 payment flow scenarios
```

## Architecture

### Plugin Structure

Follows the WordPress Plugin Boilerplate pattern with strict admin/public separation:

- **[includes/](includes/)** — Core logic: services, database DAOs, models, API handlers
- **[admin/](admin/)** — Admin pages, partials, JS/CSS
- **[public/](public/)** — Frontend templates, member portal, JS/CSS
- **[templates/email/](templates/email/)** — 13 responsive HTML email templates

### Bootstrap Flow

`smoketree-plugin.php` → instantiates `Smoketree_Plugin` → registers all hooks via `Smoketree_Plugin_Loader` → `run()` dispatches everything.

All WordPress actions/filters are registered through the loader — never call `add_action`/`add_filter` directly outside of `Smoketree_Plugin` or its admin/public classes.

### Key Layers

**Services** (`includes/services/`) — Business logic, one class per domain:
- `STSRC_Auto_Renewal_Service` — cron-driven season renewal notifications and off-session payments
- `STSRC_Balance_Service` — ledger tracking, balance adjustments, member activation
- `STSRC_Payment_Service` — Stripe Checkout session creation
- `STSRC_Email_Service` — renders and sends email templates
- `STSRC_Renewal_Service` / `STSRC_Renewal_Pricing_Service` — renewal logic and pricing

**Database DAOs** (`includes/database/`) — One class per table, all static methods, all queries via `$wpdb->prepare()`. Tables are prefixed `wp_stsrc_`: `members`, `membership_types`, `family_members`, `extra_members`, `guest_passes`, `transactions`, `payment_logs`, `renewals`, `access_codes`, `email_logs`, `promo_codes`, `affiliate_referrals`.

**API Handlers** (`includes/api/`) — Hybrid approach:
- Stripe webhooks use WP REST API (`/wp-json/stripe/v1/webhook`) via `Smoketree_Stripe_Webhooks`
- All member/admin actions use WordPress AJAX (`wp_ajax_*`) via `STSRC_Ajax_Handler`, `STSRC_Balance_Ajax`, `STSRC_Renewal_API`, `STSRC_Discount_Ajax`

**Models** (`includes/models/`) — Domain objects: `STSRC_Member`, `STSRC_Guest_Pass`, `STSRC_Membership_Type`

### Security Conventions

Every AJAX handler must verify a nonce and check user capabilities before doing anything. Stripe webhook handler verifies the `Stripe-Signature` header using the webhook secret. All DB queries use `$wpdb->prepare()`.

### Settings

All plugin options are stored in WordPress options prefixed `stsrc_` (Stripe keys, CAPTCHA config, renewal dates, registration open/closed toggle, etc.). Retrieved via `get_option('stsrc_*')`.

### Stripe Integration

- Stripe PHP SDK is vendored at `vendor/stripe/` (committed directly, no Composer)
- Plugin auto-updates via Yanis Elsts' Plugin Update Checker from the GitHub repo main branch
- Test/live mode toggled via the Settings admin page
