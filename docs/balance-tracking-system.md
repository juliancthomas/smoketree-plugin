# Balance Tracking System

## Overview

The Mixed Payment and Balance Tracking system allows members to start with offline or deferred payment methods and complete payment later through Stripe. It uses a transaction ledger as the source of truth and keeps `balance_owed` on the member record as a cached snapshot for fast UI queries.

## Core Architecture

- **Members table (`wp_stsrc_members`)**
  - `balance_owed` - current remaining amount (can be negative for overpayment).
  - `original_membership_price` - registration-time membership price snapshot.
  - `final_payment_method` - most recent successful payment method.
- **Transactions table (`wp_stsrc_transactions`)**
  - Immutable ledger entries for `initial`, `payment`, `adjustment`, `fee`, and `refund`.
  - Includes `balance_after` snapshot per transaction.
  - Stores Stripe IDs for reconciliation (`stripe_payment_intent_id`, `stripe_session_id`, `stripe_charge_id`).

## Business Flow Summary

### Registration (non-Stripe)

1. Member registers with method such as `check` or `zelle`.
2. System stores original price and sets `balance_owed`.
3. Initial ledger entry is written.
4. Member remains `pending` until balance reaches `0` or below.

### Admin Balance Management

- Admins can:
  - Apply adjustments (discounts/fees/corrections).
  - Record manual payments (check/zelle/cash).
- Every operation:
  - Creates a transaction.
  - Updates member balance snapshot.
  - Triggers auto-activation logic when balance is paid.

### Member Portal Balance Payment

1. Member sees outstanding balance card and current-year transaction history.
2. Member enters payment amount in pay-balance modal.
3. AJAX endpoint validates ownership, nonce, minimum payment, and current balance.
4. Stripe Checkout session is created and member is redirected.

### Stripe Webhooks

- `checkout.session.completed`
  - Routes to balance payment flow when metadata `payment_type=balance_payment`.
  - Records payment transaction, updates balance, sends success/admin notifications.
- `payment_intent.payment_failed`
  - Handles failed balance payment notifications.
  - Leaves balance unchanged.

## Admin Usage

### Member Edit Page

- Account balance summary
- Transaction history table with year filter and pagination
- Adjust Balance modal
- Record Manual Payment modal

### Dashboard and Reporting

- Outstanding balances dashboard widget
- Members list balance column and filters
- Integrity tool for recalculating balances from ledger

## Member Usage

- Outstanding balance card appears when `balance_owed > 0`
- Transaction history shows current-year entries
- Pay Balance modal validates minimum payment and previews resulting balance

## Status and Activation Rules

- Pending members are auto-activated when `balance_owed <= 0`.
- Admin can manually activate with outstanding balance; override is logged.

## Data Integrity Notes

- Ledger is authoritative; member `balance_owed` is a performance cache.
- Recalculation tooling is provided to detect and repair drift.
- Webhook idempotency checks prevent duplicate Stripe payment processing.

## Security Notes

- Nonce validation is enforced on AJAX writes.
- Admin actions require `manage_options`.
- Member payment actions validate member ownership.
- Webhook signature verification is required before processing.

## Developer Integration Points

- **Actions**
  - `stsrc_member_auto_activated_after_balance_paid`
  - `stsrc_member_status_override_with_balance`
  - `stsrc_balance_payment_succeeded`
  - `stsrc_balance_payment_failed`
- **Primary services**
  - `STSRC_Balance_Service`
  - `STSRC_Payment_Service`
  - `STSRC_Transaction_DB`
  - `STSRC_Member_DB`
