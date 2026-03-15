# Implementation Plan: Promo Codes & Affiliate Referral System

<brainstorming>

## Analysis of Inputs

### What exists that we build on:
- `STSRC_Database::create_tables()` — central `dbDelta`-based schema management; we extend this + add a versioned migration method in the activator pattern.
- `class-stsrc-ajax-handler.php` — existing AJAX pattern (nonce verification, `wp_send_json_error/success`, `sanitize_*`, rate limiting).
- `STSRC_Payment_Service::create_checkout_session()` — existing Stripe session creator we must modify.
- `STSRC_Email_Service::send()` — existing email dispatch we call for treasurer notification.
- `class-stsrc-access-codes-page.php` + `access-codes-list.php` — admin table + modal pattern to mirror exactly.
- `admin/partials/settings-form.php` — ACF options pattern already in use.
- `registration-form.php` + `registration.js` — front-end registration we add the Discounts section into.
- `member-portal.php` + `member-portal.js` — portal view we add affiliate code display to.
- Activator versioned upgrade pattern (`stsrc_db_version`, `upgrade_database()`) — we add a new `upgrade_promo_database()` method.

### Key sequencing decisions:
1. DB schema first (tables + members column) — everything else depends on this.
2. Service classes and DB access classes before AJAX handlers.
3. AJAX handler and plugin bootstrapping together — hooks must be registered before testing.
4. Admin UI (promo code CRUD + reporting) as a self-contained block.
5. Front-end registration form additions (HTML, JS, CSS) as a self-contained block.
6. Payment integration last on the backend side — it modifies existing critical paths.
7. Member portal affiliate code display is independent and low-risk.
8. Treasurer email template + usage recording wired in after payment integration.
9. Backfill migration separate step — one-time, guarded by option flag.
10. OpenGraph nice-to-have last.

### Risk areas to flag:
- One-time use concurrency: `SELECT ... FOR UPDATE` in a transaction.
- Stripe free registration ($0 path) — need special `payment_type`.
- Server-side revalidation must happen before Stripe session creation — ordering in `register_member()` is critical.
- `affiliate_code` column UNIQUE constraint added via `dbDelta` may need `ALTER TABLE` fallback.

</brainstorming>

---

## Section 1: Database Schema

- [ ] Step 1: Create promo codes and affiliate referrals database tables
  - **Task**: Add schema for `wp_stsrc_promo_codes`, `wp_stsrc_promo_code_usages`, and `wp_stsrc_affiliate_referrals` tables to `STSRC_Database::create_tables()`. Also add `affiliate_code VARCHAR(30) NULL DEFAULT NULL` column + unique index to `wp_stsrc_members` via `dbDelta`. Add a new `upgrade_promo_database()` private method in `Smoketree_Plugin_Activator` (following the `upgrade_renewal_database()` pattern) guarded by option `stsrc_promo_db_version`. The method calls `STSRC_Database::create_tables()` and then adds the `affiliate_code` column via `$wpdb->query("ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...")` for safety, then sets `stsrc_promo_db_version` to `1.0.0`. Call `upgrade_promo_database()` from `upgrade_database()`.
  - **Files**:
    - `includes/database/class-stsrc-database.php`: Add all three new table SQL blocks inside `create_tables()` using `dbDelta`. Add `affiliate_code` column to the `wp_stsrc_members` CREATE TABLE statement so `dbDelta` picks it up on fresh installs. Add index `idx_affiliate_code`.
    - `includes/class-smoketree-plugin-activator.php`: Add `private static function upgrade_promo_database(): void` method. Call it from `upgrade_database()`.
  - **Step Dependencies**: None
  - **User Instructions**: After saving these files, deactivate and reactivate the plugin from **Plugins → Installed Plugins** to trigger the activation hook and run the migration. Verify the three new tables exist in your DB tool (e.g., phpMyAdmin or Adminer) and that `wp_stsrc_members` now has an `affiliate_code` column.
  - **Git message**: `feat(db): add promo codes, usages, and affiliate referrals schema with activator migration`

---

## Section 2: Core Service & Database Classes

