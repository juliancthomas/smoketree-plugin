# Implementation Plan — Member Portal UI/UX Improvements

## Step 1: Portal Header — Personalized Greeting & Error Handling

- [ ] Step 1: Replace generic header with personalized greeting and fix wp_die
  - **Task**: In `member-portal.php`, replace the `wp_die()` call on missing member with a `wp_safe_redirect( home_url('/') )` + `exit`. Replace the `<h1>Member Portal</h1>` heading with `<h1>Welcome back, [First Name]</h1>` and add a status badge (Active/Pending/Inactive) inline below or beside the greeting text. Badge should reuse the existing `.stsrc-status-badge` class and its color conventions already present in the profile partial. Add minimal CSS to `member-portal.css` to style the greeting block (font sizing, badge alignment in the header context).
  - **Files**:
    - `public/templates/member-portal.php`: Replace `wp_die()` with `wp_safe_redirect( home_url('/') ); exit;`. Replace `<h1>Member Portal</h1>` block with personalized greeting + status badge markup. Member `first_name` and `status` are already available in `$member` at that point in the template.
    - `public/css/member-portal.css`: Add `.stsrc-portal-greeting` styles for the header block — large friendly heading, badge alignment, spacing.
  - **Step Dependencies**: None
  - **User Instructions**: None
  - **Git message**: `feat(portal): personalized greeting header and graceful missing-member redirect`

---

## Step 2: Member Profile Section — Address Display & Remove Auto-Renewal UI

- [ ] Step 2: Add address display row and strip auto-renewal UI from profile partial
  - **Task**: In `member-profile.php`, add a read-only address display row between the Phone row and Membership Type row. Format as a single line: Street 1, Street 2 (if present), City, State ZIP. Omit the row entirely if all address fields are empty. Then remove the entire auto-renewal block: the `stsrc-auto-renewal-row` div (containing the form, checkbox, status span, and note paragraph). Also remove the "Manage Payment Methods" button from `.stsrc-portal-actions`. The Edit Profile modal already contains address fields so editing still works via the existing modal — no modal changes needed.
  - **Files**:
    - `public/partials/member-profile.php`: Add address display row after Phone row. Remove `stsrc-auto-renewal-row` div (lines 77–113). Remove the `#stsrc-stripe-portal-btn` button block (lines 153–157).
  - **Step Dependencies**: None
  - **User Instructions**: None
  - **Git message**: `feat(portal): add address display row and remove auto-renewal UI from profile section`

---

## Step 3: Transaction History — Year Selector (AJAX) & Sign/Color Convention Fix

