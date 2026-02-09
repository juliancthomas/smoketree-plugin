# Mixed Payment Type & Balance Tracking System

## Project Description
Enhance the Smoketree membership plugin to support members who start with non-Stripe payment methods (check, Zelle, pay later) but later want to complete their balance using Stripe (credit card or ACH). The system will track all payments as individual transactions, maintain an account balance for pending members, and allow both admins and members to manage payment completion. When a member's balance reaches $0, their status will automatically change from 'pending' to 'active'.

## Target Audience
- **Primary**: Club members with pending membership status who need to complete payment
- **Secondary**: Club administrators managing member accounts and payment reconciliation
- **Tertiary**: Club treasurer tracking manual vs. automated payments and handling overpayments

## Desired Features

### Database Changes
- [ ] Create new `wp_stsrc_transactions` table
    - [ ] `transaction_id` BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY
    - [ ] `member_id` BIGINT(20) UNSIGNED NOT NULL (foreign key)
    - [ ] `transaction_type` ENUM('payment', 'adjustment', 'refund', 'fee', 'initial') NOT NULL
    - [ ] `payment_method` ENUM('check', 'zelle', 'card', 'us_bank_account', 'admin_adjustment', 'initial') DEFAULT NULL
    - [ ] `amount` DECIMAL(10,2) NOT NULL - can be positive (payment/charge) or negative (credit/refund)
    - [ ] `balance_after` DECIMAL(10,2) NOT NULL - snapshot of balance after this transaction
    - [ ] `stripe_payment_intent_id` VARCHAR(255) DEFAULT NULL
    - [ ] `stripe_charge_id` VARCHAR(255) DEFAULT NULL
    - [ ] `stripe_session_id` VARCHAR(255) DEFAULT NULL
    - [ ] `description` TEXT NOT NULL - user-facing description
    - [ ] `admin_user_id` BIGINT(20) UNSIGNED DEFAULT NULL - who made the adjustment
    - [ ] `admin_notes` TEXT DEFAULT NULL - internal notes, not shown to member
    - [ ] `created_at` DATETIME NOT NULL
    - [ ] KEY `member_id` (member_id)
    - [ ] KEY `transaction_type` (transaction_type)
    - [ ] KEY `created_at` (created_at)
    - [ ] KEY `stripe_payment_intent_id` (stripe_payment_intent_id)

