# Smoketree Swim and Recreation Club - WordPress Plugin Technical Specification

<specification_planning>

## Analysis and Planning

### 1. Core System Architecture and Key Workflows

**Primary Workflows:**
- **Member Registration Flow**: User fills form → CAPTCHA validation → Payment processing (Stripe or manual) → Account creation → Email notifications → Member activation
- **Payment Processing Flow**: Payment type selection → Stripe Checkout (if card/bank) OR email notification (if manual) → Webhook handling → Member status update
- **Member Portal Flow**: Login → Dashboard display → Profile management → Family/Extra member CRUD → Guest pass management → Access codes view
- **Guest Pass Flow**: Member purchases passes → Stripe checkout → Balance update → QR code scan at pool → Portal access → Usage logging
- **Admin Management Flow**: Member CRUD → Batch operations → Email composer → Analytics dashboard → Access code management

**Architecture Decisions:**
- WordPress Plugin Boilerplate structure for maintainability
- Custom database tables for members, memberships, guest passes, email logs (best practice)
- ACF Pro for admin configuration fields
- Stripe PHP SDK bundled directly (no Composer on shared host)
- AJAX for seamless user interactions
- WordPress user system integration for authentication

### 2. Project Structure and Organization

Following WordPress Plugin Boilerplate architecture:
- `/includes` - Core classes (activator, deactivator, loader, i18n, main plugin class)
- `/admin` - Admin-specific classes, CSS, JS, partials
- `/public` - Frontend classes, CSS, JS, templates
- `/vendor` - Stripe PHP SDK (bundled)
- `/templates` - Email templates
- `/languages` - Translation files
- Custom database tables via activation hook

**Naming Convention:**
- Prefix: `stsrc_` (Smoketree Swim and Recreation Club)
- All functions, classes, constants, database tables use this prefix

### 3. Detailed Feature Specifications

**Critical Features Requiring Detailed Planning:**
- **Registration Form**: Dynamic field rendering based on membership type, CAPTCHA integration, AJAX submission, payment type handling
- **Stripe Integration**: Webhook endpoint security, payment calculation with flat fees, customer portal linking
- **Family/Extra Members**: Dynamic form fields, validation for unique names, payment processing for extra members
- **Guest Pass System**: Balance tracking, QR code URL generation, usage logging, purchase flow
- **Batch Email System**: Member filtering, rate limiting, progress tracking, email logging
- **Auto-Renewal**: Custom solution (not Stripe subscriptions), date-based renewal, bulk status management

