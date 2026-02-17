# Mixed Payment Type & Balance Tracking System - Implementation Plan

## Section 1: Database Foundation

- [x] Step 1.1: Create Transactions Table and Enhance Members Table
  - **Task**: Create the new `wp_stsrc_transactions` table with all required fields and indexes. Add new balance-related columns to the existing `wp_stsrc_members` table. Implement database upgrade routine with version tracking to prevent duplicate migrations.
  - **Files**: Maximum 2 files
    - `includes/database/class-stsrc-transaction-db.php`: Create new class with table creation method using dbDelta, including all fields (transaction_id, member_id, transaction_type, payment_method, amount, balance_after, stripe fields, description, admin fields, created_at) and indexes (member_id, transaction_type, created_at, stripe_payment_intent_id)
    - `includes/database/class-stsrc-member-db.php`: Add method to enhance table with new columns (balance_owed with INDEX, original_membership_price, final_payment_method) using dbDelta
  - **Step Dependencies**: None - this is the foundation
  - **User Instructions**: After deployment, verify in phpMyAdmin that the new `wp_stsrc_transactions` table exists and the `wp_stsrc_members` table has the three new columns
  - **Git message**: `feat(database): add transactions table and balance tracking columns`
  - **Status**: ✅ COMPLETED

- [x] Step 1.2: Database Migration and Data Backfill
  - **Task**: Create activation hook handler to run database migrations. For existing members, backfill `original_membership_price` from their membership type, set `balance_owed` to 0 for active members, and create initial transaction records for all existing members indicating their paid status or outstanding balance.
  - **Files**: Maximum 3 files
    - `includes/class-stsrc-activator.php`: Enhance or create plugin activator class with database upgrade routine that calls transaction and member table creation methods, checks plugin version option, and runs backfill logic
    - `includes/database/class-stsrc-transaction-db.php`: Add method `backfill_initial_transactions()` to create initial transaction records for existing members
    - `includes/database/class-stsrc-member-db.php`: Add method `backfill_balance_fields()` to populate balance_owed and original_membership_price for existing members
  - **Step Dependencies**: Step 1.1 (tables must exist)
  - **User Instructions**: Test on staging environment first. Back up database before activating. After activation, verify a few existing member records have appropriate balance_owed and initial transaction entries.
  - **Git message**: `feat(database): implement migration and backfill for existing members`
  - **Status**: ✅ COMPLETED

## Section 2: Core Service Layer

- [x] Step 2.1: Transaction Database Class - Core Methods
  - **Task**: Implement the core CRUD methods in the Transaction DB class for creating and retrieving transaction records. Include proper sanitization, validation, prepared statements, and error handling with WP_Error returns.
  - **Files**: Maximum 1 file
    - `includes/database/class-stsrc-transaction-db.php`: Add methods: `create_transaction($member_id, $transaction_data)`, `get_transactions($member_id, $year = null, $page = 1, $per_page = 20)`, `get_transaction($transaction_id)`, `get_total_paid($member_id)`, `get_total_adjustments($member_id)`, `get_balance_summary($member_id)`. Use $wpdb->prepare() for all queries.
  - **Step Dependencies**: Step 1.1 (table must exist)
  - **User Instructions**: None
  - **Git message**: `feat(database): add transaction DB class core methods`
  - **Status**: ✅ COMPLETED (implemented in Step 1.1)

- [x] Step 2.2: Member Database Class - Balance Methods
  - **Task**: Add balance-specific methods to the Member DB class for updating balance fields and calculating/verifying balances from the transaction ledger.
  - **Files**: Maximum 1 file
    - `includes/database/class-stsrc-member-db.php`: Add methods: `update_balance($member_id, $new_balance, $final_payment_method = null)`, `get_members_with_balance($balance_operator = '>', $balance_amount = 0)`, `calculate_member_balance($member_id)` which sums transactions and compares to stored balance_owed for integrity checking
  - **Step Dependencies**: Step 2.1 (transaction methods needed for calculate_member_balance)
  - **User Instructions**: None
  - **Git message**: `feat(database): add member balance management methods`
  - **Status**: ✅ COMPLETED

- [x] Step 2.3: Balance Service Class
  - **Task**: Create a service class to handle all balance-related business logic, including adjustments, manual payment recording, balance calculations, status updates, and auto-activation. This class orchestrates database operations and enforces business rules.
  - **Files**: Maximum 1 file
    - `includes/services/class-stsrc-balance-service.php`: Create new class with methods: `adjust_balance($member_id, $adjustment_type, $amount, $description, $admin_notes, $admin_user_id)`, `record_manual_payment($member_id, $payment_method, $amount, $description, $admin_notes, $admin_user_id, $date_received)`, `record_stripe_payment($member_id, $amount, $payment_method, $stripe_ids, $description)`, `update_member_status_if_paid($member_id)`, `get_balance_display_data($member_id)`. Include validation for amounts, required fields, and automatic status change logic.
  - **Step Dependencies**: Steps 2.1, 2.2 (needs Transaction DB and Member DB)
  - **User Instructions**: None
  - **Git message**: `feat(services): create balance service with business logic`
  - **Status**: ✅ COMPLETED

