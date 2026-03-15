<specification_planning>
1. Core system architecture and key workflows
- Build entirely on top of the existing WordPress plugin's patterns: custom DB tables, AJAX handlers, admin pages, ACF options, and Stripe checkout integration.
- Three primary workflows:
  a. Admin creates/manages promo codes in a new admin sub-page.
  b. Registrant enters a promo or affiliate code during registration (or arrives via referral URL); AJAX validates and applies the discount; Stripe/non-Stripe payment total is adjusted.
  c. Referring member views their affiliate code in the portal and shares the link; after a successful referral registration, treasurer receives an email.
- Key cross-cutting concerns: one-discount-at-a-time enforcement, URL-based referral auto-fill, cookie persistence, idempotent code usage tracking, admin reporting.

2. Project structure and organization
- New DB classes under `includes/database/`.
- New service class for discount validation and application under `includes/services/`.
- New AJAX handler file under `includes/api/`.
- New admin page and partials under `admin/pages/` and `admin/partials/`.
- Additions to `public/partials/registration-form.php` (discount section) and `public/partials/member-portal.php` / portal class (affiliate code display).
- New email templates under `templates/`.
- ACF options additions to `admin/partials/settings-form.php`.
- JS additions to `public/js/registration.js` and `public/js/member-portal.js`.
- CSS additions to existing `public/css/` files.
- Schema managed via plugin activator/migration using `dbDelta`.

3. Detailed feature specifications
- Promo code CRUD admin page (create, edit, deactivate, delete).
- Affiliate code generation on member creation and backfill migration.
- Registration form discount section (two fields, mutual exclusion, AJAX validation, line item).
- Referral URL auto-fill + cookie persistence.
- Payment integration: reduce Stripe checkout session amount and non-Stripe `balance_owed`.
- Treasurer email notification on referral use.
- Admin reporting: promo usage table, affiliate referral log with payout toggle.
- OpenGraph meta tags (nice-to-have).

4. Database schema design
- `wp_stsrc_promo_codes`: code definitions (name, discount type, per-type discount values JSON, expiry, limits, active flag).
- `wp_stsrc_promo_code_usages`: per-member-registration usage records (code_id, member_id, registration_id, discount_amount, used_at).
- Affiliate code stored in `wp_stsrc_members` as a new `affiliate_code` column.
- `wp_stsrc_affiliate_referrals`: referral tracking (referral_code, referrer_member_id, new_member_id, credit_amount, payout_status, referred_at).
- No separate "referral code" table needed since codes are denormalized onto member row.

5. Server actions and integrations
- `validatePromoCode(code, membershipTypeId)` — checks existence, active, not expired, usage limit, one-time use, per-type discount lookup.
- `validateAffiliateCode(code, membershipTypeId)` — checks existence, belonging to an active member, per-type referral discount lookup.
- `applyDiscountToRegistration(memberId, discountPayload, baseAmount)` — computes final discounted amount, records usage.
- `generateAffiliateCode(lastName)` — generates REF-LASTNAME-#### with collision check.
- `backfillAffiliateCodes()` — migration utility.
- AJAX actions: `stsrc_validate_promo_code`, `stsrc_validate_affiliate_code` (both nopriv for registration page).
- Admin AJAX: `stsrc_create_promo_code`, `stsrc_update_promo_code`, `stsrc_delete_promo_code`, `stsrc_toggle_payout_status`.

6. Design system and component architecture
- Match existing registration card/summary styles.
- "Discounts" section: two labeled input groups with Apply buttons, success/error states, remove button.
- Toast/banner for referral arrival.
- Admin page uses same table + modal pattern as access codes page.

7. Authentication and authorization
- Promo/affiliate validation endpoints: `nopriv` (registration is public).
- Admin CRUD endpoints: `manage_options` capability + nonce.
- Payout toggle: admin-only.

8. Data flow and state management
- Client stores transient discount state (applied code, discount amount, type).
- Server recalculates discount before Stripe session creation and before non-Stripe submission.
- Never trust client-supplied discount amount for final charge.

9. Payment implementation
- Stripe: reduce line item total before creating checkout session. If discounted to $0, skip Stripe and record as free.
- Zelle/Check/Pay Later: reduce `balance_owed` by discount amount.
- Cap at $0 minimum.
</specification_planning>

# Promo Codes & Affiliate Referral System Technical Specification

## 1. System Overview

### Core Purpose and Value Proposition
Add a promotional discount layer and member referral program to the Smoketree Club registration process. Admins configure time-limited, use-capped promo codes; existing members receive auto-generated affiliate codes they can share as a URL. Both code types integrate seamlessly with the existing Stripe and non-Stripe payment flows, reducing the amount charged rather than bypassing the payment step.

