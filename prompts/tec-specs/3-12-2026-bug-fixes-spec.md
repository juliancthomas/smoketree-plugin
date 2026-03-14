# Smoketree Plugin — Bug Fixes & Admin Enhancements Technical Specification

<specification_planning>

## Analysis

### 1. Core System Architecture and Key Workflows

The plugin is a WordPress membership management system with these layers:
- **Admin UI**: PHP partials rendered by page classes in `admin/pages/`
- **AJAX API**: Central handler at `includes/api/class-stsrc-ajax-handler.php` (~3k+ lines)
- **Database layer**: Dedicated DB classes per entity in `includes/database/`
- **Services**: Business logic in `includes/services/`
- **Public/Registration**: PHP partials at `public/partials/registration-form.php` with JS-driven family member UI

Key workflows affected by this project:
1. Admin member edit → status management + soft delete
2. Public registration → payment plan visibility, family member UX, rollback safety, email reuse guard

### 2. Database Schema Changes

Two tables need a `status` column added:
- `wp_stsrc_family_members` — add `status VARCHAR(20) DEFAULT 'active'`
- `wp_stsrc_extra_members` — add `status VARCHAR(20) DEFAULT 'active'`
Also need a `deleted` status value added to `wp_stsrc_members.status` (existing enum values: active, inactive, cancelled, pending).

Migration must use `dbDelta` or direct `ALTER TABLE` via the existing migration infrastructure.

### 3. Feature Breakdown

**Feature 1: Add "Inactive" Status** — Low complexity. Two dropdown changes.

**Feature 2: Soft Delete** — High complexity. Involves:
- DB migration (2 new columns)
- New AJAX action
- Admin UI changes (button + modal)
- Query updates across multiple DB classes
- Members list filter changes
- Bulk action addition

**Feature 3: Hide "Pay Later"** — Medium complexity. PHP template change + JS change + server-side validation.

**Feature 4: Registration Rollback** — High complexity. Requires wrapping member creation in try/catch with full rollback steps, Stripe customer deletion, and logging.

**Feature 5: Family Members UX Overhaul** — Medium complexity. JS-driven pre-rendering on membership selection + client and server validation changes.

**Feature 6: Soft-Deleted Email Restore** — High complexity. New token flow, new email template, new AJAX/init handler, portal UI for family/extra member restoration.

### 4. Key Files to Modify

| File | Changes |
|------|---------|
| `admin/partials/member-edit.php` | Add Inactive to status dropdown; add Delete button + confirmation modal |
| `admin/partials/members-list.php` | Add Inactive to filter; add Deleted filter toggle; add Deleted to bulk actions |
| `includes/api/class-stsrc-ajax-handler.php` | New `stsrc_soft_delete_member` action; update `register_member`; add restore-account handler; update pay-later validation |
| `includes/database/class-stsrc-database.php` | Migration for new `status` columns |
| `includes/database/class-stsrc-family-member-db.php` | Add status filter to all reads; add soft-delete write |
| `includes/database/class-stsrc-extra-member-db.php` | Same as above |
| `public/partials/registration-form.php` | Pass `payment_plan_enabled`; hide Pay Later; overhaul family member section |
| `public/class-smoketree-plugin-public.php` or template loader | Pass `payment_plan_enabled` to registration template |
| `public/templates/member-portal.php` (and portal partial) | Show deleted family/extra members with restore option |
| `templates/` | New `restore-account.php` email template |
| `admin/pages/class-stsrc-members-page.php` | Show-Deleted toggle, filter query update |
| `admin/js/smoketree-plugin-admin.js` | Delete confirmation modal JS |
| `public/js/` (registration JS, likely in registration-form.php `<script>`) | Family member pre-render on plan selection; validation changes |

### 5. Potential Challenges

- **Rollback atomicity**: WordPress doesn't natively support DB transactions in all table operations — need to use `$wpdb->query('START TRANSACTION')` or sequential deletes with logging.
- **Stripe customer delete**: Stripe's `Customer::delete()` is idempotent but only if a customer was actually created; need to check before calling.
- **Restore flow WP user creation**: Must check if a WP user with the same email already exists before creating.
- **Family member index re-numbering**: Changing from dynamic add-only to pre-rendered fields requires ensuring the JS `reindexMembers()` still works for the remove cases on optional fields 3 & 4.
- **Backward compatibility for queries**: All existing `get` methods on family/extra DB classes must default to `status = 'active'` without breaking callers.

</specification_planning>

---

## 1. System Overview

### Core Purpose
A focused collection of bug fixes and small feature additions to the Smoketree Swim & Recreation Club WordPress membership plugin. Changes address admin member management, registration form integrity, and data lifecycle safeguards.

