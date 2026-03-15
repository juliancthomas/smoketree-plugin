# Implementation Plan: Demo Account System

## Phase 1 — Data Layer Foundation

- [ ] Step 1: Database migration and schema for `is_demo` column
  - **Task**: Add the `is_demo TINYINT(1) NOT NULL DEFAULT 0` column to the members table. Create an `upgrade_demo_database()` migration in the activator that checks `stsrc_demo_db_version`, verifies column existence with `SHOW COLUMNS`, runs the `ALTER TABLE` if needed, and updates the option to `1.0.0`. Also add `is_demo` to the `CREATE TABLE` definition in `class-stsrc-database.php` for fresh installs.
  - **Files**:
    - `includes/class-smoketree-plugin-activator.php`: Add `upgrade_demo_database()` private static method. Call it from `activate()` after existing `upgrade_promo_database()`. Follow exact pattern of `upgrade_renewal_database()` / `upgrade_promo_database()`.
    - `includes/database/class-stsrc-database.php`: Add `is_demo TINYINT(1) NOT NULL DEFAULT 0` to the members `CREATE TABLE` schema (after the last existing column, before `PRIMARY KEY`).
  - **Step Dependencies**: None
  - **User Instructions**: Deactivate and reactivate the plugin (or visit any admin page that triggers the activator) to run the migration.
  - **Git message**: `feat(database): add is_demo column to members table with migration`

- [ ] Step 2: Member DB methods for demo support
  - **Task**: Add demo-aware query modifications and a `set_demo_flag()` method to the Member DB class. Modify `get_active_member_count()` to append `AND is_demo = 0`. Add `is_demo` filter support to `get_members()` (values: `0` = real only, `1` = demo only, absent = all). Add `AND is_demo = 0` to `get_members_with_balance()`. Add `WHERE is_demo = 0` to `recalculate_all_balances()`. Add a new `set_demo_flag(int $member_id): bool` static method that performs a one-way `UPDATE ... SET is_demo = 1 WHERE member_id = %d`.
  - **Files**:
    - `includes/database/class-stsrc-member-db.php`: Modify `get_active_member_count()`, `get_members()`, `get_members_with_balance()`, `recalculate_all_balances()`. Add `set_demo_flag()`.
  - **Step Dependencies**: Step 1
  - **User Instructions**: None
  - **Git message**: `feat(member-db): add demo flag support to member queries and set_demo_flag method`

## Phase 2 — Admin API and UI

- [ ] Step 3: Admin AJAX endpoint for setting the demo flag
  - **Task**: Register a new `wp_ajax_stsrc_set_demo_flag` action in the AJAX handler. The handler must verify the `stsrc_admin_nonce` nonce, check `current_user_can('manage_options')`, validate that the `member_id` exists, call `STSRC_Member_DB::set_demo_flag($member_id)`, delete the `stsrc_outstanding_balance_stats` transient to invalidate cached dashboard stats, and return a success/error JSON response.
  - **Files**:
    - `includes/api/class-stsrc-ajax-handler.php`: Add `set_demo_flag()` method and register the `wp_ajax_stsrc_set_demo_flag` hook in the constructor or `register_hooks()` method.
  - **Step Dependencies**: Step 2
  - **User Instructions**: None
  - **Git message**: `feat(ajax): add stsrc_set_demo_flag admin AJAX endpoint`