### Key Workflows
1. **Admin creates a promo code** → sets name, discount type/value, expiry, usage limits, membership type restrictions.
2. **Member shares referral link** → `https://smoketree.us/register/?ref=REF-LASTNAME-####` — arrives at registration page with code pre-filled and validated.
3. **Registrant applies a discount** → enters promo or affiliate code, clicks Apply, AJAX validates in real-time, discount appears as a line item in the order summary.
4. **Stripe payment**: reduced total sent to Stripe Checkout session.
5. **Non-Stripe payment**: reduced `balance_owed` stored on member record.
6. **Referral completion** → treasurer receives an email notification to issue credit manually; referral log entry created.
7. **Admin reporting** → promo usage table and affiliate referral log (with payout toggle).

### System Architecture
- **Presentation**: registration form partial additions + member portal section + admin page.
- **Domain logic**: `STSRC_Discount_Service` for validation, discount calculation, code generation, and usage recording.
- **Persistence**: two new custom tables (`wp_stsrc_promo_codes`, `wp_stsrc_promo_code_usages`, `wp_stsrc_affiliate_referrals`) + new column on `wp_stsrc_members`.
- **AJAX**: public endpoints for validation (accessible to non-logged-in users); admin-only endpoints for CRUD and reporting.
- **Payments**: hooks into existing Stripe Checkout session creation and non-Stripe submission paths.
- **Notifications**: new treasurer email template using existing `STSRC_Email_Service`.

---

## 2. Project Structure

```
includes/
  database/
    class-stsrc-promo-codes-db.php          (new) — CRUD for wp_stsrc_promo_codes
    class-stsrc-affiliate-referrals-db.php  (new) — CRUD for wp_stsrc_affiliate_referrals
  services/
    class-stsrc-discount-service.php        (new) — validation, calculation, code generation
  api/
    class-stsrc-discount-ajax.php           (new) — AJAX handler for all discount actions

admin/
  pages/
    class-stsrc-promo-codes-page.php        (new) — admin promo codes list + CRUD UI
  partials/
    promo-codes-list.php                    (new) — list/table partial
    promo-codes-form.php                    (new) — add/edit modal form partial
    affiliate-referrals-report.php          (new) — referral log table partial

public/
  partials/
    registration-form.php                   (edit) — add Discounts section before Order Summary
  class-stsrc-member-portal.php             (edit) — add affiliate code display to Membership Info
  js/
    registration.js                         (edit) — discount AJAX, URL param auto-fill, cookie logic
    member-portal.js                        (edit) — copy referral link button
  css/
    smoketree-plugin-public.css             (edit) — discount section styles, toast styles

templates/
  treasurer-referral-credit.php            (new) — treasurer email for referral credit notification

includes/
  class-smoketree-plugin.php               (edit) — register new AJAX hooks, admin page, migrate columns
  class-smoketree-plugin-activator.php     (edit) — run new table creation + affiliate code backfill

admin/partials/settings-form.php           (edit) — add ACF fields for affiliate amounts
```

---

## 3. Feature Specification

### 3.1 Promo Code Management (Admin)

**User story**: As an admin, I can create, edit, deactivate, and delete promotional codes that apply a flat or percentage discount to new member registrations.

**Detailed implementation steps**:
1. Add sub-menu page "Promo Codes" under the `stsrc-dashboard` menu in `class-stsrc-promo-codes-page.php`.
2. Display a paginated table of existing codes: Code Name, Type, Discounts (per-type breakdown), Uses (used/limit), Expires, Status (Active/Inactive), Actions (Edit, Deactivate/Activate, Delete).
3. "Add New Code" button opens an inline modal form (`promo-codes-form.php`) with fields:
   - **Code Name** — text, required, unique, max 50 chars, alphanumeric + hyphens.
   - **Discount Type** — radio: `flat` | `percentage`. Applies uniformly to all membership types.
   - **Discount per Membership Type** — table showing each membership type's name, price (read-only reference), and a discount value input. Types left blank (or 0) are not eligible for this code. At least one type must have a positive value.
   - **Expiration Date** — date picker, optional. If blank, code does not expire.
   - **One-Time Use** — checkbox. If checked, code is globally consumed after a single use (ignores Usage Limit).
   - **Usage Limit** — number, optional (leave blank for unlimited). Disabled when One-Time Use is checked.
   - **Active** — checkbox, default true.
