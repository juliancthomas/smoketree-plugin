<specification_planning>
Core system architecture and workflows:
- Identify data sources: members table, new transactions table, Stripe events, admin actions.
- Map workflows: registration (non-Stripe), admin adjustments, manual payments, member balance payment, Stripe webhook completion, status auto-activation.
- Decide balance source of truth: transactions + snapshot balance_owed field.
- Define services/classes: transaction DB, balance service, payment service, webhooks, AJAX handlers.
Challenges/clarifications:
- Ensure balance_owed stays in sync with transaction ledger; define recalculation tool.
- Overpayment handling and admin notifications.
- Year filtering logic (calendar vs season renewal date).
Edge cases:
- Duplicate webhooks, partial failures, negative balances, admin overrides.

Project structure and organization:
- Place new DB, services, AJAX, webhooks, partials, emails in standard plugin folders.
- Ensure admin/public JS separation and nonce usage.
Challenges:
- Keep UI consistent with existing Tailwind-style classes.

Feature specifications:
- Define each feature with user stories, steps, validations, and UI behavior.
Challenges:
- Payment minimums, multi-method tracking, clear admin/member visibility.

Database schema design:
- Define transactions table, new columns in members, indexes, and migration strategy.
Challenges:
- Backfilling existing members, versioned dbDelta updates.

Server actions/integrations:
- Admin AJAX endpoints, Stripe checkout creation, webhook handlers, emails.
Challenges:
- Idempotency and signature verification.

Design system and component architecture:
- Balance cards, transaction tables, modals, responsive behaviors.
Challenges:
- Keep visual cues consistent; provide accessible states.

Authentication/authorization:
- Admin capabilities (manage_options), member access via portal session.
Challenges:
- Prevent cross-member data access.

Data flow/state management:
- Server-rendered sections with AJAX for updates; JS for modals and validation.
Challenges:
- Sync UI after transactions without full page reload.

Payment implementation:
- Stripe checkout for balance payments; metadata to identify flows.
Challenges:
- Payment failure handling and re-try flows.
</specification_planning>