- [ ] Add new fields to `wp_stsrc_members` table
    - [ ] `balance_owed` DECIMAL(10,2) NOT NULL DEFAULT 0.00 - current balance (calculated from transactions)
    - [ ] `original_membership_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 - snapshot at registration
    - [ ] `final_payment_method` VARCHAR(20) DEFAULT NULL - last payment method that paid toward balance
    - [ ] Keep existing `payment_type` field (shows initial payment method selected at registration)
    - [ ] INDEX on `balance_owed` (for querying members with outstanding balances)

- [ ] Database upgrade routine
    - [ ] Create transactions table if not exists
    - [ ] Add new columns to members table if not exists
    - [ ] For existing members: populate `original_membership_price` from their membership_type price
    - [ ] For existing members: set `balance_owed` to 0 if status is 'active'

### Registration Flow Changes
- [ ] When member registers with non-Stripe payment method (check, zelle, pay_later)
    - [ ] Set `original_membership_price` to membership type price
    - [ ] Set `balance_owed` to membership type price
    - [ ] Create initial transaction record:
        - [ ] `transaction_type`: 'initial'
        - [ ] `payment_method`: 'initial'
        - [ ] `amount`: 0.00
        - [ ] `balance_after`: membership price
        - [ ] `description`: "Initial membership registration - [Membership Type] ($XXX.XX)"
    - [ ] Set status to 'pending'

### Admin Settings Page
- [ ] Add new Payment Settings section
    - [ ] `stsrc_minimum_balance_payment` (Number field)
        - [ ] Label: "Minimum Balance Payment Amount"
        - [ ] Description: "Minimum amount members can pay toward their balance via Stripe (e.g., $10.00)"
        - [ ] Default: 10.00
        - [ ] Validation: Must be > 0

### Admin Balance Management
- [ ] Enhance member edit page (list and single member views)
    - [ ] Add "Account Balance" section showing:
        - [ ] Membership type name and original price
        - [ ] Total paid to date (sum of positive payment transactions)
        - [ ] Total adjustments (sum of adjustment transactions)
        - [ ] **Current Balance Owed** (large, prominent display)
        - [ ] Status badge (Paid in Full / Outstanding / Overpaid)
    
- [ ] Transaction History table (on member edit page)
    - [ ] Filterable by current membership year (based on member's created_at or season_renewal_date)
    - [ ] Columns: Date, Type, Payment Method, Description, Amount, Balance After, Admin
    - [ ] Color coding: green for payments, blue for adjustments, red for fees/charges, yellow for initial
    - [ ] Sortable by date (default: newest first)
    - [ ] Pagination (20 per page)
    - [ ] Show admin name for adjustments (link to admin user profile)
    - [ ] Show Stripe transaction links (if applicable)
    
- [ ] "Adjust Balance" functionality
    - [ ] Button opens modal dialog
    - [ ] Form fields:
        - [ ] Adjustment Type (dropdown): 'discount', 'credit', 'late_fee', 'processing_fee', 'refund', 'other'
        - [ ] Amount (number field, can be positive or negative)
            - [ ] Positive = adds to balance (charge/fee)
            - [ ] Negative = reduces balance (discount/credit)
        - [ ] Description (text field, required) - member-facing
        - [ ] Admin Notes (textarea, optional) - internal only
    - [ ] Real-time preview: "New balance will be: $XXX.XX"
    - [ ] Confirmation step: "This will change balance from $XX.XX to $YY.YY. Continue?"
    - [ ] On save:
        - [ ] Create transaction record with `transaction_type: 'adjustment'`, `payment_method: 'admin_adjustment'`
        - [ ] Update member's `balance_owed`
        - [ ] Log `admin_user_id` as current admin user
        - [ ] If new balance = $0 and status is 'pending', change status to 'active'
        - [ ] Show success message
        - [ ] Refresh transaction history table

- [ ] "Record Manual Payment" functionality
    - [ ] Separate button for recording payments received offline
    - [ ] Form fields:
        - [ ] Payment Method (dropdown): 'check', 'zelle', 'cash'
        - [ ] Amount (number, required, must be > 0)
        - [ ] Check Number (text, optional, shown if payment_method = 'check')
        - [ ] Description (text, required) - e.g., "Check #1234 received"
        - [ ] Date Received (date field, defaults to today)
        - [ ] Admin Notes (textarea, optional)
    - [ ] On save:
        - [ ] Create transaction record with `transaction_type: 'payment'`
        - [ ] Update member's `balance_owed` (subtract payment amount)
        - [ ] Update member's `final_payment_method` to the payment method used
        - [ ] If balance becomes $0 or negative, change status from 'pending' to 'active'
        - [ ] Send email to member confirming payment received

### Member Portal Enhancements
- [ ] Balance Overview Card (show only if balance_owed > 0)
    - [ ] Prominent placement at top of member portal
    - [ ] Display:
        - [ ] "Outstanding Balance" heading
        - [ ] Large balance amount with dollar sign
        - [ ] Membership type and original price
        - [ ] "Total Paid: $XXX.XX"
        - [ ] "Remaining: $XXX.XX"
    - [ ] Attention-grabbing styling (yellow/orange accent)
    - [ ] "Pay Balance" button (primary action)
    
- [ ] Transaction History Section
    - [ ] Heading: "Payment History"
    - [ ] Show only current year's transactions (based on created_at year)
    - [ ] Display for each transaction:
        - [ ] Date (formatted: "Jan 15, 2026")
        - [ ] Description
        - [ ] Payment method badge (if applicable)
        - [ ] Amount (color: green for payments/credits, red for fees/charges)
    - [ ] Sort newest first
    - [ ] Show admin adjustments with note: "Adjusted by admin"
    - [ ] Collapsible section if more than 5 transactions
    - [ ] Empty state: "No payment history for current year"

- [ ] "Pay Balance" Functionality
    - [ ] Button opens payment modal/form
    - [ ] Display current balance owed
    - [ ] Payment amount input field
        - [ ] Pre-filled with full balance amount
        - [ ] Editable by member
        - [ ] Validation: Must be >= minimum payment setting
        - [ ] Validation: Show error if less than minimum
        - [ ] Real-time preview: "After this payment, your balance will be: $XXX.XX"
    - [ ] Payment method selection (via Stripe)
        - [ ] Credit/Debit Card
        - [ ] ACH / Bank Account
    - [ ] "Continue to Payment" button
        - [ ] Creates Stripe checkout session
        - [ ] Passes metadata: `payment_type: 'balance_payment'`, `member_id`, `amount`
        - [ ] Success URL: `/member-portal?payment=success&session_id={CHECKOUT_SESSION_ID}`
        - [ ] Cancel URL: `/member-portal?payment=cancelled`

### Stripe Integration & Webhooks
- [ ] New Payment Service method: `create_balance_payment_checkout_session()`
    - [ ] Parameters: `member_id`, `amount`
    - [ ] Validates: amount >= minimum setting
    - [ ] Validates: member exists and has balance owed
    - [ ] Creates Stripe checkout session with:
        - [ ] Line item: "Balance Payment for [Membership Type] Membership"
        - [ ] Customer: member's stripe_customer_id (or create if null)
        - [ ] Metadata: `payment_type: 'balance_payment'`, `member_id`, `original_balance`, `payment_amount`
    - [ ] Returns: checkout session URL or false

- [ ] Enhance webhook handler in `class-stsrc-payment-service.php` or create dedicated webhook class
    - [ ] Handle `checkout.session.completed` for balance payments
        - [ ] Check if `metadata.payment_type === 'balance_payment'`
        - [ ] Extract: member_id, payment_amount, session_id, payment_intent, payment_method
        - [ ] Create transaction record:
            - [ ] `transaction_type`: 'payment'
            - [ ] `payment_method`: derived from Stripe payment method (card, us_bank_account)
            - [ ] `amount`: negative (reduces balance)
            - [ ] `stripe_session_id`, `stripe_payment_intent_id`, `stripe_charge_id`
            - [ ] `description`: "Online payment via [Card/ACH]"
        - [ ] Calculate new balance: `balance_owed - payment_amount`
        - [ ] Update member record:
            - [ ] Set `balance_owed` to new balance
            - [ ] Set `final_payment_method` to payment method used
            - [ ] If `balance_owed <= 0`, change `status` to 'active'
        - [ ] Send email: `balance-payment-success.php`
        - [ ] If overpaid (balance < 0), send email: `notify-admin-overpayment.php`
        
    - [ ] Handle `payment_intent.payment_failed`
        - [ ] Check if related to balance payment via metadata
        - [ ] Log failure
        - [ ] Send email: `balance-payment-failed.php` to member
        - [ ] Send email to admin: `notify-admin-payment-failed.php`

### Email Templates
- [ ] Create `balance-payment-success.php`
    - [ ] Subject: "Payment Received - Smoketree Membership"
    - [ ] Content:
        - [ ] Thank member for payment
        - [ ] Amount paid
        - [ ] New balance (or "Paid in Full" if $0)
        - [ ] Payment method used
        - [ ] Transaction date
        - [ ] Link to view full transaction history in portal
        - [ ] If balance = $0: "Your membership is now active!"
        
- [ ] Create `balance-payment-failed.php`
    - [ ] Subject: "Payment Failed - Smoketree Membership"
    - [ ] Content:
        - [ ] Payment attempt failed
        - [ ] Reason (if available from Stripe)
        - [ ] Attempted amount
        - [ ] Current balance still owed
        - [ ] "Retry Payment" button/link
        - [ ] Alternative payment methods (Zelle, check)
        
- [ ] Create `notify-admin-balance-payment.php`
    - [ ] Subject: "Balance Payment Received - [Member Name]"
    - [ ] Content:
        - [ ] Member name and email
        - [ ] Amount paid
        - [ ] Payment method
        - [ ] New balance
        - [ ] If balance = $0: "Member automatically activated"
        - [ ] Link to member admin page
        
- [ ] Create `notify-admin-overpayment.php`
    - [ ] Subject: "Member Overpayment Alert - [Member Name]"
    - [ ] Content:
        - [ ] Member name and email
        - [ ] Amount overpaid (absolute value of negative balance)
        - [ ] Payment details (method, amount, date)
        - [ ] Suggested action: "Please contact member to arrange refund"
        - [ ] Link to member admin page

- [ ] Create `manual-payment-received.php` (sent when admin records manual payment)
    - [ ] Subject: "Payment Confirmation - Smoketree Membership"
    - [ ] Content:
        - [ ] Confirm payment received
        - [ ] Payment method and amount
        - [ ] Date received
        - [ ] New balance (or "Paid in Full")
        - [ ] If balance = $0: "Your membership is now active!"

### Status Management
- [ ] Automatic status change logic
    - [ ] When `balance_owed` becomes <= 0:
        - [ ] If current status is 'pending', change to 'active'
        - [ ] Trigger membership activation actions (send welcome email, create WP user if needed, etc.)
    - [ ] When admin manually changes status to 'active' even with balance:
        - [ ] Allow it (admin override)
        - [ ] Add note in activity log: "Admin activated member with outstanding balance of $XX.XX"

### Admin Reporting
- [ ] Dashboard widget: "Outstanding Balances"
    - [ ] Total members with balance > 0
    - [ ] Total dollars outstanding
    - [ ] Average balance owed
    - [ ] Link to "View All" → filtered member list
    
- [ ] Member list table enhancements
    - [ ] Add "Balance" column (sortable)
    - [ ] Filter dropdown: "Balance Status"
        - [ ] All
        - [ ] Paid in Full ($0)
        - [ ] Outstanding (> $0)
        - [ ] Overpaid (< $0)
    - [ ] Bulk actions: "Send Payment Reminder Email"

## Design Requests
- [ ] Balance display card (member portal)
    - [ ] Yellow/orange gradient background for outstanding balance
    - [ ] Green background if balance = $0
    - [ ] Large, bold typography for balance amount ($XXX.XX)
    - [ ] Clear call-to-action button: "Pay Balance Now"
    - [ ] Card shadow and rounded corners for premium feel
    
- [ ] Transaction history table
    - [ ] Clean, striped table rows (alternating background)
    - [ ] Color-coded amounts:
        - [ ] Green: payments and credits (negative amounts)
        - [ ] Red: fees and charges (positive amounts)
    - [ ] Payment method badges (small colored pills)
    - [ ] Mobile-responsive: stack columns on mobile, most important info first
    
- [ ] Admin adjustment modal
    - [ ] Clean, centered modal overlay
    - [ ] Clear form labels and help text
    - [ ] Real-time calculation preview (prominent)
    - [ ] Two-step process: Form → Confirmation
    - [ ] Success animation when saved
    
- [ ] Payment amount input
    - [ ] Large, easy-to-tap input field
    - [ ] Dollar sign prefix
    - [ ] Show min/max validation in real-time
    - [ ] Preview calculation below input
    
- [ ] Consistent with existing Tailwind-style classes
    - [ ] Use plugin's existing design system
    - [ ] Match button styles, colors, spacing
    - [ ] Responsive breakpoints consistent with rest of portal

## Technical Implementation Notes

### Database Class Structure
- [ ] Create `class-stsrc-transaction-db.php`
    - [ ] `create_transaction()` - insert new transaction
    - [ ] `get_transactions()` - get transactions for member (with year filter)
    - [ ] `get_transaction()` - get single transaction by ID
    - [ ] `get_total_paid()` - sum of payment transactions for member
    - [ ] `get_total_adjustments()` - sum of adjustment transactions
    - [ ] `get_balance_summary()` - return array with original price, total paid, balance owed
    - [ ] All methods include proper sanitization and validation

- [ ] Enhance `class-stsrc-member-db.php`
    - [ ] `update_balance()` - update balance_owed field
    - [ ] `get_members_with_balance()` - members where balance_owed > 0
    - [ ] `calculate_member_balance()` - recalculate from transactions (for data integrity checks)

### AJAX Handlers
- [ ] Create `class-stsrc-balance-ajax.php`
    - [ ] `handle_create_balance_payment()` - creates Stripe checkout session
    - [ ] `handle_admin_adjust_balance()` - admin adjustment submission
    - [ ] `handle_admin_record_payment()` - record manual payment
    - [ ] `handle_load_transactions()` - load transaction history (with pagination)
    - [ ] All handlers check nonces and capabilities

### Payment Service Enhancements
- [ ] Add to `class-stsrc-payment-service.php`:
    - [ ] `create_balance_payment_checkout_session()` method
    - [ ] `get_minimum_balance_payment()` - retrieve setting
    - [ ] Consider refactoring webhook handler into separate class for maintainability

### Data Integrity
- [ ] Add cron job or admin tool to verify balance calculations
    - [ ] Compare `balance_owed` field vs. calculated sum from transactions
    - [ ] Report any discrepancies to admin
    - [ ] Provide "Recalculate Balances" button in admin

### Migration Strategy
- [ ] Database upgrade routine in plugin activation
- [ ] For existing members with status='active': set balance_owed = 0, create initial transaction showing paid
- [ ] For existing members with status='pending': 
    - [ ] Set balance_owed = membership type price
    - [ ] Create initial transaction
- [ ] Add `dbDelta()` calls for new table and columns
- [ ] Version tracking to prevent duplicate migrations

### Code Organization
```
includes/
  database/
    class-stsrc-transaction-db.php (NEW)
  services/
    class-stsrc-payment-service.php (ENHANCE)
    class-stsrc-balance-service.php (NEW - business logic for balance operations)
  ajax/
    class-stsrc-balance-ajax.php (NEW)
  webhooks/
    class-stsrc-stripe-webhooks.php (NEW or ENHANCE existing webhook handler)
