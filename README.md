# Smoketree Swim & Recreation Club Plugin

The Smoketree Swim & Recreation Club plugin provides a complete membership management
solution for WordPress. It handles online registration, payment processing with Stripe,
member and guest pass portals, bulk administration tools, automated email workflows,
and season-based auto-renewal.

## Features

- **Online Registration:** Guided registration form with payment plan support, auto
  validation, CAPTCHA, and Stripe Checkout integration.
- **Member Portal:** Members can manage profiles, family members, extra members,
  guest passes, and Stripe billing portal access.
- **Guest Pass Management:** Purchase guest passes, log usage, and allow admins to
  adjust balances.
- **Admin Dashboard:** Membership insights, bulk status updates, access code creation,
  email campaigns, CSV exports, and configurable settings.
- **Auto-Renewal Engine:** Cron-driven notifications and off-session renewal charges
  with Stripe payment intents and comprehensive logging.
- **Email Templates:** Thirteen responsive HTML templates for membership, payment,
  and administrative notifications.
- **Security & Logging:** Centralized logger, nonce/capability checks, rate limiting,
  and uninstall cleanup to remove tables, options, roles, and leftover data.

## Requirements

- WordPress 6.0 or later
- PHP 8.1 or later
- MySQL 5.7+, MariaDB 10.4+, or compatible
- Valid HTTPS certificate (required by Stripe)
- Stripe account with API keys (live and/or test)
- Optional: Advanced Custom Fields Pro (used to manage settings via options pages)

## Installation

1. Clone or download this repository into `wp-content/plugins/smoketree-plugin`.
2. Run `composer install` if you maintain the vendor directory separately (Stripe
   SDK is bundled, so this is normally not required).
3. Activate **Smoketree Swim & Recreation Club** from `Plugins → Installed Plugins`.
4. Upon activation the plugin will:
   - Create required database tables with the `wp_stsrc_` prefix.
   - Register the `stsrc_member` role.
   - Schedule auto-renewal cron events.
   - Seed default membership types (if none exist).

## Configuration

1. Navigate to **Smoketree → Settings** in the WordPress admin.
2. Provide Stripe API keys (publishable, secret, webhook secret, and optional test
   credentials).
3. Configure registration toggles, payment plan options, season renewal date, and
   secretary email.
4. If you use CAPTCHA, add provider keys (reCAPTCHA v3 or hCaptcha) and enable
   protection.
5. Customize email templates in `smoketree-plugin/templates/` as desired. Each template
   uses PHP placeholders that are documented in the associated service calls.

### Stripe Webhook

Configure a webhook endpoint in the Stripe dashboard pointing to:

```
https://your-domain.com/wp-json/smoketree-stripe/v1/webhook
```

Events handled: `checkout.session.completed`, `payment_intent.succeeded`,
`payment_intent.payment_failed`, and `invoice.payment_failed`.

## Member Experience

- Members access the portal at `/member-portal`.
- Forgot/reset password flows are implemented at `/forgot-password` and
  `/reset-password`.
- Guest pass instant use is available at `/guest-pass-portal`.
- Registration resides at `/register`.

## Admin Experience

The plugin introduces a “Smoketree” top-level menu containing:

- **Dashboard** – Key metrics and signups.
- **Members** – CRUD pages with filters, CSV export, and bulk actions.
- **Membership Types** – Manage plans, benefits, pricing, and Stripe product IDs.
- **Guest Passes** – View logs, adjustments, and analytics.
- **Access Codes** – Generate invite codes with expiry windows.
- **Email Campaigns** – Compose, preview, and send batch emails to filtered segments.
- **Settings** – Stripe, general, CAPTCHA, auto-renewal, and other configuration toggles.

## Development

- Code follows WordPress coding standards and uses the Plugin Boilerplate component
  structure.
- Central logging is handled with `STSRC_Logger` found in `includes/services`.
- Database schema resides in `includes/database/class-stsrc-database.php`.
- AJAX endpoints live in `includes/api/class-stsrc-ajax-handler.php`.
- Auto-renewal logic is encapsulated in `includes/services/class-stsrc-auto-renewal-service.php`.

### Testing

- Use WordPress’ built-in debug log (`wp-config.php` → `WP_DEBUG_LOG true`) to capture
  logger output.
- A CLI or WP-CLI installation is recommended for triggering cron handlers manually:
  `wp cron event run stsrc_auto_renewal_send_notifications`.
- Stripe test mode is supported by enabling “Test Mode” in plugin settings and
  supplying test keys.

## Deployment Tips

- Ensure webhooks are deployed with the correct secret for each environment.
- Confirm scheduled events via `wp cron event list`.
- Clear caches (object cache, CDN) after updating templates or CSS.
- Maintain TLS 1.2+ compatibility for Stripe API calls.

## Uninstall Cleanup

When the plugin is deleted from WordPress:

- All custom tables (`wp_stsrc_*`) are dropped.
- Scheduled auto-renewal cron jobs are removed.
- The `stsrc_member` role is deleted.
- Options beginning with `stsrc_` (including Stripe and CAPTCHA settings) are removed.
- Transients (`_transient_stsrc_*`) and Stripe processed event trackers are purged.
- Password reset user meta created by the plugin is deleted.

## License

Released under the GNU General Public License v2 or later. The full license text is
available in `smoketree-plugin/LICENSE.txt`.

## Support

This repository is maintained by the Smoketree development team. Please open an issue
or submit a pull request if you encounter bugs or want to contribute enhancements.
