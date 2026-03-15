# Demo Account System Technical Specification

## 1. System Overview

- **Core purpose and value proposition**
  - Enable administrators to create fully functional demo member accounts in production that are invisible to real business metrics, reporting, billing, automated processes, and communications.
  - Serve demonstration, developer testing, and staff training use cases without polluting real data.

- **Key workflows**
  1. Admin flags an existing member (or newly registered member) as demo via the Member Edit page.
  2. Demo member's subsequent Stripe interactions route through test keys — even when the site runs in live mode.
  3. All aggregate queries, dashboard stats, CSV exports, email dispatches, renewal processes, and balance integrity checks automatically exclude demo accounts.
  4. Demo member experiences a fully functional portal identical to a real member's portal.

- **System architecture**
  - **Data layer**: single `is_demo` column on `wp_stsrc_members`; no new tables.
  - **Payment isolation**: `STSRC_Payment_Service` selects test vs live Stripe keys based on member's `is_demo` flag.
  - **Webhook isolation**: `STSRC_Stripe_Webhooks` detects test-mode events via the Stripe `livemode` field and verifies with the test webhook secret.
  - **Reporting isolation**: all aggregate and list queries add `WHERE is_demo = 0` by default.
  - **Email suppression**: `STSRC_Email_Service::send_email()` checks member's `is_demo` flag and short-circuits.
  - **Process exclusion**: renewal eligibility, batch emails, and cron jobs exclude demo members.

## 2. Project Structure

Files modified or created (all modifications unless noted):

- `includes/class-smoketree-plugin-activator.php`
  - Add `upgrade_demo_database()` migration to add `is_demo` column.
- `includes/database/class-stsrc-database.php`
  - Add `is_demo` to the `wp_stsrc_members` CREATE TABLE schema for fresh installs.
- `includes/database/class-stsrc-member-db.php`
  - Add `is_demo` exclusion to `get_active_member_count()`.
  - Add `is_demo` filter support to `get_members()`.
  - Add `is_demo` exclusion to `get_members_with_balance()` (for reporting context).
  - Add `set_demo_flag()` method.
  - Add `recalculate_all_balances()` exclusion.
- `includes/services/class-stsrc-payment-service.php`
  - Add `get_secret_key_for_member()` and `get_publishable_key_for_member()` methods.
  - Modify `create_checkout_session()` to accept and propagate `is_demo` metadata.
  - Modify `init_stripe()` to accept optional key override.
- `includes/services/class-stsrc-email-service.php`
  - Add demo guard in `send_email()` to suppress all emails for demo members.
  - Add demo guard in `send_batch_email()` to filter out demo recipients.
- `includes/services/class-stsrc-renewal-service.php`
  - Add demo exclusion to `get_eligibility()`.
- `includes/api/class-stsrc-ajax-handler.php`
  - Add admin AJAX action `stsrc_set_demo_flag` for toggling the flag.
  - Modify `register_member()` to propagate `is_demo` when admin creates a demo registration.
- `includes/api/class-smoketree-stripe-webhooks.php`
  - Modify `handle_webhook()` to detect test-mode events and verify with test webhook secret.
  - Modify `handle_checkout_session_completed()` to recognize demo metadata and process in isolation.
- `admin/pages/class-stsrc-members-page.php`
  - Add demo badge rendering in list view.
  - Add demo filter dropdown (All / Real only / Demo only).
  - Add demo badge and toggle to member edit page.
- `admin/partials/members-list.php`
  - Render demo badge pill next to member names.
  - Render filter UI.
- `admin/partials/member-edit.php`
  - Render demo badge in header.
  - Render permanent demo toggle checkbox with confirmation.
- `admin/css/smoketree-plugin-admin.css`
  - Add `.stsrc-demo-badge` styles.
- `admin/js/smoketree-plugin-admin.js`
  - Add confirmation dialog for demo flag toggle.
  - Add AJAX handler for demo flag submission.
- `admin/class-stsrc-dashboard-widgets.php`
  - Exclude demo members from outstanding balance stats.

## 3. Feature Specification

