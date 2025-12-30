=== Smoketree Swim and Recreation Club ===
Contributors: smoketree-dev
Donate link: https://smoketree.us
Tags: membership, stripe, registration, guest passes, recreation club, swim club
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Comprehensive membership management plugin for Smoketree Swim & Recreation Club including registration, payments, member portal, guest passes, and auto-renewal.

== Description ==

The Smoketree Swim & Recreation Club plugin delivers a full WordPress-based membership management system built specifically for seasonal pool and recreation clubs. It provides secure online registration, Stripe-based payments, a member self-service portal, guest pass purchasing and tracking, admin dashboards, mass email communication, and automated renewal workflows.

**Highlight Features**

* Online registration with CAPTCHA, validation, and Stripe Checkout (card + ACH).
* Member portal for profile updates, family members, extra members, guest pass usage, and Stripe billing portal access.
* Admin tools for member management, guest pass analytics, email campaigns, access code creation, bulk actions, and CSV export.
* Season-based auto-renewal engine with notification emails, Stripe off-session charges, and detailed logging.
* Thirteen responsive HTML email templates covering all membership lifecycle touchpoints.
* Centralized logging, nonce verification, capability checks, and uninstall cleanup that removes tables, options, roles, cron events, and transients.

== Installation ==

1. Upload the `smoketree-plugin` directory to `/wp-content/plugins/`.
2. Activate **Smoketree Swim and Recreation Club** through the “Plugins” screen.
3. Go to **Smoketree → Settings** to supply Stripe API keys, toggle registration, configure season renewal, and adjust other options.
4. (Optional) Set up ACF option pages that map to plugin settings or leave the default WordPress options UI in place.
5. Configure a Stripe webhook at `https://your-domain.com/wp-json/smoketree-stripe/v1/webhook` with the webhook secret saved in plugin settings.

== Frequently Asked Questions ==

= Does this plugin require Stripe? =
Yes. All payments (registrations, guest passes, auto-renewals) are processed through Stripe using Checkout Sessions or Payment Intents. Test mode is supported via dedicated keys.

= Can members manage their own billing details? =
Yes. Members can launch the Stripe Customer Portal from the member portal to update payment methods, see invoices, and manage saved cards/bank accounts.

= What happens when the plugin is uninstalled? =
All custom database tables (`wp_stsrc_*`), transients, options, cron events, processed Stripe event trackers, and the custom `stsrc_member` role are fully removed to leave no residual data.

= Do I need Advanced Custom Fields (ACF)? =
The plugin detects ACF Pro automatically for managing settings through option pages, but it falls back to native WordPress options if ACF is not installed.

= How do auto-renewals work? =
The plugin schedules cron events that, seven days before renewal, send reminder emails, and on the renewal date attempt off-session Stripe charges for members with saved payment methods. Results are logged and members are notified of successes or failures.

== Screenshots ==

1. Member portal overview showing profile, guest pass balance, and quick actions.
2. Admin membership list with filters, bulk actions, and status badges.
3. Guest pass analytics dashboard displaying purchases, usage, and revenue.
4. Registration form with multi-step layout, CAPTCHA, and membership selection.

== Changelog ==

= 1.0.0 =
* Initial public release of the Smoketree Swim & Recreation Club plugin.
* Added full registration workflow with Stripe Checkout integration and validation.
* Delivered member portal (profile management, family members, extra members, guest passes, Stripe portal).
* Implemented admin dashboards, membership management, email campaigns, access codes, guest pass tools, and CSV exports.
* Added auto-renewal cron service with notification emails and Stripe payment intents.
* Included 13 responsive email templates and centralized logging via `STSRC_Logger`.
* Added uninstall cleanup that drops tables, removes options/roles/transients, and clears custom meta.

== Upgrade Notice ==

= 1.0.0 =
This is the first stable release. Install or upgrade to gain access to the full Smoketree Swim & Recreation Club membership management feature set.