```markdown
# Mixed Payment Type & Balance Tracking System Technical Specification

## 1. System Overview
- Core purpose and value proposition
  - Allow members who initially paid via non-Stripe methods to complete balances via Stripe.
  - Provide a transaction ledger and accurate balance tracking for admins and members.
  - Auto-activate members when balance is paid in full.
- Key workflows
  - Registration with non-Stripe payment -> pending status + initial transaction.
  - Admin adjustments and manual payments -> transaction + balance update.
  - Member portal balance payment -> Stripe checkout -> webhook -> transaction + balance update.
  - Auto-activation when balance <= 0.
- System architecture
  - Data layer: `wp_stsrc_members` (balance snapshot) + `wp_stsrc_transactions` (ledger).
  - Service layer: balance and payment services, Stripe webhooks.
  - UI layer: admin member edit enhancements, member portal balance card and history.

## 2. Project Structure
- `includes/database/`
  - `class-stsrc-transaction-db.php` (NEW)
  - `class-stsrc-member-db.php` (ENHANCE with balance methods)
- `includes/services/`
  - `class-stsrc-balance-service.php` (NEW)
  - `class-stsrc-payment-service.php` (ENHANCE)
- `includes/ajax/`
  - `class-stsrc-balance-ajax.php` (NEW)
- `includes/webhooks/`
  - `class-stsrc-stripe-webhooks.php` (NEW or ENHANCE existing handler)
- `admin/partials/`
  - `member-balance-section.php` (NEW)
  - `member-transaction-history.php` (NEW)
- `public/partials/`
  - `member-balance-card.php` (NEW)
  - `member-transaction-history.php` (NEW)
- `public/js/`
  - `balance-payment.js` (NEW)
- `emails/`
  - `balance-payment-success.php` (NEW)
  - `balance-payment-failed.php` (NEW)
  - `notify-admin-balance-payment.php` (NEW)
  - `notify-admin-overpayment.php` (NEW)
  - `manual-payment-received.php` (NEW)

## 3. Feature Specification

### 3.1 Transactions Ledger and Balance Tracking
- User story and requirements
  - As an admin, I need a complete ledger of all payments and adjustments per member.
  - As a member, I need to see my balance and history for the current year.
- Detailed implementation steps
  - Create `wp_stsrc_transactions` table.
  - Add `balance_owed`, `original_membership_price`, `final_payment_method` to members.
  - Update balance after any transaction and store `balance_after` on the transaction.
  - Provide member and admin views to read ledger and summary.
- Error handling and edge cases
  - If balance recalculation fails, log error and show admin notice.
  - Prevent negative or zero amount for manual payment entries.
  - Handle overpayments (balance < 0) and notify admin.

### 3.2 Registration with Non-Stripe Payment
- User story and requirements
  - As a member registering via check/Zelle/pay later, I should be pending and have a tracked balance.
- Detailed implementation steps
  - On registration with non-Stripe payment method:
    - Set `original_membership_price` to membership price.
    - Set `balance_owed` to membership price.
    - Insert initial transaction with `transaction_type = 'initial'`, amount 0.00, `balance_after` = price.
    - Set status to `pending`.
- Error handling and edge cases
  - If initial transaction insert fails, roll back balance changes and log.

### 3.3 Admin Balance Management
- User story and requirements
  - As an admin, I need to view balances, adjust balances, and record manual payments.
- Detailed implementation steps
  - Add account balance section to member edit page with totals and status badge.
  - Add transaction history table with filters, sorting, and pagination.
  - Add "Adjust Balance" modal with adjustment type, amount, description, notes.
  - Add "Record Manual Payment" modal with method, amount, date, and optional check number.
  - On save:
    - Create transaction with appropriate type/method.
    - Update balance.
    - Update `final_payment_method` for manual payments.
    - Auto-activate if balance <= 0 and status is pending.
    - Send manual payment email to member.
- Error handling and edge cases
  - Validate amount, required description, and minimums.
  - Use capability checks and nonce validation for all admin actions.

### 3.4 Member Portal Balance and Payments
- User story and requirements
  - As a member, I need to see my balance and pay via Stripe.
- Detailed implementation steps
  - Show balance card if `balance_owed > 0` with totals and CTA.
  - Show transaction history for the current year with badges and colors.
  - Payment modal:
    - Prefill amount with balance.
    - Validate against minimum payment setting.
    - Show real-time "balance after" preview.
  - On "Continue to Payment", call AJAX to create Stripe checkout session and redirect.
- Error handling and edge cases
  - Display validation errors if below minimum.
  - Show retry options on payment failure.

### 3.5 Admin Settings: Minimum Balance Payment
- User story and requirements
  - As an admin, I need to set a minimum balance payment amount.
- Detailed implementation steps
  - Add settings field `stsrc_minimum_balance_payment`.
  - Validate value > 0 and use in payment validation and service.
- Error handling and edge cases
  - If setting missing, fallback to default 10.00.

### 3.6 Reporting and List Views
- User story and requirements
  - As a treasurer/admin, I need a snapshot of outstanding balances.
- Detailed implementation steps
  - Dashboard widget showing counts and totals.
  - Add balance column and filters in member list.
  - Bulk action to send payment reminders.
- Error handling and edge cases
  - Ensure filters and bulk actions respect capability checks.

## 4. Database Schema

### 4.1 Tables
- `wp_stsrc_transactions`
  - `transaction_id` BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY
  - `member_id` BIGINT(20) UNSIGNED NOT NULL (FK to members)
  - `transaction_type` ENUM('payment','adjustment','refund','fee','initial') NOT NULL
  - `payment_method` ENUM('check','zelle','card','us_bank_account','admin_adjustment','initial') DEFAULT NULL
  - `amount` DECIMAL(10,2) NOT NULL
  - `balance_after` DECIMAL(10,2) NOT NULL
  - `stripe_payment_intent_id` VARCHAR(255) DEFAULT NULL
  - `stripe_charge_id` VARCHAR(255) DEFAULT NULL
  - `stripe_session_id` VARCHAR(255) DEFAULT NULL
  - `description` TEXT NOT NULL
  - `admin_user_id` BIGINT(20) UNSIGNED DEFAULT NULL
  - `admin_notes` TEXT DEFAULT NULL
  - `created_at` DATETIME NOT NULL
  - Indexes: `member_id`, `transaction_type`, `created_at`, `stripe_payment_intent_id`
- `wp_stsrc_members` additions
  - `balance_owed` DECIMAL(10,2) NOT NULL DEFAULT 0.00 (INDEX)
  - `original_membership_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00
  - `final_payment_method` VARCHAR(20) DEFAULT NULL