- [ ] Step 2: Create `STSRC_Promo_Codes_DB` database access class
  - **Task**: Create `includes/database/class-stsrc-promo-codes-db.php`. Implement all DB methods for promo codes: `create_code(array $data): int|WP_Error`, `update_code(int $code_id, array $data): bool|WP_Error`, `soft_delete_code(int $code_id): bool`, `get_code_by_name(string $name): ?object` (case-insensitive, excludes soft-deleted), `get_all_codes(array $filters = []): array` (supports `is_active`, `search`, pagination), `increment_usage_count(int $code_id): void` (atomic `UPDATE ... SET usage_count = usage_count + 1`), `record_usage(int $code_id, int $member_id, float $discount_amount, int $type_id): int`, `get_usage_report(array $filters = []): array` (join with `wp_stsrc_members` for member name + date). Use `$wpdb->prepare()` for all queries. Use `$wpdb->prefix . 'stsrc_promo_codes'` and `$wpdb->prefix . 'stsrc_promo_code_usages'`. Follow existing `class-stsrc-access-code-db.php` pattern for structure.
  - **Files**:
    - `includes/database/class-stsrc-promo-codes-db.php`: New file — full DB class.
  - **Step Dependencies**: Step 1
  - **User Instructions**: None
  - **Git message**: `feat(db): add STSRC_Promo_Codes_DB class with full CRUD and usage tracking`

- [ ] Step 3: Create `STSRC_Affiliate_Referrals_DB` database access class
  - **Task**: Create `includes/database/class-stsrc-affiliate-referrals-db.php`. Implement: `create_referral(array $data): int|WP_Error`, `update_payout_status(int $referral_id, string $status, int $admin_user_id): bool` (sets `paid_at` and `paid_by_user_id` when marking paid; clears them on revert to pending), `get_referral_log(array $filters = []): array` (join with `wp_stsrc_members` twice for referrer + new member names; supports `payout_status`, `date_from`, `date_to` filters), `get_by_new_member_id(int $member_id): ?object` (idempotency check). All queries use `$wpdb->prepare()`.
  - **Files**:
    - `includes/database/class-stsrc-affiliate-referrals-db.php`: New file — full DB class.
  - **Step Dependencies**: Step 1
  - **User Instructions**: None
  - **Git message**: `feat(db): add STSRC_Affiliate_Referrals_DB class with referral log and payout tracking`

- [ ] Step 4: Create `STSRC_Discount_Service` service class
  - **Task**: Create `includes/services/class-stsrc-discount-service.php`. Implement all service methods:
    - `validate_promo_code(string $code, int $membership_type_id): array|WP_Error` — runs the full 6-step validation sequence from spec §3.5. Returns `{code_id, discount_type, discount_value, computed_amount, label}` or `WP_Error` with descriptive message.
    - `validate_affiliate_code(string $code): array|WP_Error` — normalizes to uppercase, looks up `wp_stsrc_members.affiliate_code`, checks `status = 'active'`. Returns `{referrer_member_id, referrer_name, discount_amount}` or `WP_Error`.
    - `compute_discounted_total(float $base, string $discount_type, float $discount_value): float` — handles `flat` and `percentage` types, rounds percentage to nearest cent with `round()`, floors at `0.00`.
    - `generate_affiliate_code(string $last_name): string` — sanitizes last name (uppercase, strip non-alpha, truncate 10 chars; fallback `MEMBER`), generates `REF-{LASTNAME}-{####}`, checks collision against `wp_stsrc_members.affiliate_code`, retries up to 20 times then uses 6-digit suffix.
    - `backfill_affiliate_codes(): array` — processes members where `affiliate_code IS NULL` in batches of 100, generates and saves codes, returns `{processed, skipped, errors[]}`.
    - `record_discount_usage(int $member_id, array $discount_payload): void` — routes to `STSRC_Promo_Codes_DB::record_usage()` (+ `increment_usage_count`) or `STSRC_Affiliate_Referrals_DB::create_referral()` based on `discount_payload['type']`. For affiliate, also triggers the treasurer email (see Step 10). Idempotent — checks for existing record before inserting.
    - Use DB transactions + `SELECT ... FOR UPDATE` on one-time-use promo code insertion to prevent concurrency double-use (spec §4, impl note 4).
  - **Files**:
    - `includes/services/class-stsrc-discount-service.php`: New file — full service class.
  - **Step Dependencies**: Steps 2, 3
  - **User Instructions**: None
  - **Git message**: `feat(service): add STSRC_Discount_Service with validation, calculation, code generation, and usage recording`

