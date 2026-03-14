# Implementation Plan

## Foundation: Schema + Data Layer

- [ ] Step 1: Add related-member `status` columns migration
  - **Task**: Add migration logic to create `status VARCHAR(20) NOT NULL DEFAULT 'active'` on `stsrc_family_members` and `stsrc_extra_members` if missing, and wire it to activation/migration execution paths.
  - **Files**:
    - `includes/database/class-stsrc-database.php`: Add idempotent column checks + `ALTER TABLE` migration method and invocation hooks.
    - `admin/pages/class-stsrc-migration-page.php`: Ensure manual migration runner executes the new migration.
  - **Step Dependencies**: None
  - **User Instructions**: Run the plugin migration page once in wp-admin after deployment to backfill existing installs.
  - **Git message**: `feat(db): add status columns for family and extra members`

- [ ] Step 2: Enforce active-only reads and add soft-delete/restore DB helpers
  - **Task**: Update family/extra DB classes so normal reads default to active records only; add methods for including deleted, listing deleted, soft-deleting by member, restoring by id, and fetching by id.
  - **Files**:
    - `includes/database/class-stsrc-family-member-db.php`: Add `status='active'` default query behavior + new helper methods.
    - `includes/database/class-stsrc-extra-member-db.php`: Mirror family-member DB changes.
  - **Step Dependencies**: Step 1
  - **User Instructions**: None
  - **Git message**: `refactor(db): add active-status defaults and restore helpers`

## Admin Member Lifecycle

- [ ] Step 3: Add `inactive` status options in admin edit/list filters
  - **Task**: Add `inactive` to member edit status dropdown and members-list filter dropdown for admin consistency.
  - **Files**:
    - `admin/partials/member-edit.php`: Add `inactive` option to status `<select>`.
    - `admin/partials/members-list.php`: Add `inactive` option to status filter UI.
  - **Step Dependencies**: None
  - **User Instructions**: None
  - **Git message**: `feat(admin): add inactive status options to member ui`

- [ ] Step 4: Add soft-delete AJAX action with security + cascade status updates
  - **Task**: Implement `stsrc_soft_delete_member` handler with nonce/capability checks, validation, member/family/extra status updates to `deleted`, WP user deletion, and structured success/error responses.
  - **Files**:
    - `includes/api/class-stsrc-ajax-handler.php`: Register action and implement `soft_delete_member()` with logging.
    - `includes/database/class-stsrc-family-member-db.php`: Use soft-delete helper in action flow.
    - `includes/database/class-stsrc-extra-member-db.php`: Use soft-delete helper in action flow.
  - **Step Dependencies**: Step 1, Step 2
  - **User Instructions**: None
  - **Git message**: `feat(admin): add secure soft-delete member ajax workflow`

- [ ] Step 5: Add delete button + confirmation modal on member edit page
  - **Task**: Add danger-zone delete button and confirmation modal summarizing affected records, with nonce/member metadata for AJAX delete.
  - **Files**:
    - `admin/partials/member-edit.php`: Add delete UI and modal markup.
    - `admin/pages/class-stsrc-members-page.php` (or edit-page controller): Pass family/extra counts and user-state metadata to view.
    - `admin/css/smoketree-plugin-admin.css` (or existing admin style location): Add danger-button and modal styling.
  - **Step Dependencies**: Step 4
  - **User Instructions**: None
  - **Git message**: `feat(admin): add delete member confirmation modal ui`

- [ ] Step 6: Wire admin JS delete flow and post-delete redirect
  - **Task**: Add JS handlers to open/close modal, build impact summary, call soft-delete AJAX, handle errors, and redirect with success flag.
  - **Files**:
    - `admin/js/smoketree-plugin-admin.js`: Add modal interactions and AJAX submit logic.
  - **Step Dependencies**: Step 5
  - **User Instructions**: Clear browser cache if admin JS is aggressively cached.
  - **Git message**: `feat(admin): implement member delete modal interactions`

- [ ] Step 7: Hide deleted members by default + add show-deleted controls
  - **Task**: Update list query/filtering so deleted members are excluded by default, add show-deleted toggle, and include `deleted` in bulk status dropdown.
  - **Files**:
    - `admin/partials/members-list.php`: Add show-deleted toggle and bulk option.
    - `admin/pages/class-stsrc-members-page.php`: Update query conditions for default exclusion + toggle override.
  - **Step Dependencies**: Step 4
  - **User Instructions**: None
  - **Git message**: `feat(admin): add show-deleted filter and bulk deleted status`

## Registration Payment + Integrity

- [ ] Step 8: Gate "Pay Later" by plugin option in rendered form
  - **Task**: Read `stsrc_payment_plan_enabled` in public render path and conditionally render/remove Pay Later option in registration markup.
  - **Files**:
    - `public/class-smoketree-plugin-public.php`: Read option and pass flag into template scope.
    - `public/partials/registration-form.php`: Conditionally render Pay Later radio option.
  - **Step Dependencies**: None
  - **User Instructions**: Confirm `stsrc_payment_plan_enabled` is set correctly in plugin settings before testing.
  - **Git message**: `feat(registration): hide pay-later when plan is disabled`