4. Save triggers `wp_ajax_stsrc_create_promo_code` / `wp_ajax_stsrc_update_promo_code`.
5. Delete triggers `wp_ajax_stsrc_delete_promo_code` with confirmation dialog.
6. Deactivate/Activate toggles the `is_active` flag without deleting usage history.

**Error handling and edge cases**:
- Duplicate code name: return 409 with "A code with this name already exists."
- Percentage value out of 1–100: return 422.
- Editing a code that has been used: allow edits to expiry/active/limit, warn that changing value/type affects historical reporting accuracy.
- Deleting a code with existing usages: require explicit confirmation; set `deleted_at` (soft delete) to preserve historical records.

---

### 3.2 Affiliate Code System

**User story**: As an existing active member, I have an automatically assigned affiliate referral code displayed in my portal that I can share to give new members a discount.

**Detailed implementation steps**:
1. Add `affiliate_code` VARCHAR(30) column to `wp_stsrc_members` (nullable, unique).
2. On new member creation (inside `STSRC_Ajax_Handler::register_member` after successful record insert), call `STSRC_Discount_Service::generate_affiliate_code($last_name)`:
   - Sanitize last name: uppercase, strip non-alpha, truncate to 10 chars (e.g., `SMITH`).
   - Generate 4-digit random suffix.
   - Check `wp_stsrc_members.affiliate_code` for collision.
   - Retry up to 20 times; if no unique code found after 20 attempts, use 6-digit suffix.
   - Return formatted code: `REF-{LASTNAME}-{####}` (e.g., `REF-SMITH-4821`).
   - Save to `affiliate_code` column.
3. **Backfill migration**: one-time task run during plugin activation/upgrade for any member where `affiliate_code IS NULL`. Iterate in batches of 100; generate and save codes respecting collision rules.
4. **Member portal display**: in the Membership Information section of `member-portal.php`, add a block:
   - "Your Referral Code: `REF-SMITH-4821`"
   - "Referral Link: `https://smoketree.us/register/?ref=REF-SMITH-4821`"
   - "Copy Referral Link" button: JS clipboard copy → swap button text to "Copied!" for 2 seconds.
5. Only display this block if member `status = 'active'`.

**Error handling and edge cases**:
- If member has no last name, use `MEMBER` as the name segment.
- Codes are case-insensitive on validation (normalize to uppercase on input).
- Inactive/cancelled member's code: validation rejects with "This referral code is no longer active."

---

### 3.3 Referral Link System (URL-Based Flow)

**User story**: As a prospective new member, I receive a referral link and when I visit it, the affiliate code is automatically applied to my registration without me having to type anything.

**Detailed implementation steps**:
1. **URL detection** (in `registration.js` on DOMContentLoaded):
   - Read `URLSearchParams` for `ref` parameter.
   - If present, populate the Affiliate Code input field with the value.
   - Trigger the AJAX validation flow (same as clicking "Apply").
   - Store the code in a cookie (`stsrc_ref_code`, 48-hour expiry, path `/`, `SameSite=Lax`). Cookie set via JS on client; HttpOnly is not feasible for JS-set cookies — use `SameSite=Lax` and `Secure` (when HTTPS).
2. **Cookie fallback** (if `ref` param absent):
   - Check for `stsrc_ref_code` cookie.
   - If present, populate and validate the Affiliate Code field as above.
3. **Lock behavior on successful validation**:
   - Disable/grey-out the Promo Code field and its Apply button with tooltip: "Only one discount can be applied."
   - Show toast/banner: "Referral discount from [Referring Member's Full Name] will be applied at checkout!" — use a fixed-position banner below the sticky header, dismissible.
4. **Manual override**: if the user clears the affiliate code and types a different code, cookie is not updated (cookie represents the original referral intent).
5. **OpenGraph (nice-to-have)**: hook `wp_head` → if `$_GET['ref']` is set and valid, output custom OG meta tags: `og:title = "Join Smoketree Club — [Referrer Name] sent you a discount!"`.

**Error handling and edge cases**:
- Invalid `ref` code in URL: silently fail the auto-apply (show error inline, do not show toast), do not block registration.
- Cookie and URL param both present but different: URL param takes priority.

---

### 3.4 Discount Section in Registration Form

**User story**: As a registrant, I can enter a promo code or an affiliate referral code to receive a discount that is reflected in my order summary before I pay.