---

## Section 3: AJAX Handler & Plugin Bootstrap

- [ ] Step 5: Create `STSRC_Discount_Ajax` AJAX handler and register all hooks
  - **Task**: Create `includes/api/class-stsrc-discount-ajax.php`. Implement all six AJAX actions:
    - **Public** (`wp_ajax_nopriv_` + `wp_ajax_`): `validate_promo_code()` — verifies `stsrc_registration_nonce`, sanitizes `code` and `membership_type_id`, calls `STSRC_Discount_Service::validate_promo_code()`, returns JSON. `validate_affiliate_code()` — same nonce, sanitizes `code`, calls service, returns JSON.
    - **Admin** (`wp_ajax_` only, `manage_options` + `stsrc_admin_nonce`): `create_promo_code()`, `update_promo_code()`, `delete_promo_code()`, `toggle_payout_status()`. Each sanitizes inputs, delegates to the appropriate DB class, and returns JSON success/error.
    - Update `includes/class-smoketree-plugin.php`: In `define_public_hooks()`, register `wp_ajax_nopriv_stsrc_validate_promo_code`, `wp_ajax_stsrc_validate_promo_code`, `wp_ajax_nopriv_stsrc_validate_affiliate_code`, `wp_ajax_stsrc_validate_affiliate_code`. In `define_admin_hooks()`, register `wp_ajax_stsrc_create_promo_code`, `wp_ajax_stsrc_update_promo_code`, `wp_ajax_stsrc_delete_promo_code`, `wp_ajax_stsrc_toggle_payout_status`. Add `require_once` for all new PHP classes in `load_dependencies()`.
  - **Files**:
    - `includes/api/class-stsrc-discount-ajax.php`: New file — AJAX handler class.
    - `includes/class-smoketree-plugin.php`: Add `require_once` for all 4 new class files in `load_dependencies()`. Register 8 new AJAX action hooks.
  - **Step Dependencies**: Steps 2, 3, 4
  - **User Instructions**: None
  - **Git message**: `feat(ajax): add STSRC_Discount_Ajax handler and register all discount AJAX hooks`

---

## Section 4: Admin UI — Promo Code Management

- [ ] Step 6: Create admin promo codes page (list + CRUD modal)
  - **Task**: Create `admin/pages/class-stsrc-promo-codes-page.php` as a new admin sub-page under `stsrc-dashboard`. The page renders `admin/partials/promo-codes-list.php` (paginated table: Code Name, Type, Value, Uses used/limit, Expires, Membership Restriction, Status badge, Edit/Deactivate/Delete actions) and `admin/partials/promo-codes-form.php` (modal with all fields: code name, discount type radio, discount value, expiration date picker, one-time use checkbox, usage limit input, membership type multi-select, active checkbox). Modal opens/closes via JS. All saves/deletes go through the AJAX handler from Step 5. Match `class-stsrc-access-codes-page.php` structure exactly. Register the sub-menu in `class-smoketree-plugin.php` `define_admin_hooks()` → `add_submenu_page()`. Enqueue admin JS/CSS conditionally on this page only.
  - **Files**:
    - `admin/pages/class-stsrc-promo-codes-page.php`: New file — admin page class, sub-menu registration, asset enqueueing.
    - `admin/partials/promo-codes-list.php`: New file — HTML table partial for listing promo codes with status badges and action buttons.
    - `admin/partials/promo-codes-form.php`: New file — modal form partial for add/edit promo code with all fields.
    - `includes/class-smoketree-plugin.php`: Add `require_once` for the new page class; add `add_action('admin_menu', ...)` for sub-menu registration.
  - **Step Dependencies**: Step 5
  - **User Instructions**: None
  - **Git message**: `feat(admin): add promo codes admin page with list table and CRUD modal`