### Key Workflows Affected
1. **Admin member lifecycle** — status management (adding `inactive`, `deleted`), soft-delete with cascading related-record handling
2. **Public registration** — payment option visibility, family member pre-rendering, full rollback on failure, soft-deleted email detection
3. **Member portal** — surface previously deleted family/extra members for selective restoration

### System Architecture
The plugin follows a layered MVC-style architecture within WordPress:
- **Presentation**: PHP partials + JS in `admin/partials/` and `public/partials/`
- **AJAX API**: `includes/api/class-stsrc-ajax-handler.php` handles all `wp_ajax_*` actions
- **Database**: Per-entity DB classes in `includes/database/`
- **Services**: Business logic classes in `includes/services/`
- **Email**: Templates in `templates/`, sent via `class-stsrc-email-service.php`

---

## 2. Project Structure

Only files requiring changes are listed; the rest of the plugin structure remains unchanged.

```
smoketree-plugin/
├── admin/
│   ├── js/
│   │   └── smoketree-plugin-admin.js          [MODIFIED] — delete-member modal JS
│   └── partials/
│       ├── member-edit.php                    [MODIFIED] — Inactive status, Delete button + modal
│       └── members-list.php                   [MODIFIED] — Inactive filter, Deleted toggle + bulk
│
├── includes/
│   ├── api/
│   │   └── class-stsrc-ajax-handler.php       [MODIFIED] — soft-delete action, rollback, restore
│   └── database/
│       ├── class-stsrc-database.php           [MODIFIED] — migration for status columns
│       ├── class-stsrc-family-member-db.php   [MODIFIED] — status filter, soft-delete write
│       └── class-stsrc-extra-member-db.php    [MODIFIED] — status filter, soft-delete write
│
├── public/
│   ├── class-smoketree-plugin-public.php      [MODIFIED] — pass payment_plan_enabled to form
│   └── partials/
│       ├── registration-form.php              [MODIFIED] — Pay Later visibility, family UX
│       └── family-members.php                 [MODIFIED if separate] — portal restore UI
│
├── public/templates/
│   └── member-portal.php                      [MODIFIED] — deleted family/extra restore section
│
└── templates/
    └── restore-account.php                    [NEW] — "Restore Your Account" email template
```

---

## 3. Feature Specifications

### 3.1 Admin — Add "Inactive" Status to Member Edit Dropdown

**User story**: As an admin, I can set a member's status to `inactive` via the edit page and filter the members list by `inactive`.

**Implementation steps**:

1. In `admin/partials/member-edit.php`, locate the `<select name="status">` element.
2. Add `<option value="inactive" <?php selected($member->status, 'inactive'); ?>>Inactive</option>` alongside existing options (`active`, `pending`, `cancelled`).
3. In `admin/partials/members-list.php`, locate the status filter `<select>` (used for filtering the list).
4. Add `<option value="inactive">Inactive</option>` to that dropdown.

**Edge cases**: No business logic changes needed — `inactive` is already a recognized status value in the DB and handled by existing update flows.

---

### 3.2 Admin — Soft Delete Member from Edit Page

**User story**: As an admin, I can soft-delete a member from the edit page, which sets the member and all associated records to `deleted` status and removes the WordPress user account, with a confirmation modal explaining exactly what will be affected.

#### 3.2.1 Database Migration — Add `status` to `stsrc_family_members` and `stsrc_extra_members`

In `includes/database/class-stsrc-database.php`, add a migration method called `add_status_columns_to_related_tables()`:

```php
public function add_status_columns_to_related_tables() {
    global $wpdb;
    $family_table = $wpdb->prefix . 'stsrc_family_members';
    $extra_table  = $wpdb->prefix . 'stsrc_extra_members';

    $family_col = $wpdb->get_results("SHOW COLUMNS FROM `{$family_table}` LIKE 'status'");
    if (empty($family_col)) {
        $wpdb->query("ALTER TABLE `{$family_table}` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active'");
    }

    $extra_col = $wpdb->get_results("SHOW COLUMNS FROM `{$extra_table}` LIKE 'status'");
    if (empty($extra_col)) {
        $wpdb->query("ALTER TABLE `{$extra_table}` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active'");
    }
}
```

Call this method from the plugin activation hook AND from the migration admin page so it can be run manually. The migration admin page is `admin/pages/class-stsrc-migration-page.php`.

#### 3.2.2 DB Class Updates — Default `status = 'active'` Filter

**`includes/database/class-stsrc-family-member-db.php`**:

- All `get_by_member_id()` and similar read methods must append `AND status = 'active'` to their `WHERE` clauses.
- Add a new method `get_all_by_member_id_including_deleted($member_id)` (no status filter) for use by the soft-delete action and the member portal restore flow.
- Add method `soft_delete_by_member_id($member_id)`:
  ```php
  public function soft_delete_by_member_id($member_id) {
      global $wpdb;
      return $wpdb->update(
          $wpdb->prefix . 'stsrc_family_members',
          ['status' => 'deleted'],
          ['member_id' => $member_id],
          ['%s'],
          ['%d']
      );
  }
  ```