**Detailed implementation steps**:
1. Add a "Discounts" `<section>` to `registration-form.php` immediately before the Order Summary section.
2. Layout:
   ```html
   <section id="stsrc-discounts-section">
     <h3>Discounts</h3>
     <div class="stsrc-discount-field" id="promo-code-group">
       <label>Promo Code</label>
       <input type="text" id="stsrc_promo_code" name="promo_code" placeholder="Enter promo code" />
       <button type="button" id="apply-promo-btn">Apply</button>
       <div class="stsrc-discount-feedback" id="promo-feedback"></div>
     </div>
     <div class="stsrc-discount-field" id="affiliate-code-group">
       <label>Referral Code</label>
       <input type="text" id="stsrc_affiliate_code" name="affiliate_code" placeholder="Enter referral code" />
       <button type="button" id="apply-affiliate-btn">Apply</button>
       <div class="stsrc-discount-feedback" id="affiliate-feedback"></div>
     </div>
   </section>
   ```
3. **Apply button behavior** (AJAX):
   - Promo: POST to `wp_ajax_nopriv_stsrc_validate_promo_code` with `{code, membership_type_id, nonce}`.
   - Affiliate: POST to `wp_ajax_nopriv_stsrc_validate_affiliate_code` with `{code, nonce}`.
   - On success: show inline "✓ {Discount Label} — -{amount}" in feedback div; show "Remove" link; add discount line item to order summary; disable the other discount field.
   - On error: show red inline message.
4. **Mutual exclusion**: when one discount is applied, the other field is disabled with `disabled` attribute + greyed CSS + helper text "Only one discount can be applied."
5. **Remove**: clicking "Remove" clears the applied discount, removes the order summary line item, re-enables both fields, clears the cookie (for affiliate).
6. **Order summary line items**:
   - Promo: "Promo: TuckerDay100 — -$100.00" or "Promo: TuckerDay100 — -10%"
   - Affiliate: "Referral Discount — -$500.00"
7. **Hidden fields** added to form on successful apply: `applied_discount_type` (`promo` | `affiliate`), `applied_discount_code`, `applied_discount_amount` (computed dollar value). Server re-validates these before processing.

**Error handling and edge cases**:
- Server always revalidates discount on final form submission; client-supplied amount is ignored for charge calculation.
- If membership type changes after applying a code that is type-restricted, discount is invalidated: show warning.
- Codes must be re-validated server-side if submitted more than 10 minutes after initial AJAX validation (staleness window).

---

### 3.5 Discount Validation Logic

**User story**: The system validates codes accurately, rejecting expired, exhausted, type-mismatched, or inactive codes with clear messaging.

**Promo code validation sequence** (`STSRC_Discount_Service::validate_promo_code`):
1. Code exists in `wp_stsrc_promo_codes` → else "Invalid promo code."
2. `is_active = 1` → else "This promo code is no longer active."
3. `expires_at IS NULL OR expires_at >= NOW()` → else "This promo code has expired."
4. `is_one_time_use = 1`: check `wp_stsrc_promo_code_usages.code_id` usage count = 0 → else "This promo code has already been used."
5. `usage_limit IS NULL OR (SELECT COUNT(*) FROM wp_stsrc_promo_code_usages WHERE code_id = ?) < usage_limit` → else "This promo code's usage limit has been reached."
6. Look up `discount_values[membership_type_id]` → if absent or <= 0: "This promo code is not valid for the selected membership type."
7. Return discount DTO: `{type, value (for this type), computed_dollar_amount, label}`.

**Affiliate code validation sequence** (`STSRC_Discount_Service::validate_affiliate_code`):
1. Normalize code to uppercase.
2. Lookup in `wp_stsrc_members.affiliate_code` → else "Invalid referral code."
3. Member `status = 'active'` → else "This referral code is no longer active."
4. Look up per-type discount from `stsrc_affiliate_type_discounts` option using the selected `membership_type_id` → if absent or <= 0: "No referral discount is configured for the selected membership type."
5. Return referrer DTO: `{member_id, full_name, discount_amount}`.

---

### 3.6 Payment Integration

**Stripe path (card / ACH)**:
1. Before calling `STSRC_Payment_Service::create_checkout_session()`, resolve final discounted total:
   ```
   discounted_total = max(0, base_amount - discount_dollar_amount)
   ```
2. Pass `discounted_total` as the line item amount.
3. Add a second line item for "Discount Applied" with a negative amount (or reduce membership line item directly — use negative line item approach for Stripe clarity).
4. Include metadata: `discount_code`, `discount_type`, `discount_amount`.
5. If `discounted_total = 0.00`: skip Stripe entirely → record registration as `payment_type = 'free'` and finalize immediately.

**Non-Stripe path (Zelle / Check / Pay Later)**:
1. Compute discounted `balance_owed = max(0, base_amount - discount_dollar_amount)`.
2. Store on member record.
3. Payment instructions email reflects the reduced amount.