- [ ] Step 7: Create admin affiliate referral log report
  - **Task**: Create `admin/partials/affiliate-referrals-report.php` — a paginated table partial with columns: Referrer Name, New Member Name, Date, Discount Given ($), Credit Owed ($), Payout Status badge (Pending/Paid), "Mark as Paid" / "Revert to Pending" action button. Payout toggle calls `wp_ajax_stsrc_toggle_payout_status` via AJAX and updates the row inline. Add a "Referral Report" tab or sub-section to the promo codes admin page (tabbed layout using the same pattern as existing admin pages). Wire the report partial into `class-stsrc-promo-codes-page.php`. Support URL parameter `?tab=referrals` to pre-select the tab. Include a filter bar for payout status (All / Pending / Paid).
  - **Files**:
    - `admin/partials/affiliate-referrals-report.php`: New file — referral log table partial with payout toggle.
    - `admin/pages/class-stsrc-promo-codes-page.php`: Add tab navigation and include the referral report partial when `?tab=referrals`.
  - **Step Dependencies**: Steps 3, 6
  - **User Instructions**: None
  - **Git message**: `feat(admin): add affiliate referral log tab with payout status toggle to promo codes page`

---

## Section 5: ACF Settings Fields

- [ ] Step 8: Add affiliate discount and credit ACF settings fields
  - **Task**: In `admin/partials/settings-form.php`, add two new ACF number fields to the existing plugin settings group: `stsrc_affiliate_new_member_discount` (label: "New Member Referral Discount ($)", default 500) and `stsrc_affiliate_referrer_credit` (label: "Referrer Credit Amount ($)", default 50). Follow the existing `get_field($key, 'option') ?: $default` fallback pattern already used for other settings. Add helper text beneath each field explaining its purpose. Also register the ACF field group programmatically if the plugin creates its options fields via `acf_add_local_field_group()` — follow whatever pattern is already used for the settings ACF group.
  - **Files**:
    - `admin/partials/settings-form.php`: Add two new ACF number fields with labels, defaults, and helper text.
  - **Step Dependencies**: None
  - **User Instructions**: After deploying, go to **Smoketree → Settings** and verify the two new fields appear. Set your preferred default values and save.
  - **Git message**: `feat(settings): add affiliate new-member discount and referrer credit ACF fields`

---

## Section 6: Registration Form — Discount Section (HTML + CSS)

- [ ] Step 9: Add Discounts section HTML to registration form and discount styles
  - **Task**: In `public/partials/registration-form.php`, insert a `<section id="stsrc-discounts-section">` block immediately before the Order Summary section. The section contains: a heading "Discounts", a promo code field group (label, text input `#stsrc_promo_code`, Apply button `#apply-promo-btn`, feedback div `#promo-feedback`), an affiliate/referral code field group (same structure, IDs `stsrc_affiliate_code`, `apply-affiliate-btn`, `affiliate-feedback`). Add four hidden inputs that JS will populate on successful apply: `applied_discount_type`, `applied_discount_code`, `applied_discount_amount`, `applied_discount_computed`. Also add a referral banner placeholder `<div id="stsrc-referral-banner" class="stsrc-referral-banner" style="display:none;"></div>` at the top of the registration page (outside the form card, inside the page wrapper).
    In `public/css/smoketree-plugin-public.css`, add all discount section styles: default input/button state, success-applied state (green border, checkmark, "Remove" link), error state (red border), disabled/greyed state (opacity 0.5, cursor not-allowed, helper text), referral banner (fixed below header, green background, dismissible), discount line item in order summary (green `-` prefix), spacing (8px scale, 16px between fields, 24px top margin). Use existing CSS variables/color tokens from the file.
  - **Files**:
    - `public/partials/registration-form.php`: Add Discounts section HTML with all inputs and hidden fields. Add referral banner div.
    - `public/css/smoketree-plugin-public.css`: Add all discount section styles (approx. 80–120 lines).
  - **Step Dependencies**: None
  - **User Instructions**: None
  - **Git message**: `feat(ui): add discount section HTML and styles to registration form`

---

## Section 7: Registration Form — JavaScript