- Add method `restore($family_member_id)` for portal-side restoration:
  ```php
  public function restore($family_member_id) {
      global $wpdb;
      return $wpdb->update(
          $wpdb->prefix . 'stsrc_family_members',
          ['status' => 'active'],
          ['id' => $family_member_id],
          ['%s'],
          ['%d']
      );
  }
  ```

**`includes/database/class-stsrc-extra-member-db.php`**: Apply identical changes.

#### 3.2.3 AJAX Action — `stsrc_soft_delete_member`

Add to `includes/api/class-stsrc-ajax-handler.php`:

- Hook: `add_action('wp_ajax_stsrc_soft_delete_member', [$this, 'soft_delete_member']);`
- Method: `soft_delete_member()`

**Implementation steps**:

1. Verify nonce (`stsrc_admin_nonce`).
2. Verify current user has `manage_options` capability.
3. Sanitize and validate `$member_id` from `$_POST`.
4. Fetch the member record; return error if not found or already `deleted`.
5. Count affected family members and extra members (`get_all_by_member_id_including_deleted` is NOT used here — use `get_by_member_id` to count only active ones being deleted).
6. Soft-delete the primary member: `$wpdb->update(stsrc_members, ['status' => 'deleted'], ['member_id' => $id])`.
7. Soft-delete family members: `STSRC_Family_Member_DB::soft_delete_by_member_id($member_id)`.
8. Soft-delete extra members: `STSRC_Extra_Member_DB::soft_delete_by_member_id($member_id)`.
9. Delete the WordPress user: `wp_delete_user($member->user_id)`. If `user_id` is 0 or null, skip gracefully.
10. Log the action via `STSRC_Activity_Log` or `STSRC_Logger`.
11. Return JSON success with message.

**Input**: `{ member_id: int, nonce: string }`
**Output**: `{ success: true, message: "Member deleted." }` or `{ success: false, message: "..." }`

#### 3.2.4 Admin UI — Delete Button + Confirmation Modal

**`admin/partials/member-edit.php`** — add to the form footer, clearly separated from the Save button:

```html
<!-- Positioned in its own row, right-aligned, away from Save -->
<div class="stsrc-danger-zone">
    <button type="button" id="stsrc-delete-member-btn" class="button button-danger"
        data-member-id="<?php echo esc_attr($member_id); ?>"
        data-family-count="<?php echo esc_attr($family_count); ?>"
        data-extra-count="<?php echo esc_attr($extra_count); ?>"
        data-member-name="<?php echo esc_attr($member_first . ' ' . $member_last); ?>"
        data-has-wp-user="<?php echo esc_attr($member->user_id ? '1' : '0'); ?>">
        Delete Member
    </button>
</div>

<!-- Confirmation Modal -->
<div id="stsrc-delete-member-modal" style="display:none;">
    <div class="stsrc-modal-backdrop"></div>
    <div class="stsrc-modal-content">
        <h2>Confirm Member Deletion</h2>
        <p>This will permanently affect the following records:</p>
        <ul id="stsrc-delete-summary-list"></ul>
        <p><strong>This action cannot be undone.</strong></p>
        <div class="stsrc-modal-actions">
            <button id="stsrc-confirm-delete-btn" class="button button-danger">Yes, Delete Member</button>
            <button id="stsrc-cancel-delete-btn" class="button">Cancel</button>
        </div>
    </div>
</div>
```

`$family_count` and `$extra_count` are computed in the page controller before rendering the partial by calling the respective DB classes.

**`admin/js/smoketree-plugin-admin.js`** — add modal logic:

1. On `#stsrc-delete-member-btn` click:
   - Read `data-*` attributes to build the summary list (member name, N family members, N extra members, WordPress user if `data-has-wp-user="1"`).
   - Populate `#stsrc-delete-summary-list` with that content.
   - Show `#stsrc-delete-member-modal`.
2. On `#stsrc-cancel-delete-btn` click: hide modal.
3. On `#stsrc-confirm-delete-btn` click:
   - Disable button, show spinner.
   - `$.post(ajaxurl, { action: 'stsrc_soft_delete_member', member_id, nonce })`.
   - On success: redirect to members list with `?deleted=1` query param to show a success notice.
   - On error: show error message inside the modal.

#### 3.2.5 Members List — Deleted Status Handling

**`admin/partials/members-list.php`**:

1. Add `<option value="deleted">Deleted</option>` to the bulk status change `<select>` (used by the bulk action form).
2. Add a "Show Deleted Members" checkbox or toggle filter near the existing status filter.

