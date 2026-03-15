<specification_planning>
1. Core system architecture and key workflows
- Build on existing WordPress plugin architecture and reuse registration pricing + Stripe utilities.
- Add a renewal decision flow: eligibility check -> type selection -> household/family/extra member reconciliation -> pricing + fee calculation -> payment path (Stripe or non-Stripe) -> record updates -> notifications.
- Ensure idempotent completion for webhook and non-Stripe pending renewals to prevent duplicate renewals.
- Challenge: preventing double-renewal across UI race conditions and webhook retries.
- Solution: persist renewal season record with unique constraint and enforce server-side checks before every finalization.

2. Project structure and organization
- Add dedicated renewal service layer, UI partial, and email templates while reusing existing helpers for member lookup, pricing, Stripe checkout session creation, and email dispatch.
- Keep business rules in service class (not template files) to avoid drift between portal UI and webhook processing.
- Challenge: existing plugin may mix rendering and logic in large files.
- Solution: introduce minimal, targeted service classes and thin wrappers in existing controllers.

3. Detailed feature specifications
- Decompose into: admin controls, eligibility, renewal wizard/cards, transition rules, order summary + processing fees, payment handling, member updates, and notifications.
- Add downgrade warning and explicit remove/keep selectors for family members when reducing plan capacity.
- Edge cases: member changes type then back, stale price cache, family count mismatch, abandoned Stripe checkout, payment method switched mid-flow.

4. Database schema design
- Prefer additive renewal ledger table over single-column approach for auditability.
- Add `season_key` field strategy (e.g., `2026`) and uniqueness per member per season.
- Include soft-delete consistency for family and extra members.
- Challenge: historical compatibility with existing `balance_owed` and `original_membership_price`.
- Solution: store snapshot values in renewal record while still updating canonical member fields.

5. Server actions and integrations
- Introduce explicit actions for eligibility, quote calculation, intent creation, non-Stripe pending submission, Stripe success finalization, and admin confirmation.
- Reuse Stripe Checkout and webhook infrastructure with new metadata `payment_context=renewal`.
- Edge cases: webhook duplicate events, missing metadata, failed member update after paid session.

6. Design system and component architecture
- Match registration card and summary UI for visual continuity.
- Build reusable card and summary blocks for renewal context.
- Surface destructive changes (downgrades, soft-delete outcomes) with warning alert pattern.

7. Authentication and authorization implementation
- Restrict renewal to logged-in member portal users and scoped member id ownership.
- Restrict admin confirmation and settings changes to admin roles/capabilities.
- Add nonce + capability checks in all mutation endpoints.

8. Data flow and state management
- Keep source of truth server-side for eligibility and totals; client stores transient wizard state only.
- Recalculate totals server-side before creating Stripe/non-Stripe submission.
- Edge cases: stale frontend calculations, direct endpoint tampering, invalid type transition payload.

9. Payment implementation
- Stripe path: checkout session with renewal metadata, webhook finalization.
- Non-Stripe path: pending status + balance owed + admin follow-up.
- Clarify TBD behavior: whether existing `balance_owed` is rolled into Stripe checkout amount immediately (recommended yes for single settlement).
</specification_planning>

# Membership Renewal Portal Technical Specification

## 1. System Overview
- Core purpose and value proposition
  - Enable existing members to renew for the next season in the portal with full transparency on pricing, benefits, and membership composition changes.
  - Support both immediate online payment (Stripe card/ACH) and deferred offline settlement (Zelle/check) without creating parallel manual workflows.
- Key workflows
  - Admin enables renewal section globally.
  - Member opens renewal section, sees current membership context, selects target membership type, adjusts family/extra members, reviews live order summary, and chooses payment method.
  - Stripe flow: member redirected to checkout, webhook finalizes renewal.
  - Non-Stripe flow: renewal marked pending, balance owed displayed, admin confirms offline payment later.
- System architecture
  - Presentation: portal section rendered in existing member-facing templates/partials.
  - Domain logic: renewal service encapsulating transition rules, validation, pricing, and persistence orchestration.
  - Persistence: member record updates + renewal ledger table to track season-specific renewals.
  - Payments: Stripe Checkout + Stripe webhooks for asynchronous completion.
  - Notifications: member confirmation and admin notice templates.

## 2. Project Structure
- `admin/pages/class-stsrc-settings-page.php`
  - Add ACF-backed toggle handling for `stsrc_renewal_enabled`.
- `public/partials/registration-form.php` (reference only)
  - Reuse pricing/fee display patterns and styling tokens.
- `public/partials/renewal-section.php` (new)
  - Render renewal cards, downgrade warnings, family/extra member controls, and order summary.