- [ ] Step 10: Add discount AJAX validation, URL param auto-fill, and cookie logic to registration.js
  - **Task**: In `public/js/registration.js`, add a discount module within the existing JS file structure. On `DOMContentLoaded`:
    1. **URL/cookie auto-apply**: Read `URLSearchParams` for `ref` param; if present, populate `#stsrc_affiliate_code`, set 48hr cookie (`stsrc_ref_code; max-age=172800; path=/; SameSite=Lax`), trigger affiliate validation. If `ref` absent, read `stsrc_ref_code` cookie and auto-populate + validate if found. URL param takes priority over cookie.
    2. **Apply button handlers**: On click of `#apply-promo-btn` — read promo code value, POST to `stsrc_validate_promo_code` AJAX action with nonce + code + current `membership_type_id`. On click of `#apply-affiliate-btn` — POST to `stsrc_validate_affiliate_code` with nonce + code.
    3. **Success handler** (shared): populate feedback div with "✓ {label}"; show "Remove" link; update hidden fields (`applied_discount_type`, `applied_discount_code`, `applied_discount_computed`); add/update order summary discount line item; disable the other field group with `disabled` attr + grey class + "Only one discount can be applied." helper text. For affiliate success, show referral banner: "Referral discount from [Name] will be applied at checkout!" with dismiss ✕.
    4. **Error handler**: show red inline message in feedback div.
    5. **Remove handler**: clear feedback, re-enable both fields, remove order summary line item, clear hidden fields, clear affiliate cookie (`max-age=0`), hide referral banner.
    6. **Membership type change**: if a promo code is applied and membership type changes, re-validate promo code silently; if now invalid, show warning and force user to re-apply.
    7. **`wp_localize_script`**: In `class-smoketree-plugin.php` `define_public_hooks()` (or wherever registration script is enqueued), add `ref_cookie_name: 'stsrc_ref_code'` to the `stsrc_registration` localized data object if not already present.
  - **Files**:
    - `public/js/registration.js`: Add discount module (~180–220 lines).
    - `includes/class-smoketree-plugin.php`: Add `ref_cookie_name` and `affiliate_discount_label` keys to the localized script data for the registration page.
  - **Step Dependencies**: Steps 5, 9
  - **User Instructions**: None
  - **Git message**: `feat(js): add discount AJAX validation, referral URL auto-fill, and cookie logic to registration`

---

## Section 8: Payment Integration

- [ ] Step 11: Integrate discount into Stripe checkout session creation and non-Stripe submission
  - **Task**: Modify `includes/services/class-stsrc-payment-service.php` — extend `create_checkout_session()` to accept an optional `?array $discount_data` parameter. If provided: subtract discount from base amount (floor at 0 cents), add a second negative line item `"Discount: {code}"` to the session for audit clarity (spec §10), add metadata keys `discount_code`, `discount_type`, `discount_amount`. If `discounted_total === 0.00`, return `['status' => 'free', 'member_id' => ...]` instead of a Stripe session. Recalculate processing fee on the discounted subtotal, not the original.
    Modify `includes/api/class-stsrc-ajax-handler.php` — in `register_member()`: (a) after input validation, if `applied_discount_type` is set in POST, call `STSRC_Discount_Service::validate_promo_code()` or `::validate_affiliate_code()` to re-validate server-side; if validation fails, `wp_send_json_error()` immediately; (b) compute `$discounted_total` via `STSRC_Discount_Service::compute_discounted_total()`; (c) use `$discounted_total` for `balance_owed` on non-Stripe paths; (d) pass `$discount_data` to `create_checkout_session()`; (e) on free registration, set `payment_type = 'promo_free'` or `'referral_free'`, `balance_owed = 0.00`, skip Stripe, finalize immediately; (f) after successful registration (Stripe or non-Stripe), call `STSRC_Discount_Service::record_discount_usage($member_id, $discount_payload)`.
    Also update the Stripe webhook handler `class-smoketree-stripe-webhooks.php`: in the `checkout.session.completed` handler, after finalizing registration, call `STSRC_Discount_Service::record_discount_usage()` using metadata from the session if present — idempotent check prevents double recording.
  - **Files**:
    - `includes/services/class-stsrc-payment-service.php`: Extend `create_checkout_session()` with optional `$discount_data`, negative line item, metadata, free-registration bypass.
    - `includes/api/class-stsrc-ajax-handler.php`: Add server-side discount re-validation + application in `register_member()`. Call `record_discount_usage()` after successful registration. Handle `promo_free`/`referral_free` payment types.
    - `includes/api/class-smoketree-stripe-webhooks.php`: Add idempotent `record_discount_usage()` call in `checkout.session.completed` handler using session metadata.
  - **Step Dependencies**: Steps 4, 5
  - **User Instructions**: None
  - **Git message**: `feat(payment): integrate discount into Stripe checkout session and non-Stripe registration flow`