- [x] Step 2.4: Enhance Payment Service for Balance Payments
  - **Task**: Add method to existing Payment Service class to create Stripe checkout sessions specifically for balance payments. Include validation for minimum payment amounts, member existence, and outstanding balance.
  - **Files**: Maximum 2 files
    - `includes/services/class-stsrc-payment-service.php`: Add method `create_balance_payment_checkout_session($member_id, $amount)` that validates amount against minimum setting, verifies member has balance owed, creates checkout session with appropriate metadata (payment_type: 'balance_payment', member_id, original_balance, payment_amount), and returns session URL or WP_Error
    - `includes/services/class-stsrc-payment-service.php`: Add helper method `get_minimum_balance_payment()` to retrieve setting with fallback to default 10.00
  - **Step Dependencies**: None (can be built independently)
  - **User Instructions**: None
  - **Git message**: `feat(payments): add balance payment checkout session creation`
  - **Status**: ✅ COMPLETED

## Section 3: Admin Settings

- [x] Step 3.1: Add Minimum Balance Payment Setting
  - **Task**: Add a new "Payment Settings" section to the plugin's admin settings page with a field for the minimum balance payment amount. Include validation to ensure the value is greater than 0.
  - **Files**: Maximum 2 files
    - `admin/class-stsrc-settings.php`: Add new settings section and field for `stsrc_minimum_balance_payment` with label "Minimum Balance Payment Amount", description explaining purpose, default value 10.00, and validation callback ensuring value > 0
    - `admin/partials/settings-page.php`: Add HTML for the new settings field in appropriate section using WordPress settings API pattern
  - **Step Dependencies**: None
  - **User Instructions**: After deployment, navigate to plugin settings page and verify the new "Minimum Balance Payment Amount" field appears with default value 10.00
  - **Git message**: `feat(settings): add minimum balance payment amount setting`
  - **Status**: ✅ COMPLETED

## Section 4: Admin UI - Balance Management

- [x] Step 4.1: Admin Member Balance Display Section
  - **Task**: Create a partial template to display the balance overview on the admin member edit page. Show membership type, original price, total paid, total adjustments, current balance owed (prominent), and status badge (Paid in Full / Outstanding / Overpaid).
  - **Files**: Maximum 2 files
    - `admin/partials/member-balance-section.php`: Create new partial that retrieves balance data via Balance Service, displays in clean card/section layout with proper styling, includes status badge logic based on balance amount
    - `admin/class-stsrc-admin.php`: Add action hook to render the balance section on member edit page, likely hooking into an existing member edit page hook or filter
  - **Step Dependencies**: Step 2.3 (needs Balance Service for data)
  - **User Instructions**: View any member's edit page in admin and verify the new "Account Balance" section appears above or near the member details
  - **Git message**: `feat(admin): add balance overview section to member edit page`
  - **Status**: ✅ COMPLETED

- [x] Step 4.2: Admin Transaction History Table
  - **Task**: Create a transaction history table partial for the admin member edit page. Include columns for date, type, payment method, description, amount, balance after, and admin name. Add filtering by current year, sorting by date, pagination, color coding, and links to admin profiles and Stripe transactions.
  - **Files**: Maximum 3 files
    - `admin/partials/member-transaction-history.php`: Create table partial with WP_List_Table or custom table implementation, fetch transactions via Transaction DB, apply color coding (green for payments, blue for adjustments, red for fees, yellow for initial), include pagination controls (20 per page), add Stripe transaction links when applicable
    - `admin/js/balance-management.js`: Add JavaScript for sorting, filtering by year, and pagination without page reload using AJAX
    - `admin/css/balance-management.css`: Add styles for transaction table, color coding, badges, responsive behavior
  - **Step Dependencies**: Steps 2.1, 4.1 (needs Transaction DB and to be on member edit page)
  - **User Instructions**: View a member with existing transactions and verify the table displays correctly with pagination and color coding
  - **Git message**: `feat(admin): add transaction history table to member page`
  - **Status**: ✅ COMPLETED

- [x] Step 4.3: Adjust Balance Modal - UI and Validation
  - **Task**: Create modal dialog UI for admin balance adjustments. Include form fields for adjustment type dropdown, amount (with positive/negative validation), description, and admin notes. Add real-time preview of new balance and two-step confirmation.
  - **Files**: Maximum 3 files
    - `admin/partials/adjust-balance-modal.php`: Create modal HTML template with form fields, include hidden fields for member_id and nonce, add preview section for calculated new balance
    - `admin/js/balance-management.js`: Add modal open/close logic, real-time balance preview calculation as amount is typed, form validation, confirmation step before submission
    - `admin/css/balance-management.css`: Style modal overlay, form layout, confirmation step, success animation
  - **Step Dependencies**: Step 4.1 (appears on same page)
  - **User Instructions**: Click "Adjust Balance" button on member edit page and verify modal opens with all fields and preview working correctly
  - **Git message**: `feat(admin): create adjust balance modal UI`
  - **Status**: ✅ COMPLETED