admin/
  partials/
    member-balance-section.php (NEW - balance UI for admin member edit page)
    member-transaction-history.php (NEW - transaction table for admin)
public/
  partials/
    member-balance-card.php (NEW - balance card for member portal)
    member-transaction-history.php (NEW - transaction list for member portal)
  js/
    balance-payment.js (NEW - handle payment modal, amount validation, AJAX)
emails/
  balance-payment-success.php (NEW)
  balance-payment-failed.php (NEW)
  notify-admin-balance-payment.php (NEW)
  notify-admin-overpayment.php (NEW)
  manual-payment-received.php (NEW)
```

## Other Notes & Considerations

### Security
- [ ] All AJAX handlers verify nonces
- [ ] Admin actions check `manage_options` capability
- [ ] Sanitize all inputs (amounts, descriptions, admin notes)
- [ ] Validate Stripe webhook signatures
- [ ] Prevent SQL injection with prepared statements

### Testing Scenarios
- [ ] Member registers with "check", pays full balance via Stripe card → status becomes active, final_payment_method = 'card'
- [ ] Member registers with "zelle" for $500, admin records $200 check payment, member pays $300 via ACH → final_payment_method = 'us_bank_account'
- [ ] Admin applies $50 discount → transaction created, balance reduced
- [ ] Admin adds $25 late fee → transaction created, balance increased
- [ ] Member accidentally pays $600 for $500 membership → overpayment email sent to admin
- [ ] Member tries to pay $5 when minimum is $10 → validation error
- [ ] Stripe payment fails → failure email sent, balance unchanged
- [ ] Admin manually activates member with outstanding balance → allowed, activity logged

### Performance
- [ ] Index on `balance_owed` for fast queries
- [ ] Index on `member_id` in transactions table
- [ ] Pagination for transaction history (20 per page)
- [ ] Cache balance calculations where appropriate

### Future Enhancements (Out of Scope)
- Payment plans (automatic installments)
- Automatic late fees
- Payment reminders based on due dates
- Batch refund processing
- Stripe Connect for marketplace scenarios

### Questions Resolved
✅ Auto-activation: Yes, automatically change to 'active' when balance = $0
✅ Minimum payment: Admin setting in settings page
✅ Initial transaction: Create immediately at registration with $0 paid
✅ Payment amount: Member can choose (must meet minimum)
✅ Expiration dates: No changes needed
✅ Transaction visibility: Members see all transactions including admin adjustments
✅ Overpayment: Send email to admin/treasurer with details

## Additional Questions for Refinement

1. **Admin "Record Manual Payment" location**: Should this be:
   - A button on the member edit page (next to "Adjust Balance")?
   - Available from the member list as a bulk action?
   - Both?

2. **Payment confirmation emails**: When admin records a manual payment, should we automatically send a confirmation email to the member, or should the admin decide (checkbox)?

3. **Transaction descriptions**: For Stripe payments, should we include the last 4 digits of the card/account? (e.g., "Payment via card ending in 4242")

4. **Dashboard widget**: Should the "Outstanding Balances" widget also show a breakdown by payment type? (e.g., "10 members registered with 'check' still owe $2,500")

5. **Bulk payment reminders**: If we add a bulk action to send payment reminders, should there be a minimum balance threshold? (e.g., only send if balance > $50)

6. **Year filtering logic**: For transaction history, should "current year" be:
   - Calendar year (Jan 1 - Dec 31)?
   - Or based on your season renewal date setting?
