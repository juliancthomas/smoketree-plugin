# Implementation Plan

## Renewal Foundation and Data Model
- [ ] Step 1: Add renewal feature toggle and seasonal gate checks
  - **Task**: Introduce `stsrc_renewal_enabled` control usage and centralized checks so renewal UI/actions are disabled unless the feature is enabled and the member is eligible for the season.
  - **Files**:
    - `admin/pages/class-stsrc-settings-page.php`: Add or wire up the renewal toggle field handling and persistence.
    - `public/class-stsrc-member-portal.php`: Gate renewal section visibility by settings and authenticated member context.
    - `includes/helpers/class-stsrc-renewal-helpers.php`: Add helper methods for `is_renewal_enabled()` and season key resolution.
  - **Step Dependencies**: None
  - **User Instructions**: In WordPress admin, verify the renewal toggle exists and can be switched on/off before starting feature QA.
  - **Git message**: `feat(renewal): add feature toggle and seasonal eligibility gates`

- [ ] Step 2: Create renewal ledger table and upgrade routine
  - **Task**: Add a dedicated renewal table to track one renewal per member per season, with status lifecycle and payment metadata to support auditability and idempotency.
  - **Files**:
    - `includes/database/class-stsrc-renewal-db.php`: Create table schema and CRUD helpers for renewal records.
    - `includes/class-stsrc-activator.php`: Register schema upgrade/version checks and run migration safely.
    - `uninstall.php`: Remove renewal table data when plugin uninstall cleanup is executed.
  - **Step Dependencies**: Step 1
  - **User Instructions**: After activation or upgrade, confirm the renewal table exists in DB and includes indexes on member/season/status.
  - **Git message**: `feat(database): add renewal ledger table and migration hooks`

- [ ] Step 3: Add renewal eligibility and idempotency queries
  - **Task**: Implement checks that prevent duplicate renewals for the same season from UI retries, parallel tabs, and webhook replay.
  - **Files**:
    - `includes/database/class-stsrc-renewal-db.php`: Add methods for eligibility lookup, duplicate detection, and status transitions.
    - `includes/services/class-stsrc-renewal-service.php`: Add `get_eligibility()` and guard rails for repeated submissions.
  - **Step Dependencies**: Step 2
  - **User Instructions**: Test by trying to submit the same renewal twice and verify the second action returns a deterministic "already renewed/pending" response.
  - **Git message**: `feat(renewal): enforce per-season renewal idempotency`

## Renewal Business Rules and Pricing Engine
- [ ] Step 4: Implement membership transition rule engine
  - **Task**: Codify all upgrade/downgrade rules (Civic/Duo/Single/Household), required family counts, and forced soft-deletion behavior for disallowed retained members.
  - **Files**:
    - `includes/services/class-stsrc-renewal-service.php`: Add transition validation and normalized transition action outputs.
    - `includes/database/class-stsrc-family-member-db.php`: Add helper operations for retain/remove/soft-delete workflows.
    - `includes/database/class-stsrc-extra-member-db.php`: Add helpers enforcing Household-only extras and max 3 extras.
  - **Step Dependencies**: Step 3
  - **User Instructions**: Validate all documented transitions using at least one fixture member per starting membership type.
  - **Git message**: `feat(renewal): add membership transition validation and reconciliation`

- [ ] Step 5: Implement renewal pricing and fee calculation service
  - **Task**: Build server-side quote calculation for membership base price, extras, existing `balance_owed`, and payment-method fees (card, ACH cap, Zelle/check no fee).
  - **Files**:
    - `includes/services/class-stsrc-renewal-pricing-service.php`: Create pricing engine methods and response DTO structure.
    - `includes/services/class-stsrc-renewal-service.php`: Integrate pricing service into quote/submit pipeline.
  - **Step Dependencies**: Step 4
  - **User Instructions**: Run pricing tests across all payment methods and verify fee math matches registration formulas.
  - **Git message**: `feat(renewal): add server-side renewal quote and processing fee logic`

- [ ] Step 6: Add renewal draft/intent creation workflow
  - **Task**: Persist renewal intents with full transition snapshot before payment, including method selection and authoritative totals.
  - **Files**:
    - `includes/services/class-stsrc-renewal-service.php`: Add intent creation method with snapshot JSON and status lifecycle.
    - `includes/database/class-stsrc-renewal-db.php`: Add insert/update methods for initiated and pending-payment states.
  - **Step Dependencies**: Step 5
  - **User Instructions**: Confirm intent rows are created before payment redirect and include selected plan/family composition snapshot.
  - **Git message**: `feat(renewal): persist renewal intents with transition snapshots`

