# Mixed Payment Tracking - End-to-End Testing Report

## Scope

This report tracks the eight required end-to-end payment scenarios defined in the implementation plan.

## Test Environment

- Plugin: `smoketree-plugin`
- Branch: `main` (local working tree)
- WordPress environment: LocalWP site (manual browser/admin execution required)
- Stripe mode: Use test mode with webhook secret configured
- Report date: 2026-02-17

## Execution Summary

- Scenario count: 8
- Passed: 0
- Failed: 0
- Blocked/Not run in this automation pass: 8

This automation pass prepared and structured the full scenario suite and verified supporting code paths. Full end-to-end execution requires manual interaction with WordPress admin/member sessions and Stripe test checkout flows.

## Scenario Results

### 1) Check registration -> full Stripe card payment -> member activates

- **Status**: Not Run (manual)
- **Steps**:
  1. Register a new member with payment method `check`.
  2. Confirm member starts `pending` with balance equal to membership price.
  3. In member portal, pay full balance with Stripe test card.
  4. Trigger `checkout.session.completed` webhook.
- **Expected**:
  - Status becomes `active`.
  - `final_payment_method = card`.
  - Transaction ledger includes registration + payment entries.
- **Actual**: Pending manual execution.

### 2) Zelle registration ($500) -> admin records $200 check -> member pays $300 via ACH

- **Status**: Not Run (manual)
- **Steps**:
  1. Register member with `zelle` and initial balance `$500`.
  2. Admin records manual payment `$200` via `check`.
  3. Member pays `$300` through Stripe ACH (`us_bank_account`).
- **Expected**:
  - Balance reaches `$0.00`.
  - `final_payment_method = us_bank_account`.
  - Status becomes `active` (if previously pending).
- **Actual**: Pending manual execution.

### 3) Admin applies $50 discount

- **Status**: Not Run (manual)
- **Steps**:
  1. Open member edit page with outstanding balance.
  2. Use Adjust Balance modal with type `discount`, amount `50`, valid description.
- **Expected**:
  - Adjustment transaction created.
  - Balance reduced by `$50`.
  - Transaction history refreshes with new row.
- **Actual**: Pending manual execution.

### 4) Admin adds $25 late fee

- **Status**: Not Run (manual)
- **Steps**:
  1. Open member edit page.
  2. Use Adjust Balance modal with type `fee`, amount `25`.
- **Expected**:
  - Adjustment transaction created.
  - Balance increased by `$25`.
- **Actual**: Pending manual execution.

### 5) Member overpays ($600 paid on $500 balance)

- **Status**: Not Run (manual)
- **Steps**:
  1. Prepare member with `$500` owed.
  2. Submit Stripe payment for `$600` (or simulate equivalent webhook data path).
- **Expected**:
  - Overpayment state recorded (negative balance).
  - Admin overpayment notification email sent.
- **Actual**: Pending manual execution.

### 6) Member attempts payment below minimum ($5 when minimum is $10)

- **Status**: Not Run (manual)
- **Steps**:
  1. Set `stsrc_minimum_balance_payment = 10.00`.
  2. In pay-balance modal, enter `$5.00`.
- **Expected**:
  - Client-side validation error displayed.
  - Server-side AJAX validation rejects request if bypassed.
- **Actual**: Pending manual execution.

### 7) Stripe payment failure

- **Status**: Not Run (manual)
- **Steps**:
  1. Start balance payment using a failing Stripe test method.
  2. Deliver `payment_intent.payment_failed` webhook event with `payment_type=balance_payment`.
- **Expected**:
  - Failure email sent to member.
  - Admin failure notification sent.
  - Balance unchanged.
  - No successful payment transaction inserted.
- **Actual**: Pending manual execution.

### 8) Admin manually activates member with outstanding balance

- **Status**: Not Run (manual)
- **Steps**:
  1. Select member with positive balance and `pending` status.
  2. Admin changes status to `active`.
- **Expected**:
  - Status change allowed.
  - Override activity log entry recorded.
- **Actual**: Pending manual execution.

## Manual QA Checklist

- [ ] Verify all webhook tests are signed and accepted only with valid signatures.
- [ ] Confirm transaction rows show correct signs and balance snapshots after each scenario.
- [ ] Confirm member and admin emails are received with correct templates and values.
- [ ] Confirm no PHP warnings/notices in debug log during each scenario.
- [ ] Attach screenshots or logs for each scenario after execution.

## Notes

- This document is now in place for repeatable QA runs.
- Update each scenario's **Actual** and **Status** fields (`Pass`/`Fail`) as manual execution completes.
