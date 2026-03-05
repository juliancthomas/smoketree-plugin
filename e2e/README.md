# Smoketree Plugin — E2E Test Suite

End-to-end tests for the Smoketree Swim & Recreation Club WordPress plugin, built with [Playwright](https://playwright.dev/) and designed to run against a [LocalWP](https://localwp.com/) environment.

## Prerequisites

- **Node.js** 18+ and npm
- **LocalWP** site running (`smoketree-ai.local`)
- **MySQL client** on PATH (ships with most LocalWP installs, or install via your OS package manager)
- **Chromium** (installed automatically by Playwright)

## Setup

```bash
cd e2e

# 1. Install dependencies
npm install

# 2. Install Playwright browsers (first time only)
npx playwright install chromium

# 3. Create your environment config
cp .env.test.example .env.test
# Edit .env.test with your LocalWP database port, admin credentials, etc.

# 4. Seed test data into the database
npm run seed
```

The seed script connects directly to your LocalWP MariaDB and creates:

- **4 membership types:** Household ($300), Duo ($225), Individual ($175), Civic ($50)
- **5 test members:** one per membership type, plus one with a $75 outstanding balance
- **2 access codes:** `TESTCODE2026` (general) and `POOLCODE2026` (premium/pool-only)
- Ensures registration is enabled

## Running Tests

```bash
# Run all tests (headless)
npm test

# Run with a visible browser
npm run test:headed

# Interactive Playwright UI (recommended for debugging)
npm run test:ui

# Run a specific test suite
npm run test:auth
npm run test:registration
npm run test:portal
npm run test:family
npm run test:extra
npm run test:guest
npm run test:balance
```

## Test Structure

```
e2e/
├── fixtures/
│   ├── seed-data.ts         # Database seeding (run before tests)
│   └── test-members.ts      # Shared test member credentials
├── utils/
│   ├── db.ts                # Direct MySQL query helpers
│   ├── helpers.ts           # Form fill helpers, waitForAjax, unique generators
│   ├── login.ts             # loginAsMember, loginAsAdmin, logout
│   └── wp-cli.ts            # WP-CLI wrapper (optional)
├── tests/
│   ├── global-setup.ts      # Auto-seeds data before test run
│   ├── global-teardown.ts
│   ├── auth.spec.ts         # Login, logout, forgot/reset password, redirects
│   ├── registration.spec.ts # Form validation, membership selection, order summary, Stripe
│   ├── member-portal.spec.ts# Profile, edit, change password, access codes, balance
│   ├── family-members.spec.ts  # Family member CRUD (Household/Duo)
│   ├── extra-members.spec.ts   # Extra member add with payment (Household)
│   ├── guest-passes.spec.ts    # Purchase and use guest passes
│   └── balance-payment.spec.ts # Pay outstanding balance via Stripe
├── .env.test                # Local config (gitignored)
├── .env.test.example        # Template for .env.test
└── playwright.config.ts     # Playwright configuration
```

### Test Coverage by Area

| Suite | Tests | What it covers |
|-------|------:|----------------|
| `auth.spec.ts` | 16 | Login (valid/invalid/empty), password toggle, logout, forgot password, reset password (invalid token), wp-login redirect, portal access guards |
| `registration.spec.ts` | 37 | Required fields, membership card selection, family/extra member limits, order summary math (fees, caps), payment method toggles, auto-renewal, password mismatch, duplicate email, Stripe redirect, Zelle completion |
| `member-portal.spec.ts` | 24 | Profile display, edit profile, change password, access codes (pool vs non-pool), balance card, guest pass section, section visibility per membership type |
| `family-members.spec.ts` | 11 | Add/edit/delete family members, Household limit (4), Duo limit (1), Individual excluded |
| `extra-members.spec.ts` | 11 | Add modal, payment method, $50 summary, Stripe flow, visibility per membership type |
| `guest-passes.spec.ts` | 12 | Balance display, purchase modal, quantity math, Stripe flow, guest pass portal access |
| `balance-payment.spec.ts` | 11 | Balance card, Pay Balance modal, card/ACH fee calculations, Stripe flow |
| **Total** | **141** | |

## Cleaning Up

```bash
# Remove all test-created members, users, and access codes
npm run seed:reset
```

This only removes records created by the seed script (identified by known test email addresses and access code values). It does not touch your real data.

## Stripe Configuration

Tests that involve Stripe Checkout (registration with card/bank, extra members, guest pass purchases, balance payments) will:

1. **Redirect to `checkout.stripe.com`** if your `.env.test` has valid Stripe test keys and the plugin is configured in test mode.
2. **Gracefully handle missing keys** — the tests verify either a Stripe redirect or an error response, so they won't hard-fail if Stripe isn't configured.

To fully test payment flows end-to-end, add your Stripe test keys to `.env.test`:

```
STRIPE_TEST_PK=pk_test_...
STRIPE_TEST_SK=sk_test_...
```

Use Stripe's [test card numbers](https://docs.stripe.com/testing#cards) (e.g. `4242 4242 4242 4242`) when completing checkout in headed mode.

## Troubleshooting

**Tests can't connect to the database**
- Verify LocalWP is running
- Check `DB_PORT` in `.env.test` matches LocalWP's database port (visible in LocalWP site settings)
- Ensure `mysql` is on your PATH

**Login tests fail with "Invalid email or password"**
- Run `npm run seed` to create test accounts
- WordPress stores passwords as MD5 on first seed; the password auto-upgrades on first login

**SSL certificate errors**
- The Playwright config has `ignoreHTTPSErrors: true` enabled, which handles LocalWP's self-signed certificates

**Tests are slow**
- Tests run sequentially (`workers: 1`) to avoid conflicts from shared state. This is intentional for a WordPress plugin where AJAX calls share the same session database.