- `includes/services/class-stsrc-renewal-service.php` (new)
  - Core rule engine and orchestrator.
- `includes/services/class-stsrc-renewal-pricing-service.php` (new or extension)
  - Pricing + processing fee calculations.
- `includes/api/class-stsrc-renewal-api.php` (new)
  - Endpoints/actions for eligibility, quote, submit, and pending confirmation.
- `includes/api/class-smoketree-stripe-webhooks.php`
  - Extend webhook handling for `payment_context=renewal`.
- `templates/email/renewal-confirmation.php` (new)
- `templates/email/renewal-confirmation-civic.php` (new)
- `templates/email/renewal-admin-notice.php` (new)
- `uninstall.php`
  - Drop renewal table/options if plugin currently removes custom structures on uninstall.

## 3. Feature Specification
### 3.1 Admin Renewal Controls
- User story and requirements
  - As an admin, I can enable/disable renewal globally so members only see renewal when the season opens.
- Detailed implementation steps
  - Add/read `stsrc_renewal_enabled` setting.
  - Gate renewal section render and renewal endpoints behind this flag.
  - Reuse `stsrc_season_renewal_date` for expiration recalculation baseline.
- Error handling and edge cases
  - If disabled while member is mid-flow, submission endpoint returns a disabled error and instructs refresh.

### 3.2 Renewal Eligibility and Season Tracking
- User story and requirements
  - As a member, I should only be able to renew once per season.
- Detailed implementation steps
  - Compute `season_key` from configured renewal date or explicit season setting (`YYYY`).
  - On entry and on submit, check renewal ledger for existing successful/pending record for same member + season.
  - Add unique DB constraint for `member_id + season_key + status in (pending, completed)` via schema approach described below.
- Error handling and edge cases
  - Duplicate submission, browser double-click, webhook replay all resolve idempotently to existing renewal record.

### 3.3 Renewal UI and Membership Cards
- User story and requirements
  - As a member, I can compare all membership types, see my current type, and understand gains/losses before confirming.
- Detailed implementation steps
  - Show type cards mirroring registration form design.
  - Badge current type: "You are currently a X member."
  - Display per-type benefits and compute "lost benefits" notice when downgrading.
  - Preload existing family/extra members and present keep/remove/add controls.
- Error handling and edge cases
  - If membership type metadata is missing, disable submit and show fallback support message.

### 3.4 Membership Type Transition Rules
- User story and requirements
  - As a member, I can move to any type, but family composition must satisfy type constraints.
- Detailed implementation steps
  - Enforce rules server-side for every transition:
    - Civic -> Household: require >= 2 family members.
    - Civic -> Duo: require >= 1.
    - Civic -> Single: require 0.
    - Duo -> Household: require total >= 2.
    - Duo -> Civic/Single: soft-delete existing family member.
    - Single -> Household: require >= 2.
    - Single -> Duo: require >= 1.
    - Single -> Civic: no family required.
    - Household -> Duo: force explicit selection of 1 family member to retain; soft-delete remaining family + all extra members.
    - Household -> Single/Civic: soft-delete all family + extra members.
  - Limit extra members to Household only, max 3.
- Error handling and edge cases
  - Reject payloads that retain more members than selected type allows.
  - If selected retained member ids do not belong to account, reject with authorization error.

### 3.5 Pricing and Live Order Summary
- User story and requirements
  - As a member, I see an accurate total including membership, extras, fees, and prior balance.
- Detailed implementation steps
  - Membership base price loaded from membership type DB record.
  - Extra members charged `$50 * extra_count` (Household only).
  - Existing `balance_owed` included in subtotal (recommended default behavior).
  - Processing fees:
    - Card: `round((subtotal * 0.029) + 0.30, 2)`
    - ACH: `min(round(subtotal * 0.008, 2), 5.00)`
    - Zelle/Check: `0.00`
  - Total = subtotal + processing_fee.
  - Recalculate server-side before final submit.
- Error handling and edge cases
  - Prevent negative totals if credits exist by clamping minimum payable to `0.00`.
  - If payment method changes, quote endpoint recomputes fee immediately.

### 3.6 Payment Flow
- User story and requirements
  - As a member, I can pay with card/ACH through Stripe or choose Zelle/check and complete later with admin confirmation.
- Detailed implementation steps
  - Card/ACH:
    - Create Stripe Checkout session with line items for renewal amount and metadata (`payment_context=renewal`, `member_id`, `season_key`, `target_type_id`, composition snapshot).
    - Redirect to Stripe.
    - Webhook on checkout success finalizes renewal transactionally.
  - Zelle/Check:
    - Create renewal record with `status=pending_payment`.
    - Update member `balance_owed` to include renewal amount and set membership status to pending (if supported by existing status model).
    - Show payment instructions in portal and email.