## 5. Server Actions

### 5.1 Database Actions
- `Transaction_DB::create_transaction($member_id, $data)`
  - Inserts transaction, calculates `balance_after` (or use provided).
  - Returns transaction ID or WP_Error.
- `Transaction_DB::get_transactions($member_id, $year, $page, $per_page)`
  - Returns transactions filtered by year and paginated.
- `Transaction_DB::get_total_paid($member_id)`
  - Sums payment transactions.
- `Transaction_DB::get_total_adjustments($member_id)`
  - Sums adjustment transactions.
- `Member_DB::update_balance($member_id, $new_balance, $final_payment_method = null)`
  - Updates balance snapshot and optional final method.
- `Member_DB::calculate_member_balance($member_id)`
  - Sums transactions to verify `balance_owed`.

### 5.2 Other Actions
- AJAX: `handle_create_balance_payment`
  - Validates nonce, member ownership, minimum payment.
  - Calls payment service to create checkout session.
- AJAX: `handle_admin_adjust_balance`
  - Validates admin capability and inputs.
  - Inserts adjustment transaction, updates balance.
- AJAX: `handle_admin_record_payment`
  - Validates admin capability and inputs.
  - Inserts payment transaction, updates balance, emails member.
- Webhook: `checkout.session.completed`
  - Verify signature.
  - If `metadata.payment_type == 'balance_payment'`, insert payment transaction, update balance, set final method, auto-activate.
  - Email success and admin notification; email overpayment alert if balance < 0.
- Webhook: `payment_intent.payment_failed`
  - If metadata indicates balance payment, send failure emails.

## 6. Design System

### 6.1 Visual Style
- Color palette
  - Outstanding balance: yellow/orange gradient background.
  - Paid in full: green background.
  - Payments/credits: green text.
  - Fees/charges: red text.
  - Adjustments: blue text.
- Typography
  - Large bold balance amount (e.g., 28-32px equivalent).
  - Standard body text for descriptions (14-16px).
- Component styling patterns
  - Card with rounded corners and shadow.
  - Pills/badges for payment method.
  - Modal with centered layout and two-step confirmation.
- Spacing and layout principles
  - Consistent padding with existing Tailwind-style utility spacing.
  - Mobile-first responsive layout with stacked columns.

## 8. Authentication & Authorization
- Member portal access via logged-in member or existing portal auth.
- Admin actions restricted to `manage_options` (or existing admin capability).
- Nonce validation for all AJAX endpoints.
- Stripe webhook signature verification required for all webhooks.

## 9. Data Flow
- Server/client data passing mechanisms
  - Member portal loads balance and history server-side.
  - AJAX endpoints handle adjustments, manual payments, and payment session creation.
  - Webhooks update balances after Stripe events.
- State management architecture
  - Use localized JS data (nonce, member ID) in `balance-payment.js`.
  - Refresh transaction history via AJAX after updates.
  - Balance summary recalculated on server to keep UI in sync.

## 10. Stripe Integration
- Webhook handling process
  - Validate signature.
  - Route to balance payment handler when metadata indicates `balance_payment`.
  - Store Stripe IDs on transaction for reconciliation.
  - Ensure idempotency by checking existing `stripe_payment_intent_id` or `stripe_session_id`.
- Product/Price configuration details
  - Use dynamic line items with label "Balance Payment for [Membership Type] Membership".
  - Amount comes from user input, validated against minimum.
  - Metadata: `payment_type`, `member_id`, `original_balance`, `payment_amount`.
```