- [x] Step 4.4: Adjust Balance - AJAX Handler
  - **Task**: Create AJAX endpoint to handle balance adjustment submissions from admin. Validate nonce, capability, amount, and required fields. Call Balance Service to create adjustment transaction, update balance, and trigger status change if applicable. Return success/error response.
  - **Files**: Maximum 2 files
    - `includes/api/class-stsrc-balance-ajax.php`: Create new AJAX handler class with method `handle_admin_adjust_balance()` that validates inputs, checks current_user_can('manage_options'), calls Balance Service adjust_balance method, returns JSON response with new balance and success message
    - `includes/class-smoketree-plugin.php`: Register new AJAX action hooks (wp_ajax_stsrc_adjust_balance) to route to the handler class
  - **Step Dependencies**: Step 2.3 (needs Balance Service)
  - **User Instructions**: None (integration tested in next step)
  - **Git message**: `feat(ajax): add balance adjustment handler`
  - **Status**: ✅ COMPLETED

- [ ] Step 4.5: Adjust Balance - Integration and Testing
  - **Task**: Connect the adjust balance modal form to the AJAX handler. Add submission logic, loading states, error handling, success messages, and automatic refresh of balance section and transaction history after successful adjustment.
  - **Files**: Maximum 1 file
    - `admin/js/balance-management.js`: Add AJAX form submission for adjust balance modal, handle loading state (disable submit button, show spinner), display errors or success message, refresh balance display and transaction table on success, close modal on success
  - **Step Dependencies**: Steps 4.3, 4.4 (needs modal UI and AJAX handler)
  - **User Instructions**: Test adjusting a member's balance with various amounts (positive, negative, zero edge case). Verify balance updates, transaction appears in history, and status changes to active if balance reaches zero.
  - **Git message**: `feat(admin): connect adjust balance modal to backend`

- [x] Step 4.6: Record Manual Payment Modal - UI
  - **Task**: Create modal dialog for admins to record manual payments received offline (check, Zelle, cash). Include fields for payment method dropdown, amount (required, must be > 0), optional check number (shown conditionally), description, date received (defaults to today), and admin notes.
  - **Files**: Maximum 3 files
    - `admin/partials/record-payment-modal.php`: Create modal HTML with form fields, conditional display logic for check number field based on payment method selection, date picker for date received
    - `admin/js/balance-management.js`: Add modal open/close handlers, conditional field display logic, form validation for positive amounts and required fields
    - `admin/css/balance-management.css`: Style the record payment modal consistently with adjust balance modal
  - **Step Dependencies**: Step 4.1 (appears on same page)
  - **User Instructions**: Click "Record Manual Payment" button and verify modal opens, check number field appears only when "check" is selected
  - **Git message**: `feat(admin): create record manual payment modal UI`
  - **Status**: ✅ COMPLETED

- [x] Step 4.7: Record Manual Payment - AJAX Handler and Integration
  - **Task**: Create AJAX endpoint for manual payment recording. Validate inputs, call Balance Service to create payment transaction, update balance and final_payment_method, trigger status change if applicable, queue confirmation email to member. Connect modal to handler with loading states and refresh logic.
  - **Files**: Maximum 2 files
    - `includes/ajax/class-stsrc-balance-ajax.php`: Add method `handle_admin_record_payment()` that validates nonce and capability, validates amount > 0 and required fields, calls Balance Service record_manual_payment method, triggers email queue, returns JSON success/error
    - `admin/js/balance-management.js`: Add form submission handler for record payment modal, AJAX call with loading state, error/success display, refresh balance and transaction history on success
  - **Step Dependencies**: Steps 2.3, 4.6 (needs Balance Service and modal UI)
  - **User Instructions**: Test recording a manual check payment for $100. Verify transaction appears, balance decreases, and member receives confirmation email.
  - **Git message**: `feat(admin): implement manual payment recording with email notification`
  - **Status**: ✅ COMPLETED

## Section 5: Admin Reporting and List Enhancements

- [x] Step 5.1: Dashboard Widget - Outstanding Balances
  - **Task**: Create a WordPress dashboard widget showing summary statistics for outstanding member balances: total members with balance > 0, total dollars outstanding, average balance owed, and link to filtered member list.
  - **Files**: Maximum 2 files
    - `admin/class-stsrc-dashboard-widgets.php`: Create new class or add to existing dashboard class with method to register widget, query Member DB for statistics using get_members_with_balance method, display formatted summary with link to members list with balance filter
    - `admin/css/dashboard-widgets.css`: Style widget with clear typography, formatted currency amounts, and call-to-action link
  - **Step Dependencies**: Step 2.2 (needs Member DB balance query methods)
  - **User Instructions**: Navigate to WordPress dashboard and verify the "Outstanding Balances" widget appears with current data
  - **Git message**: `feat(admin): add outstanding balances dashboard widget`
  - **Status**: ✅ COMPLETED