**Processing fees** recalculated on the discounted subtotal (not original price).

**Error handling**:
- If discount validation fails at submission time (code used by someone else in the interim): return error requiring registrant to remove discount or enter a new code.
- Never store a negative `balance_owed`; floor at `0.00`.

---

### 3.7 Usage Recording

On successful registration completion (after Stripe webhook or non-Stripe submission):
1. Insert row into `wp_stsrc_promo_code_usages` (for promo code) or `wp_stsrc_affiliate_referrals` (for affiliate code).
2. For one-time-use promo codes, the usage count check during revalidation at submission prevents double-use without needing a dedicated lock. For high-concurrency safety, use a DB transaction + re-check before insert.
3. Increment `usage_count` denormalized column on `wp_stsrc_promo_codes` for fast reporting queries (derived from usages table is authoritative; `usage_count` is a cached counter).

---

### 3.8 Affiliate Referral Credit (Treasurer Email)

**User story**: The treasurer receives an email whenever a new member registers using an affiliate code so they can manually issue the referrer's credit.

**Implementation steps**:
1. After successful registration with affiliate code, call `STSRC_Email_Service::send()` with template `treasurer-referral-credit.php`.
2. Recipient: `get_option('stsrc_treasurer_email')`.
3. Template variables:
   - `referrer_name` — referring member's full name
   - `referrer_email` — referring member's email
   - `new_member_name` — new registrant's full name
   - `new_member_email` — new registrant's email
   - `credit_amount` — `get_field('stsrc_affiliate_referrer_credit', 'option') ?: 50`
   - `registration_date` — current date
4. Email subject: "Referral Credit Due — [Referrer Name]".
5. No automatic balance adjustment is made.

---

### 3.9 Admin Reporting & Tracking

**Promo Code Usage Report** (tab or section on the Promo Codes admin page):
- Table columns: Code Name, Type, Value, Total Uses, Total Discount Given ($), Members Who Used It (expandable list with name + date).
- Filters: date range, code name.

**Affiliate Referral Log** (separate tab or sub-section):
- Table columns: Referrer Name, New Member Name, Date, Discount Given to New Member ($), Credit Owed to Referrer ($), Payout Status (Pending / Paid Out), Actions.
- "Mark as Paid" toggle per row — calls `wp_ajax_stsrc_toggle_payout_status`.
- Filter by payout status.

---

## 4. Database Schema

### 4.1 `wp_stsrc_members` (existing — alter)
Add column:
```sql
affiliate_code VARCHAR(30) NULL DEFAULT NULL UNIQUE
```
Add index:
```sql
KEY idx_affiliate_code (affiliate_code)
```

---

