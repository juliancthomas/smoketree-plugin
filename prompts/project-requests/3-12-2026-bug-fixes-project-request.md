# Smoketree Plugin — Bug Fixes & Admin Enhancements

## Project Description
A collection of bug fixes and small feature additions to the Smoketree Swim & Recreation Club WordPress membership plugin, addressing issues identified during a meeting with the club president. Covers admin member management improvements, registration form fixes, and data integrity safeguards.

## Target Audience
- Club administrators (wp-admin users managing members)
- Prospective members (public registration form users)

## Desired Features

### 1. Admin — Add "Inactive" Status to Member Edit Dropdown
- [ ] Add "Inactive" option to the status `<select>` in `admin/partials/member-edit.php`
- [ ] Add "Inactive" to the members list filter dropdown for consistency

### 2. Admin — Soft Delete Member from Edit Page
- [ ] Add a new `deleted` member status
- [ ] Add a "Delete Member" button to the member edit page (red/danger styling, positioned away from Save)
- [ ] Soft-delete sets the primary member's status to `deleted`
- [ ] Delete the associated WordPress user account
- [ ] Add `status` column to `stsrc_family_members` table (VARCHAR(20), default `active`)
- [ ] Add `status` column to `stsrc_extra_members` table (VARCHAR(20), default `active`)
- [ ] Soft-delete all associated family members (set status to `deleted`)
- [ ] Soft-delete all associated extra members (set status to `deleted`)
- [ ] Add `deleted` to the bulk status change dropdown on the members list
- [ ] Confirmation dialog before deletion: clearly lists what will be affected (member, family members, extra members, WP user)
- [ ] Members list: hide `deleted` members by default
- [ ] Members list: add a "Show Deleted" filter toggle to reveal soft-deleted members
- [ ] Update all existing queries that read family/extra members to filter by `status = 'active'` by default

### 3. Registration — Hide "Pay Later" When Payment Plan Disabled
- [ ] Read the `stsrc_payment_plan_enabled` option and pass it to the registration form template
- [ ] Completely remove the "Pay Later" radio option from the rendered HTML when the setting is disabled
- [ ] Server-side validation: reject `pay_later` payment type when the setting is disabled

### 4. Registration — Prevent Email Lockout on Failed Registration
- [ ] Implement full rollback on any failure after member account creation:
    - [ ] Delete the member record from `stsrc_members`
    - [ ] Delete the WordPress user
    - [ ] Delete any family member records that were created
    - [ ] Delete/void the Stripe customer if one was created
- [ ] Rollback triggers on Stripe checkout session creation failure
- [ ] Rollback triggers on any other post-account-creation failure
- [ ] Ensure the email is fully available for a fresh registration attempt
- [ ] Add logging for all rollback events

### 5. Registration — Family Members UX Overhaul
- [ ] **Household** (family-limit: 4): Pre-render 4 family member input groups when selected
    - [ ] First 2 fields are locked (no remove button) — enforces minimum of 2
    - [ ] Fields 3 and 4 have a remove button (optional family members)
    - [ ] No "Add Family Member" button
- [ ] **Duo** (family-limit: 1): Pre-render 1 locked family member input group when selected
    - [ ] No remove button, no "Add Family Member" button
- [ ] Client-side validation: Household requires at least 2 family members with first + last name filled
- [ ] Client-side validation: Duo requires 1 family member with first + last name filled
- [ ] Server-side validation: Household registrations must include >= 2 family members
- [ ] Server-side validation: Duo registrations must include >= 1 family member

### 6. Registration — Soft-Deleted Email Reuse ("Restore Your Account")
- [ ] During registration, if the submitted email matches a member with `deleted` status:
    - [ ] Block registration (don't create a new account)
    - [ ] Send a "Restore Your Account" email with a secure, time-limited token link (24hr expiry)
    - [ ] Show user a friendly message: "We found a previous account with this email. A restoration link has been sent."
- [ ] When the user clicks the restore link:
    - [ ] Flip member status from `deleted` to `inactive`
    - [ ] Create a new WordPress user for the member's email
    - [ ] Redirect to password reset page (they need to set a new password)
- [ ] Family/extra member restoration handled in the Member Portal:
    - [ ] Portal shows the member's previously soft-deleted family members with an option to restore each one
    - [ ] Portal shows the member's previously soft-deleted extra members with an option to restore each one
- [ ] Member pays their balance via the existing portal payment flow to move from `inactive` to `active`
- [ ] Separate email template from the existing cancelled-member reactivation email (different messaging/context)

## Design Requests
- [ ] Delete button on member edit page: red/danger styling, clearly separated from Save button
- [ ] Confirmation modal for deletion: states what will be affected (member record, N family members, N extra members, WordPress user)
- [ ] Pre-rendered family member fields match the existing styling of dynamically-added ones
- [ ] Locked family member fields: no remove button, but visually identical otherwise
- [ ] Validation error for missing family members: clear inline message, auto-scroll to the family section

## Technical Notes
- **Database migration**: Add `status` column (VARCHAR(20), default `active`) to `stsrc_family_members` and `stsrc_extra_members` tables
- **Query updates**: All existing queries reading family/extra members must filter by `status = 'active'` unless explicitly requesting deleted records
- **Unique email constraint**: Soft-deleted members retain their email in the DB; the restore flow handles re-registration attempts rather than allowing duplicate inserts
- **Foreign keys**: CASCADE constraints won't fire on soft delete — related records need explicit handling
- **Existing reactivation flow**: The cancelled-member reactivation flow (token-based email link) provides a reference pattern, but the deleted-member restore flow is a separate path with different behavior (no data overwrite, WP user recreation, portal-based restoration)