**`admin/pages/class-stsrc-members-page.php`** (or wherever the query is built):

1. By default, add `AND status != 'deleted'` (or `AND status IN ('active','inactive','pending','cancelled')`) to the members query `WHERE` clause.
2. When the "Show Deleted" toggle is active (pass via GET param `show_deleted=1`), remove that filter to include deleted members.

---

### 3.3 Registration — Hide "Pay Later" When Payment Plan Disabled

**User story**: As a prospective member, I only see payment options that are currently enabled by the club.

#### 3.3.1 Pass Setting to Template

In `public/class-smoketree-plugin-public.php` (or the method that renders the registration page template), before including `public/partials/registration-form.php`:

```php
$payment_plan_enabled = get_option('stsrc_payment_plan_enabled', '0') === '1';
// Pass to template scope:
include plugin_dir_path(__FILE__) . 'partials/registration-form.php';
// $payment_plan_enabled is available in the partial's scope
```

If the template is included via a shortcode callback or a page template class, ensure `$payment_plan_enabled` is extracted into scope before the include.

#### 3.3.2 Hide in Template

In `public/partials/registration-form.php`, wrap the "Pay Later" radio option:

```php
<?php if ($payment_plan_enabled) : ?>
<label class="stsrc-payment-option">
    <input type="radio" name="payment_type" value="pay_later">
    <span>Pay Later</span>
</label>
<?php endif; ?>
```

If "Pay Later" is inside a JS-rendered section, ensure the PHP guard outputs its presence/absence before JS runs, not via JS toggling.

#### 3.3.3 Server-Side Validation

In `class-stsrc-ajax-handler.php`, inside `register_member()`, after the payment type is read from `$_POST`:

```php
if ($payment_type === 'pay_later') {
    $plan_enabled = get_option('stsrc_payment_plan_enabled', '0') === '1';
    if (!$plan_enabled) {
        wp_send_json_error(['message' => 'Pay Later is not currently available.']);
        return;
    }
}
```

---

### 3.4 Registration — Prevent Email Lockout on Full Rollback

**User story**: If any step after member account creation fails during registration, the system completely removes all partially-created records so the user can register again with the same email.

#### 3.4.1 Rollback Helper

Add a private method `rollback_registration(array $context)` to `class-stsrc-ajax-handler.php`:

```
Parameters:
  $context = [
    'member_id'          => int|null,
    'wp_user_id'         => int|null,
    'stripe_customer_id' => string|null,
    'family_member_ids'  => int[],   // IDs of any family members already inserted
  ]
```

**Steps** (execute in order, catch each independently and log failures):

1. If `family_member_ids` is non-empty: delete each from `wp_stsrc_family_members` by ID.
2. If `member_id` is set: delete from `wp_stsrc_members`.
3. If `wp_user_id` is set: `wp_delete_user($wp_user_id)`.
4. If `stripe_customer_id` is set: call Stripe `Customer::delete($stripe_customer_id)` wrapped in try/catch.
5. Log each rollback step with `STSRC_Logger::log("Registration rollback: {step}", 'warning')`.

#### 3.4.2 Wrap Registration Flow

In `register_member()`, restructure the post-account-creation steps to pass context into `rollback_registration()` on any failure:

```php
$rollback_context = [
    'member_id'          => null,
    'wp_user_id'         => null,
    'stripe_customer_id' => null,
    'family_member_ids'  => [],
];

// Step: Create WP user
$wp_user_id = wp_create_user(...);
if (is_wp_error($wp_user_id)) {
    wp_send_json_error(['message' => '...']);
    return;
}
$rollback_context['wp_user_id'] = $wp_user_id;

// Step: Insert member record
$member_id = $member_db->create_member([...]);
if (!$member_id) {
    $this->rollback_registration($rollback_context);
    wp_send_json_error(['message' => '...']);
    return;
}
$rollback_context['member_id'] = $member_id;

// Step: Insert family members
foreach ($family_members as $fm) {
    $fm_id = $family_db->insert($member_id, $fm);
    if ($fm_id) {
        $rollback_context['family_member_ids'][] = $fm_id;
    }
}

// Step: Create Stripe customer (if applicable)
if ($needs_stripe) {
    try {
        $customer = \Stripe\Customer::create([...]);
        $rollback_context['stripe_customer_id'] = $customer->id;
    } catch (\Exception $e) {
        $this->rollback_registration($rollback_context);
        wp_send_json_error(['message' => 'Payment setup failed. Please try again.']);
        return;
    }
}

// Step: Create Stripe Checkout Session
try {
    $session = \Stripe\Checkout\Session::create([...]);
} catch (\Exception $e) {
    $this->rollback_registration($rollback_context);
    wp_send_json_error(['message' => 'Could not create checkout. Please try again.']);
    return;
}
```