- [x] Step 5.2: Member List - Balance Column and Filters
  - **Task**: Enhance the admin members list table to include a sortable "Balance" column and add a filter dropdown for "Balance Status" (All / Paid in Full / Outstanding / Overpaid). Update queries to support sorting and filtering by balance_owed.
  - **Files**: Maximum 2 files
    - `admin/pages/class-stsrc-members-page.php`: Add support for balance status filtering and balance sorting in the members list data pipeline
    - `admin/partials/members-list.php`: Add balance status dropdown, sortable balance column header, and balance cell rendering
    - `admin/css/smoketree-plugin-admin.css`: Style balance column alignment, sortable header indicator, and positive/negative/zero balance colors
  - **Step Dependencies**: Step 1.2 (members have balance data)
  - **User Instructions**: Navigate to members list, verify Balance column appears, test sorting by clicking column header, test filtering using Balance Status dropdown
  - **Git message**: `feat(admin): add balance column and filters to member list`
  - **Status**: ✅ COMPLETED

## Section 6: Member Portal Experience

- [x] Step 6.1: Member Balance Card - UI Display
  - **Task**: Create a prominent balance overview card for the member portal that displays only when balance_owed > 0. Show outstanding balance (large), membership type, original price, total paid, remaining balance, and a primary "Pay Balance" button. Use attention-grabbing yellow/orange styling.
  - **Files**: Maximum 3 files
    - `public/partials/member-balance-card.php`: Create card partial that retrieves member balance data, conditionally renders only if balance > 0, displays formatted amounts and membership details with prominent balance amount
    - `public/class-stsrc-member-portal.php`: Add method to render balance card at top of member portal dashboard, hook into appropriate portal display hook/filter
    - `public/css/member-portal.css`: Add styles for balance card including yellow/orange gradient background for outstanding balance, green for paid in full, large bold typography for amount, rounded corners, shadow, responsive layout
  - **Step Dependencies**: Step 2.3 (needs Balance Service for data)
  - **User Instructions**: Log in to member portal as a member with outstanding balance. Verify the balance card appears prominently at the top with correct amounts and styling.
  - **Git message**: `feat(portal): add balance overview card for members`
  - **Status**: ✅ COMPLETED

- [x] Step 6.2: Member Transaction History Section
  - **Task**: Create transaction history display for member portal showing current year's transactions. Include date, description, payment method badge, and colored amount. Show newest first, limit to current year, make collapsible if more than 5 transactions, include empty state.
  - **Files**: Maximum 3 files
    - `public/partials/member-transaction-history.php`: Create partial that fetches transactions for current member filtered by current calendar year using Transaction DB, displays in clean list/table format with badges and color coding, includes empty state message, collapsible wrapper for > 5 transactions
    - `public/js/member-portal.js`: Add collapsible toggle logic for transaction history section if needed
    - `public/css/member-portal.css`: Style transaction list with alternating rows or card layout, color code amounts (green for payments/credits, red for fees), style payment method badges as colored pills, mobile responsive stacking
  - **Step Dependencies**: Step 2.1 (needs Transaction DB)
  - **User Instructions**: Log in as member with transaction history and verify transactions display correctly for current year only, with appropriate colors and badges
  - **Git message**: `feat(portal): add transaction history section for members`
  - **Status**: ✅ COMPLETED

- [x] Step 6.3: Pay Balance Modal - UI and Validation
  - **Task**: Create payment modal for members to pay their balance via Stripe. Include current balance display, editable payment amount input (pre-filled with full balance), real-time validation against minimum payment setting, preview of balance after payment, and payment method selection info.
  - **Files**: Maximum 3 files
    - `public/partials/pay-balance-modal.php`: Create modal template with current balance display, amount input field with dollar sign prefix, validation error display area, real-time balance preview section, hidden fields for member_id and nonce, "Continue to Payment" button
    - `public/js/balance-payment.js`: Create new JavaScript file with modal open/close handlers, real-time amount validation against minimum (localize minimum setting value), calculate and display preview balance, disable submit if amount invalid, form submission handler
    - `public/css/member-portal.css`: Add modal styles including large touch-friendly input, clear validation states (error/success), preview display formatting, consistent button styling
  - **Step Dependencies**: Step 3.1 (needs minimum payment setting)
  - **User Instructions**: Click "Pay Balance" button and verify modal opens, try entering amount below minimum and verify error message, verify preview calculation updates in real-time
  - **Git message**: `feat(portal): create pay balance modal with validation`
  - **Status**: ✅ COMPLETED