- Error handling and edge cases
  - If Stripe checkout created but member closes tab, renewal remains `initiated`; only webhook marks completion.
  - Duplicate webhook events must no-op after first successful completion.

### 3.7 Member Record Updates on Renewal Completion
- User story and requirements
  - As the system, I must persist a fully updated membership profile once renewal is complete.
- Detailed implementation steps
  - Update `membership_type_id`.
  - Recalculate `expiration_date = stsrc_season_renewal_date + expiration_period` (from selected type).
  - Update `original_membership_price` to renewed membership base.
  - Update `balance_owed`:
    - Stripe success: subtract paid amount from outstanding combined total (normally to `0.00` unless additional debt exists).
    - Non-Stripe pending: keep total owed until admin confirmation.
  - Apply family/extra member upserts and soft deletions.
  - Skip waiver re-signing.
- Error handling and edge cases
  - Run updates in DB transaction; rollback on partial failure.
  - Log before/after snapshots in renewal ledger for audit.

### 3.8 Notifications
- User story and requirements
  - As a member/admin, I receive context-appropriate renewal notifications.
- Detailed implementation steps
  - Member email templates:
    - `renewal-confirmation.php` for pool-access types.
    - `renewal-confirmation-civic.php` for Civic.
    - Include payment instructions for non-Stripe methods.
  - Admin notice template `renewal-admin-notice.php` includes member details, old -> new type, amount, and payment method.
  - Recipients: Treasurer, Secretary (parse comma-separated `stsrc_secretary_email`), President, Vice President.
- Error handling and edge cases
  - If one recipient email fails validation, continue sending to valid recipients and log failures.

## 4. Database Schema
### 4.1 Tables
- `stsrc_members` (existing, updates only)
  - Fields used/updated: `membership_type_id`, `expiration_date`, `balance_owed`, `original_membership_price`, `status` (if present), timestamps.
  - Indexes: ensure index on `id`, `membership_type_id`.

- `stsrc_member_family` (existing)
  - Add/confirm soft-delete columns: `deleted_at`, `is_deleted` (or existing equivalent).
  - Indexes: `(member_id, is_deleted)`.

- `stsrc_member_extra_members` (existing or analogous)
  - Extra member records with soft-delete support.
  - Indexes: `(member_id, is_deleted)`.

- `stsrc_member_renewals` (new)
  - `id` BIGINT PK AUTO_INCREMENT
  - `member_id` BIGINT NOT NULL FK -> `stsrc_members.id`
  - `season_key` VARCHAR(16) NOT NULL (example `2026`)
  - `old_membership_type_id` BIGINT NOT NULL
  - `new_membership_type_id` BIGINT NOT NULL
  - `payment_method` ENUM('card','ach','zelle','check') NOT NULL
  - `payment_context` VARCHAR(32) NOT NULL DEFAULT 'renewal'
  - `stripe_checkout_session_id` VARCHAR(255) NULL
  - `stripe_payment_intent_id` VARCHAR(255) NULL
  - `subtotal_amount` DECIMAL(10,2) NOT NULL
  - `processing_fee_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00
  - `total_amount` DECIMAL(10,2) NOT NULL
  - `previous_balance_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00
  - `status` ENUM('initiated','pending_payment','completed','failed','cancelled') NOT NULL
  - `transition_snapshot_json` LONGTEXT NOT NULL (selected members retained/removed/added)
  - `notes` TEXT NULL
  - `created_at` DATETIME NOT NULL
  - `updated_at` DATETIME NOT NULL
  - `completed_at` DATETIME NULL
  - Constraints/indexes:
    - Index `(member_id, season_key)`
    - Index `(status)`
    - Unique `(member_id, season_key, status)` may be emulated by application logic if DB engine/collation makes filtered unique constraints impractical.

## 5. Server Actions
### 5.1 Database Actions
- `getRenewalEligibility(memberId, seasonKey) -> { eligible, reason }`
  - Check member exists, renewal enabled, season not already renewed/pending.
  - SQL: select from `stsrc_member_renewals` by `member_id` and `season_key` where status in (`pending_payment`,`completed`).

- `calculateRenewalQuote(memberId, targetTypeId, familyPayload, extraPayload, paymentMethod) -> QuoteDTO`
  - Validate transition and capacities, compute subtotal/fees/total.
  - SQL: read membership type pricing + existing balance + active family/extra members.