---

### 3.5 Registration — Family Members UX Overhaul

**User story**: When a Household or Duo membership is selected, the correct number of family member fields are pre-rendered and enforced without requiring the user to click "Add."

#### 3.5.1 Client-Side Pre-Rendering

In `public/partials/registration-form.php` (or the inline `<script>` block at the bottom), update the membership card selection handler:

**On membership card click** (where `data-allows-family="1"` is detected):

```javascript
function renderFamilyMembersForPlan(familyLimit) {
    const container = document.getElementById('stsrc-family-members-container');
    container.innerHTML = ''; // clear existing

    const lockedCount = familyLimit === 4 ? 2 : 1; // Household: 2 locked, Duo: 1 locked
    const totalToRender = familyLimit; // Household: 4, Duo: 1

    for (let i = 1; i <= totalToRender; i++) {
        const isLocked = i <= lockedCount;
        const html = buildFamilyMemberHtml(i, isLocked);
        container.insertAdjacentHTML('beforeend', html);
    }

    // Hide "Add Family Member" button — pre-rendered replaces it
    document.getElementById('stsrc-add-family-member').style.display = 'none';
}

function buildFamilyMemberHtml(num, isLocked) {
    const removeBtn = !isLocked
        ? `<button type="button" class="stsrc-remove-family-member stsrc-btn-remove">Remove</button>`
        : '';
    return `
        <div class="stsrc-family-member-item" data-index="${num}">
            <h3>Family Member ${num}</h3>
            <div class="stsrc-form-row">
                <div class="stsrc-form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="family_members[${num}][first_name]" required>
                </div>
                <div class="stsrc-form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="family_members[${num}][last_name]" required>
                </div>
            </div>
            <div class="stsrc-form-group">
                <label>Email (optional)</label>
                <input type="email" name="family_members[${num}][email]">
            </div>
            ${removeBtn}
        </div>
    `;
}
```

When a non-family membership is selected, clear `#stsrc-family-members-container` and restore the "Add Family Member" button to its default state (if previously hidden).

The existing `reindexMembers()` function should continue to work for the remove-button cases (fields 3 and 4 in Household mode).

#### 3.5.2 Client-Side Validation

In the form submit handler (before AJAX call):

```javascript
function validateFamilyMembers(familyLimit) {
    if (familyLimit === 0) return true;
    const minRequired = familyLimit === 4 ? 2 : 1; // Household: 2, Duo: 1
    const items = document.querySelectorAll('.stsrc-family-member-item');
    let filledCount = 0;

    items.forEach(item => {
        const fn = item.querySelector('input[name*="[first_name]"]').value.trim();
        const ln = item.querySelector('input[name*="[last_name]"]').value.trim();
        if (fn && ln) filledCount++;
    });

    if (filledCount < minRequired) {
        showFamilyValidationError(
            `Please add at least ${minRequired} family member${minRequired > 1 ? 's' : ''} with first and last name.`
        );
        scrollToFamilySection();
        return false;
    }
    return true;
}

function showFamilyValidationError(message) {
    const el = document.getElementById('stsrc-family-validation-error');
    el.textContent = message;
    el.style.display = 'block';
}

function scrollToFamilySection() {
    document.getElementById('stsrc-family-members-section')
        .scrollIntoView({ behavior: 'smooth', block: 'start' });
}
```

Add `<div id="stsrc-family-validation-error" class="stsrc-error-message" style="display:none;"></div>` above the family members container in the template.

#### 3.5.3 Server-Side Validation

In `register_member()` in `class-stsrc-ajax-handler.php`, after reading family members from `$_POST`:

```php
$family_limit = intval($membership_type->family_limit ?? 0);

if ($family_limit >= 1) {
    $min_required = ($family_limit === 4) ? 2 : 1;
    $valid_family = array_filter($family_members, function($fm) {
        return !empty(trim($fm['first_name'])) && !empty(trim($fm['last_name']));
    });
    if (count($valid_family) < $min_required) {
        wp_send_json_error([
            'message' => "This membership requires at least {$min_required} family member(s)."
        ]);
        return;
    }
}
```

---

### 3.6 Registration — Soft-Deleted Email Reuse ("Restore Your Account")

**User story**: If a user attempts to register with an email belonging to a previously soft-deleted member, they receive a secure link to restore their account rather than getting an error or duplicate record.

#### 3.6.1 Detection During Registration

In `register_member()`, when checking if the email already exists (before creating any records):

```php
$existing = $member_db->get_by_email($email);
if ($existing) {
    if ($existing->status === 'deleted') {
        // Soft-deleted member — trigger restore flow
        $this->send_restore_account_email($existing);
        wp_send_json_error([
            'message' => 'We found a previous account with this email. A restoration link has been sent to your inbox.',
            'code'    => 'restore_sent',
        ]);
        return;
    }
    // Existing active/pending/cancelled member — existing error handling
    wp_send_json_error(['message' => 'An account with this email already exists.']);
    return;
}
```