- [x] Step 6.4: Balance Payment AJAX Handler
  - **Task**: Create AJAX endpoint for members to request a balance payment checkout session. Validate member ownership, nonce, minimum amount, and that member has outstanding balance. Call Payment Service to create Stripe checkout session and return session URL or error.
  - **Files**: Maximum 2 files
    - `includes/api/class-stsrc-balance-ajax.php`: Add method `handle_create_balance_payment()` that validates nonce, verifies current user owns the member_id, validates amount against minimum and balance owed, calls Payment Service create_balance_payment_checkout_session, returns JSON with session URL or error message
    - `includes/class-smoketree-plugin.php`: Register AJAX action hook (`wp_ajax_stsrc_create_balance_payment`) to route to handler
  - **Step Dependencies**: Step 2.4 (needs Payment Service balance payment method)
  - **User Instructions**: None (tested in next step)
  - **Git message**: `feat(ajax): add balance payment session creation handler`
  - **Status**: ✅ COMPLETED

- [x] Step 6.5: Balance Payment - Stripe Redirect Integration
  - **Task**: Connect pay balance modal submission to AJAX handler. On successful session creation, redirect member to Stripe checkout page. Handle errors gracefully with user-friendly messages. Configure success and cancel return URLs.
  - **Files**: Maximum 2 files
    - `public/js/balance-payment.js`: Add AJAX form submission for pay balance, send amount and member_id to endpoint, handle loading state during request, on success redirect to Stripe session URL, on error display message in modal, configure success URL as '/member-portal?payment=success&session_id={CHECKOUT_SESSION_ID}' and cancel URL as '/member-portal?payment=cancelled'
    - `public/class-stsrc-member-portal.php`: Add handler to detect success/cancelled query parameters on portal page load and display appropriate message banner to member
  - **Step Dependencies**: Steps 6.3, 6.4 (needs modal and AJAX handler)
  - **User Instructions**: Test full payment flow - submit payment, verify redirect to Stripe, complete test payment, verify redirect back to portal with success message
  - **Git message**: `feat(portal): connect balance payment to Stripe checkout`
  - **Status**: ✅ COMPLETED

## Section 7: Registration Flow Enhancement

- [ ] Step 7.1: Update Registration for Non-Stripe Payments
  - **Task**: Modify the member registration flow to handle non-Stripe payment methods (check, Zelle, pay_later). When a member registers with these methods, set original_membership_price and balance_owed to membership type price, create initial transaction record, and set status to 'pending'.
  - **Files**: Maximum 2 files
    - `includes/class-stsrc-registration.php`: Enhance registration completion method to detect non-Stripe payment methods, set balance_owed and original_membership_price to membership type price, call Transaction DB to create initial transaction (type: 'initial', method: 'initial', amount: 0.00, balance_after: membership price), set member status to 'pending'
    - `includes/database/class-stsrc-transaction-db.php`: Ensure create_transaction method supports initial transaction type with appropriate defaults
  - **Step Dependencies**: Step 2.1 (needs Transaction DB)
  - **User Instructions**: Test registration with payment method "check" - verify member is created with status 'pending', balance_owed equals membership price, and initial transaction exists
  - **Git message**: `feat(registration): add balance tracking for non-stripe registrations`

## Section 8: Stripe Webhook Integration

- [ ] Step 8.1: Webhook Handler - Balance Payment Success
  - **Task**: Create or enhance Stripe webhook handler to process successful balance payments. Handle checkout.session.completed events where metadata.payment_type === 'balance_payment'. Extract payment details, create transaction record, update balance and final_payment_method, trigger status change if balance reaches zero, send success emails to member and admin.
  - **Files**: Maximum 2 files
    - `includes/webhooks/class-stsrc-stripe-webhooks.php`: Create new webhook handler class or enhance existing one with method `handle_balance_payment_success($event)` that verifies signature, extracts metadata and payment details from event, checks idempotency via existing stripe_payment_intent_id, calls Balance Service record_stripe_payment method, checks if balance <= 0 and triggers status change, queues member success email and admin notification email, checks for overpayment and queues admin overpayment alert if balance < 0
    - `includes/class-stsrc-webhooks.php`: Register webhook endpoint and route checkout.session.completed events with balance_payment metadata to handler
  - **Step Dependencies**: Step 2.3 (needs Balance Service)
  - **User Instructions**: Use Stripe CLI to send test webhook: `stripe trigger checkout.session.completed`. Verify transaction is created, balance updates, and emails are sent.
  - **Git message**: `feat(webhooks): handle successful balance payments from stripe`

- [ ] Step 8.2: Webhook Handler - Payment Failure
  - **Task**: Handle payment_intent.payment_failed webhook events for balance payments. Detect failed balance payments via metadata, log the failure, send failure notification email to member and admin notification. Do not create transaction record for failed payments.
  - **Files**: Maximum 2 files
    - `includes/webhooks/class-stsrc-stripe-webhooks.php`: Add method `handle_balance_payment_failure($event)` that checks metadata for payment_type === 'balance_payment', logs failure details, queues member failure email with retry instructions and admin notification email
    - `includes/class-stsrc-webhooks.php`: Route payment_intent.payment_failed events to handler
  - **Step Dependencies**: Step 8.1 (uses same webhook infrastructure)
  - **User Instructions**: Test with declined test card. Verify member receives failure email with retry link and no transaction is created.
  - **Git message**: `feat(webhooks): handle failed balance payments and notifications`

## Section 9: Email Templates