## Member Portal Renewal Experience
- [ ] Step 7: Create renewal portal partial and membership card UI
  - **Task**: Add renewal section UI that mirrors registration card patterns, highlights current plan, displays benefits, and surfaces downgrade warnings.
  - **Files**:
    - `public/partials/renewal-section.php`: Create renewal cards, warnings, payment options, and summary container markup.
    - `public/css/member-portal.css`: Add renewal section styles consistent with existing portal design.
    - `public/class-stsrc-member-portal.php`: Render the renewal partial in the member portal flow.
  - **Step Dependencies**: Steps 1, 4
  - **User Instructions**: Verify visual consistency with existing registration cards and test responsive behavior on mobile widths.
  - **Git message**: `feat(portal): add renewal section cards and downgrade messaging`

- [ ] Step 8: Add frontend renewal state and dynamic summary updates
  - **Task**: Implement client-side interactions for membership selection, family/extra adjustments, payment method switching, and quote refresh calls.
  - **Files**:
    - `public/js/member-portal.js`: Add renewal interaction handlers and AJAX quote refresh logic.
    - `public/class-stsrc-public.php`: Localize renewal nonce, endpoints, and member context for JS.
  - **Step Dependencies**: Step 7
  - **User Instructions**: Test that changing payment method or member composition updates the order summary without page reload.
  - **Git message**: `feat(portal): add interactive renewal state and live order summary`

- [ ] Step 9: Add renewal AJAX/API endpoints (eligibility, quote, submit)
  - **Task**: Expose secure endpoints for eligibility checks, authoritative quote calculation, and final submit intent creation with nonce and ownership validation.
  - **Files**:
    - `includes/api/class-stsrc-renewal-api.php`: Create renewal endpoint handlers for eligibility/quote/submit.
    - `includes/class-smoketree-plugin.php`: Register AJAX or REST routes for renewal API handlers.
  - **Step Dependencies**: Steps 6, 8
  - **User Instructions**: Confirm unauthorized or tampered requests are rejected and valid member requests succeed.
  - **Git message**: `feat(api): add secure renewal eligibility quote and submit endpoints`

## Payment Completion and Record Updates
- [ ] Step 10: Add Stripe checkout session creation for renewals
  - **Task**: Create Stripe checkout sessions for card/ACH renewals using renewal metadata (`payment_context=renewal`, member/season/renewal ids).
  - **Files**:
    - `includes/services/class-stsrc-payment-service.php`: Add renewal checkout creation with dynamic line items and metadata.
    - `includes/services/class-stsrc-renewal-service.php`: Call payment service and return redirect URL to client.
  - **Step Dependencies**: Step 9
  - **User Instructions**: Configure Stripe webhook/event subscriptions for checkout completion in staging before production rollout.
  - **Git message**: `feat(payments): create stripe checkout sessions for renewals`

- [ ] Step 11: Extend Stripe webhook handler for renewal completion
  - **Task**: Add renewal branch in webhook processor with signature verification, idempotent event handling, and transactional completion.
  - **Files**:
    - `includes/api/class-smoketree-stripe-webhooks.php`: Route renewal events and call renewal finalization flow.
    - `includes/services/class-stsrc-renewal-service.php`: Add `finalize_stripe_renewal()` orchestration with no-op replay protection.
  - **Step Dependencies**: Step 10
  - **User Instructions**: Use Stripe CLI replay tests to confirm duplicate webhook delivery does not duplicate renewal effects.
  - **Git message**: `feat(webhooks): finalize renewals from stripe events idempotently`

- [ ] Step 12: Implement non-Stripe pending payment path (Zelle/Check)
  - **Task**: Complete deferred payment path by marking renewal as pending payment, updating member balances/status, and storing payment instructions context.
  - **Files**:
    - `includes/services/class-stsrc-renewal-service.php`: Add non-Stripe submit flow and pending status transition.
    - `includes/database/class-stsrc-member-db.php`: Add/update balance/status helper writes used by pending flow.
    - `includes/api/class-stsrc-renewal-api.php`: Wire non-Stripe submit response messaging and status.
  - **Step Dependencies**: Step 9
  - **User Instructions**: Verify member portal shows outstanding balance and pending renewal status after selecting Zelle/check.
  - **Git message**: `feat(renewal): add zelle-check pending payment workflow`

- [ ] Step 13: Apply transactional member updates on completion
  - **Task**: On completed renewal, update membership type, expiration date from season renewal date + expiration period, composition changes, and pricing fields atomically.
  - **Files**:
    - `includes/services/class-stsrc-renewal-service.php`: Add transactional persistence for final member updates and rollback handling.
    - `includes/database/class-stsrc-member-db.php`: Add targeted update helpers for renewal field writes.
    - `includes/database/class-stsrc-family-member-db.php`: Add batch soft-delete/upsert operations for family changes.
    - `includes/database/class-stsrc-extra-member-db.php`: Add batch soft-delete/upsert operations for extra members.
  - **Step Dependencies**: Steps 11, 12
  - **User Instructions**: Validate that failed partial update simulations rollback cleanly and keep renewal/member records consistent.
  - **Git message**: `feat(renewal): finalize member and household updates transactionally`