---

## Section 9: Member Portal — Affiliate Code Display

- [ ] Step 12: Add affiliate referral code display to member portal
  - **Task**: In `public/class-stsrc-member-portal.php` (or wherever the Membership Information section is rendered), add an affiliate code block inside the Membership Information section, conditionally rendered only when `$member->status === 'active'`. The block displays: "Your Referral Code: `REF-SMITH-4821`" (monospace), "Referral Link: `https://smoketree.us/register/?ref=REF-SMITH-4821`" (shown as text), and a "Copy Referral Link" button (`#copy-referral-btn`, `data-url="..."` attribute). If `affiliate_code` is null for a somehow-missed active member, call `STSRC_Discount_Service::generate_affiliate_code()` on-the-fly and save it before rendering.
    In `public/js/member-portal.js`, add a click handler for `#copy-referral-btn`: read `data-url`, call `navigator.clipboard.writeText()`, on success swap button text to "Copied!" and reset after 2 seconds (using `setTimeout`). Graceful fallback for browsers without clipboard API (select text in a temp input).
    In `public/css/smoketree-plugin-public.css`, add styles for the affiliate code block: monospace code display, referral link text, copy button styling matching the existing portal button style, "Copied!" state (green text swap).
  - **Files**:
    - `public/class-stsrc-member-portal.php`: Add affiliate code block to Membership Information section with conditional `status === active` check.
    - `public/js/member-portal.js`: Add clipboard copy handler for referral link button.
    - `public/css/smoketree-plugin-public.css`: Add affiliate code block styles.
  - **Step Dependencies**: Step 4
  - **User Instructions**: None
  - **Git message**: `feat(portal): add affiliate referral code display and copy link button to member portal`

---

## Section 10: Treasurer Email Template

- [ ] Step 13: Create treasurer referral credit email template
  - **Task**: Create `templates/treasurer-referral-credit.php` — a treasurer notification email template using the existing plugin email template structure (header, body, footer styling matching other templates in `templates/`). Template variables: `$referrer_name`, `$referrer_email`, `$new_member_name`, `$new_member_email`, `$credit_amount`, `$registration_date`. Subject line: "Referral Credit Due — [Referrer Name]". Body clearly states who referred whom, the credit amount owed, and instructs the treasurer to issue the credit manually. No automated balance adjustment is made.
    Wire the email send into `STSRC_Discount_Service::record_discount_usage()` (already scaffolded in Step 4): after inserting the affiliate referral record, call `STSRC_Email_Service::send()` with recipient = `get_option('stsrc_treasurer_email')`, template = `treasurer-referral-credit.php`, and all template variables populated from the referral and member data.
  - **Files**:
    - `templates/treasurer-referral-credit.php`: New email template file.
    - `includes/services/class-stsrc-discount-service.php`: Add `STSRC_Email_Service::send()` call inside `record_discount_usage()` for affiliate discount type. Add `require_once` for email service if not already loaded.
  - **Step Dependencies**: Steps 3, 4
  - **User Instructions**: None
  - **Git message**: `feat(email): add treasurer referral credit notification email template and trigger`

---

## Section 11: Backfill Migration