- [ ] Step 9.1: Balance Payment Success Email (Member)
  - **Task**: Create email template sent to member when balance payment is successfully processed. Include thank you message, amount paid, new balance (or "Paid in Full" if zero), payment method, transaction date, and link to portal transaction history. If balance reaches zero, include activation message.
  - **Files**: Maximum 2 files
    - `emails/balance-payment-success.php`: Create email template with conditional logic for $0 balance (show "Paid in Full!" and activation message), display payment details formatted as currency, include payment method badge, add link to member portal transaction history
    - `includes/class-stsrc-email-service.php`: Add method `send_balance_payment_success_email($member_id, $transaction_id)` that retrieves member and transaction data, loads template, sends email
  - **Step Dependencies**: None (standalone template)
  - **User Instructions**: Trigger a test balance payment and verify member receives formatted email with correct details
  - **Git message**: `feat(emails): add balance payment success template`

- [ ] Step 9.2: Balance Payment Failed Email (Member)
  - **Task**: Create email template sent to member when balance payment fails. Include failure notification, reason if available from Stripe, attempted amount, current balance still owed, "Retry Payment" button linking to portal, and alternative payment method information.
  - **Files**: Maximum 2 files
    - `emails/balance-payment-failed.php`: Create email template with empathetic failure message, display attempted amount and reason, show current outstanding balance, include prominent CTA button to retry payment in portal, list alternative methods (Zelle, check with instructions)
    - `includes/class-stsrc-email-service.php`: Add method `send_balance_payment_failed_email($member_id, $amount, $reason)` that loads template and sends email
  - **Step Dependencies**: None
  - **User Instructions**: Test with failed payment and verify member receives helpful email with retry options
  - **Git message**: `feat(emails): add balance payment failed template`

- [ ] Step 9.3: Admin Notification Emails (Balance Payment and Overpayment)
  - **Task**: Create two admin notification templates: one for successful balance payments and one for overpayment alerts. Include member details, payment information, new balance, and link to member admin page.
  - **Files**: Maximum 3 files
    - `emails/notify-admin-balance-payment.php`: Create template with member name/email, amount paid, payment method, new balance, conditional "Member automatically activated" message if balance = 0, link to member edit page
    - `emails/notify-admin-overpayment.php`: Create template with overpayment alert messaging, member details, overpayment amount (absolute value of negative balance), payment details, suggested action to contact member for refund, link to member edit page
    - `includes/class-stsrc-email-service.php`: Add methods `send_admin_balance_payment_notification($member_id, $transaction_id)` and `send_admin_overpayment_alert($member_id, $transaction_id)` that retrieve data, load templates, send to admin email from settings
  - **Step Dependencies**: None
  - **User Instructions**: Test balance payment completion and overpayment scenario. Verify admin receives appropriate emails.
  - **Git message**: `feat(emails): add admin balance payment notifications`

- [ ] Step 9.4: Manual Payment Confirmation Email (Member)
  - **Task**: Create email template sent to member when admin records a manual payment on their behalf. Confirm payment received, show payment method and amount, date received, new balance (or "Paid in Full"), and activation message if applicable.
  - **Files**: Maximum 2 files
    - `emails/manual-payment-received.php`: Create template confirming manual payment receipt, display payment method (check, Zelle, cash) with check number if applicable, show amount and date received, display new balance or "Paid in Full" badge, include activation message if balance = 0
    - `includes/class-stsrc-email-service.php`: Add method `send_manual_payment_confirmation_email($member_id, $transaction_id)` that loads template and sends
  - **Step Dependencies**: None
  - **User Instructions**: Record a manual payment via admin and verify member receives confirmation email
  - **Git message**: `feat(emails): add manual payment confirmation template`

- [ ] Step 9.5: Integrate Email Sending into Workflow
  - **Task**: Hook email sending into appropriate workflow points: Balance Service methods, webhook handlers, and AJAX handlers. Ensure emails are triggered after successful transaction creation and balance updates.
  - **Files**: Maximum 3 files
    - `includes/services/class-stsrc-balance-service.php`: Add email trigger calls in record_manual_payment (send manual payment confirmation), record_stripe_payment (send success and admin notification), and auto-activation logic
    - `includes/webhooks/class-stsrc-stripe-webhooks.php`: Ensure webhook handlers trigger appropriate emails after processing events
    - `includes/ajax/class-stsrc-balance-ajax.php`: Ensure manual payment handler triggers email after successful recording
  - **Step Dependencies**: Steps 9.1-9.4 (email templates must exist)
  - **User Instructions**: Test all payment flows and verify emails are sent at correct times with accurate data
  - **Git message**: `feat(emails): integrate email notifications into payment workflows`

## Section 10: Status Management and Auto-Activation