- [ ] Step 9: Enforce server-side payment-type validation for Pay Later
  - **Task**: In registration handler, reject `pay_later` when payment plan option is disabled regardless of client UI state.
  - **Files**:
    - `includes/api/class-stsrc-ajax-handler.php`: Add validation guard and JSON error response.
  - **Step Dependencies**: Step 8
  - **User Instructions**: None
  - **Git message**: `fix(registration): block disabled pay-later submissions server-side`

- [ ] Step 10: Add full registration rollback helper and integrate failure paths
  - **Task**: Implement rollback context tracking (member/wp user/family members/Stripe customer), rollback helper, and invoke it on all post-account-creation failures (including checkout-session failures), with logging per rollback step.
  - **Files**:
    - `includes/api/class-stsrc-ajax-handler.php`: Add `rollback_registration()` and refactor `register_member()` control flow.
  - **Step Dependencies**: None
  - **User Instructions**: Test with Stripe test keys and simulate session creation failures.
  - **Git message**: `fix(registration): add full rollback for failed account setup`

## Registration Family UX + Validation

- [ ] Step 11: Pre-render family-member inputs by plan (Household/Duo)
  - **Task**: Replace add-only family UX with plan-driven pre-rendering: Household renders 4 (first 2 locked), Duo renders 1 locked, optional remove for allowed slots only, hide add button where required.
  - **Files**:
    - `public/partials/registration-form.php`: Update family members HTML/JS rendering behavior and locked-slot handling.
  - **Step Dependencies**: None
  - **User Instructions**: Manually test switching between membership plans multiple times before submit.
  - **Git message**: `feat(registration): pre-render family members based on plan limits`

- [ ] Step 12: Add client and server minimum-family validation
  - **Task**: Add inline client validation + auto-scroll for missing required family entries and enforce matching server-side minimum checks for Household/Duo before processing.
  - **Files**:
    - `public/partials/registration-form.php`: Add client validation messaging and submit guards.
    - `includes/api/class-stsrc-ajax-handler.php`: Add server minimum family-member validation logic.
  - **Step Dependencies**: Step 11
  - **User Instructions**: Verify both JS-enabled and JS-disabled submission behavior.
  - **Git message**: `fix(registration): enforce minimum family member requirements`

## Deleted Email Restore Flow

- [ ] Step 13: Detect deleted-email registration attempts and send restore email
  - **Task**: In existing-email checks, branch deleted-status users into restore flow; generate tokenized 24h link via transient; send dedicated restore email template; return friendly user-facing message/code.
  - **Files**:
    - `includes/api/class-stsrc-ajax-handler.php`: Add deleted-email detection + `send_restore_account_email()` helper.
    - `includes/services/class-stsrc-email-service.php` (if needed): Support new template usage path.
    - `templates/restore-account.php`: New restore-account email template.
  - **Step Dependencies**: Step 4
  - **User Instructions**: Ensure site mail delivery is configured (SMTP/plugin) to verify restore emails.
  - **Git message**: `feat(registration): add deleted-account restore email flow`

- [ ] Step 14: Implement restore-link redemption and WP user recreation
  - **Task**: Add `init` handler for restore links, validate token/expiry, set member status to `inactive`, recreate WP user safely, update member user_id, consume token, and redirect to password reset.
  - **Files**:
    - `includes/api/class-stsrc-ajax-handler.php`: Add `handle_restore_account_request()` and hook registration.
    - `includes/database/class-stsrc-member-db.php` (or existing member access file): Add/verify helpers for member lookup by id/email.
  - **Step Dependencies**: Step 13
  - **User Instructions**: Confirm target password reset URL/flow in this WP install and adjust redirect path if needed.
  - **Git message**: `feat(auth): redeem restore token and recreate member wp user`

- [ ] Step 15: Add portal UI + AJAX restore actions for deleted family/extra members
  - **Task**: Show deleted related members in portal restore section and implement authenticated AJAX restore endpoints with ownership checks and status flips back to `active`.
  - **Files**:
    - `public/templates/member-portal.php`: Render restore section with deleted family/extra lists and action buttons.
    - `public/js/smoketree-plugin-public.js` (or existing portal JS location): Add restore button AJAX handlers.
    - `includes/api/class-stsrc-ajax-handler.php`: Add `stsrc_restore_family_member` and `stsrc_restore_extra_member`.
    - `includes/database/class-stsrc-family-member-db.php`: Use `get_deleted_by_member_id()`, `get_by_id()`, `restore()`.
    - `includes/database/class-stsrc-extra-member-db.php`: Use `get_deleted_by_member_id()`, `get_by_id()`, `restore()`.
  - **Step Dependencies**: Step 2, Step 14
  - **User Instructions**: Test with a restored inactive account and verify only owned records are restorable.
  - **Git message**: `feat(portal): restore deleted family and extra members`

## Hardening + Verification

- [ ] Step 16: End-to-end validation, logging checks, and regression pass
  - **Task**: Run targeted QA scenarios for admin delete, registration rollback, payment gating, family validation, and restore flows; tighten error handling and log clarity where needed.
  - **Files**:
    - `includes/api/class-stsrc-ajax-handler.php`: Final error-path/log message refinements.
    - `admin/partials/member-edit.php`: UX text tweaks if confirmation copy is unclear.
    - `public/partials/registration-form.php`: Final validation-message polish.
  - **Step Dependencies**: Steps 1-15
  - **User Instructions**: Execute manual QA matrix in staging with Stripe test mode before production deploy.
  - **Git message**: `chore(qa): finalize bug-fix flow validation and error handling`