- [ ] Step 4: Member edit page — demo badge and toggle
  - **Task**: On the member edit/detail page, display a large purple "DEMO ACCOUNT" badge in the header when `is_demo === 1`, and a static note "This account is permanently flagged as a demo account." When `is_demo === 0`, render a clearly separated "Demo Account" section at the bottom of the form (before or alongside the Danger Zone) with a checkbox labeled "Flag as Demo Account", a red permanence warning, and JavaScript confirmation dialog. On confirm, fire AJAX to `stsrc_set_demo_flag`; on success, replace the checkbox with the locked badge and show a success notice; on cancel or error, uncheck the checkbox.
  - **Files**:
    - `admin/partials/member-edit.php`: Add demo badge rendering in header area. Add demo toggle section near bottom of form.
    - `admin/js/smoketree-plugin-admin.js`: Add confirmation dialog handler and AJAX call for demo flag toggle. On success, swap checkbox for locked badge.
    - `admin/css/smoketree-plugin-admin.css`: Add `.stsrc-demo-badge` (11px, pill, muted purple `#7C3AED`, white text, 9999px radius, 2px 8px padding) and `.stsrc-demo-badge--large` (13px, 4px 12px padding) styles. Add `.stsrc-demo-toggle-section` styles.
  - **Step Dependencies**: Step 3
  - **User Instructions**: None
  - **Git message**: `feat(admin): add demo badge and permanent toggle to member edit page`

- [ ] Step 5: Members list — demo badge and filter dropdown
  - **Task**: In the members list, display a small "DEMO" badge pill next to the member name for demo accounts. Add a filter dropdown (`demo_filter`) with options: "All Members" (default), "Real Members Only", "Demo Members Only". Wire the filter in the members page class to read `$_GET['demo_filter']` and map it to the `is_demo` filter key passed to `get_members()`. Preserve the filter value in the filter form and in pagination links.
  - **Files**:
    - `admin/partials/members-list.php`: Add demo badge after member name in table rows. Add `<select name="demo_filter">` dropdown to the existing filter bar. Preserve selected value.
    - `admin/pages/class-stsrc-members-page.php`: Read `$_GET['demo_filter']`, sanitize, and map to `is_demo` filter value (`real` → `0`, `demo` → `1`, `all`/absent → no filter). Pass to `get_members()`.
  - **Step Dependencies**: Step 2, Step 4 (for CSS)
  - **User Instructions**: None
  - **Git message**: `feat(admin): add demo badge and filter dropdown to members list`

## Phase 3 — Stripe Payment Isolation

- [ ] Step 6: Payment service — demo-aware Stripe key selection
  - **Task**: Add `get_secret_key_for_member(array $member): string` and `get_publishable_key_for_member(array $member): string` methods that return test keys when `$member['is_demo']` is truthy, otherwise delegate to `get_secret_key()` / `get_publishable_key()`. Modify `init_stripe()` to accept an optional `?string $secret_key = null` parameter — if provided use it, else fall back to `get_secret_key()`. Modify `create_checkout_session()` (and `create_checkout_session_with_details()` if it exists as a separate method) to look up the member record, check `is_demo`, call `init_stripe()` with the correct key, and add `'is_demo' => '1'` to checkout session metadata for demo members. Ensure all checkout session creation paths (registration payment, balance payment, guest pass purchase, renewal) pass member context so the correct key is resolved. Add error handling: if test keys are not configured for a demo checkout, log error and return failure.
  - **Files**:
    - `includes/services/class-stsrc-payment-service.php`: Add `get_secret_key_for_member()`, `get_publishable_key_for_member()`. Modify `init_stripe()` signature. Modify `create_checkout_session()` and all Stripe API call sites.
  - **Step Dependencies**: Step 2
  - **User Instructions**: Ensure Stripe test keys (`stsrc_stripe_test_secret_key`, `stsrc_stripe_test_publishable_key`) are configured in plugin settings even when the site is in live mode. These are required for demo account payments.
  - **Git message**: `feat(payment): add demo-aware Stripe key selection and checkout isolation`

- [ ] Step 7: Webhook handler — dual-mode signature verification and demo event processing
  - **Task**: Modify `handle_webhook()` to read the `livemode` boolean from the raw parsed event payload before signature verification. If `livemode === false`, use `stsrc_stripe_test_webhook_secret` for signature verification; otherwise use the production `stsrc_stripe_webhook_secret`. In `handle_checkout_session_completed()`, after extracting metadata, check for `is_demo` in session metadata. If `is_demo === '1'`, verify the member's `is_demo` flag in the DB and process the payment normally (data isolation comes from the member flag in reporting layers). If a test-mode event arrives but the associated member is not flagged as demo, log a warning and skip processing. Handle edge cases: missing `livemode` defaults to live-mode, log error if test and live webhook secrets are identical.
  - **Files**:
    - `includes/api/class-smoketree-stripe-webhooks.php`: Modify `handle_webhook()` for dual-mode secret selection. Modify `handle_checkout_session_completed()` for demo metadata check.
  - **Step Dependencies**: Step 6
  - **User Instructions**: Register a second webhook endpoint in the Stripe **test-mode** dashboard pointing to the same webhook URL. Use the test webhook signing secret and save it as `stsrc_stripe_test_webhook_secret` in plugin settings.
  - **Git message**: `feat(webhooks): add dual-mode signature verification for demo/live Stripe events`