- [ ] Step 10.1: Implement Auto-Activation Logic
  - **Task**: Centralize status change logic in Balance Service. When balance_owed becomes <= 0 and current status is 'pending', automatically change to 'active' and trigger membership activation actions (welcome email, create WP user if needed, etc.). Allow admin manual activation even with outstanding balance but log the override.
  - **Files**: Maximum 2 files
    - `includes/services/class-stsrc-balance-service.php`: Enhance update_member_status_if_paid method to check current status and balance, change status to 'active' if pending and balance <= 0, call existing membership activation methods (welcome email, WP user creation), add activity log entry. Add method handle_admin_status_override to log when admin manually activates with outstanding balance.
    - `admin/class-stsrc-member-admin.php`: Hook into status change action, if admin changes to active and balance > 0, call Balance Service handle_admin_status_override to log activity
  - **Step Dependencies**: Step 2.3 (Balance Service exists)
  - **User Instructions**: Test paying balance to zero and verify status automatically changes to 'active'. Test admin manually activating with balance and verify it's allowed with logged note.
  - **Git message**: `feat(status): implement auto-activation on zero balance`

## Section 11: Data Integrity and Admin Tools

- [ ] Step 11.1: Balance Recalculation Tool
  - **Task**: Create admin tool to verify and recalculate member balances from transaction ledger. Add "Recalculate Balances" button in plugin settings or tools page that compares stored balance_owed with calculated sum from transactions, reports discrepancies, and offers to fix them.
  - **Files**: Maximum 3 files
    - `admin/class-stsrc-admin-tools.php`: Create new admin tools page or section with "Verify Balance Integrity" tool that uses Member DB calculate_member_balance for all members or specific member, displays report of discrepancies in table format, provides "Recalculate All" button to fix mismatches
    - `admin/partials/admin-tools-page.php`: Create UI for tools page with clear instructions, progress indicator for bulk operations, results display table
    - `includes/database/class-stsrc-member-db.php`: Add method `recalculate_all_balances()` that iterates members with balances, calculates from transactions, updates balance_owed where mismatch found, returns report array
  - **Step Dependencies**: Step 2.2 (needs Member DB calculate method)
  - **User Instructions**: Navigate to plugin tools page, click "Verify Balance Integrity" and verify tool runs and reports status
  - **Git message**: `feat(admin): add balance integrity verification tool`

- [ ] Step 11.2: Admin Activity Logging
  - **Task**: Enhance admin actions to log important balance-related activities for audit trail. Log adjustments, manual payments, status overrides, and bulk operations with admin user, timestamp, and action details.
  - **Files**: Maximum 2 files
    - `includes/class-stsrc-activity-log.php`: Create or enhance activity log class with method `log_balance_activity($member_id, $action_type, $details, $admin_user_id)` that stores log entries in options or custom table
    - `includes/services/class-stsrc-balance-service.php`: Add activity logging calls in all balance modification methods (adjustments, manual payments, status changes)
  - **Step Dependencies**: None
  - **User Instructions**: Perform admin balance adjustment and verify activity is logged (view in dedicated log viewer or debug log)
  - **Git message**: `feat(admin): add activity logging for balance operations`

## Section 12: Testing and Polish

- [ ] Step 12.1: JavaScript Asset Enqueuing and Localization
  - **Task**: Ensure all new JavaScript files are properly enqueued with correct dependencies, versioning, and localized data (nonces, AJAX URLs, settings). Add proper conditional loading to only load where needed.
  - **Files**: Maximum 3 files
    - `admin/class-stsrc-admin.php`: Add wp_enqueue_script calls for balance-management.js with dependencies (jQuery), version from plugin constant, localize script with ajax_url, nonces, and minimum payment setting
    - `public/class-stsrc-public.php`: Add wp_enqueue_script for balance-payment.js on member portal page only, localize with ajax_url, nonce, member_id, minimum payment amount, current balance
    - `includes/class-stsrc-assets.php`: Create or enhance asset management class to centralize script/style enqueuing with proper conditional loading logic
  - **Step Dependencies**: Steps 4.2, 6.3 (JS files exist)
  - **User Instructions**: Check browser console for errors, verify AJAX calls work, verify nonces are present in localized data
  - **Git message**: `feat(assets): properly enqueue and localize balance management scripts`

- [ ] Step 12.2: CSS Asset Enqueuing and Responsive Testing
  - **Task**: Enqueue all new CSS files with proper versioning. Test responsive behavior on mobile devices for balance cards, transaction tables, and modals. Ensure accessibility standards for color contrast and interactive elements.
  - **Files**: Maximum 3 files
    - `admin/class-stsrc-admin.php`: Add wp_enqueue_style for balance-management.css and dashboard-widgets.css
    - `public/class-stsrc-public.php`: Add wp_enqueue_style for member-portal.css enhancements
    - `admin/css/balance-management.css` and `public/css/member-portal.css`: Review and enhance responsive breakpoints, test on mobile/tablet viewports, ensure modals are touch-friendly, verify color contrast meets WCAG AA standards
  - **Step Dependencies**: Steps 4.2, 6.1, 6.2 (CSS files exist)
  - **User Instructions**: Test on mobile device or browser responsive mode - verify tables stack properly, modals are usable, buttons are large enough for touch, text is readable
  - **Git message**: `feat(assets): enqueue styles and improve responsive design`