- `createRenewalIntent(input) -> { renewalId, stripeSessionUrl? }`
  - Persist `initiated` renewal row with snapshot and amounts.
  - If Stripe method, create checkout session and store session id.
  - If non-Stripe, mark as `pending_payment` and update member pending balance/status.

- `finalizeRenewalFromStripe(sessionId, webhookEventId) -> { applied }`
  - Idempotency check by session id/event id.
  - Transactionally apply member updates, family/extra changes, mark renewal `completed`.

- `confirmOfflineRenewalPayment(renewalId, adminUserId) -> { applied }`
  - Admin-only action to mark pending non-Stripe as completed and activate season updates if deferred.

### 5.2 Other Actions
- External API integrations
  - Stripe Checkout Session API:
    - Endpoint: `POST /v1/checkout/sessions` via Stripe SDK.
    - Metadata fields: `payment_context=renewal`, `member_id`, `season_key`, `renewal_id`.
  - Stripe webhook endpoint (existing plugin route):
    - Handle `checkout.session.completed` and optionally `payment_intent.succeeded` as secondary confirmation.
- File handling procedures
  - Load email templates from `templates/email`.
  - Render using existing plugin mail rendering helper and sanitize output.

## 6. Design System
### 6.1 Visual Style
- Color palette (aligned to existing portal tokens)
  - Primary: `#1F4D3A`
  - Secondary: `#2E7D5B`
  - Neutral text: `#1F2937`
  - Border/background neutral: `#E5E7EB`
  - Warning: `#B45309`
  - Warning background: `#FEF3C7`
  - Success: `#166534`
- Typography
  - Font family: inherit existing portal stack (likely system sans/Inter-style stack).
  - Heading sizes: 24/20/18 px equivalent for section/title/card.
  - Body: 14-16 px; weight 400/500/600 for hierarchy.
- Component styling patterns
  - Membership cards use same border radius, padding, hover/selected states as registration.
  - Current plan badge and downgrade alert are persistent and high-contrast.
  - Order summary is sticky on desktop and inline stacked on mobile.
- Spacing and layout principles
  - 8px base spacing scale.
  - Two-column layout on >= 1024px (cards + summary), single-column on mobile.
  - Group destructive actions (member removals) under explicit subheadings.

## 8. Authentication & Authorization
- Member-facing renewal actions require authenticated portal session and ownership check (`current_user` linked to `member_id`).
- All state-changing endpoints require nonce verification and POST-only handling.
- Admin actions (`renewal_enabled`, offline confirmation) require capability checks (e.g., `manage_options` or plugin-specific capability).
- Do not trust client payload for member ids; resolve server-side from authenticated context.

## 9. Data Flow
- Server/client data passing mechanisms
  - Initial page load provides current member profile, active composition, available membership types, renewal enabled flag, and season key.
  - Client posts selection payload to quote endpoint for live totals and validation messages.
  - Final submit sends normalized payload and selected payment method; server revalidates and persists.
- State management architecture
  - Client state: ephemeral wizard state only (selected type, selected family retention, new additions, payment method).
  - Server state: authoritative for eligibility, pricing, and final updates.
  - Recommended response shape:
    - `quote`: `{subtotal, processing_fee, total, warnings[], transition_actions[]}`
    - `submit`: `{status, redirect_url?, renewal_id, message}`

## 10. Stripe Integration
- Webhook handling process
  - Extend existing webhook class to branch on `metadata.payment_context === 'renewal'`.
  - Validate event signature using existing Stripe secret.
  - On `checkout.session.completed`:
    - Read `renewal_id/member_id/season_key`.
    - Verify renewal record exists and not already completed.
    - Finalize renewal transactionally.
    - Send member/admin confirmation emails.
  - Log processing outcome for observability and replay safety.
- Product/Price configuration details
  - Use dynamic amount checkout (recommended) instead of static Stripe product catalog because totals include variable extras, fees, and prior balance.
  - Line item naming convention:
    - `Membership Renewal - {Membership Type} ({Season Key})`
    - Optional line item for `Processing Fee` when applicable.
  - Include currency and rounding logic consistent with registration flow.

Confirmed implementation decisions:
1. Existing `balance_owed` is included in renewal quote/checkout totals for one-time settlement.
2. Renewal supports Stripe (card/ACH) plus offline Zelle/check pending flow; no additional "Pay Later" mode is used.
3. Season key defaults to year-based format (`YYYY`) from renewal date context.
4. Offline confirmations are admin-only operations protected by capability and nonce checks.
5. Renewal completion updates and household reconciliation execute transactionally with rollback on failure.