**Edge Cases to Handle:**
- Payment failure during registration (don't create account)
- Duplicate email prevention
- Family member name uniqueness validation
- Extra member payment before activation
- Guest pass balance at 0 (show purchase link)
- Registration toggle disabled (redirect or message)
- Password reset flow security
- Webhook replay attacks (idempotency)

### 4. Database Schema Design

**Custom Tables:**
- `stsrc_members` - Core member data
- `stsrc_membership_types` - Membership type configurations
- `stsrc_family_members` - Family member records
- `stsrc_extra_members` - Extra member records
- `stsrc_guest_passes` - Guest pass purchases and usage
- `stsrc_email_logs` - Batch email audit trail
- `stsrc_access_codes` - Access code management
- `stsrc_payment_logs` - Payment transaction records

**Relationships:**
- Members → Membership Types (foreign key)
- Members → Family Members (one-to-many)
- Members → Extra Members (one-to-many)
- Members → Guest Passes (one-to-many)
- Members → Payment Logs (one-to-many)

**Indexes:**
- Email (unique)
- Membership type ID
- Status
- Stripe customer ID
- Created date

### 5. Server Actions and Integrations

**Database Actions:**
- Member CRUD operations
- Membership type CRUD
- Family/Extra member management
- Guest pass balance updates
- Email log entries
- Payment record creation

**External Integrations:**
- Stripe API (Checkout Sessions, Customers, Webhooks)
- CAPTCHA service (Google reCAPTCHA v3 or hCaptcha)
- WP Mail SMTP (email delivery)
- ACF Pro (admin fields)

**File Handling:**
- Email template rendering
- CSV export generation
- Attachment handling for batch emails

### 6. Design System and Component Architecture

**Visual Style:**
- Modern, premium UI/UX
- Custom CSS (no Tailwind to avoid WordPress admin conflicts)
- Dark mode support for member portal
- Responsive design (mobile-first)
- WCAG accessibility compliance

**Component Patterns:**
- AJAX form submissions with loading states
- Modal dialogs for confirmations
- Toast notifications for feedback
- Data tables with filtering/sorting
- Progress indicators for batch operations

### 7. Authentication and Authorization Implementation

**Authentication:**
- WordPress user system integration
- Custom login page template
- Password reset flow with email
- Session management via WordPress

**Authorization:**
- Capability checks for admin functions
- Member portal access (logged-in members only)
- Guest pass portal (login required)
- Role-based access control

### 8. Data Flow and State Management

**Server/Client Communication:**
- AJAX endpoints for form submissions
- REST API endpoints (optional, for future expansion)
- Webhook endpoints for Stripe
- Form data via POST requests

**State Management:**
- WordPress transients for caching
- Database for persistent state
- Session data for temporary state
- JavaScript for UI state (minimal, server-driven)

### 9. Payment Implementation

**Stripe Integration Details:**
- Checkout Session creation with exact amounts (including flat fees)
- Webhook endpoint for payment events
- Customer creation and linking
- Customer Portal integration
- Idempotency handling for webhooks

**Payment Types:**
- Card/Bank Account: Stripe Checkout redirect
- Zelle/Check/Pay Later: Email notifications only

**Fee Structure:**
- Single: $6 flat fee
- Duo: $8 flat fee
- Household: $10 flat fee

**Challenges:**
- Webhook security (signature verification)
- Payment failure handling
- Idempotency for duplicate webhooks
- Customer Portal session creation

</specification_planning>

## 1. System Overview

### Core Purpose and Value Proposition

The Smoketree Swim and Recreation Club WordPress Plugin is a comprehensive membership management system that handles the complete lifecycle of club membership from registration through renewal. The plugin provides:

- **Automated Member Registration**: Streamlined registration process with integrated payment processing
- **Self-Service Member Portal**: Members can manage their profiles, family members, guest passes, and payment methods
- **Administrative Control**: Complete member management, batch operations, and analytics for club administrators
- **Payment Processing**: Integrated Stripe payment handling with support for multiple payment types
- **Guest Pass Management**: Purchase, tracking, and usage logging for guest passes
- **Communication Tools**: Batch email system with filtering and tracking capabilities

### Key Workflows

1. **Member Registration Workflow**
   - User accesses `/register` page
   - Fills out registration form with membership selection
   - CAPTCHA validation
   - Payment type selection determines flow:
     - Card/Bank Account → Stripe Checkout → Webhook → Account activation
     - Zelle/Check/Pay Later → Email notifications → Manual activation
   - Welcome email sent upon activation
   - Admin notifications sent

2. **Member Portal Workflow**
   - Member logs in via custom login page
   - Dashboard displays membership status, family members, guest passes
   - Member can edit profile, manage family/extra members, purchase guest passes
   - Access codes displayed for facility access

3. **Guest Pass Workflow**
   - Member purchases guest passes via portal
   - Stripe checkout processes payment
   - Balance updated, email confirmation sent
   - QR code scan at pool → redirects to guest pass portal (login required)
   - Usage logged when pass is redeemed

4. **Admin Management Workflow**
   - Admin accesses WordPress admin menu
   - Member CRUD operations, filtering, search
   - Batch email composer with member filtering
   - Guest pass usage analytics
   - Access code management

5. **Auto-Renewal Workflow**
   - Admin sets season renewal date
   - Members opt-in for auto-renewal in portal
   - Email notification sent before renewal date
   - Automatic payment processing on renewal date
   - Status updated to active

### System Architecture

**Technology Stack:**
- PHP 8.0.30
- WordPress 6.9
- MariaDB (via WordPress $wpdb)
- Stripe PHP SDK (bundled)
- ACF Pro (admin configuration)
- WP Mail SMTP (email delivery)

**Architecture Pattern:**
- Object-Oriented Programming with namespacing
- WordPress Plugin Boilerplate structure
- Hook-based architecture (actions and filters)
- Separation of concerns (admin/public/includes)
- Custom database tables for data persistence

**Security Model:**
- Input validation and sanitization
- Output escaping
- Nonce verification for all forms
- Capability checks for admin functions
- SQL injection prevention via $wpdb->prepare()
- Webhook signature verification

## 2. Project Structure

```
smoketree-plugin/
├── smoketree-plugin.php                    # Main plugin file (bootstrap)
├── uninstall.php                      # Cleanup on uninstall
├── README.txt                         # Plugin readme
├── LICENSE.txt                        # License file
│
├── includes/                          # Core plugin classes
│   ├── class-smoketree-plugin.php          # Main plugin class
│   ├── class-smoketree-plugin-loader.php   # Hook orchestrator
│   ├── class-smoketree-plugin-i18n.php    # Internationalization
│   ├── class-smoketree-plugin-activator.php # Activation hook
│   ├── class-smoketree-plugin-deactivator.php # Deactivation hook
│   │
│   ├── database/                      # Database classes
│   │   ├── class-stsrc-database.php  # Database setup
│   │   ├── class-stsrc-member-db.php # Member database operations
│   │   ├── class-stsrc-membership-db.php # Membership type operations
│   │   └── class-stsrc-guest-pass-db.php # Guest pass operations
│   │
│   ├── api/                           # API handlers
│   │   ├── class-stsrc-ajax-handler.php # AJAX endpoints
│   │   ├── class-smoketree-stripe-webhooks.php # Stripe webhooks (Smoketree_Stripe_Webhooks)
│   │   └── class-stsrc-rest-api.php  # REST API (optional)
│   │
│   ├── services/                      # Business logic services
│   │   ├── class-stsrc-member-service.php # Member business logic
│   │   ├── class-stsrc-payment-service.php # Payment processing
│   │   ├── class-stsrc-email-service.php # Email sending
│   │   └── class-stsrc-captcha-service.php # CAPTCHA validation
│   │
│   └── models/                        # Data models
│       ├── class-stsrc-member.php     # Member model
│       ├── class-stsrc-membership-type.php # Membership type model
│       └── class-stsrc-guest-pass.php # Guest pass model
│
├── admin/                             # Admin area
│   ├── class-smoketree-plugin-admin.php   # Admin main class
│   ├── css/
│   │   └── smoketree-plugin-admin.css      # Admin styles
│   ├── js/
│   │   └── smoketree-plugin-admin.js      # Admin scripts
│   │
│   ├── pages/                         # Admin page classes
│   │   ├── class-stsrc-members-page.php # Members management
│   │   ├── class-stsrc-memberships-page.php # Membership types
│   │   ├── class-stsrc-guest-passes-page.php # Guest pass management
│   │   ├── class-stsrc-email-page.php # Batch email composer
│   │   ├── class-stsrc-settings-page.php # Plugin settings
│   │   └── class-stsrc-dashboard-page.php # Admin dashboard
│   │
│   └── partials/                      # Admin templates
│       ├── members-list.php
│       ├── member-edit.php
│       ├── membership-types-list.php
│       ├── email-composer.php
│       └── dashboard-widgets.php
│
├── public/                            # Frontend
│   ├── class-smoketree-plugin-public.php  # Public main class
│   ├── css/
│   │   └── smoketree-plugin-public.css    # Frontend styles (with dark mode)
│   ├── js/
│   │   └── smoketree-plugin-public.js     # Frontend scripts
│   │
│   ├── templates/                     # Page templates
│   │   ├── registration-form.php     # Registration page
│   │   ├── member-portal.php         # Member dashboard
│   │   ├── login.php                 # Login page
│   │   ├── forgot-password.php       # Password reset
│   │   └── guest-pass-portal.php     # Guest pass page
│   │
│   └── partials/                      # Frontend partials
│       ├── registration-form.php
│       ├── member-profile.php
│       ├── family-members.php
│       └── guest-pass-balance.php
│
├── templates/                         # Email templates
│   ├── payment-success.php
│   ├── subscription-update.php
│   ├── password-reset.php
│   ├── payment-reminder.php
│   ├── thank-you-pay-later.php
│   ├── welcome.php
│   ├── welcome-civic.php
│   ├── treasurer-pay-later.php
│   ├── notify-admin-of-member.php
│   ├── notify-admin-of-guest-pass.php
│   ├── notify-admin-guest-pass-was-used.php
│   ├── notify-admins-of-failed-registration.php
│   └── guest-pass-purchase.php
│
├── vendor/                            # Third-party libraries
│   └── stripe/                        # Stripe PHP SDK (bundled)
│       └── stripe-php/
│
├── languages/                         # Translation files
│   └── smoketree-plugin.pot
│
└── acf-json/                          # ACF field exports (optional)
    └── group_*.json
```

**Naming Conventions:**
- Plugin prefix: `stsrc_` (all functions, classes, constants, database tables)
- Class names: `STSRC_` prefix (e.g., `STSRC_Member_Service`)
- Database tables: `wp_stsrc_` prefix (e.g., `wp_stsrc_members`)
- Options: `stsrc_` prefix (e.g., `stsrc_stripe_secret_key`)

## 3. Feature Specification

### 3.1 Member Registration

**User Story:** As a prospective member, I want to register for club membership online, select my membership type, provide my information, and complete payment so that I can become an active member.

**Requirements:**
- Registration form accessible at `/register` page
- Registration toggle (ACF field) to enable/disable registration
- CAPTCHA integration (Google reCAPTCHA v3 or hCaptcha)
- Form validation (prevent spam/jibberish)
- AJAX form submission with loading states
- Payment type selection determines processing flow
- Duplicate email prevention
- Account creation only on successful payment (for card/bank)

**Implementation Steps:**

1. **Registration Page Template**
   - Create custom page template or shortcode
   - Check registration toggle (ACF field: `stsrc_registration_enabled`)
   - If disabled, display message and return early
   - Enqueue registration form CSS/JS

2. **Form Field Rendering**
   - Render all required fields (see registration-form-fields.md)
   - Auto-fill City: "Tucker", State: "GA", Zip: "30084"
   - Dynamic membership selection dropdown (filter by "Is Selectable")
   - Conditional family member fields (show only for Household/Duo)
   - Conditional extra member fields (show only for Household)
   - CAPTCHA widget integration
   - Payment type radio buttons

3. **Form Validation (Client-Side)**
   - Real-time email format validation
   - Phone number format validation
   - Password strength validation (WordPress standards)
   - Password match validation
   - Required field validation
   - Family member name uniqueness (client-side check)
   - CAPTCHA token generation

4. **AJAX Form Submission**
   - Prevent default form submission
   - Collect all form data
   - Validate CAPTCHA token
   - Show loading state
   - POST to AJAX endpoint: `stsrc_register_member`
   - Handle response (success/error)

5. **Server-Side Processing**
   - Verify nonce: `stsrc_registration_nonce`
   - Validate CAPTCHA token (server-side)
   - Validate all input data
   - Check for duplicate email
   - Check registration toggle status
   - Determine payment flow based on payment type

6. **Payment Processing Branch**
   
   **Card/Bank Account:**
   - Calculate total (membership price + flat fee)
   - Create Stripe Checkout Session
   - Store registration data in transient (key: `stsrc_registration_{session_id}`)
   - Return checkout URL to frontend
   - Redirect user to Stripe Checkout
   - Webhook handles account creation (see 3.2)

   **Zelle/Check/Pay Later:**
   - Create WordPress user account (status: pending)
   - Create member record (status: pending)
   - Send email to admin (treasurer-pay-later.php)
   - Send email to member (thank-you-pay-later.php)
   - Return success response

7. **Error Handling**
   - Invalid CAPTCHA → return error message
   - Duplicate email → return error message
   - Validation failures → return field-specific errors
   - Registration disabled → return error message
   - Display errors in form UI

**Error Handling and Edge Cases:**
- **Payment Failure**: Don't create account, return error, allow retry
- **Duplicate Email**: Check before processing, return clear error
- **Registration Disabled**: Check ACF field, show message
- **CAPTCHA Failure**: Retry CAPTCHA, don't proceed
- **Stripe API Error**: Log error, return user-friendly message
- **Network Timeout**: Show retry option, don't lose form data

### 3.2 Payment Processing

**User Story:** As a member or admin, I want payment processing to work seamlessly with Stripe integration, handling multiple payment types and providing proper notifications.

**Requirements:**
- Stripe Checkout for card/bank account payments
- Flat fee calculation ($6 Single, $8 Duo, $10 Household)
- Webhook handling for payment events
- Email notifications for all payment types
- Customer Portal integration
- Payment plan toggle (ACF field)

**Implementation Steps:**

1. **Stripe Checkout Session Creation**
   - Endpoint: `stsrc_create_checkout_session` (AJAX)
   - Calculate total: `membership_price + flat_fee`
   - Create Stripe Customer (if not exists)
   - Create Checkout Session with:
     - Line items (membership type)
     - Success URL: `{site_url}/member-portal?payment=success`
     - Cancel URL: `{site_url}/register?payment=cancelled`
     - Metadata: registration data, member ID (if exists)
   - Store session ID in transient
   - Return checkout URL

2. **Flat Fee Calculation**
   ```php
   $fees = [
       'single' => 6.00,
       'duo' => 8.00,
       'household' => 10.00
   ];
   $total = $membership_price + $fees[$membership_type_slug];
   ```

3. **Webhook Endpoint**
   - Endpoint: `/wp-json/stripe/v1/webhook`
   - Registered via: `register_rest_route('stripe/v1', '/webhook', ...)`
   - Callback: `Smoketree_Stripe_Webhooks::handle_webhook`
   - Permission: `__return_true` (public endpoint for Stripe)
   - Verify webhook signature
   - Handle events:
     - `checkout.session.completed` → Activate member, send welcome email
     - `payment_intent.succeeded` → Update payment log
     - `payment_intent.payment_failed` → Send failure notification
     - `customer.subscription.updated` → Update member status (if using subscriptions)

4. **Webhook Processing (checkout.session.completed)**
   - Retrieve registration data from transient (using session ID)
   - Create WordPress user account
   - Create member record (status: active)
   - Link Stripe Customer ID
   - Create family members (if any)
   - Send welcome email (welcome.php or welcome-civic.php)
   - Send admin notification (notify-admin-of-member.php)
   - Clear transient
   - Log payment transaction

5. **Payment Failure Handling**
   - Event: `payment_intent.payment_failed`
   - Keep member as pending (if account exists)
   - Send failure notification (notify-admins-of-failed-registration.php)
   - Log failure reason

6. **Customer Portal Integration**
   - Generate Customer Portal session URL
   - Endpoint: `stsrc_get_customer_portal_url`
   - Requires: Stripe Customer ID
   - Return portal URL for member to access

7. **Manual Payment Types (Zelle/Check/Pay Later)**
   - No Stripe processing
   - Create account with pending status
   - Send emails (treasurer-pay-later.php, thank-you-pay-later.php)
   - Admin manually activates after payment received

**Error Handling and Edge Cases:**
- **Webhook Replay**: Check idempotency (store processed event IDs)
- **Invalid Signature**: Log and reject webhook
- **Missing Registration Data**: Log error, send admin notification
- **Stripe API Errors**: Retry with exponential backoff, log errors
- **Duplicate Webhook**: Check event ID, skip if already processed

### 3.3 Member Portal

**User Story:** As a member, I want to access a self-service portal where I can view my membership details, manage my profile, add family members, purchase guest passes, and update my payment methods.

**Requirements:**
- Custom login page
- Dashboard with membership overview
- Profile editing (AJAX)
- Family member CRUD (AJAX)
- Extra member CRUD (AJAX, with payment)
- Guest pass purchase interface
- Link to Stripe Customer Portal
- Access codes display
- Responsive design with dark mode

**Implementation Steps:**

1. **Login System**
   - Custom login page template (`/login`)
   - Use WordPress authentication (`wp_authenticate`)
   - Redirect to member portal on success
   - Handle login errors gracefully
   - "Remember me" functionality

2. **Member Portal Dashboard**
   - Template: `member-portal.php`
   - Check user authentication (redirect to login if not logged in)
   - Retrieve member data from database
   - Display:
     - Membership type and status
     - Expiration date
     - Family members list (with edit/delete)
     - Extra members list (with edit/delete)
     - Guest pass balance
     - Recent activity log
     - Access codes
   - Enqueue portal CSS/JS

3. **Profile Editing**
   - AJAX endpoint: `stsrc_update_profile`
   - Fields: First name, Last name, Email, Phone, Address
   - Validate input
   - Update WordPress user and member record
   - Return success/error response
   - Update UI without page refresh

4. **Password Change**
   - AJAX endpoint: `stsrc_change_password`
   - Verify current password
   - Validate new password strength
   - Update WordPress user password
   - Send confirmation email (optional)

5. **Family Member CRUD**
   - **Create**: AJAX endpoint `stsrc_add_family_member`
     - Validate: name uniqueness, max count (4 for Household, 1 for Duo)
     - Insert into `stsrc_family_members` table
     - Return updated list
   - **Read**: Load from database, display in portal
   - **Update**: AJAX endpoint `stsrc_update_family_member`
     - Validate name uniqueness
     - Update record
   - **Delete**: AJAX endpoint `stsrc_delete_family_member`
     - Soft delete or hard delete (based on requirements)
     - Update UI

6. **Extra Member CRUD**
   - **Create**: AJAX endpoint `stsrc_add_extra_member`
     - Validate: max 3 extra members (Household only)
     - Create Stripe Checkout for $50
     - On payment success, create extra member record
   - **Read/Update/Delete**: Similar to family members
   - Payment required before activation

7. **Guest Pass Purchase**
   - Display current balance (even if 0)
   - Purchase form (quantity input)
   - AJAX endpoint: `stsrc_purchase_guest_passes`
   - Create Stripe Checkout Session ($5 per pass)
   - Redirect to Stripe
   - On success, update balance and redirect back to portal
   - Send confirmation email (guest-pass-purchase.php)

8. **Access Codes Display**
   - Retrieve codes from `stsrc_access_codes` table
   - Display in secure format
   - Only show active codes
   - Refresh button (if codes rotate)

**Error Handling and Edge Cases:**
- **Unauthenticated Access**: Redirect to login with return URL
- **Invalid Member Data**: Show error, allow retry
- **Family Member Limit Reached**: Disable add button, show message
- **Extra Member Payment Failure**: Keep as pending, show retry option
- **Stripe Customer Portal Error**: Show fallback message

### 3.4 Guest Pass Management

**User Story:** As a member or admin, I want to purchase, track, and use guest passes, with proper logging and notifications.

**Requirements:**
- Guest pass pricing: $5 each
- Unlimited purchase quantity for members
- Admin can add passes to member accounts
- Balance tracking per member
- Guest pass portal page (QR code access)
- Usage logging with date/time
- Email notifications on purchase and usage

**Implementation Steps:**

1. **Guest Pass Balance Tracking**
   - Store balance in `stsrc_members` table (`guest_pass_balance` column)
   - Update on purchase (increment)
   - Update on usage (decrement)
   - Update on admin adjustment

2. **Member Purchase Flow**
   - Member selects quantity in portal
   - AJAX: `stsrc_purchase_guest_passes`
   - Calculate total: `quantity * 5.00`
   - Create Stripe Checkout Session
   - Success URL: `{site_url}/guest-pass-portal?purchase=success`
   - On webhook: Update balance, send email (guest-pass-purchase.php)
   - Send admin notification (notify-admin-of-guest-pass.php)

3. **Guest Pass Portal Page**
   - Template: `guest-pass-portal.php`
   - URL: `/guest-pass-portal` (for QR code linking)
   - Require login (redirect if not authenticated)
   - Display:
     - Current balance (even if 0)
     - Purchase link (if balance is 0)
     - Recent usage log
   - QR code URL provided to third-party generator

4. **Guest Pass Usage Logging**
   - Table: `stsrc_guest_passes`
   - Fields: member_id, used_at, payment_status, admin_adjusted, notes
   - Log on usage (admin or member-initiated)
   - Send admin notification (notify-admin-guest-pass-was-used.php)

5. **Admin Guest Pass Management**
   - Admin interface to add/remove passes
   - AJAX endpoint: `stsrc_admin_adjust_guest_passes`
   - Log adjustment with admin user ID
   - Update balance
   - Send notification (optional)

6. **Guest Pass Analytics**
   - Admin dashboard widget
   - Filter by date range
   - Show: total purchased, total used, active balance
   - Export to CSV

**Error Handling and Edge Cases:**
- **Balance at 0**: Show purchase link, don't hide page
- **Payment Failure**: Don't update balance, allow retry
- **Unauthenticated QR Access**: Redirect to login
- **Invalid Member**: Show error message

### 3.5 Admin Member Management

**User Story:** As an admin, I want to manage members, view analytics, export data, and perform batch operations.

**Requirements:**
- Member CRUD operations
- Search and filtering
- CSV export with filters
- Member count display (paid/active only)
- Bulk status updates
- Member detail view

**Implementation Steps:**

1. **Members List Page**
   - Admin page: `STSRC_Members_Page`
   - WordPress admin menu: "Smoketree Members"
   - Display table with columns:
     - Name, Email, Membership Type, Status, Payment Type, Created Date
   - Pagination
   - Search box (name, email)
   - Filters:
     - Membership type dropdown
     - Status dropdown (active, pending, cancelled)
     - Payment type dropdown
     - Date range picker

2. **Member CRUD Operations**
   - **Create**: Form to add new member manually
   - **Read**: Detail view with all member data
   - **Update**: Edit form (AJAX or page reload)
   - **Delete**: Soft delete (change status to cancelled) or hard delete

3. **CSV Export**
   - AJAX endpoint: `stsrc_export_members`
   - Apply current filters
   - Generate CSV with columns:
     - Name, Email, Phone, Address, Membership Type, Status, Payment Type, Created Date, Expiration Date
   - Download file
   - Use WordPress CSV functions or custom implementation

4. **Member Count Display**
   - Dashboard widget
   - Query: `SELECT COUNT(*) FROM stsrc_members WHERE status = 'active' AND payment_type IN ('card', 'bank_account')`
   - Display with icon/formatting
   - Cache with transient (5 minutes)

5. **Bulk Operations**
   - Checkbox selection for multiple members
   - Bulk actions:
     - Change status (active/pending/cancelled)
     - Send email
     - Export selected
   - AJAX processing with progress indicator

**Error Handling and Edge Cases:**
- **Large Member Lists**: Implement pagination, lazy loading
- **Export Timeout**: Use background processing or chunk exports
- **Invalid Filters**: Validate and show error

### 3.6 Batch Email System

**User Story:** As an admin, I want to send batch emails to filtered member groups with attachments and track delivery status.

**Requirements:**
- Email composer interface
- Member filtering (membership type, status, payment type, date range)
- Multiple attachment support
- Progress tracking
- Email logging
- Rate limiting

**Implementation Steps:**

1. **Email Composer Page**
   - Admin page: `STSRC_Email_Page`
   - Form fields:
     - Subject
     - Message body (WYSIWYG editor)
     - Member filters (same as member list)
     - Attachments (multiple file upload)
     - Preview recipient count
   - "Send Test Email" button
   - "Send to All" button

2. **Member Filtering**
   - Build query based on selected filters
   - Display preview count
   - Validate: at least one filter or "send to all" confirmation

3. **Email Sending Process**
   - AJAX endpoint: `stsrc_send_batch_email`
   - Get filtered member list
   - Process in batches (e.g., 10 at a time)
   - For each member:
     - Render email template with member data
     - Use `wp_mail()` (via WP Mail SMTP)
     - Log result (sent/failed)
     - Rate limit: 1 email per second (or configurable)
   - Return progress updates via AJAX polling
   - Store email log in `stsrc_email_logs` table

4. **Email Logging**
   - Table: `stsrc_email_logs`
   - Fields: email_id, member_id, subject, status, sent_at, error_message
   - Track: sent, failed, bounced
   - Admin view: Email history page

5. **Rate Limiting**
   - Use WordPress transients or option to track send rate
   - Limit: 60 emails per minute (configurable)
   - Queue excess emails for later processing
   - Show progress bar during sending

6. **Template Rendering**
   - Use email templates from `/templates` directory
   - Replace placeholders: `{first_name}`, `{last_name}`, `{email}`, etc.
   - Support HTML emails
   - Include attachments

**Error Handling and Edge Cases:**
- **SMTP Failure**: Log error, continue with next email
- **Large Attachment**: Validate file size, show warning
- **Rate Limit Exceeded**: Queue emails, show message
- **No Recipients**: Show error, prevent sending

### 3.7 Membership Types Management

**User Story:** As an admin, I want to configure membership types with pricing, benefits, and Stripe product IDs.

**Requirements:**
- CRUD operations for membership types
- All fields from project requirements
- Benefits selection (checkboxes)
- Default membership types setup
- Stripe Product ID linking

**Implementation Steps:**

1. **Membership Types List Page**
   - Admin page: `STSRC_Memberships_Page`
   - Display table with: Name, Price, Stripe Product ID, Is Selectable, Best Seller
   - Add/Edit/Delete buttons

2. **Membership Type Form**
   - Fields:
     - Name (required)
     - Description (textarea)
     - Price (decimal, required)
     - Expiration Period (days, required)
     - Stripe Product ID (text, optional)
     - Is Selectable (checkbox)
     - Mark as Best Seller (checkbox)
     - Can Have Additional Members (checkbox)
     - Benefits (checkboxes):
       - Up to 5 people
       - 2 people
       - 1 person
       - Pool use for season
       - Lakefront and Dock
       - Playground
       - Tennis/Pickleball Court
       - Dog Run
       - Pavilion
       - Membership Voting Rights

3. **Default Membership Types Setup**
   - Activation hook creates default types:
     - Household: $X, 4 family members, 3 extra members, all benefits
     - Duo: $X, 1 family member, all benefits
     - Single: $X, no family members, all benefits
     - Civic: $X, no pool, voting only

4. **Database Operations**
   - Store in `stsrc_membership_types` table
   - Benefits stored as JSON or separate table (recommend JSON for simplicity)
   - Update Stripe products when membership type updated

**Error Handling and Edge Cases:**
- **Duplicate Names**: Validate uniqueness
- **Invalid Price**: Validate positive number
- **Stripe Product Not Found**: Show warning, allow creation

### 3.8 Auto-Renewal System

**User Story:** As a member or admin, I want members to be able to opt-in for automatic renewal on a season-wide date, with email notifications and automatic payment processing.

**Requirements:**
- Member opt-in in portal
- Admin sets renewal date (season-wide)
- Email notification before renewal
- Automatic payment processing on renewal date
- Custom solution (not Stripe subscriptions)

**Implementation Steps:**

1. **Member Opt-In**
   - Checkbox in member portal: "Enable Auto-Renewal"
   - AJAX endpoint: `stsrc_toggle_auto_renewal`
   - Update member record: `auto_renewal_enabled = true/false`

2. **Renewal Date Configuration**
   - ACF field: `stsrc_season_renewal_date` (date picker)
   - Admin sets once per season
   - Stored in WordPress options

3. **Renewal Notification Email**
   - Cron job or scheduled task
   - Check: 7 days before renewal date
   - Query: Members with `auto_renewal_enabled = true` and `status = 'active'`
   - Send email: payment-reminder.php template
   - Include: renewal date, amount, payment method update link

4. **Automatic Renewal Processing**
   - Cron job on renewal date
   - Query: Members with `auto_renewal_enabled = true` and `status = 'active'`
   - For each member:
     - Get Stripe Customer ID
     - Get default payment method
     - Create Payment Intent for membership price + fee
     - Process payment
     - On success:
       - Update expiration date
       - Send confirmation email
       - Log transaction
     - On failure:
       - Keep status as active (or change to pending)
       - Send failure notification
       - Log error

5. **Bulk Status Management**
   - Admin interface: "Start New Season"
   - Bulk update: Set all active members to cancelled
   - Reset guest pass balances (optional)
   - Clear auto-renewal flags (optional)

**Error Handling and Edge Cases:**
- **No Payment Method**: Send notification, skip renewal
- **Payment Failure**: Keep member active, send notification
- **Renewal Date Passed**: Process immediately on next cron run
- **Member Cancelled**: Skip renewal

### 3.9 Access Codes Management

**User Story:** As an admin, I want to manage access codes that members can view in their portal for facility access.

**Requirements:**
- Admin CRUD for access codes
- Code display in member portal
- Secure storage
- Expiration/rotation support

**Implementation Steps:**

1. **Access Codes Admin Page**
   - Admin page: `STSRC_Access_Codes_Page`
   - List of codes with: Code, Description, Expiration Date, Is Active
   - Add/Edit/Delete functionality

2. **Database Storage**
   - Table: `stsrc_access_codes`
   - Fields: code_id, code, description, expires_at, is_active, created_at
   - Encrypt codes in database (optional, or store as plain text if not sensitive)

3. **Member Portal Display**
   - Query active codes (not expired, is_active = true)
   - Display in member portal dashboard
   - Format: Code with description
   - Refresh button (if codes rotate frequently)

**Error Handling and Edge Cases:**
- **Expired Codes**: Don't display, show message if all expired
- **No Active Codes**: Show message to contact admin

### 3.10 Password Reset Flow

**User Story:** As a member, I want to reset my password if I forget it, receiving a secure reset link via email.

**Requirements:**
- Forgot password page
- Email with reset link
- Secure token generation
- Password reset page
- Email template: password-reset.php

**Implementation Steps:**

1. **Forgot Password Page**
   - Template: `forgot-password.php`
   - Form: Email input
   - AJAX submission: `stsrc_forgot_password`
   - Validate email exists
   - Generate reset token (WordPress function: `wp_generate_password`)
   - Store token in user meta with expiration (1 hour)
   - Send email with reset link: `{site_url}/reset-password?token={token}&email={email}`

2. **Password Reset Page**
   - Template: `reset-password.php`
   - Validate token from URL parameters
   - Check expiration
   - Form: New password, Confirm password
   - AJAX submission: `stsrc_reset_password`
   - Validate password strength
   - Update WordPress user password
   - Invalidate token
   - Redirect to login

3. **Email Template**
   - Use password-reset.php template
   - Include: Reset link with token
   - Expiration notice
   - Security warning

**Error Handling and Edge Cases:**
- **Invalid Token**: Show error, allow new request
- **Expired Token**: Show error, allow new request
- **Email Not Found**: Don't reveal, show generic success message (security)
- **Weak Password**: Show requirements, prevent submission

## 4. Database Schema

### 4.1 Tables

#### 4.1.1 `wp_stsrc_members`

Core member information table.

**Schema:**
```sql
CREATE TABLE wp_stsrc_members (
    member_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    membership_type_id BIGINT(20) UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    payment_type VARCHAR(20) NOT NULL,
    stripe_customer_id VARCHAR(255) DEFAULT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    street_1 VARCHAR(255) NOT NULL,
    street_2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) NOT NULL DEFAULT 'Tucker',
    state VARCHAR(2) NOT NULL DEFAULT 'GA',
    zip VARCHAR(10) NOT NULL DEFAULT '30084',
    country VARCHAR(2) NOT NULL DEFAULT 'US',
    referral_source VARCHAR(100) DEFAULT NULL,
    waiver_full_name VARCHAR(255) NOT NULL,
    waiver_signed_date DATE NOT NULL,
    guest_pass_balance INT(11) NOT NULL DEFAULT 0,
    auto_renewal_enabled TINYINT(1) NOT NULL DEFAULT 0,
    expiration_date DATE DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (member_id),
    UNIQUE KEY email (email),
    UNIQUE KEY user_id (user_id),
    KEY membership_type_id (membership_type_id),
    KEY status (status),
    KEY stripe_customer_id (stripe_customer_id),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Relationships:**
- `user_id` → `wp_users.ID` (WordPress user)
- `membership_type_id` → `wp_stsrc_membership_types.membership_type_id`

**Indexes:**
- Primary key: `member_id`
- Unique: `email`, `user_id`
- Foreign key: `membership_type_id`
- Index: `status`, `stripe_customer_id`, `created_at`

#### 4.1.2 `wp_stsrc_membership_types`

Membership type configurations.

**Schema:**
```sql
CREATE TABLE wp_stsrc_membership_types (
    membership_type_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    expiration_period INT(11) NOT NULL COMMENT 'Days until expiration',
    stripe_product_id VARCHAR(255) DEFAULT NULL,
    is_selectable TINYINT(1) NOT NULL DEFAULT 1,
    is_best_seller TINYINT(1) NOT NULL DEFAULT 0,
    can_have_additional_members TINYINT(1) NOT NULL DEFAULT 0,
    benefits JSON DEFAULT NULL COMMENT 'Array of benefit IDs',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (membership_type_id),
    UNIQUE KEY name (name),
    KEY is_selectable (is_selectable)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Relationships:**
- One-to-many with `wp_stsrc_members`

**Indexes:**
- Primary key: `membership_type_id`
- Unique: `name`
- Index: `is_selectable`

#### 4.1.3 `wp_stsrc_family_members`

Family member records (free for Household/Duo).

**Schema:**
```sql
CREATE TABLE wp_stsrc_family_members (
    family_member_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (family_member_id),
    KEY member_id (member_id),
    UNIQUE KEY member_name (member_id, first_name, last_name),
    FOREIGN KEY (member_id) REFERENCES wp_stsrc_members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Relationships:**
- `member_id` → `wp_stsrc_members.member_id` (CASCADE delete)

**Indexes:**
- Primary key: `family_member_id`
- Foreign key: `member_id`
- Unique: `member_id, first_name, last_name` (prevent duplicates)

#### 4.1.4 `wp_stsrc_extra_members`

Extra member records (Household only, $50 each).

**Schema:**
```sql
CREATE TABLE wp_stsrc_extra_members (
    extra_member_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (extra_member_id),
    KEY member_id (member_id),
    UNIQUE KEY member_name (member_id, first_name, last_name),
    FOREIGN KEY (member_id) REFERENCES wp_stsrc_members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Relationships:**
- `member_id` → `wp_stsrc_members.member_id` (CASCADE delete)

**Indexes:**
- Primary key: `extra_member_id`
- Foreign key: `member_id`
- Unique: `member_id, first_name, last_name`

#### 4.1.5 `wp_stsrc_guest_passes`

Guest pass purchases and usage log.

**Schema:**
```sql
CREATE TABLE wp_stsrc_guest_passes (
    guest_pass_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    quantity INT(11) NOT NULL DEFAULT 1,
    amount DECIMAL(10,2) NOT NULL,
    stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
    used_at DATETIME DEFAULT NULL,
    payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_adjusted TINYINT(1) NOT NULL DEFAULT 0,
    adjusted_by BIGINT(20) UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (guest_pass_id),
    KEY member_id (member_id),
    KEY used_at (used_at),
    KEY created_at (created_at),
    FOREIGN KEY (member_id) REFERENCES wp_stsrc_members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Relationships:**
- `member_id` → `wp_stsrc_members.member_id` (CASCADE delete)
- `adjusted_by` → `wp_users.ID` (admin user)

**Indexes:**
- Primary key: `guest_pass_id`
- Foreign key: `member_id`
- Index: `used_at`, `created_at`

#### 4.1.6 `wp_stsrc_email_logs`

Batch email audit trail.

**Schema:**
```sql
CREATE TABLE wp_stsrc_email_logs (
    email_log_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    email_campaign_id VARCHAR(100) NOT NULL,
    member_id BIGINT(20) UNSIGNED DEFAULT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    error_message TEXT DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (email_log_id),
    KEY email_campaign_id (email_campaign_id),
    KEY member_id (member_id),
    KEY status (status),
    KEY sent_at (sent_at),
    FOREIGN KEY (member_id) REFERENCES wp_stsrc_members(member_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Relationships:**
- `member_id` → `wp_stsrc_members.member_id` (SET NULL on delete)

**Indexes:**
- Primary key: `email_log_id`
- Index: `email_campaign_id`, `member_id`, `status`, `sent_at`

#### 4.1.7 `wp_stsrc_access_codes`

Access code management.

**Schema:**
```sql
CREATE TABLE wp_stsrc_access_codes (
    code_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (code_id),
    KEY is_active (is_active),
    KEY expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Indexes:**
- Primary key: `code_id`
- Index: `is_active`, `expires_at`

#### 4.1.8 `wp_stsrc_payment_logs`

Payment transaction records.

**Schema:**
```sql
CREATE TABLE wp_stsrc_payment_logs (
    payment_log_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
    stripe_checkout_session_id VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    fee_amount DECIMAL(10,2) DEFAULT NULL,
    payment_type VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    stripe_event_id VARCHAR(255) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (payment_log_id),
    KEY member_id (member_id),
    KEY stripe_payment_intent_id (stripe_payment_intent_id),
    KEY status (status),
    KEY created_at (created_at),
    FOREIGN KEY (member_id) REFERENCES wp_stsrc_members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Relationships:**
- `member_id` → `wp_stsrc_members.member_id` (CASCADE delete)

**Indexes:**
- Primary key: `payment_log_id`
- Foreign key: `member_id`
- Index: `stripe_payment_intent_id`, `status`, `created_at`

## 5. Server Actions

### 5.1 Database Actions

#### 5.1.1 Member Database Operations

**Class:** `STSRC_Member_DB`

**Methods:**

1. **`create_member(array $data): int`**
   - **Description:** Creates a new member record
   - **Input Parameters:**
     - `$data`: Array with member fields (first_name, last_name, email, etc.)
   - **Return Value:** Member ID (int) on success, false on failure
   - **SQL Operation:**
     ```php
     $wpdb->insert(
         $wpdb->prefix . 'stsrc_members',
         $data,
         ['%s', '%s', '%s', ...] // format strings
     );
     ```

2. **`get_member(int $member_id): ?array`**
   - **Description:** Retrieves member by ID
   - **Input Parameters:** `$member_id` (int)
   - **Return Value:** Member array or null
   - **SQL Operation:**
     ```php
     $wpdb->get_row(
         $wpdb->prepare(
             "SELECT * FROM {$wpdb->prefix}stsrc_members WHERE member_id = %d",
             $member_id
         ),
         ARRAY_A
     );
     ```

3. **`get_member_by_email(string $email): ?array`**
   - **Description:** Retrieves member by email
   - **Input Parameters:** `$email` (string)
   - **Return Value:** Member array or null
   - **SQL Operation:** Similar to `get_member`, with email WHERE clause

4. **`update_member(int $member_id, array $data): bool`**
   - **Description:** Updates member record
   - **Input Parameters:**
     - `$member_id` (int)
     - `$data` (array): Fields to update
   - **Return Value:** true on success, false on failure
   - **SQL Operation:**
     ```php
     $wpdb->update(
         $wpdb->prefix . 'stsrc_members',
         $data,
         ['member_id' => $member_id],
         ['%s', '%s', ...],
         ['%d']
     );
     ```

5. **`delete_member(int $member_id): bool`**
   - **Description:** Soft delete (change status to cancelled) or hard delete
   - **Input Parameters:** `$member_id` (int)
   - **Return Value:** true on success, false on failure
   - **SQL Operation:** UPDATE status or DELETE

6. **`get_members(array $filters = []): array`**
   - **Description:** Retrieves filtered member list
   - **Input Parameters:**
     - `$filters`: Array with keys: membership_type_id, status, payment_type, date_from, date_to, search
   - **Return Value:** Array of member arrays
   - **SQL Operation:** Dynamic WHERE clause based on filters

7. **`get_active_member_count(): int`**
   - **Description:** Counts active, paid members
   - **Return Value:** Count (int)
   - **SQL Operation:**
     ```php
     $wpdb->get_var(
         "SELECT COUNT(*) FROM {$wpdb->prefix}stsrc_members 
          WHERE status = 'active' 
          AND payment_type IN ('card', 'bank_account')"
     );
     ```

#### 5.1.2 Membership Type Database Operations

**Class:** `STSRC_Membership_DB`

**Methods:**

1. **`create_membership_type(array $data): int`**
2. **`get_membership_type(int $id): ?array`**
3. **`get_all_membership_types(bool $selectable_only = false): array`**
4. **`update_membership_type(int $id, array $data): bool`**
5. **`delete_membership_type(int $id): bool`**

#### 5.1.3 Family Member Database Operations

**Class:** `STSRC_Family_Member_DB`

**Methods:**

1. **`add_family_member(int $member_id, array $data): int`**
   - **Description:** Adds family member to member account
   - **Validation:** Check name uniqueness, max count
   - **SQL Operation:** INSERT with unique constraint check

2. **`get_family_members(int $member_id): array`**
3. **`update_family_member(int $family_member_id, array $data): bool`**
4. **`delete_family_member(int $family_member_id): bool`**
5. **`count_family_members(int $member_id): int`**

#### 5.1.4 Guest Pass Database Operations

**Class:** `STSRC_Guest_Pass_DB`

**Methods:**

1. **`update_guest_pass_balance(int $member_id, int $quantity): bool`**
   - **Description:** Increments guest pass balance
   - **SQL Operation:** UPDATE with increment

2. **`use_guest_pass(int $member_id): bool`**
   - **Description:** Decrements balance and logs usage
   - **SQL Operation:** UPDATE balance, INSERT usage log

3. **`get_guest_pass_balance(int $member_id): int`**
4. **`get_guest_pass_log(int $member_id, array $filters = []): array`**
5. **`admin_adjust_balance(int $member_id, int $adjustment, string $notes): bool`**

### 5.2 Other Actions

#### 5.2.1 AJAX Handlers

**Class:** `STSRC_Ajax_Handler`

**Endpoints:**

1. **`stsrc_register_member`**
   - **Action:** `wp_ajax_nopriv_stsrc_register_member`
   - **Description:** Handles registration form submission
   - **Nonce:** `stsrc_registration_nonce`
   - **Process:**
     - Verify nonce
     - Validate CAPTCHA
     - Validate input
     - Check duplicate email
     - Process payment (Stripe or manual)
     - Return JSON response

2. **`stsrc_update_profile`**
   - **Action:** `wp_ajax_stsrc_update_profile`
   - **Description:** Updates member profile
   - **Capability Check:** User must be logged in
   - **Process:** Validate, update database, return success

3. **`stsrc_add_family_member`**
4. **`stsrc_update_family_member`**
5. **`stsrc_delete_family_member`**
6. **`stsrc_purchase_guest_passes`**
7. **`stsrc_create_checkout_session`**
8. **`stsrc_get_customer_portal_url`**
9. **`stsrc_send_batch_email`**
10. **`stsrc_export_members`**
11. **`stsrc_forgot_password`**
12. **`stsrc_reset_password`**

#### 5.2.2 Stripe Integration

**Class:** `STSRC_Payment_Service`

**Methods:**

1. **`create_checkout_session(array $data): string`**
   - **Description:** Creates Stripe Checkout Session
   - **Input Parameters:**
     - `amount` (float): Total amount
     - `membership_type_id` (int)
     - `member_id` (int, optional): For existing members
     - `success_url` (string)
     - `cancel_url` (string)
   - **Return Value:** Checkout session URL (string)
   - **Stripe API:** `\Stripe\Checkout\Session::create()`

2. **`create_customer(array $data): string`**
   - **Description:** Creates Stripe Customer
   - **Input Parameters:** email, name, metadata
   - **Return Value:** Customer ID (string)
   - **Stripe API:** `\Stripe\Customer::create()`

3. **`get_customer_portal_url(string $customer_id): string`**
   - **Description:** Generates Customer Portal session URL
   - **Stripe API:** `\Stripe\BillingPortal\Session::create()`

4. **`handle_payment_success(string $session_id): void`**
   - **Description:** Processes successful payment
   - **Process:**
     - Retrieve session from Stripe
     - Get registration data from transient
     - Create member account
     - Activate member
     - Send emails

**Webhook Handler Class:** `Smoketree_Stripe_Webhooks`

**Method:**

1. **`handle_webhook(WP_REST_Request $request): WP_REST_Response`**
   - **Description:** REST API callback for Stripe webhook endpoint
   - **Endpoint:** `/wp-json/stripe/v1/webhook`
   - **Registration:** `register_rest_route('stripe/v1', '/webhook', ['methods' => 'POST', 'callback' => ['Smoketree_Stripe_Webhooks', 'handle_webhook'], 'permission_callback' => '__return_true'])`
   - **Process:**
     - Verify webhook signature
     - Parse event type
     - Route to appropriate handler:
       - `checkout.session.completed` → Activate member, send welcome email
       - `payment_intent.succeeded` → Update payment log
       - `payment_intent.payment_failed` → Handle failure
     - Log event (idempotency check)
     - Return WP_REST_Response

#### 5.2.3 Email Service

**Class:** `STSRC_Email_Service`

**Methods:**

1. **`send_email(string $template, array $data, string $to, string $subject, array $attachments = []): bool`**
   - **Description:** Sends email using template
   - **Input Parameters:**
     - `$template`: Template filename (e.g., 'welcome.php')
     - `$data`: Array of template variables
     - `$to`: Recipient email
     - `$subject`: Email subject
     - `$attachments`: Array of file paths
   - **Process:**
     - Load template file
     - Replace placeholders
     - Use `wp_mail()` with WP Mail SMTP
     - Log email

2. **`send_batch_email(array $recipients, string $template, array $template_data, string $subject, array $attachments = []): array`**
   - **Description:** Sends email to multiple recipients
   - **Process:**
     - Loop through recipients
     - Send individual emails with rate limiting
     - Log each send
     - Return results array

3. **`render_template(string $template, array $data): string`**
   - **Description:** Renders email template with data
   - **Process:**
     - Load template file
     - Extract variables
     - Replace placeholders: `{first_name}`, `{last_name}`, etc.
     - Return HTML

#### 5.2.4 CAPTCHA Service

**Class:** `STSRC_Captcha_Service`

**Methods:**

1. **`verify_token(string $token): bool`**
   - **Description:** Verifies CAPTCHA token (server-side)
   - **Input Parameters:** `$token` (string): CAPTCHA token
   - **Return Value:** true if valid, false otherwise
   - **Process:**
     - Make API request to CAPTCHA service
     - Verify response
     - Check score threshold (for reCAPTCHA v3)

## 6. Design System

### 6.1 Visual Style

**Color Palette:**
- Primary: `#0066CC` (Blue - trust, professionalism)
- Secondary: `#00A86B` (Green - nature, recreation)
- Accent: `#FF6B35` (Orange - energy, action)
- Success: `#28A745` (Green)
- Error: `#DC3545` (Red)
- Warning: `#FFC107` (Yellow)
- Background (Light): `#FFFFFF`
- Background (Dark): `#1A1A1A`
- Text (Light): `#333333`
- Text (Dark): `#E0E0E0`
- Border: `#DDDDDD`

**Typography:**
- Font Family (Primary): `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif`
- Font Family (Headings): `'Montserrat', sans-serif` (or similar modern sans-serif)
- Font Sizes:
  - H1: `2.5rem` (40px)
  - H2: `2rem` (32px)
  - H3: `1.5rem` (24px)
  - H4: `1.25rem` (20px)
  - Body: `1rem` (16px)
  - Small: `0.875rem` (14px)
- Font Weights:
  - Regular: `400`
  - Medium: `500`
  - Semi-bold: `600`
  - Bold: `700`

**Component Styling Patterns:**
- Buttons: Rounded corners (`border-radius: 6px`), padding `12px 24px`, hover effects
- Forms: Input padding `12px`, border `1px solid #DDDDDD`, focus state with primary color
- Cards: Box shadow `0 2px 8px rgba(0,0,0,0.1)`, border radius `8px`, padding `24px`
- Tables: Striped rows, hover effects, responsive (scroll on mobile)

**Spacing and Layout:**
- Base spacing unit: `8px`
- Container max-width: `1200px`
- Grid system: CSS Grid or Flexbox
- Responsive breakpoints:
  - Mobile: `< 768px`
  - Tablet: `768px - 1024px`
  - Desktop: `> 1024px`

**Dark Mode:**
- Toggle switch in member portal
- CSS variables for color theming
- Media query: `@media (prefers-color-scheme: dark)` as fallback
- Custom class: `.dark-mode` for manual toggle

### 6.2 Component Architecture

**Reusable Components:**
- Form inputs (text, email, select, checkbox, radio)
- Buttons (primary, secondary, danger)
- Modal dialogs
- Toast notifications
- Loading spinners
- Data tables
- Cards
- Tabs

**AJAX Patterns:**
- Loading states: Show spinner, disable form
- Success feedback: Toast notification, update UI
- Error handling: Display error message, highlight invalid fields

## 7. Authentication & Authorization

### 7.1 Authentication

**WordPress User Integration:**
- Each member has a corresponding WordPress user account
- User role: `stsrc_member` (custom role)
- Login uses WordPress authentication system
- Custom login page template for branding

**Password Management:**
- WordPress password hashing
- Password reset via email link
- Password strength validation (WordPress standards)

**Session Management:**
- WordPress session handling
- "Remember me" functionality
- Logout clears session

### 7.2 Authorization

**Capability Checks:**
- Admin functions: `current_user_can('manage_options')`
- Member portal: `is_user_logged_in()` and `user_has_role('stsrc_member')`
- Member data access: Verify `user_id` matches logged-in user

**Role-Based Access:**
- **Administrator**: Full access to all features
- **stsrc_member**: Access to member portal, own data only
- **Custom capabilities**: `stsrc_manage_members`, `stsrc_send_emails`, etc.

**Data Access Control:**
- Members can only view/edit their own data
- Admins can view/edit all member data
- AJAX endpoints verify user permissions

## 8. Data Flow

### 8.1 Server/Client Data Passing

**AJAX Communication:**
- Endpoints: `admin-ajax.php` with action hooks
- Request format: POST with JSON or form data
- Response format: JSON
- Nonce verification for all requests

**REST API (Optional):**
- Endpoint prefix: `/wp-json/stsrc/v1/`
- Authentication: WordPress nonce or JWT
- Used for: Webhooks, future mobile app

**Form Submissions:**
- Standard POST requests for non-AJAX forms
- Nonce fields in all forms
- Server-side validation and processing

### 8.2 State Management

**Server-Side State:**
- Database: Persistent member data, settings
- WordPress Options: Plugin configuration, ACF fields
- Transients: Caching, temporary data (registration data before payment)

**Client-Side State:**
- Minimal JavaScript state (UI only)
- Form data: Stored in DOM, submitted on action
- No complex state management library needed

**Caching Strategy:**
- Transients for expensive queries (member count, etc.)
- Cache duration: 5-15 minutes
- Clear cache on data updates

## 9. Stripe Integration

### 9.1 Webhook Handling Process

**Webhook Endpoint:**
- URL: `{site_url}/wp-json/stripe/v1/webhook`
- Method: POST
- Content-Type: `application/json`
- Registered via: `register_rest_route('stripe/v1', '/webhook', ...)`
- Callback Class: `Smoketree_Stripe_Webhooks`
- Callback Method: `handle_webhook`
- Permission Callback: `__return_true` (public endpoint for Stripe webhooks)

**Security:**
- Verify webhook signature using Stripe secret
- Compare signature from header with computed signature
- Reject if signature doesn't match

**Event Processing:**
1. Receive webhook payload
2. Verify signature
3. Parse event type
4. Route to appropriate handler:
   - `checkout.session.completed` → Activate member
   - `payment_intent.succeeded` → Log payment
   - `payment_intent.payment_failed` → Handle failure
5. Log event (idempotency check)
6. Process event
7. Return 200 status

**Idempotency:**
- Store processed event IDs in database or transient
- Check before processing
- Skip if already processed

### 9.2 Product/Price Configuration

**Stripe Products:**
- One product per membership type
- Product ID stored in `stsrc_membership_types.stripe_product_id`
- Product name: Membership type name
- Product description: Membership type description

**Pricing:**
- Prices calculated dynamically (membership price + flat fee)
- Not using Stripe Prices (one-time payments via Checkout)
- Amount sent to Stripe: Exact total in cents

**Customer Management:**
- Create Stripe Customer on registration (if card/bank payment)
- Store Customer ID in `stsrc_members.stripe_customer_id`
- Link to Customer Portal for payment method updates

**Checkout Session Configuration:**
- Mode: `payment` (one-time)
- Payment method types: `card`, `us_bank_account`
- Success URL: Member portal with success parameter
- Cancel URL: Registration page with cancel parameter
- Metadata: Include member_id, membership_type_id for reference

### 9.3 Payment Flow Details

**Registration Payment:**
1. User selects payment type (Card/Bank Account)
2. Calculate total: `membership_price + flat_fee`
3. Create Checkout Session
4. Store registration data in transient (key: `stsrc_registration_{session_id}`)
5. Redirect to Stripe Checkout
6. User completes payment
7. Webhook receives `checkout.session.completed`
8. Retrieve registration data from transient
9. Create WordPress user and member record
10. Activate member
11. Send welcome email
12. Clear transient

**Guest Pass Purchase:**
1. Member selects quantity
2. Calculate total: `quantity * 5.00`
3. Create Checkout Session
4. Success URL: Guest pass portal
5. Webhook updates balance
6. Send confirmation email

**Extra Member Payment:**
1. Member adds extra member
2. Create Checkout Session for $50
3. On success, activate extra member
4. Update member record

**Auto-Renewal Payment:**
1. Cron job triggers on renewal date
2. Get member's Stripe Customer ID
3. Get default payment method
4. Create Payment Intent
5. Confirm payment
6. Update expiration date
7. Send confirmation email

---

## Implementation Notes

### Development Phases

**Phase 1: Core Infrastructure**
- Plugin structure setup
- Database schema creation
- Basic admin pages
- Member CRUD operations

**Phase 2: Registration & Payment**
- Registration form
- CAPTCHA integration
- Stripe integration
- Webhook handling
- Email templates

**Phase 3: Member Portal**
- Login system
- Dashboard
- Profile management
- Family/Extra member CRUD

**Phase 4: Guest Passes**
- Purchase flow
- Balance tracking
- Usage logging
- Portal page

**Phase 5: Admin Features**
- Batch email system
- Analytics dashboard
- Access codes
- Export functionality

**Phase 6: Auto-Renewal**
- Opt-in system
- Renewal processing
- Bulk status management

### Testing Considerations

- Unit tests for database operations
- Integration tests for Stripe webhooks
- End-to-end tests for registration flow
- Security testing (nonce, capability checks)
- Performance testing (batch email, large member lists)

### Security Checklist

- [ ] All inputs validated and sanitized
- [ ] All outputs escaped
- [ ] Nonces on all forms
- [ ] Capability checks on all admin functions
- [ ] SQL injection prevention ($wpdb->prepare())
- [ ] Webhook signature verification
- [ ] Password strength validation
- [ ] Rate limiting on forms
- [ ] CAPTCHA on registration
- [ ] Secure password reset tokens

---

This specification provides comprehensive guidance for implementing the Smoketree Swim and Recreation Club WordPress Plugin. All features, database schemas, and implementation details are documented to enable efficient development and code generation.