## Phase 4 — Process Exclusion

- [ ] Step 8: Email service — suppress all emails for demo members
  - **Task**: Add a centralized demo guard at the top of `send_email()` that checks whether the member associated with the email is a demo account. Implement a private helper method `is_demo_member(array $data): bool` that extracts `member_id` from `$data['member_id']` or `$data['member']`, looks up `is_demo` from the DB (with a static property cache to avoid repeated queries within a single request), and returns `true` if demo. When the guard triggers, return `true` (silent success) so callers don't log send failures. In `send_batch_email()`, filter out demo recipients before the send loop — either by checking each recipient or by ensuring the feeding query includes `is_demo = 0`.
  - **Files**:
    - `includes/services/class-stsrc-email-service.php`: Add `is_demo_member()` helper. Add guard to `send_email()`. Add recipient filtering to `send_batch_email()`.
  - **Step Dependencies**: Step 2
  - **User Instructions**: None
  - **Git message**: `feat(email): suppress all emails for demo member accounts`

- [ ] Step 9: Renewal service — exclude demo accounts from eligibility
  - **Task**: Add an early check at the top of `get_eligibility()` in `STSRC_Renewal_Service` that looks up the member and checks `is_demo`. If the member is a demo account, return `['eligible' => false, 'reason' => 'demo_account']` immediately. This prevents demo accounts from appearing in renewal flows, being processed by renewal crons, or seeing a renewal option in the portal. Also audit any batch renewal query in `STSRC_Auto_Renewal_Service` or cron hooks that iterate members — add `is_demo = 0` filter where needed.
  - **Files**:
    - `includes/services/class-stsrc-renewal-service.php`: Add demo check to `get_eligibility()`.
    - `includes/services/class-stsrc-auto-renewal-service.php`: Add `is_demo = 0` filter to any member iteration queries (if applicable).
  - **Step Dependencies**: Step 2
  - **User Instructions**: None
  - **Git message**: `feat(renewal): exclude demo accounts from renewal eligibility and auto-renewal`

## Phase 5 — Reporting and Aggregate Exclusion

- [ ] Step 10: Dashboard widgets and CSV export exclusion
  - **Task**: In `STSRC_Dashboard_Widgets::get_outstanding_balance_stats()`, the underlying `get_members_with_balance()` change from Step 2 automatically excludes demo members. Ensure the transient cache `stsrc_outstanding_balance_stats` is deleted when a member is flagged as demo (already handled in Step 3). Audit all export/CSV code paths: in the members list CSV export (likely in `members-list.php` or `class-stsrc-members-page.php`), ensure the export query passes `'is_demo' => 0` to `get_members()` so demo accounts are never included in data exports. Audit any other aggregate queries in guest pass reporting or family member counts — join to members table and add `is_demo = 0` where needed.
  - **Files**:
    - `admin/class-stsrc-dashboard-widgets.php`: Verify exclusion works via the updated `get_members_with_balance()`. No code change likely needed, but confirm.
    - `admin/pages/class-stsrc-members-page.php`: Ensure CSV export path passes `'is_demo' => 0` filter.
    - `admin/partials/members-list.php`: If CSV export logic lives here, add demo exclusion filter.
  - **Step Dependencies**: Step 2, Step 5
  - **User Instructions**: None
  - **Git message**: `feat(reporting): exclude demo accounts from dashboard stats and CSV exports`