### 3.1 Demo Flag on Member Records

- **User story**: As an admin, I can permanently flag any member as a demo account so the system isolates that account from all real business data.
- **Detailed implementation steps**
  1. Add `is_demo TINYINT(1) NOT NULL DEFAULT 0` to `wp_stsrc_members` via migration.
  2. In `class-smoketree-plugin-activator.php`, add `upgrade_demo_database()`:
     - Check `get_option('stsrc_demo_db_version', '0.0.0')`.
     - If below `1.0.0`, run `ALTER TABLE {$table} ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0`.
     - Update option to `1.0.0`.
  3. In `class-stsrc-database.php`, add `is_demo TINYINT(1) NOT NULL DEFAULT 0` to the members CREATE TABLE definition for fresh installs.
  4. In `class-stsrc-member-db.php`, add:
     ```php
     public static function set_demo_flag( int $member_id ): bool
     ```
     - Sets `is_demo = 1` for the given member. One-way only — no method to unset.
  5. In `class-stsrc-ajax-handler.php`, register `wp_ajax_stsrc_set_demo_flag` action:
     - Verify nonce `stsrc_admin_nonce`.
     - Verify `current_user_can('manage_options')`.
     - Call `STSRC_Member_DB::set_demo_flag( $member_id )`.
     - Return success JSON.
  6. Admin member edit page: render checkbox labeled "Flag as Demo Account" with a warning: "This action is permanent and cannot be reversed." Checkbox only visible when `is_demo === 0`. When `is_demo === 1`, display locked badge instead.
  7. JavaScript confirmation dialog: "Are you sure? Once flagged as demo, this account cannot be converted back to a real account." On confirm, fire AJAX to `stsrc_set_demo_flag`.
- **Error handling and edge cases**
  - If member is already demo, the set operation is idempotent (no error, no change).
  - Non-admin users receive `403` if they attempt the AJAX call.
  - The flag column defaults to `0`, so all existing members remain real accounts after migration.

### 3.2 Stripe Payment Isolation