- [ ] Step 12.3: Security Audit and Input Sanitization Review
  - **Task**: Comprehensive security review of all new code. Verify all AJAX handlers check nonces and capabilities, all database inputs use prepared statements and sanitization, all output is properly escaped, webhook signatures are verified.
  - **Files**: Maximum 5 files (review and enhance existing)
    - `includes/ajax/class-stsrc-balance-ajax.php`: Review all methods for nonce verification (wp_verify_nonce), capability checks (current_user_can), input sanitization (sanitize_text_field, absint, floatval), proper WP_Error returns
    - `includes/webhooks/class-stsrc-stripe-webhooks.php`: Verify webhook signature validation before processing any events
    - `includes/database/class-stsrc-transaction-db.php`: Verify all queries use $wpdb->prepare()
    - `admin/partials/*.php`: Review all output for proper escaping (esc_html, esc_attr, esc_url)
    - `public/partials/*.php`: Same escaping review for member-facing output
  - **Step Dependencies**: All previous steps (reviewing existing code)
  - **User Instructions**: Review security checklist - no user input should reach database without sanitization, no output without escaping, all admin actions require capability checks
  - **Git message**: `fix(security): comprehensive security audit and sanitization review`

- [ ] Step 12.4: End-to-End Testing - Full Payment Scenarios
  - **Task**: Comprehensive testing of all payment workflows following the testing scenarios defined in requirements. Document test cases and results.
  - **Testing Scenarios**:
    1. Member registers with "check", pays full balance via Stripe card → status becomes active, final_payment_method = 'card'
    2. Member registers with "zelle" for $500, admin records $200 check payment, member pays $300 via ACH → final_payment_method = 'us_bank_account'
    3. Admin applies $50 discount → transaction created, balance reduced
    4. Admin adds $25 late fee → transaction created, balance increased
    5. Member accidentally pays $600 for $500 membership → overpayment email sent to admin
    6. Member tries to pay $5 when minimum is $10 → validation error
    7. Stripe payment fails → failure email sent, balance unchanged
    8. Admin manually activates member with outstanding balance → allowed, activity logged
  - **Files**: Maximum 1 file
    - `docs/testing-report.md`: Create testing documentation with test cases, steps, expected results, actual results, screenshots or logs
  - **Step Dependencies**: All previous steps (full system must be complete)
  - **User Instructions**: Execute all test scenarios in order, document results, fix any bugs discovered
  - **Git message**: `test: complete end-to-end payment workflow testing`

- [ ] Step 12.5: Documentation and Code Comments
  - **Task**: Add comprehensive PHPDoc comments to all new classes and methods. Create or update plugin documentation with feature overview, admin instructions, and developer notes for the balance tracking system.
  - **Files**: Maximum 5 files
    - All new class files: Add PHPDoc blocks to classes and methods with @param, @return, @throws annotations
    - `docs/balance-tracking-system.md`: Create new documentation explaining system architecture, workflows, admin usage, member experience, and developer integration points
    - `README.md`: Update plugin README with balance tracking feature section
    - `docs/api-reference.md`: Document new public methods in services that other developers might hook into
    - `CHANGELOG.md`: Add entry for new balance tracking feature with all changes listed
  - **Step Dependencies**: All previous steps
  - **User Instructions**: Review documentation for completeness and accuracy
  - **Git message**: `docs: add comprehensive documentation for balance tracking system`

## Summary

This implementation plan breaks down the Mixed Payment Type & Balance Tracking System into 45 manageable steps across 12 sections. The plan follows a logical dependency order, starting with database foundation, building service layers, creating admin and member UIs, integrating Stripe webhooks, adding email notifications, and finishing with testing and polish.

**Key Implementation Approach:**
- Transaction ledger as source of truth with balance_owed as performance cache
- Service layer (Balance Service) centralizes business logic and orchestrates operations
- Separate AJAX handlers for admin and member-facing actions
- Dedicated webhook handlers for Stripe events
- Comprehensive email notifications for all payment events
- Auto-activation when balance reaches zero
- Admin tools for balance verification and data integrity

**Technical Standards Applied:**
- OOP with classes, namespaces, and type declarations (PHP 8.0+)
- WordPress coding standards and security best practices
- Nonce verification and capability checks on all actions
- Prepared statements for all database queries
- Input sanitization and output escaping throughout
- Responsive, accessible UI design matching existing plugin patterns

**Estimated Complexity:**
This is a large feature touching ~40-50 new or modified files. Each step modifies 1-5 files maximum to keep changes manageable. Total implementation time will depend on existing codebase familiarity and testing thoroughness. The modular approach allows for incremental deployment and testing of each section.

**Critical Success Factors:**
1. Database integrity maintained through transaction ledger
2. All payment flows properly integrated with Stripe webhooks
3. Email notifications sent at appropriate points
4. Security verification on all admin and member actions
5. Comprehensive testing of edge cases (overpayment, failures, partial payments)
6. Clear, intuitive UI for both admins and members