#### 3.6.2 Send Restore Email

Add method `send_restore_account_email(object $member)` to `class-stsrc-ajax-handler.php`:

```php
private function send_restore_account_email($member) {
    $token = bin2hex(random_bytes(32));
    $expiry = 24 * HOUR_IN_SECONDS;
    set_transient('stsrc_restore_account_' . $token, ['member_id' => $member->member_id], $expiry);

    $restore_url = home_url('?action=stsrc_restore_account&token=' . $token);

    // Use STSRC_Email_Service to send template: templates/restore-account.php
    STSRC_Email_Service::send_template(
        $member->email,
        'Restore Your Smoketree Account',
        'restore-account',
        [
            'first_name'  => $member->first_name,
            'restore_url' => $restore_url,
        ]
    );

    STSRC_Logger::log("Restore account email sent to member #{$member->member_id}", 'info');
}
```

#### 3.6.3 Handle Restore Link Click

Hook on `init` (alongside the existing `handle_reactivation_request()`):

```php
add_action('init', [$this, 'handle_restore_account_request']);

public function handle_restore_account_request() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'stsrc_restore_account') return;
    if (empty($_GET['token'])) return;

    $token   = sanitize_text_field($_GET['token']);
    $data    = get_transient('stsrc_restore_account_' . $token);

    if (!$data || empty($data['member_id'])) {
        wp_redirect(home_url('/register?restore=expired'));
        exit;
    }

    $member_id = intval($data['member_id']);
    global $wpdb;

    // 1. Flip member status to 'inactive'
    $wpdb->update(
        $wpdb->prefix . 'stsrc_members',
        ['status' => 'inactive'],
        ['member_id' => $member_id],
        ['%s'], ['%d']
    );

    // 2. Get member record for email
    $member = $member_db->get_by_id($member_id);

    // 3. Create new WordPress user
    $wp_user_id = wp_create_user($member->email, wp_generate_password(), $member->email);
    if (!is_wp_error($wp_user_id)) {
        $wpdb->update(
            $wpdb->prefix . 'stsrc_members',
            ['user_id' => $wp_user_id],
            ['member_id' => $member_id],
            ['%d'], ['%d']
        );
    }

    // 4. Delete transient
    delete_transient('stsrc_restore_account_' . $token);

    // 5. Redirect to password reset page
    wp_redirect(home_url('/reset-password?restored=1'));
    exit;
}
```

#### 3.6.4 Member Portal — Restore Deleted Family/Extra Members

In `public/templates/member-portal.php` (or the relevant portal partial), after the existing family/extra member sections, add a "Restore Previous Members" section that is only rendered when deleted records exist.

**PHP logic** (in the portal page controller or at the top of the template):

```php
$deleted_family  = STSRC_Family_Member_DB::get_deleted_by_member_id($member_id);
$deleted_extra   = STSRC_Extra_Member_DB::get_deleted_by_member_id($member_id);
$has_deleted     = !empty($deleted_family) || !empty($deleted_extra);
```

Add new DB methods:
- `STSRC_Family_Member_DB::get_deleted_by_member_id($member_id)` — queries `WHERE member_id = %d AND status = 'deleted'`
- `STSRC_Extra_Member_DB::get_deleted_by_member_id($member_id)` — same pattern

**Portal HTML section** (only shown if `$has_deleted`):

```html
<?php if ($has_deleted) : ?>
<div class="stsrc-restore-members-section">
    <h2>Restore Previous Members</h2>
    <p>The following members from your previous account can be restored.</p>

    <?php if (!empty($deleted_family)) : ?>
    <h3>Family Members</h3>
    <ul>
        <?php foreach ($deleted_family as $fm) : ?>
        <li>
            <?php echo esc_html($fm->first_name . ' ' . $fm->last_name); ?>
            <button class="stsrc-restore-family-btn" data-id="<?php echo esc_attr($fm->id); ?>">Restore</button>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if (!empty($deleted_extra)) : ?>
    <h3>Extra Members</h3>
    <ul>
        <?php foreach ($deleted_extra as $em) : ?>
        <li>
            <?php echo esc_html($em->first_name . ' ' . $em->last_name); ?>
            <button class="stsrc-restore-extra-btn" data-id="<?php echo esc_attr($em->id); ?>">Restore</button>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php endif; ?>
```

Add AJAX actions `stsrc_restore_family_member` and `stsrc_restore_extra_member` in the AJAX handler, both protected by nonce and login check:

```php
// stsrc_restore_family_member
public function restore_family_member() {
    check_ajax_referer('stsrc_portal_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error();
    $id        = intval($_POST['id']);
    $member_id = $this->get_current_member_id(); // helper to get member_id from WP user
    // Verify the family member belongs to this member
    $fm = STSRC_Family_Member_DB::get_by_id($id); // need a get_by_id method
    if (!$fm || $fm->member_id !== $member_id || $fm->status !== 'deleted') {
        wp_send_json_error(['message' => 'Invalid request.']);
        return;
    }
    STSRC_Family_Member_DB::restore($id);
    wp_send_json_success(['message' => 'Family member restored.']);
}
```

Mirror for `stsrc_restore_extra_member`.

#### 3.6.5 New Email Template — `templates/restore-account.php`

Separate file from the existing `reactivation.php` template. Content:

```php
<?php
// Variables available: $first_name, $restore_url
?>
<p>Hi <?php echo esc_html($first_name); ?>,</p>

<p>We found a previous Smoketree Swim & Recreation Club account associated with this email address.</p>

<p>Click the link below to restore your account. This link expires in 24 hours.</p>

<p><a href="<?php echo esc_url($restore_url); ?>">Restore My Account</a></p>

<p>After restoring your account, you will be prompted to set a new password. 
Your previous membership details will be preserved, and you can restore any 
previously listed family or extra members from your member portal.</p>

<p>To become an active member, you will need to pay your membership balance 
through the member portal.</p>

<p>If you did not attempt to register, you can safely ignore this email.</p>
```

---

## 4. Database Schema

### 4.1 `wp_stsrc_members` (existing — status values extended)

| Column | Type | Notes |
|--------|------|-------|
| status | VARCHAR(20) | Existing values: `active`, `inactive`, `pending`, `cancelled`. **Add**: `deleted` |

No column change needed — `deleted` is just a new valid string value.

### 4.2 `wp_stsrc_family_members` (modified)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | INT | PK, AUTO_INCREMENT | existing |
| member_id | INT | FK → stsrc_members | existing |
| first_name | VARCHAR(100) | NOT NULL | existing |
| last_name | VARCHAR(100) | NOT NULL | existing |
| email | VARCHAR(200) | | existing |
| **status** | **VARCHAR(20)** | **NOT NULL DEFAULT 'active'** | **NEW** |

Unique key: `(member_id, first_name, last_name)` — existing, unaffected.

### 4.3 `wp_stsrc_extra_members` (modified)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | INT | PK, AUTO_INCREMENT | existing |
| member_id | INT | FK → stsrc_members | existing |
| first_name | VARCHAR(100) | NOT NULL | existing |
| last_name | VARCHAR(100) | NOT NULL | existing |
| email | VARCHAR(200) | | existing |
| payment_status | VARCHAR(50) | | existing |
| stripe_payment_intent_id | VARCHAR(100) | | existing |
| **status** | **VARCHAR(20)** | **NOT NULL DEFAULT 'active'** | **NEW** |

---

## 5. Server Actions

### 5.1 Database Actions

#### `stsrc_family_members` — new/modified methods

| Method | Description | Returns |
|--------|-------------|---------|
| `get_by_member_id($member_id)` | **Modified**: adds `AND status = 'active'` | array of objects |
| `get_all_by_member_id_including_deleted($member_id)` | **New**: no status filter | array of objects |
| `get_deleted_by_member_id($member_id)` | **New**: `WHERE status = 'deleted'` | array of objects |
| `get_by_id($id)` | **New**: fetch single record by primary key | object or null |
| `soft_delete_by_member_id($member_id)` | **New**: sets all to `deleted` | int (rows affected) |
| `restore($family_member_id)` | **New**: sets single record to `active` | int (rows affected) |

#### `stsrc_extra_members` — identical method additions

Same pattern as family members above.

#### `stsrc_members` — `get_by_email($email)` (verify existing)

Used by the restore-account detection. Confirm this method exists and returns the member object with `status` field.

### 5.2 AJAX Actions (new / modified)

| Action | Auth | Description |
|--------|------|-------------|
| `stsrc_soft_delete_member` | admin | Soft-deletes member + family + extra + WP user |
| `stsrc_restore_family_member` | logged-in | Restores a deleted family member (portal) |
| `stsrc_restore_extra_member` | logged-in | Restores a deleted extra member (portal) |
| `stsrc_register_member` | public | **Modified**: rollback, pay-later guard, family validation, deleted-email guard |

### 5.3 `init` Hooks (new)

| Hook callback | Trigger | Description |
|---------------|---------|-------------|
| `handle_restore_account_request()` | `?action=stsrc_restore_account&token=` | Restores deleted member, creates WP user, redirects to password reset |

---

## 6. Design System

### 6.1 Delete Button Styling

The "Delete Member" button follows WordPress admin danger styling conventions:

```css
/* In admin/css/ or inline in member-edit.php */
.stsrc-danger-zone {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #dcdcde;
    text-align: right;
}

#stsrc-delete-member-btn.button-danger {
    background: #d63638;
    border-color: #d63638;
    color: #fff;
}

#stsrc-delete-member-btn.button-danger:hover {
    background: #b32d2e;
    border-color: #b32d2e;
}
```

### 6.2 Confirmation Modal

- Backdrop: semi-transparent overlay (`rgba(0,0,0,0.5)`)
- Modal container: white, centered, `max-width: 480px`, with padding and box shadow
- Summary list: bulleted list of affected records
- Action buttons: "Yes, Delete Member" (red/danger) and "Cancel" (default WP button), right-aligned

### 6.3 Pre-Rendered Family Member Fields

- Match the HTML structure of dynamically-added fields exactly (same classes, same input name patterns)
- Locked fields: no "Remove" button, visually identical to unlocked fields otherwise
- Validation error message: inline red text, `display:none` by default, shown on validation failure

---

## 8. Authentication & Authorization

### Admin Actions

- `stsrc_soft_delete_member`: requires `check_ajax_referer('stsrc_admin_nonce', 'nonce')` + `current_user_can('manage_options')`.

### Portal Actions

- `stsrc_restore_family_member` and `stsrc_restore_extra_member`: require `check_ajax_referer('stsrc_portal_nonce', 'nonce')` + `is_user_logged_in()`.
- Additionally verify the requested record's `member_id` matches the current user's member record before restoring.

### Restore Account Token

- Token: `bin2hex(random_bytes(32))` (64 hex chars)
- Stored in: `stsrc_restore_account_{token}` transient with 24-hour expiry
- One-time use: transient deleted immediately on successful redemption
- Expired token: redirect to `/register?restore=expired` with a user-friendly message

---

## 9. Data Flow

### Soft Delete Flow (Admin)

```
Admin clicks "Delete Member"
  → JS reads data-* attributes
  → JS builds confirmation modal content
  → Admin confirms
  → JS AJAX: stsrc_soft_delete_member { member_id, nonce }
  → PHP: verify nonce + caps
  → PHP: UPDATE stsrc_members SET status='deleted'
  → PHP: UPDATE stsrc_family_members SET status='deleted' WHERE member_id=X
  → PHP: UPDATE stsrc_extra_members SET status='deleted' WHERE member_id=X
  → PHP: wp_delete_user(user_id)
  → PHP: log action
  → JS: redirect to members list with ?deleted=1
```

### Registration Rollback Flow

```
User submits registration
  → Server: validate inputs
  → Server: check email not in use (including deleted-status check)
  → Server: create WP user [rollback_context.wp_user_id set]
  → Server: insert member record [rollback_context.member_id set]
  → Server: insert family members [rollback_context.family_member_ids populated]
  → Server: create Stripe customer [rollback_context.stripe_customer_id set]
  → Server: create Stripe Checkout Session
    [if ANY step fails → rollback_registration($context) → JSON error]
  → Server: return Stripe session URL
  → JS: redirect to Stripe
```

### Restore Account Flow

```
User attempts to register with deleted-member email
  → Server: detect deleted status
  → Server: generate token, store transient, send email
  → Server: return JSON error with code 'restore_sent'
  → UI: show friendly message to user

User clicks restore link
  → Server (init hook): verify token, fetch transient
  → Server: UPDATE member status → 'inactive'
  → Server: wp_create_user with member's email
  → Server: UPDATE member.user_id → new WP user ID
  → Server: delete transient
  → Server: redirect to /reset-password?restored=1

User sets password, logs in
  → Portal: shows deleted family/extra members with Restore buttons
  → User clicks Restore on each desired member
  → AJAX: stsrc_restore_family_member / stsrc_restore_extra_member
  → Server: verify ownership, set status → 'active'

User pays balance via portal
  → Balance paid → status moves from 'inactive' → 'active'
```

---

## 10. Implementation Order & Dependencies

The features should be implemented in this sequence to respect dependencies:

1. **Database migration** (Feature 3.2.1) — must run first; all other features depend on `status` columns
2. **DB class updates** — update `class-stsrc-family-member-db.php` and `class-stsrc-extra-member-db.php` with default `status = 'active'` filter and new methods
3. **Feature 3.1** — trivial dropdown additions, no dependencies
4. **Feature 3.2** — soft delete (depends on DB migration + DB class updates)
5. **Feature 3.3** — hide Pay Later (no DB dependency)
6. **Feature 3.4** — registration rollback (no new DB tables, refactor of existing flow)
7. **Feature 3.5** — family member UX overhaul (JS + server validation changes)
8. **Feature 3.6** — restore account flow (depends on DB `status` columns + DB class `get_deleted_by_member_id` methods)