- [ ] Step 3: Add per-member available-year lookup, AJAX handler, year selector UI, and fix debit/credit display
  - **Task**:

    **3a — DB method**: Add `get_transaction_years( int $member_id ): array` to `STSRC_Transaction_DB`. It should return a sorted array of distinct years (newest first) from `YEAR(created_at)` for the given member. This avoids fetching all transactions to derive years.

    **3b — AJAX handler**: Add `get_member_transactions( void ): void` public method to `STSRC_Ajax_Handler`. It must: verify nonce (`stsrc_portal_nonce`), get `$member_id` from the logged-in user's member record, accept a `year` POST param (int, default current year), call `STSRC_Transaction_DB::get_transactions()`, then output the transaction list rows as HTML (the inner `stsrc-transaction-list` content) via `wp_send_json_success`. Use the same sign/color logic described in 3d below.

    **3c — Action registration**: Register `wp_ajax_stsrc_get_member_transactions` → `$ajax_handler, 'get_member_transactions'` in `class-smoketree-plugin.php` (logged-in only, no `nopriv`).

    **3d — Partial UI + sign/color fix**: Rewrite `member-transaction-history.php` to:
    - Call `STSRC_Transaction_DB::get_transaction_years( $member_id )` for the year selector options.
    - Render a `<select id="stsrc-transaction-year-select">` with the available years, defaulting to current year.
    - Fix sign/color logic using transaction type: `payment`, `fee`, `initial` → debit (red, `–$X`); `refund` → credit (green, `+$X`); `adjustment` → derive from stored amount sign (positive stored amount = credit/green `+$X`, negative = debit/red `–$X`). Use CSS classes `stsrc-transaction-amount--debit` (red) and `stsrc-transaction-amount--credit` (green).
    - Wrap the transaction rows in a `<div id="stsrc-transaction-list-container">` so JS can replace its innerHTML on AJAX reload.
    - Update the collapsible Show More/Less button behavior to be re-initialized after AJAX reload.

    **3e — JS**: Add `initTransactionYearSelector()` to `member-portal.js`. On `change` of `#stsrc-transaction-year-select`, POST to `stsrcPublic.ajaxUrl` with `action: 'stsrc_get_member_transactions'`, `nonce: stsrcPublic.portalNonce`, `year: selectedYear`. On success, replace `#stsrc-transaction-list-container` innerHTML with the returned HTML, then re-initialize the Show More/Less toggle.

    **3f — CSS**: Ensure `.stsrc-transaction-amount--debit` is red and `.stsrc-transaction-amount--credit` is green. Add year selector row styles.

  - **Files**:
    - `includes/database/class-stsrc-transaction-db.php`: Add `get_transaction_years( int $member_id ): array` static method.
    - `includes/api/class-stsrc-ajax-handler.php`: Add `get_member_transactions(): void` method.
    - `includes/class-smoketree-plugin.php`: Register `wp_ajax_stsrc_get_member_transactions`.
    - `public/partials/member-transaction-history.php`: Add year selector, fix sign/color logic, add container div for AJAX swap.
    - `public/js/member-portal.js`: Add `initTransactionYearSelector()`, call it from `$(document).ready`.
    - `public/css/member-portal.css`: Confirm/update debit = red, credit = green; add year selector row styles.
  - **Step Dependencies**: None (self-contained)
  - **User Instructions**: None
  - **Git message**: `feat(portal): transaction year selector with AJAX reload and corrected debit/credit display`

---

## Step 4: Access Codes — Credential Card Redesign

- [ ] Step 4: Extract access codes to a partial and redesign as styled credential cards
  - **Task**: Extract the inline access codes block from `member-portal.php` into a new partial `public/partials/member-access-codes.php`. Replace the plain `<strong>` code value and `<p>` description with a card layout: each code in a styled `.stsrc-access-code-card` div containing a monospace `.stsrc-access-code-card__value` element (large, legible) and a secondary `.stsrc-access-code-card__description` element below it. The card should feel like a credential display — visually distinct with a contained border/background, not a plain list item. Update `member-portal.php` to `include` the new partial (passing `$access_codes` via `$data` which is already available). Add card styles to `member-portal.css`.
  - **Files**:
    - `public/templates/member-portal.php`: Replace the inline access codes HTML block with `include plugin_dir_path( __FILE__ ) . '../partials/member-access-codes.php';`
    - `public/partials/member-access-codes.php`: New file — reads `$data['access_codes']`, renders styled card grid.
    - `public/css/member-portal.css`: Add `.stsrc-access-code-card`, `__value`, `__description` styles — monospace code value, card container with border/background, responsive grid or flex wrap.
  - **Step Dependencies**: None
  - **User Instructions**: None
  - **Git message**: `feat(portal): redesign access codes as styled credential cards`

---

## Key Considerations

- **Sign convention (Step 3)**: The fix uses `transaction_type` as the authority for debit vs. credit direction. The stored `amount` field's sign is not trustworthy as a standalone display signal. `adjustment` is the only type that can legitimately go either direction, so it falls back to the stored amount sign.
- **AJAX nonce reuse (Step 3)**: `stsrcPublic.portalNonce` (`stsrc_portal_nonce`) is already localized to the member portal page — no new nonce infrastructure needed for the year selector.
- **No JS changes needed for Step 2**: The auto-renewal toggle JS handler fires on `#stsrc-auto-renewal-toggle` change — since the element is removed, the handler simply never attaches. No dead code removal required.
- **Address display (Step 2)**: Show the address row only when at least one address field is non-empty to avoid a blank row for older records that predate address collection.