- [ ] Step 14: Run one-time affiliate code backfill for existing members
  - **Task**: In `Smoketree_Plugin_Activator::upgrade_promo_database()` (created in Step 1), after creating tables, add a backfill block guarded by option `stsrc_affiliate_code_backfill_done`. If the option is not set (or `!== '1'`), load `STSRC_Discount_Service` and call `backfill_affiliate_codes()` (implemented in Step 4). Log the result via `error_log()` (`STSRC Affiliate Backfill: processed X, skipped Y, errors Z`). Set `stsrc_affiliate_code_backfill_done` to `'1'` after successful completion so it never runs again.
    Also add a manual trigger: in the admin migration page (`admin/pages/class-stsrc-migration-page.php`), add a "Backfill Affiliate Codes" button/section that calls a new `wp_ajax_stsrc_run_affiliate_backfill` AJAX action. This gives admins a manual safety valve. Register this AJAX action in `class-smoketree-plugin.php`.
  - **Files**:
    - `includes/class-smoketree-plugin-activator.php`: Add backfill call inside `upgrade_promo_database()` guarded by `stsrc_affiliate_code_backfill_done` option.
    - `admin/pages/class-stsrc-migration-page.php`: Add "Backfill Affiliate Codes" UI section with manual trigger button.
    - `includes/class-smoketree-plugin.php`: Register `wp_ajax_stsrc_run_affiliate_backfill` hook.
    - `includes/api/class-stsrc-discount-ajax.php`: Add `run_affiliate_backfill()` admin AJAX method.
  - **Step Dependencies**: Steps 1, 4
  - **User Instructions**: After deploying, go to **Smoketree → Migration** and click "Backfill Affiliate Codes" to generate codes for all existing members. Check the result summary. Alternatively, deactivate/reactivate the plugin to trigger automatically. After running, verify `affiliate_code` values in the `wp_stsrc_members` table.
  - **Git message**: `feat(migration): add one-time affiliate code backfill with activation trigger and manual admin UI`

---

## Section 12: OpenGraph Meta Tags (Nice-to-Have)

- [ ] Step 15: Add OpenGraph meta tag customization for referral links
  - **Task**: In `includes/class-smoketree-plugin.php` or a dedicated filter, hook into `wp_head` using `add_action('wp_head', ...)`. Check if the current page is the registration page AND `$_GET['ref']` is set. If so, validate the ref code (call `STSRC_Discount_Service::validate_affiliate_code()`) to get the referrer's name. If valid, output custom OG meta tags:
    - `og:title`: "Join Smoketree Club — [Referrer Name] sent you a discount!"
    - `og:description`: "Use [Referrer Name]'s referral link to get a discount on your Smoketree Club membership."
    - `og:url`: current registration URL with `?ref=` parameter.
    Output these via `printf('<meta property="og:title" content="%s" />', esc_attr($title))` etc. Only output if not already output by a theme/SEO plugin (check for existing OG title — use `has_filter('wp_head', ...)` or simply add with a low priority so it doesn't conflict). Skip silently if code is invalid.
  - **Files**:
    - `includes/class-smoketree-plugin.php`: Add `wp_head` hook in `define_public_hooks()` pointing to a new method `output_referral_og_tags()`.
  - **Step Dependencies**: Step 4
  - **User Instructions**: Test by sharing a referral link on Facebook's [Open Graph Debugger](https://developers.facebook.com/tools/debug/) and confirming the custom title/description appear.
  - **Git message**: `feat(seo): add OpenGraph meta tag customization for referral link sharing`

---

## Summary

This plan implements the Promo Codes & Affiliate Referral System in 15 sequential steps across 12 logical sections. The sequencing ensures:

1. **Schema first** — all downstream code has stable tables to work against.
2. **Service/DB classes before AJAX** — handlers delegate to tested, isolated classes.
3. **Bootstrap wiring alongside the handler** — hooks are registered before any UI testing.
4. **Admin UI** (Steps 6–7) and **front-end UI** (Steps 9–10) are self-contained blocks that can be reviewed independently.
5. **Payment integration** (Step 11) comes after all service logic is proven, as it modifies the most critical existing code path.
6. **Backfill** (Step 14) is isolated and guarded so it cannot accidentally run twice.
7. **OpenGraph** (Step 15) is decoupled and can be deferred without blocking anything else.

**Key implementation considerations**:
- Server-side discount revalidation in `register_member()` is non-negotiable — the client amount is advisory only.
- The `SELECT ... FOR UPDATE` transaction guard on one-time-use codes is critical for correctness under concurrent submissions.
- The `dbDelta` approach for the `affiliate_code` column addition should be tested on a staging DB before production activation — `dbDelta` can miss certain column alterations; the `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` fallback in the activator provides safety.
- All three new payment type values (`promo_free`, `referral_free`) must be added to any `ENUM` or validation logic that checks `payment_type` in the existing codebase.