### 4.2 `wp_stsrc_promo_codes` (new)
```sql
CREATE TABLE wp_stsrc_promo_codes (
  code_id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code_name          VARCHAR(50)     NOT NULL,
  discount_type      ENUM('flat','percentage') NOT NULL,
  discount_values    TEXT            NOT NULL COMMENT 'JSON object mapping membership_type_id to discount value, e.g. {"1":100,"2":75}',
  expires_at         DATETIME        NULL DEFAULT NULL,
  is_one_time_use    TINYINT(1)      NOT NULL DEFAULT 0,
  usage_limit        INT UNSIGNED    NULL DEFAULT NULL,
  usage_count        INT UNSIGNED    NOT NULL DEFAULT 0,
  is_active          TINYINT(1)      NOT NULL DEFAULT 1,
  deleted_at         DATETIME        NULL DEFAULT NULL,
  created_at         DATETIME        NOT NULL,
  updated_at         DATETIME        NOT NULL,
  PRIMARY KEY (code_id),
  UNIQUE KEY uq_code_name (code_name),
  KEY idx_is_active (is_active),
  KEY idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 4.3 `wp_stsrc_promo_code_usages` (new)
```sql
CREATE TABLE wp_stsrc_promo_code_usages (
  usage_id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code_id            BIGINT UNSIGNED NOT NULL,
  member_id          BIGINT UNSIGNED NOT NULL,
  discount_amount    DECIMAL(10,2)   NOT NULL,
  membership_type_id BIGINT UNSIGNED NOT NULL,
  used_at            DATETIME        NOT NULL,
  PRIMARY KEY (usage_id),
  KEY idx_code_id (code_id),
  KEY idx_member_id (member_id),
  UNIQUE KEY uq_member_code (member_id, code_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
Note: `uq_member_code` prevents the same member from using the same code twice (belt-and-suspenders alongside one-time-use logic).

---

### 4.4 `wp_stsrc_affiliate_referrals` (new)
```sql
CREATE TABLE wp_stsrc_affiliate_referrals (
  referral_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referral_code      VARCHAR(30)     NOT NULL,
  referrer_member_id BIGINT UNSIGNED NOT NULL,
  new_member_id      BIGINT UNSIGNED NOT NULL,
  new_member_discount DECIMAL(10,2)  NOT NULL,
  referrer_credit    DECIMAL(10,2)   NOT NULL,
  payout_status      ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  paid_at            DATETIME        NULL DEFAULT NULL,
  paid_by_user_id    BIGINT UNSIGNED NULL DEFAULT NULL,
  referred_at        DATETIME        NOT NULL,
  PRIMARY KEY (referral_id),
  KEY idx_referrer (referrer_member_id),
  KEY idx_new_member (new_member_id),
  KEY idx_payout_status (payout_status),
  UNIQUE KEY uq_new_member (new_member_id) COMMENT 'One referral per new registration'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 5. Server Actions

### 5.1 Database Actions

**`STSRC_Promo_Codes_DB`**
- `create_code(array $data): int|WP_Error` — insert row, return `code_id`.
- `update_code(int $code_id, array $data): bool|WP_Error` — update row.
- `soft_delete_code(int $code_id): bool` — set `deleted_at`.
- `get_code_by_name(string $name): object|null` — for validation (case-insensitive).
- `get_all_codes(array $filters = []): array` — for admin list.
- `increment_usage_count(int $code_id): void` — atomic increment.
- `record_usage(int $code_id, int $member_id, float $discount_amount, int $type_id): int` — insert into usages table.
- `get_usage_report(array $filters = []): array` — joined query for reporting.

SQL for validation:
```sql
SELECT * FROM wp_stsrc_promo_codes
WHERE code_name = %s
  AND is_active = 1
  AND deleted_at IS NULL
LIMIT 1;
```

**`STSRC_Affiliate_Referrals_DB`**
- `create_referral(array $data): int|WP_Error`
- `update_payout_status(int $referral_id, string $status, int $admin_user_id): bool`
- `get_referral_log(array $filters = []): array` — joined with members table.
- `get_by_new_member_id(int $member_id): object|null` — idempotency check.

**`STSRC_Discount_Service`** (service, not DB class)
- `validate_promo_code(string $code, int $membership_type_id): array|WP_Error` — returns `{code_id, type, value, computed_amount, label}`.
- `validate_affiliate_code(string $code): array|WP_Error` — returns `{referrer_member_id, referrer_name, discount_amount}`.
- `compute_discounted_total(float $base, string $discount_type, float $discount_value): float` — handles percentage rounding and floor at 0.
- `generate_affiliate_code(string $last_name): string` — generates unique REF-LASTNAME-#### code.
- `backfill_affiliate_codes(): array` — batch process, returns `{processed, skipped, errors[]}`.
- `record_discount_usage(int $member_id, array $discount_payload): void` — writes to appropriate table and sends treasurer email if affiliate.

---

### 5.2 AJAX Actions

**Public (nopriv + priv)**

| AJAX Action | Handler | Description |
|---|---|---|
| `stsrc_validate_promo_code` | `STSRC_Discount_Ajax::validate_promo_code` | Validate promo code, return discount details or error |
| `stsrc_validate_affiliate_code` | `STSRC_Discount_Ajax::validate_affiliate_code` | Validate affiliate code, return referrer name + discount or error |

Request payload for `stsrc_validate_promo_code`:
```json
{
  "nonce": "...",
  "code": "TuckerDay100",
  "membership_type_id": 3
}
```
Success response:
```json
{
  "success": true,
  "data": {
    "code_id": 7,
    "discount_type": "flat",
    "discount_value": 100,
    "computed_amount": 100.00,
    "label": "Promo: TuckerDay100 — -$100.00"
  }
}
```

Request payload for `stsrc_validate_affiliate_code`:
```json
{
  "nonce": "...",
  "code": "REF-SMITH-4821"
}
```
Success response:
```json
{
  "success": true,
  "data": {
    "referrer_member_id": 42,
    "referrer_name": "Jane Smith",
    "discount_amount": 500.00,
    "label": "Referral Discount — -$500.00"
  }
}
```

**Admin-only AJAX**

| AJAX Action | Handler | Description |
|---|---|---|
| `stsrc_create_promo_code` | `STSRC_Discount_Ajax::create_promo_code` | Create new promo code |
| `stsrc_update_promo_code` | `STSRC_Discount_Ajax::update_promo_code` | Update existing promo code |
| `stsrc_delete_promo_code` | `STSRC_Discount_Ajax::delete_promo_code` | Soft-delete promo code |
| `stsrc_toggle_payout_status` | `STSRC_Discount_Ajax::toggle_payout_status` | Toggle affiliate payout pending/paid |

All admin actions verify `current_user_can('manage_options')` and `check_ajax_referer('stsrc_admin_nonce')`.

---

### 5.3 Registration Form Integration

In `STSRC_Ajax_Handler::register_member()`, after input validation and before member record creation:

1. If `applied_discount_type` is set in POST data:
   - Re-validate the code server-side (call `STSRC_Discount_Service::validate_promo_code` or `::validate_affiliate_code`).
   - If validation fails: return error — do not proceed with registration.
2. Compute `$discounted_total = STSRC_Discount_Service::compute_discounted_total($base_price, $type, $value)`.
3. Proceed with member record creation using `$discounted_total` for `balance_owed` / Stripe session amount.
4. After successful registration, call `STSRC_Discount_Service::record_discount_usage(...)`.

---

### 5.4 External Integrations

**Stripe Checkout Session** (modification to `STSRC_Payment_Service::create_checkout_session`):
- Accept optional `$discount_data` parameter.
- If discount present, reduce line item unit amount.
- Add metadata: `discount_code`, `discount_type`, `discount_amount`.
- If net total is `0.00`, skip session creation and return a `free_registration` flag.

**WordPress Cookie** (JS-only, no server-side PHP cookie):
- `document.cookie = 'stsrc_ref_code=REF-SMITH-4821; max-age=172800; path=/; SameSite=Lax'`
- Read via `document.cookie` parsing on page load.
- Clear on Remove: `document.cookie = 'stsrc_ref_code=; max-age=0; path=/'`

---

## 6. Design System

### 6.1 Visual Style

**Color palette** (consistent with existing portal tokens):
- Primary green: `#1F4D3A`
- Secondary green: `#2E7D5B`
- Success state: `#166534` on `#DCFCE7`
- Error state: `#991B1B` on `#FEE2E2`
- Warning / info: `#92400E` on `#FEF3C7`
- Neutral text: `#1F2937`
- Border/background: `#E5E7EB`, `#F9FAFB`
- Disabled field: `#9CA3AF` text on `#F3F4F6` background

**Typography**:
- Section heading: 18px / weight 600
- Field label: 14px / weight 500
- Feedback text: 13px / weight 400

**Discount section component states**:
- **Default**: white input, green Apply button (matches existing form button style).
- **Success applied**: input border `#166534`, green checkmark icon, feedback text green, "Remove" link in red-400.
- **Disabled (other field)**: input + button `opacity: 0.5`, `cursor: not-allowed`, helper text below.
- **Error**: input border `#EF4444`, feedback text `#991B1B`.

**Toast / Referral banner**:
- Fixed banner below header (not a floating toast).
- Background: `#DCFCE7`, border-left: `4px solid #166534`.
- Dismissible with ✕ button.
- Text: "Referral discount from [Name] will be applied at checkout!"

**Order summary discount line item**:
- Same row styling as existing line items.
- Amount displayed in green with `-` prefix.
- Discount row appears immediately below membership type row.

**Spacing**: 8px base scale; 16px gap between promo and affiliate fields; 24px top margin for Discounts section.

**Admin page**:
- Matches existing `stsrc-access-codes` table pattern (WP List Table style with custom modal).
- Status badge: green "Active" / grey "Inactive" pill.

---

## 8. Authentication & Authorization

- **Public AJAX** (`stsrc_validate_promo_code`, `stsrc_validate_affiliate_code`): registered with `wp_ajax_nopriv_` — no login required. Both verify `check_ajax_referer('stsrc_registration_nonce', 'nonce')`.
- **Admin CRUD AJAX**: registered with `wp_ajax_` only. Verify `current_user_can('manage_options')` and `check_ajax_referer('stsrc_admin_nonce')`.
- **Payout toggle**: admin-only, same capability check.
- **Server-side revalidation**: discount applied at registration never trusts client-computed amount. Amount is always recomputed server-side from the validated code.
- **Affiliate code display in portal**: only rendered when `$member->status === 'active'`.

---

## 9. Data Flow

### Server/Client Data Passing

**Registration page load**:
- PHP outputs `wp_localize_script('stsrc-registration', 'stsrc_registration', {...})` including:
  - `ajax_url`, `nonce`, `membership_type_id` (updated on type selection)
  - `ref_cookie_name: 'stsrc_ref_code'`
  - `affiliate_discount_label: 'Referral Discount'`

**AJAX validation flow**:
```
[User clicks Apply / page auto-applies ?ref param]
  → JS POSTs {nonce, code, membership_type_id} to AJAX endpoint
  → Server validates and returns discount DTO or WP_Error
  → JS updates order summary, disables other field, sets hidden inputs
```

**Registration submission**:
```
[User submits form]
  → Hidden fields: applied_discount_type, applied_discount_code, applied_discount_amount
  → Server: re-validates code from scratch
  → Computes discounted total
  → Creates Stripe session (if card/ACH) with reduced amount, OR stores balance_owed (if Zelle/Check)
  → On success: records usage, sends treasurer email (affiliate only)
```

### State Management
- Client state: ephemeral discount state in JS module variables (code, type, computed amount). Reset on Remove.
- Server state: authoritative for all pricing and validation. Client state is advisory only.
- Hidden form fields bridge client discount state to server for revalidation context (not for trusted amounts).

---

## 10. Stripe Integration

### Checkout Session Modification
- Extend `STSRC_Payment_Service::create_checkout_session()` with optional `$discount_data`:
  ```php
  if ($discount_data) {
      $line_item_amount = max(0, $base_amount_cents - intval($discount_data['amount'] * 100));
      // Add discount line item with negative unit_amount as separate Stripe line_item
  }
  ```
- Add discount metadata to session:
  ```php
  'metadata' => [
      'discount_code'   => $discount_data['code'],
      'discount_type'   => $discount_data['type'],
      'discount_amount' => $discount_data['amount'],
  ]
  ```
- Processing fee is recalculated on the discounted subtotal before adding to session.

### Webhook Handling
- No new webhook events are required for discounts.
- `checkout.session.completed` handler (existing): after finalizing registration, check `metadata.discount_code`. If present, call `STSRC_Discount_Service::record_discount_usage()` (idempotent — check `wp_stsrc_promo_code_usages` / `wp_stsrc_affiliate_referrals` for existing entry by `new_member_id` before inserting).

### Free Registration ($0 Total)
- If discounted total is `$0.00`, bypass Stripe entirely.
- Set member `payment_type = 'promo_free'` or `'referral_free'`.
- Set `balance_owed = 0.00`.
- Call `record_discount_usage()` immediately.
- Send standard welcome email.

### Product/Price Configuration
- Discounts applied as dynamic line item reductions on the existing membership price.
- No static Stripe coupon objects are used — discount logic is managed entirely in plugin.
- Negative line item approach for audit clarity:
  - Line 1: `Smoketree Club Membership — ${original_price}`
  - Line 2 (if discount): `Discount: {code_name} — -${discount_amount}`
  - Line 3 (if fee): `Processing Fee — ${fee}`

---

## ACF Configuration Fields

Add to existing settings form (`admin/partials/settings-form.php`):

| Field Key | Type | Default | Description |
|---|---|---|---|
| `stsrc_affiliate_type_discounts` | JSON (Text) | `{}` | JSON object mapping `membership_type_id` to dollar discount for new members using a referral code, e.g. `{"1":100,"2":75,"3":50,"4":25}`. Types without an entry receive no referral discount. |
| `stsrc_affiliate_referrer_credit` | Number | 50 | Dollar credit owed to the referring member (for treasurer) |

The settings form renders a dynamic table for `stsrc_affiliate_type_discounts` showing each membership type's name, price, and discount input. `stsrc_affiliate_referrer_credit` uses the existing `get_field($key, 'option') ?: $default` fallback pattern.

---

## Implementation Notes

1. **Naming disambiguation**: `wp_stsrc_promo_codes` is distinct from `wp_stsrc_access_codes`. UI labels and variable names must not use the word "access code" for the new promo feature. Use "promo code" and "referral code" consistently.
2. **Backfill migration**: run via `register_activation_hook` and guarded by a version flag (`stsrc_affiliate_code_backfill_done` option). Mark complete after first successful run.
3. **Percentage rounding**: `round($base_price * ($percent / 100), 2)` — round half up to nearest cent.
4. **One-time use concurrency**: use a DB transaction with `SELECT ... FOR UPDATE` on the `wp_stsrc_promo_code_usages` table before inserting to prevent double-use under concurrent submissions.
5. **Cookie security**: `SameSite=Lax; Secure` (when HTTPS). 48-hour TTL. Name: `stsrc_ref_code`.
6. **Promo codes are registration-only**: no validation path touches the renewal flow. Discount fields should not render in `renewal-section.php`.
7. **Affiliate codes are reusable**: the unique constraint on `wp_stsrc_affiliate_referrals.new_member_id` (not on `referral_code`) means any number of new members can use the same referral code, but each new member can only have one referral record.