- **User story**: As the system, demo account payments must route through Stripe test keys so they never appear in live Stripe dashboards or affect real revenue.
- **Detailed implementation steps**
  1. In `STSRC_Payment_Service`, add two new methods:
     ```php
     public function get_secret_key_for_member( array $member ): string
     public function get_publishable_key_for_member( array $member ): string
     ```
     - If `$member['is_demo']` is truthy, return test keys (same keys used when `stsrc_stripe_test_mode` is enabled): `stsrc_stripe_test_secret_key` and `stsrc_stripe_test_publishable_key`.
     - Otherwise, return normal keys via existing `get_secret_key()` / `get_publishable_key()`.
  2. Modify `init_stripe()` to accept an optional `$secret_key` parameter:
     ```php
     private function init_stripe( ?string $secret_key = null ): void
     ```
     - If provided, use it; otherwise fall back to `get_secret_key()`.
  3. Modify `create_checkout_session()` and `create_checkout_session_with_details()`:
     - Accept optional `$member` parameter (or extract from `$data['member_id']`).
     - Look up member record if not provided; check `is_demo`.
     - Call `init_stripe( $this->get_secret_key_for_member( $member ) )`.
     - Add `'is_demo' => '1'` to checkout session metadata when member is demo.
  4. All existing checkout session creation call sites (`register_member` payment flow, balance payment, guest pass purchase, renewal) must pass the member context through so the Payment Service can resolve the correct keys.
  5. Since the registration form does not use Stripe.js on the frontend (it redirects to Stripe's hosted Checkout), no publishable key swap is needed client-side. The hosted Checkout page automatically uses the correct mode based on the secret key used to create the session.
- **Error handling and edge cases**
  - If test keys are not configured but a demo checkout is attempted, log an error and return a failure response: "Demo account payments require Stripe test keys to be configured."
  - Demo Stripe customers exist only in Stripe's test environment. If a `stripe_customer_id` from live mode is stored on a member before they are flagged as demo, the Payment Service must create a new test-mode Stripe customer for the demo member on next checkout.

### 3.3 Webhook Handling for Demo Payments

- **User story**: As the system, Stripe test-mode webhook events from demo account payments must be processed correctly without interfering with live event handling.
- **Detailed implementation steps**
  1. Every Stripe event object includes a top-level `livemode` boolean. In `handle_webhook()`:
     ```php
     $event_data = json_decode( $payload, true );
     $is_live_event = $event_data['livemode'] ?? true;
     ```
  2. Select the appropriate webhook secret for signature verification:
     - If `$is_live_event === false`, use `stsrc_stripe_test_webhook_secret`.
     - Otherwise, use the production `stsrc_stripe_webhook_secret`.
  3. Verify the signature with the selected secret. If verification fails, return `400`.
  4. In `handle_checkout_session_completed()`, after extracting metadata:
     - Check for `is_demo` in session metadata.
     - If `is_demo === '1'`, look up the member and confirm `member.is_demo === 1`.
     - Process the payment normally (update payment logs, member status, etc.) — the data goes into the same tables but the member's `is_demo` flag ensures exclusion from all reporting.
  5. If a test-mode event arrives but the member is not flagged as demo, log a warning and skip processing (this would indicate a stray test event).
  6. Register a separate webhook endpoint in Stripe's test-mode dashboard pointing to the same URL, using the test webhook signing secret stored in `stsrc_stripe_test_webhook_secret`.
- **Error handling and edge cases**
  - If both live and test webhook secrets are the same (misconfiguration), log an error.
  - Idempotency: reuse existing `is_event_processed()` mechanism — test and live event IDs occupy different namespaces in Stripe, so no collision risk.
  - If `livemode` is missing from the event payload (should not happen with valid Stripe events), default to live-mode handling.

### 3.4 Exclusion from Aggregate Counts and Reporting

- **User story**: As an admin, demo accounts must never inflate real membership counts, balance totals, or any business metrics.
- **Detailed implementation steps**
  1. **`STSRC_Member_DB::get_active_member_count()`**
     - Append `AND is_demo = 0` to the existing `WHERE status = 'active'` query.
     ```php
     $wpdb->prepare(
         "SELECT COUNT(*) FROM {$table} WHERE status = %s AND is_demo = 0",
         'active'
     );
     ```
  2. **`STSRC_Member_DB::get_members()`**
     - Add a new filter key `is_demo` accepting values: `0` (real only), `1` (demo only), or `null`/absent (all).
     - Default behavior when called from admin list page: return all (admin can filter via dropdown).
     - When called from reporting/export contexts: pass `'is_demo' => 0`.
  3. **`STSRC_Member_DB::get_members_with_balance()`**
     - Add `AND is_demo = 0` to the WHERE clause. This method is used for balance reporting in dashboard widgets.
  4. **`STSRC_Member_DB::recalculate_all_balances()`**
     - Add `WHERE is_demo = 0` to skip demo accounts during batch recalculation.
  5. **`STSRC_Dashboard_Widgets::get_outstanding_balance_stats()`**
     - The `get_members_with_balance()` change above handles this automatically.
     - Clear the transient cache `stsrc_outstanding_balance_stats` after any demo flag change.
  6. **CSV/Data Exports**
     - Audit all export code paths. Add `AND is_demo = 0` to export queries.
     - If exports use `get_members()`, pass `'is_demo' => 0` filter.
  7. **Balance Integrity Checks**
     - Any balance recalculation or integrity audit queries must exclude `is_demo = 1`.
- **Error handling and edge cases**
  - If an admin explicitly filters for demo accounts in the members list, the count header should note "Showing demo accounts" to avoid confusion.
  - Aggregate endpoints called by external integrations (if any) must also exclude demo accounts.

### 3.5 Exclusion from Automated Processes

- **User story**: As the system, demo accounts must never receive automated emails, participate in auto-renewal, or be included in batch operations.
- **Detailed implementation steps**
  1. **Email Suppression** — central guard in `STSRC_Email_Service::send_email()`:
     ```php
     public function send_email( string $template, array $data, string $to, string $subject, array $attachments = array() ): bool {
         if ( $this->is_demo_member( $data ) ) {
             return true; // silently succeed without sending
         }
         // ... existing logic
     }
     ```
     - `is_demo_member( $data )` checks `$data['member_id']` or `$data['member']` and looks up `is_demo` from DB (with static cache to avoid repeated queries).
     - Returns `true` (not `false`) so callers treat it as a successful send — avoids error handling noise.
  2. **Batch Email** — filter in `send_batch_email()`:
     - Before iterating recipients, filter out any with `is_demo = 1`.
     - Alternatively, ensure the member query feeding batch email includes `is_demo = 0`.
  3. **Admin Notification Emails**
     - Emails triggered by demo account activity (e.g., `notify-admin-of-member.php`, `notify-admin-of-guest-pass.php`) are also suppressed by the central guard above since they pass member data through `send_email()`.
  4. **Auto-Renewal Exclusion**
     - In `STSRC_Renewal_Service::get_eligibility()`, add early check:
       ```php
       if ( (int) $member['is_demo'] === 1 ) {
           return [ 'eligible' => false, 'reason' => 'demo_account' ];
       }
       ```
     - This prevents demo accounts from appearing in renewal flows or being processed by any renewal cron.
  5. **Cron/Batch Processes**
     - Audit all cron hooks registered by the plugin. Any that iterate members must add `is_demo = 0` filter.
- **Error handling and edge cases**
  - If a demo member manually navigates to the renewal section in the portal, they see an ineligibility message (no special demo messaging — just "not eligible").
  - Email suppression returns `true` to avoid triggering error alerts for "failed" emails.

### 3.6 Balance, Guest Pass, and Family Member Isolation

- **User story**: As a demo user, balances, guest passes, and family members work normally in the portal. As the system, demo data is excluded from all aggregate reporting.
- **Detailed implementation steps**
  1. **Balances**
     - Demo member balances are tracked in `wp_stsrc_transactions` and `wp_stsrc_members.balance_owed` exactly like real members.
     - Portal balance views, payment flows, and admin member-detail views work normally.
     - Exclusion: `get_members_with_balance()` and all balance aggregate queries add `is_demo = 0`.
  2. **Guest Passes**
     - Demo member guest pass purchases and usage recorded in `wp_stsrc_guest_passes` normally.
     - Portal guest pass views and QR flows work identically.
     - Exclusion: any aggregate guest pass reporting query must join to `wp_stsrc_members` and filter `is_demo = 0`.
  3. **Family Members and Extra Members**
     - Demo member family and extra member records in `wp_stsrc_family_members` and `wp_stsrc_extra_members` function normally.
     - Exclusion: any aggregate counts of family/extra members must join to members and filter `is_demo = 0`.
  4. **Stripe payments for demo balances/guest passes**
     - Use the same key-swapping mechanism from §3.2 — payment service checks `is_demo` on the member before creating checkout sessions.
- **Error handling and edge cases**
  - If a demo member's balance payment webhook arrives, it is processed via the test-mode webhook path (§3.3).
  - Demo guest pass QR codes function at the pool (if QR scanning is purely portal-based, no isolation needed).

### 3.7 Admin UI — Members List

- **User story**: As an admin, I can easily identify demo accounts and filter the members list to show real, demo, or all accounts.
- **Detailed implementation steps**
  1. **Demo Badge**
     - In `members-list.php`, after rendering the member name, check `$member['is_demo']`:
       ```php
       if ( (int) $member['is_demo'] === 1 ) {
           echo '<span class="stsrc-demo-badge">DEMO</span>';
       }
       ```
     - CSS class `.stsrc-demo-badge`:
       ```css
       .stsrc-demo-badge {
           display: inline-block;
           padding: 2px 8px;
           font-size: 11px;
           font-weight: 600;
           line-height: 1.4;
           color: #ffffff;
           background-color: #7C3AED;
           border-radius: 9999px;
           margin-left: 8px;
           vertical-align: middle;
           text-transform: uppercase;
           letter-spacing: 0.5px;
       }
       ```
  2. **Filter Dropdown**
     - Add a `<select>` filter named `demo_filter` with options:
       - `all` — "All Members" (default)
       - `real` — "Real Members Only"
       - `demo` — "Demo Members Only"
     - In `class-stsrc-members-page.php`, read `$_GET['demo_filter']` and map to the `is_demo` filter passed to `get_members()`:
       - `real` → `'is_demo' => 0`
       - `demo` → `'is_demo' => 1`
       - `all` / absent → no `is_demo` filter
  3. **Active member count display**
     - The count shown in the admin header uses `get_active_member_count()`, which already excludes demo accounts (§3.4). No change needed here.
- **Error handling and edge cases**
  - If no demo accounts exist, the filter still renders but "Demo Members Only" returns an empty list with a message.

### 3.8 Admin UI — Member Edit / Detail Page

- **User story**: As an admin, I can see and set the demo flag on individual member records.
- **Detailed implementation steps**
  1. **Demo Badge on Detail/Edit Page**
     - In the page header area of `member-edit.php`, when `is_demo === 1`:
       ```php
       <span class="stsrc-demo-badge stsrc-demo-badge--large">DEMO ACCOUNT</span>
       ```
     - Large variant CSS:
       ```css
       .stsrc-demo-badge--large {
           font-size: 13px;
           padding: 4px 12px;
       }
       ```
  2. **Demo Toggle (when `is_demo === 0`)**
     - Render a clearly separated section at the bottom of the edit form:
       ```html
       <div class="stsrc-demo-toggle-section">
           <h3>Demo Account</h3>
           <label>
               <input type="checkbox" id="stsrc-demo-flag" value="1">
               Flag this member as a Demo Account
           </label>
           <p class="description" style="color: #b91c1c;">
               Warning: This action is permanent and cannot be reversed.
               Demo accounts are excluded from all reporting, billing, and communications.
           </p>
       </div>
       ```
  3. **JavaScript Confirmation**
     - On checkbox change, show a browser `confirm()` dialog:
       ```
       "Are you sure you want to flag this member as a demo account?
       This action is PERMANENT and cannot be undone.
       Demo accounts are excluded from all reporting, billing, emails, and automated processes."
       ```
     - On confirm: fire AJAX POST to `stsrc_set_demo_flag` with `member_id` and `_ajax_nonce`.
     - On success: replace checkbox with the locked demo badge, show success notice.
     - On cancel: uncheck the checkbox.
  4. **Locked State (when `is_demo === 1`)**
     - Hide the checkbox. Show the large demo badge and a static note: "This account is permanently flagged as a demo account."
- **Error handling and edge cases**
  - If the AJAX call fails (network error, permission issue), show an error notice and uncheck the checkbox.
  - Page refresh always reflects the current DB state.

### 3.9 Member Portal (Demo User Experience)

- **User story**: As a demo user, my portal experience is identical to a real member's — no visible demo indicators.
- **Detailed implementation steps**
  1. No changes to any portal template or public-facing partial.
  2. Portal authentication, profile editing, family management, guest pass purchase, balance viewing, and all interactive features work identically.
  3. The `is_demo` column is never exposed to the frontend or included in portal AJAX responses.
  4. When a demo user makes a Stripe payment (balance, guest pass, etc.), the backend routes through test keys transparently — the user sees the normal Stripe Checkout flow but with test-mode card entry (Stripe's test-mode Checkout page accepts test card numbers like `4242 4242 4242 4242`).
- **Error handling and edge cases**
  - If a demo user attempts to use a real credit card on a test-mode Checkout page, Stripe will reject it. This is expected and desired — demo users should use test card numbers.
  - No portal-side messaging explains this; it is the admin's responsibility to communicate test card numbers to demo users.

## 4. Database Schema

### 4.1 Tables

- **`wp_stsrc_members`** (existing, modification only)
  - New column:
    | Field | Type | Default | Constraints |
    |-------|------|---------|-------------|
    | `is_demo` | `TINYINT(1)` | `0` | `NOT NULL DEFAULT 0` |
  - No new indexes. The column is low-cardinality (nearly all `0`); aggregate queries append `AND is_demo = 0` to existing indexed WHERE clauses.
  - Column position: added after the last existing column via `ALTER TABLE ... ADD COLUMN`.

### 4.2 Migration

- **Version key**: `stsrc_demo_db_version`
- **Target version**: `1.0.0`
- **Migration SQL**:
  ```sql
  ALTER TABLE {$prefix}stsrc_members
  ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0;
  ```
- **Execution**: called from `Smoketree_Plugin_Activator::upgrade_database()` → `upgrade_demo_database()`.
- **Idempotency**: check column existence before ALTER to avoid errors on re-activation:
  ```php
  $column_exists = $wpdb->get_results(
      "SHOW COLUMNS FROM {$table} LIKE 'is_demo'"
  );
  if ( empty( $column_exists ) ) {
      $wpdb->query( "ALTER TABLE {$table} ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0" );
  }
  ```

## 5. Server Actions

### 5.1 Database Actions

- **`set_demo_flag( int $member_id ): bool`**
  - Sets `is_demo = 1` for the specified member.
  - SQL: `UPDATE {$table} SET is_demo = 1 WHERE id = %d`
  - Returns `true` on success, `false` on failure.
  - One-way operation: no corresponding `unset_demo_flag()` method.

- **`get_active_member_count(): int`** (modified)
  - SQL: `SELECT COUNT(*) FROM {$table} WHERE status = 'active' AND is_demo = 0`

- **`get_members( array $filters ): array`** (modified)
  - New filter key `is_demo`:
    - `0` → `AND is_demo = 0`
    - `1` → `AND is_demo = 1`
    - Absent / `null` → no demo filter (returns all)

- **`get_members_with_balance( string $operator, float $amount ): array`** (modified)
  - SQL: `SELECT * FROM {$table} WHERE balance_owed {$op} %f AND is_demo = 0 ORDER BY balance_owed DESC`

### 5.2 Other Actions

- **Admin AJAX: `stsrc_set_demo_flag`**
  - Method: POST
  - Parameters: `member_id` (int), `_ajax_nonce` (string)
  - Authentication: `manage_options` capability + nonce verification
  - Response: `{ success: true }` or `{ success: false, data: { message: "..." } }`

- **Payment Service: demo-aware key selection**
  - `get_secret_key_for_member( array $member ): string`
    - If `$member['is_demo']` → return test secret key
    - Else → return `get_secret_key()` (which already handles global test mode)
  - `get_publishable_key_for_member( array $member ): string`
    - Same logic with publishable keys
  - Both methods require Stripe test keys to be configured even when the site is in live mode

- **Webhook: dual-mode signature verification**
  - Read `$event_data['livemode']` from parsed payload before signature verification
  - Select appropriate webhook secret based on `livemode`
  - Verify signature, then proceed with normal event routing
  - Demo events are processed identically to real events — isolation comes from the member's `is_demo` flag in reporting/exclusion layers

## 6. Design System

### 6.1 Visual Style

- **Demo Badge**
  - Background: `#7C3AED` (muted purple)
  - Text: `#FFFFFF`
  - Border radius: `9999px` (pill shape)
  - Font: `11px` / `600` weight / uppercase / `0.5px` letter-spacing
  - Padding: `2px 8px` (standard) / `4px 12px` (large variant)
  - Margin-left: `8px` when inline with member name

- **Typography**
  - Inherits existing admin font stack
  - Warning text on edit page: `#b91c1c` (red-700) for permanence warning

- **Component patterns**
  - Badge uses existing admin UI spacing and alignment conventions
  - Toggle section separated from main form fields with a horizontal rule or distinct background section
  - Confirmation dialog uses native browser `confirm()` for reliability

- **Spacing and layout**
  - Badge sits inline with member name in list rows (vertical-align: middle)
  - Edit page badge sits in the header area next to the member name/title
  - Toggle section has `24px` top margin separation from preceding form content

## 8. Authentication & Authorization

- Only users with `manage_options` capability can view or toggle the `is_demo` flag.
- The `stsrc_set_demo_flag` AJAX action requires:
  1. `current_user_can('manage_options')` — capability check.
  2. `check_ajax_referer('stsrc_admin_nonce')` — nonce verification.
  3. Valid `member_id` that exists in the database.
- Demo users authenticate and access the portal using the same mechanism as real members — no changes to portal auth.
- The `is_demo` flag is never included in any portal-facing API response or template variable.

## 9. Data Flow

- **Server/client data passing**
  - Admin member list: `is_demo` included in member data passed to list template for badge rendering and filter state.
  - Admin member edit: `is_demo` included in member data for badge/toggle rendering.
  - Portal: `is_demo` never passed to frontend.
  - Stripe checkout: `is_demo` passed as metadata on checkout session; read back in webhook.

- **State management architecture**
  - Server-side: `is_demo` is the single source of truth, stored in `wp_stsrc_members`.
  - No caching of demo status — always read from DB (with potential static cache within a single request for performance).
  - Admin JS: only manages the toggle interaction state and AJAX call. No persistent client-side demo state.
  - Transient cache (`stsrc_outstanding_balance_stats`) must be invalidated when a member's demo flag changes.

## 10. Stripe Integration

- **Key selection process**
  1. Caller provides member context (member array or member_id) to Payment Service.
  2. Payment Service calls `get_secret_key_for_member($member)`.
  3. If `$member['is_demo'] === 1`:
     - Secret key: `stsrc_stripe_test_secret_key` (ACF option or wp_option fallback).
     - Publishable key: `stsrc_stripe_test_publishable_key`.
  4. If `$member['is_demo'] === 0` or absent:
     - Delegate to existing `get_secret_key()` / `get_publishable_key()` which respect the global test mode toggle.
  5. Stripe SDK initialized with the selected secret key before API calls.

- **Checkout session metadata**
  - All demo checkout sessions include: `'is_demo' => '1'` in the metadata object.
  - This enables webhook handler to quickly identify demo events without a DB lookup.

- **Webhook handling process**
  1. Receive POST at existing webhook endpoint.
  2. Parse raw payload JSON to read `livemode`.
  3. Select webhook signing secret: test secret if `livemode === false`, live secret otherwise.
  4. Verify signature with selected secret.
  5. Check idempotency via `is_event_processed()`.
  6. Route event to handler.
  7. In `handle_checkout_session_completed()`:
     - Read `is_demo` from session metadata.
     - If demo: verify member `is_demo === 1` in DB; process payment updates normally.
     - If not demo and `livemode === false`: log warning, skip (stray test event).
  8. Mark event as processed.

- **Test mode vs demo mode distinction**
  - **Global test mode** (`stsrc_stripe_test_mode`): entire site uses test keys for all members. Existing behavior, unchanged.
  - **Demo mode**: per-member test key usage while the site is in live mode. New behavior.
  - When global test mode is ON, demo key swapping is redundant but harmless (both paths resolve to test keys).
  - When global test mode is OFF (production), only demo members use test keys.

---

**Confirmed implementation decisions:**
1. `is_demo` is a permanent, one-way flag — no reversal mechanism.
2. No limit on the number of demo accounts.
3. No separate admin page for demo management — managed inline via existing Members list and edit pages.
4. Email suppression is centralized in `STSRC_Email_Service::send_email()` — returns `true` silently.
5. Stripe key swapping is server-side only — no client-side publishable key swap needed because the plugin uses Stripe's hosted Checkout (no Stripe.js on registration form).
6. Webhook dual-mode verification uses the Stripe event's `livemode` field to select the correct signing secret.
7. Demo members are excluded from renewal eligibility, not just renewal cron — so a demo user navigating to the renewal section sees "not eligible."
8. Demo badge uses muted purple (`#7C3AED`) for visual distinction without being obnoxious.
9. All existing member queries default to excluding demo accounts in reporting/aggregate contexts. The admin members list includes demo accounts by default (with badge) unless filtered.