## Notifications and Admin Operations
- [ ] Step 14: Add member renewal confirmation email templates
  - **Task**: Create member-facing renewal emails for pool-access plans and civic plans, with conditional non-Stripe payment instruction content.
  - **Files**:
    - `templates/email/renewal-confirmation.php`: Add standard renewal confirmation template.
    - `templates/email/renewal-confirmation-civic.php`: Add civic-specific renewal confirmation template.
    - `includes/class-stsrc-email-service.php`: Add methods to render/send renewal member confirmations.
  - **Step Dependencies**: Step 13
  - **User Instructions**: Review wording and branding in both templates before enabling in production.
  - **Git message**: `feat(email): add member renewal confirmation templates`

- [ ] Step 15: Add admin renewal notice template and recipients
  - **Task**: Send admin notices for renewal submissions/completions including member details, old/new type, amount, and payment method to configured roles/emails.
  - **Files**:
    - `templates/email/renewal-admin-notice.php`: Add admin notification template.
    - `includes/class-stsrc-email-service.php`: Add admin renewal notice method and recipient aggregation logic.
    - `admin/pages/class-stsrc-settings-page.php`: Ensure secretary email parsing/validation supports comma-separated values.
  - **Step Dependencies**: Step 14
  - **User Instructions**: Confirm Treasurer, Secretary, President, and Vice President emails are configured and valid.
  - **Git message**: `feat(email): add admin renewal notice workflow and recipients`

- [ ] Step 16: Add admin offline payment confirmation action
  - **Task**: Provide admin-only action to confirm Zelle/check renewals and move pending records to completed with corresponding member activation updates.
  - **Files**:
    - `includes/api/class-stsrc-renewal-api.php`: Add admin confirm endpoint with capability and nonce checks.
    - `includes/services/class-stsrc-renewal-service.php`: Add `confirm_offline_payment()` method.
    - `admin/pages/class-stsrc-members-page.php`: Add renewal pending-payment controls on member/admin view.
  - **Step Dependencies**: Steps 12, 13
  - **User Instructions**: Test with a pending non-Stripe renewal and verify completion updates all related records consistently.
  - **Git message**: `feat(admin): add offline renewal payment confirmation action`

## Security, Testing, and Rollout
- [ ] Step 17: Perform renewal security hardening pass
  - **Task**: Ensure all renewal endpoints validate ownership, sanitize input, enforce nonces/capabilities, and escape output in renewal UI.
  - **Files**:
    - `includes/api/class-stsrc-renewal-api.php`: Harden validation/sanitization and permissions.
    - `public/partials/renewal-section.php`: Escape all output and validate displayed dynamic data.
    - `includes/services/class-stsrc-renewal-service.php`: Add defensive checks and normalized error objects.
  - **Step Dependencies**: Steps 9, 16
  - **User Instructions**: Run a manual tampering test by altering payload values in browser dev tools and confirm server rejects invalid data.
  - **Git message**: `fix(security): harden renewal endpoints and portal rendering`

- [ ] Step 18: Add renewal observability and failure logging
  - **Task**: Add structured logging for eligibility failures, quote mismatches, payment errors, and webhook retries to speed triage.
  - **Files**:
    - `includes/services/class-stsrc-renewal-service.php`: Add logging at critical decision/failure points.
    - `includes/api/class-smoketree-stripe-webhooks.php`: Add renewal-specific webhook processing logs.
  - **Step Dependencies**: Step 17
  - **User Instructions**: Verify logs appear for both successful and failed renewal attempts in your configured debug environment.
  - **Git message**: `chore(renewal): add structured logging for renewal lifecycle events`

- [ ] Step 19: Execute end-to-end renewal QA matrix and publish test notes
  - **Task**: Run scenario coverage across all membership transitions, payment methods, duplicate submission attempts, and webhook replay cases; document outcomes and fixes.
  - **Files**:
    - `docs/testing/renewal-test-report.md`: Add executed test matrix and pass/fail notes.
    - `prompts/tec-specs/renewal-tech-spec.md`: Update assumptions/decisions section with confirmed behavior (if needed).
  - **Step Dependencies**: Steps 11 through 18
  - **User Instructions**: Use Stripe test mode and Stripe CLI for webhook replay and failure simulation during QA.
  - **Git message**: `test(renewal): add end-to-end renewal scenario validation report`

## Summary
This plan delivers the renewal portal as a staged enhancement to the existing plugin, with business rules and pricing centralized in services, payment outcomes finalized idempotently, and member/admin communications integrated into the same lifecycle. The sequencing minimizes risk by introducing data model and eligibility safeguards first, then layering UX, API, payment, and completion logic in testable increments.

Key considerations for implementation are strict server-side recalculation of totals, one-renewal-per-season enforcement, and transactional member record updates to avoid partial state. The final security and QA steps ensure safe rollout before enabling the renewal toggle for members.
