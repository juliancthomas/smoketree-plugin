# Implementation Plan

<brainstorming>
After reviewing all the project documentation, I've identified the following key areas that need to be implemented:

1. **Core Infrastructure**: Plugin structure setup, database schema, basic configuration
2. **Database Layer**: Custom tables for members, membership types, family/extra members, guest passes, email logs, access codes, payment logs
3. **Authentication & Authorization**: WordPress user integration, custom roles, login/logout, password reset
4. **Registration System**: Form with CAPTCHA, dynamic fields, payment type handling, Stripe integration
5. **Payment Processing**: Stripe Checkout, webhooks, flat fee calculation, customer portal
6. **Member Portal**: Dashboard, profile management, family/extra member CRUD, guest pass management
7. **Admin Dashboard**: Member management, membership types, batch operations, analytics
8. **Guest Pass System**: Purchase flow, balance tracking, usage logging, QR code portal
9. **Email System**: Template rendering, batch sending, logging, notifications
10. **Auto-Renewal**: Opt-in system, renewal processing, bulk status management
11. **Access Codes**: Admin CRUD, member display
12. **UI/UX**: Custom CSS, dark mode, responsive design, AJAX interactions

The plan should follow WordPress Plugin Boilerplate architecture, use the `stsrc_` prefix consistently, and ensure each step is atomic and manageable. I'll organize it into logical phases that build upon each other.
</brainstorming>

## Phase 1: Core Infrastructure & Database Setup

- [ ] Step 1: Rename and configure main plugin files
  - **Task**: Update plugin header, rename main plugin file, update class names and prefixes from `plugin-name` to `smoketree-plugin` and `stsrc_`, update version constant, set text domain to `smoketree-plugin`
  - **Files**:
    - `smoketree-plugin/plugin-name.php`: Rename to `smoketree-plugin.php`, update plugin header, rename activation/deactivation functions, update class instantiation
    - `smoketree-plugin/includes/class-plugin-name.php`: Rename to `class-smoketree-plugin.php`, update class name to `Smoketree_Plugin`, update all references
    - `smoketree-plugin/includes/class-plugin-name-loader.php`: Rename to `class-smoketree-plugin-loader.php`, update class name to `Smoketree_Plugin_Loader`
    - `smoketree-plugin/includes/class-plugin-name-i18n.php`: Rename to `class-smoketree-plugin-i18n.php`, update class name to `Smoketree_Plugin_i18n`, set text domain
    - `smoketree-plugin/includes/class-plugin-name-activator.php`: Rename to `class-smoketree-plugin-activator.php`, update class name to `Smoketree_Plugin_Activator`
    - `smoketree-plugin/includes/class-plugin-name-deactivator.php`: Rename to `class-smoketree-plugin-deactivator.php`, update class name to `Smoketree_Plugin_Deactivator`
    - `smoketree-plugin/admin/class-plugin-name-admin.php`: Rename to `class-smoketree-plugin-admin.php`, update class name to `Smoketree_Plugin_Admin`
    - `smoketree-plugin/public/class-plugin-name-public.php`: Rename to `class-smoketree-plugin-public.php`, update class name to `Smoketree_Plugin_Public`
  - **Step Dependencies**: None
  - **User Instructions**: None
  - **Git Commit**: `feat: rename plugin files and update class names to smoketree-plugin`

- [ ] Step 2: Create database schema and setup classes
  - **Task**: Create database setup class with methods to create all custom tables (members, membership_types, family_members, extra_members, guest_passes, email_logs, access_codes, payment_logs) following the schema from technical specification. Update activator to call database setup.
  - **Files**:
    - `smoketree-plugin/includes/database/class-stsrc-database.php`: Create new class `STSRC_Database` with `create_tables()` method that creates all 8 custom tables with proper indexes and foreign keys
    - `smoketree-plugin/includes/class-smoketree-plugin-activator.php`: Update `activate()` method to call `STSRC_Database::create_tables()`, create custom user role `stsrc_member`, set default options
  - **Step Dependencies**: Step 1
  - **User Instructions**: None
  - **Git Commit**: `feat: create database schema and setup classes for all custom tables`

- [ ] Step 3: Create database operation classes for members and membership types
  - **Task**: Create database operation classes with CRUD methods for members and membership types using $wpdb->prepare() for security.
  - **Files**:
    - `smoketree-plugin/includes/database/class-stsrc-member-db.php`: Create class `STSRC_Member_DB` with methods: `create_member()`, `get_member()`, `get_member_by_email()`, `update_member()`, `delete_member()`, `get_members()`, `get_active_member_count()`
    - `smoketree-plugin/includes/database/class-stsrc-membership-db.php`: Create class `STSRC_Membership_DB` with methods: `create_membership_type()`, `get_membership_type()`, `get_all_membership_types()`, `update_membership_type()`, `delete_membership_type()`
  - **Step Dependencies**: Step 2
  - **User Instructions**: None
  - **Git Commit**: `feat: add database operation classes for members and membership types`

- [ ] Step 4: Create database operation classes for family members, extra members, and guest passes
  - **Task**: Create database classes for family members, extra members, and guest pass operations with proper validation and foreign key relationships.
  - **Files**:
    - `smoketree-plugin/includes/database/class-stsrc-family-member-db.php`: Create class `STSRC_Family_Member_DB` with methods: `add_family_member()`, `get_family_members()`, `update_family_member()`, `delete_family_member()`, `count_family_members()`
    - `smoketree-plugin/includes/database/class-stsrc-extra-member-db.php`: Create class `STSRC_Extra_Member_DB` with methods: `add_extra_member()`, `get_extra_members()`, `update_extra_member()`, `delete_extra_member()`, `count_extra_members()`
    - `smoketree-plugin/includes/database/class-stsrc-guest-pass-db.php`: Create class `STSRC_Guest_Pass_DB` with methods: `update_guest_pass_balance()`, `use_guest_pass()`, `get_guest_pass_balance()`, `get_guest_pass_log()`, `admin_adjust_balance()`
  - **Step Dependencies**: Step 2
  - **User Instructions**: None
  - **Git Commit**: `feat: add database classes for family members, extra members, and guest passes`

- [ ] Step 5: Create database classes for email logs, access codes, and payment logs
  - **Task**: Create database operation classes for email logging, access code management, and payment transaction logging.
  - **Files**:
    - `smoketree-plugin/includes/database/class-stsrc-email-log-db.php`: Create class `STSRC_Email_Log_DB` with methods: `log_email()`, `get_email_logs()`, `get_campaign_logs()`, `update_email_status()`
    - `smoketree-plugin/includes/database/class-stsrc-access-code-db.php`: Create class `STSRC_Access_Code_DB` with methods: `create_access_code()`, `get_access_codes()`, `get_active_access_codes()`, `update_access_code()`, `delete_access_code()`
    - `smoketree-plugin/includes/database/class-stsrc-payment-log-db.php`: Create class `STSRC_Payment_Log_DB` with methods: `log_payment()`, `get_payment_logs()`, `get_payment_by_intent_id()`, `update_payment_status()`
  - **Step Dependencies**: Step 2
  - **User Instructions**: None
  - **Git Commit**: `feat: add database classes for email logs, access codes, and payment logs`

- [ ] Step 6: Create model classes for data representation
  - **Task**: Create model classes that wrap database operations and provide object-oriented interfaces for member, membership type, and guest pass data.
  - **Files**:
    - `smoketree-plugin/includes/models/class-stsrc-member.php`: Create class `STSRC_Member` with properties and methods to represent member data
    - `smoketree-plugin/includes/models/class-stsrc-membership-type.php`: Create class `STSRC_Membership_Type` with properties and methods for membership type data
    - `smoketree-plugin/includes/models/class-stsrc-guest-pass.php`: Create class `STSRC_Guest_Pass` with properties and methods for guest pass data
  - **Step Dependencies**: Steps 3, 4, 5
  - **User Instructions**: None
  - **Git Commit**: `feat: create model classes for member, membership type, and guest pass`

## Phase 2: Service Layer & Core Functionality

- [ ] Step 7: Create email service class with template rendering
  - **Task**: Create email service class that can render email templates from the templates directory, replace placeholders, and send emails via wp_mail(). Support batch sending with rate limiting.
  - **Files**:
    - `smoketree-plugin/includes/services/class-stsrc-email-service.php`: Create class `STSRC_Email_Service` with methods: `send_email()`, `send_batch_email()`, `render_template()`, `replace_placeholders()`
    - `smoketree-plugin/templates/payment-success.php`: Create email template file (placeholder for now)
    - `smoketree-plugin/templates/subscription-update.php`: Create email template file
    - `smoketree-plugin/templates/password-reset.php`: Create email template file
    - `smoketree-plugin/templates/payment-reminder.php`: Create email template file
    - `smoketree-plugin/templates/thank-you-pay-later.php`: Create email template file
    - `smoketree-plugin/templates/welcome.php`: Create email template file
    - `smoketree-plugin/templates/welcome-civic.php`: Create email template file
    - `smoketree-plugin/templates/treasurer-pay-later.php`: Create email template file
    - `smoketree-plugin/templates/notify-admin-of-member.php`: Create email template file
    - `smoketree-plugin/templates/notify-admin-of-guest-pass.php`: Create email template file
    - `smoketree-plugin/templates/notify-admin-guest-pass-was-used.php`: Create email template file
    - `smoketree-plugin/templates/notify-admins-of-failed-registration.php`: Create email template file
    - `smoketree-plugin/templates/guest-pass-purchase.php`: Create email template file
  - **Step Dependencies**: Step 5
  - **User Instructions**: None
  - **Git Commit**: `feat: create email service class and email template files`

- [ ] Step 8: Create CAPTCHA service class
  - **Task**: Create CAPTCHA service class that integrates with Google reCAPTCHA v3 or hCaptcha. Include both client-side token generation and server-side verification.
  - **Files**:
    - `smoketree-plugin/includes/services/class-stsrc-captcha-service.php`: Create class `STSRC_Captcha_Service` with methods: `verify_token()`, `get_site_key()`, `get_secret_key()`, `is_enabled()`
  - **Step Dependencies**: None
  - **User Instructions**: Configure CAPTCHA site key and secret key in plugin settings (will be added in settings step)
  - **Git Commit**: `feat: create CAPTCHA service class for spam prevention`

- [ ] Step 9: Integrate Stripe PHP SDK and create payment service
  - **Task**: Download and include Stripe PHP SDK in vendor directory (no Composer). Create payment service class with methods for creating checkout sessions, customers, and customer portal URLs. Include flat fee calculation logic.
  - **Files**:
    - `smoketree-plugin/includes/services/class-stsrc-payment-service.php`: Create class `STSRC_Payment_Service` with methods: `create_checkout_session()`, `create_customer()`, `get_customer_portal_url()`, `calculate_total_with_fee()`, `get_flat_fee()`, `handle_payment_success()`
  - **Step Dependencies**: None
  - **User Instructions**: Download Stripe PHP SDK from https://github.com/stripe/stripe-php and place in `smoketree-plugin/vendor/stripe/stripe-php/`. Configure Stripe API keys in plugin settings.
  - **Git Commit**: `feat: integrate Stripe SDK and create payment service class`

- [ ] Step 10: Create Stripe webhook handler
  - **Task**: Create REST API endpoint for Stripe webhooks with signature verification. Handle checkout.session.completed, payment_intent.succeeded, and payment_intent.payment_failed events.
  - **Files**:
    - `smoketree-plugin/includes/api/class-smoketree-stripe-webhooks.php`: Create class `Smoketree_Stripe_Webhooks` with method `handle_webhook()` and event routing logic
    - `smoketree-plugin/includes/class-smoketree-plugin.php`: Register REST API route for webhook endpoint
  - **Step Dependencies**: Step 9
  - **User Instructions**: Configure webhook endpoint URL in Stripe dashboard: `{site_url}/wp-json/stripe/v1/webhook`. Add webhook signing secret to plugin settings.
  - **Git Commit**: `feat: create Stripe webhook handler with signature verification`

- [ ] Step 11: Create member service class
  - **Task**: Create member service class that orchestrates member-related business logic, combining database operations, email sending, and WordPress user management.
  - **Files**:
    - `smoketree-plugin/includes/services/class-stsrc-member-service.php`: Create class `STSRC_Member_Service` with methods: `create_member_account()`, `activate_member()`, `update_member_profile()`, `change_password()`, `get_member_data()`, `check_duplicate_email()`
  - **Step Dependencies**: Steps 3, 7
  - **User Instructions**: None
  - **Git Commit**: `feat: create member service class for business logic`

## Phase 3: AJAX Handlers & API Endpoints

- [ ] Step 12: Create AJAX handler class and registration endpoint
  - **Task**: Create AJAX handler class and implement registration endpoint that validates CAPTCHA, checks duplicate emails, processes payment type selection, and handles both Stripe and manual payment flows.
  - **Files**:
    - `smoketree-plugin/includes/api/class-stsrc-ajax-handler.php`: Create class `STSRC_Ajax_Handler` with method `register_member()` and registration logic
    - `smoketree-plugin/includes/class-smoketree-plugin.php`: Register AJAX actions for logged-in and non-logged-in users
  - **Step Dependencies**: Steps 8, 9, 11
  - **User Instructions**: None
  - **Git Commit**: `feat: create AJAX handler and member registration endpoint`

- [ ] Step 13: Add AJAX endpoints for member portal operations
  - **Task**: Add AJAX endpoints for profile updates, password changes, family member CRUD, extra member CRUD, and guest pass purchases.
  - **Files**:
    - `smoketree-plugin/includes/api/class-stsrc-ajax-handler.php`: Add methods: `update_profile()`, `change_password()`, `add_family_member()`, `update_family_member()`, `delete_family_member()`, `add_extra_member()`, `update_extra_member()`, `delete_extra_member()`, `purchase_guest_passes()`, `get_customer_portal_url()`
  - **Step Dependencies**: Step 12
  - **User Instructions**: None
  - **Git Commit**: `feat: add AJAX endpoints for member portal operations`

- [ ] Step 14: Add AJAX endpoints for admin operations
  - **Task**: Add AJAX endpoints for admin member management, batch email sending, CSV export, and access code management.
  - **Files**:
    - `smoketree-plugin/includes/api/class-stsrc-ajax-handler.php`: Add methods: `export_members()`, `send_batch_email()`, `admin_adjust_guest_passes()`, `bulk_update_members()`
  - **Step Dependencies**: Step 12
  - **User Instructions**: None
  - **Git Commit**: `feat: add AJAX endpoints for admin operations`

- [ ] Step 15: Add password reset AJAX endpoints
  - **Task**: Add AJAX endpoints for forgot password and password reset functionality with secure token generation and email sending.
  - **Files**:
    - `smoketree-plugin/includes/api/class-stsrc-ajax-handler.php`: Add methods: `forgot_password()`, `reset_password()`, `validate_reset_token()`
  - **Step Dependencies**: Step 12, Step 7
  - **User Instructions**: None
  - **Git Commit**: `feat: add password reset AJAX endpoints`

## Phase 4: Admin Interface

- [ ] Step 16: Create admin menu structure and dashboard page
  - **Task**: Create WordPress admin menu with submenu items for Members, Membership Types, Guest Passes, Batch Email, Settings, and Dashboard. Create dashboard page class with widgets for member count and recent activity.
  - **Files**:
    - `smoketree-plugin/admin/pages/class-stsrc-dashboard-page.php`: Create class `STSRC_Dashboard_Page` with dashboard rendering
    - `smoketree-plugin/admin/partials/dashboard-widgets.php`: Create dashboard widget templates
    - `smoketree-plugin/admin/class-smoketree-plugin-admin.php`: Add menu registration and page instantiation
  - **Step Dependencies**: Step 3
  - **User Instructions**: None
  - **Git Commit**: `feat: create admin menu structure and dashboard page`

- [ ] Step 17: Create members management admin page
  - **Task**: Create admin page for member CRUD operations with search, filtering (membership type, status, payment type, date range), pagination, and member detail/edit views.
  - **Files**:
    - `smoketree-plugin/admin/pages/class-stsrc-members-page.php`: Create class `STSRC_Members_Page` with member list, search, filters, and CRUD operations
    - `smoketree-plugin/admin/partials/members-list.php`: Create member list table template
    - `smoketree-plugin/admin/partials/member-edit.php`: Create member edit form template
    - `smoketree-plugin/admin/class-smoketree-plugin-admin.php`: Register members page
  - **Step Dependencies**: Step 16
  - **User Instructions**: None
  - **Git Commit**: `feat: create members management admin page`

- [ ] Step 18: Create membership types management admin page
  - **Task**: Create admin page for membership type CRUD with all required fields (name, description, price, expiration period, Stripe product ID, is_selectable, is_best_seller, can_have_additional_members, benefits checkboxes). Create default membership types on activation.
  - **Files**:
    - `smoketree-plugin/admin/pages/class-stsrc-memberships-page.php`: Create class `STSRC_Memberships_Page` with membership type CRUD
    - `smoketree-plugin/admin/partials/membership-types-list.php`: Create membership types list template
    - `smoketree-plugin/admin/partials/membership-type-edit.php`: Create membership type form template
    - `smoketree-plugin/includes/class-smoketree-plugin-activator.php`: Add method to create default membership types
  - **Step Dependencies**: Step 16
  - **User Instructions**: None
  - **Git Commit**: `feat: create membership types management page and default types`

- [ ] Step 19: Create guest passes management admin page
  - **Task**: Create admin page for viewing guest pass usage logs, adjusting balances, and viewing analytics with date filtering.
  - **Files**:
    - `smoketree-plugin/admin/pages/class-stsrc-guest-passes-page.php`: Create class `STSRC_Guest_Passes_Page` with usage log display and balance adjustment
    - `smoketree-plugin/admin/partials/guest-passes-list.php`: Create guest pass log table template
  - **Step Dependencies**: Step 16
  - **User Instructions**: None
  - **Git Commit**: `feat: create guest passes management admin page`

- [ ] Step 20: Create batch email composer admin page
  - **Task**: Create admin page for composing batch emails with member filtering, attachment support, preview recipient count, and progress tracking during sending.
  - **Files**:
    - `smoketree-plugin/admin/pages/class-stsrc-email-page.php`: Create class `STSRC_Email_Page` with email composer interface
    - `smoketree-plugin/admin/partials/email-composer.php`: Create email composer form template
  - **Step Dependencies**: Step 16, Step 7
  - **User Instructions**: None
  - **Git Commit**: `feat: create batch email composer admin page`

- [ ] Step 21: Create access codes management admin page
  - **Task**: Create admin page for CRUD operations on access codes with expiration date and active status management.
  - **Files**:
    - `smoketree-plugin/admin/pages/class-stsrc-access-codes-page.php`: Create class `STSRC_Access_Codes_Page` with access code CRUD
    - `smoketree-plugin/admin/partials/access-codes-list.php`: Create access codes list template
  - **Step Dependencies**: Step 16
  - **User Instructions**: None
  - **Git Commit**: `feat: create access codes management admin page`

- [ ] Step 22: Create settings page with ACF integration
  - **Task**: Create settings page that uses ACF fields for configuration: Stripe API keys, CAPTCHA keys, registration toggle, payment plan toggle, secretary email, season renewal date. Create ACF field groups.
  - **Files**:
    - `smoketree-plugin/admin/pages/class-stsrc-settings-page.php`: Create class `STSRC_Settings_Page` with settings form
    - `smoketree-plugin/admin/partials/settings-form.php`: Create settings form template
    - `smoketree-plugin/acf-json/group_settings.json`: Create ACF field group export (optional, can be created via ACF UI)
  - **Step Dependencies**: Step 16
  - **User Instructions**: Install ACF Pro plugin. Create ACF field groups via ACF UI or import JSON file. Configure Stripe API keys, CAPTCHA keys, and other settings.
  - **Git Commit**: `feat: create settings page with ACF integration`

- [ ] Step 23: Create admin CSS and JavaScript
  - **Task**: Create admin-specific CSS and JavaScript files for styling admin pages, handling AJAX interactions, form validation, and UI enhancements.
  - **Files**:
    - `smoketree-plugin/admin/css/smoketree-plugin-admin.css`: Create admin stylesheet with table styles, form styles, button styles
    - `smoketree-plugin/admin/js/smoketree-plugin-admin.js`: Create admin JavaScript for AJAX calls, form handling, data tables, filters
    - `smoketree-plugin/admin/class-smoketree-plugin-admin.php`: Update enqueue methods to load CSS/JS on admin pages
  - **Step Dependencies**: Step 16
  - **User Instructions**: None
  - **Git Commit**: `feat: create admin CSS and JavaScript files`

## Phase 5: Frontend Templates & Member Portal

- [ ] Step 24: Create registration page template and form
  - **Task**: Create registration page template with all required fields, dynamic family/extra member fields based on membership selection, CAPTCHA integration, payment type selection, and AJAX form submission.
  - **Files**:
    - `smoketree-plugin/public/templates/registration-form.php`: Create registration page template
    - `smoketree-plugin/public/partials/registration-form.php`: Create registration form partial with all fields
    - `smoketree-plugin/public/class-smoketree-plugin-public.php`: Register page template, enqueue assets on registration page
  - **Step Dependencies**: Steps 12, 8
  - **User Instructions**: Create a WordPress page with slug "register" and assign the registration template, or use page template detection
  - **Git Commit**: `feat: create registration page template and form`

- [ ] Step 25: Create login and password reset page templates
  - **Task**: Create login page template, forgot password page template, and password reset page template with proper form handling and redirects.
  - **Files**:
    - `smoketree-plugin/public/templates/login.php`: Create login page template
    - `smoketree-plugin/public/templates/forgot-password.php`: Create forgot password page template
    - `smoketree-plugin/public/templates/reset-password.php`: Create password reset page template
    - `smoketree-plugin/public/class-smoketree-plugin-public.php`: Register page templates, handle login/logout redirects
  - **Step Dependencies**: Step 15
  - **User Instructions**: Create WordPress pages with slugs "login", "forgot-password", and "reset-password"
  - **Git Commit**: `feat: create login and password reset page templates`

- [ ] Step 26: Create member portal dashboard template
  - **Task**: Create member portal dashboard template that displays membership details, family members, extra members, guest pass balance, access codes, and provides links to edit profile, manage members, and purchase guest passes.
  - **Files**:
    - `smoketree-plugin/public/templates/member-portal.php`: Create member portal dashboard template
    - `smoketree-plugin/public/partials/member-profile.php`: Create member profile display partial
    - `smoketree-plugin/public/partials/family-members.php`: Create family members list partial
    - `smoketree-plugin/public/partials/guest-pass-balance.php`: Create guest pass balance display partial
    - `smoketree-plugin/public/class-smoketree-plugin-public.php`: Register member portal template, check authentication
  - **Step Dependencies**: Step 13
  - **User Instructions**: Create WordPress page with slug "member-portal" and assign template
  - **Git Commit**: `feat: create member portal dashboard template`

- [ ] Step 27: Create guest pass portal page template
  - **Task**: Create guest pass portal page template that displays balance (even if 0), purchase link if balance is 0, usage log, and requires login. This page will be accessed via QR code.
  - **Files**:
    - `smoketree-plugin/public/templates/guest-pass-portal.php`: Create guest pass portal template
    - `smoketree-plugin/public/class-smoketree-plugin-public.php`: Register guest pass portal template, require authentication
  - **Step Dependencies**: Step 13
  - **User Instructions**: Create WordPress page with slug "guest-pass-portal". Provide URL to third-party QR code generator service.
  - **Git Commit**: `feat: create guest pass portal page template`

- [ ] Step 28: Create frontend CSS with dark mode support
  - **Task**: Create frontend CSS file with modern styling, responsive design, dark mode support using CSS variables, and styling for all frontend templates.
  - **Files**:
    - `smoketree-plugin/public/css/smoketree-plugin-public.css`: Create frontend stylesheet with color variables, dark mode styles, responsive breakpoints, form styles, button styles, card styles
    - `smoketree-plugin/public/class-smoketree-plugin-public.php`: Update enqueue method to load CSS on frontend pages
  - **Step Dependencies**: Steps 24, 25, 26, 27
  - **User Instructions**: None
  - **Git Commit**: `feat: create frontend CSS with dark mode support`

- [ ] Step 29: Create frontend JavaScript for AJAX interactions
  - **Task**: Create frontend JavaScript file that handles AJAX form submissions, loading states, error handling, dynamic form fields (family/extra members), and UI interactions.
  - **Files**:
    - `smoketree-plugin/public/js/smoketree-plugin-public.js`: Create frontend JavaScript for registration form, member portal AJAX, form validation, loading states, toast notifications
    - `smoketree-plugin/public/class-smoketree-plugin-public.php`: Update enqueue method to load JS with localized script data (AJAX URL, nonces)
  - **Step Dependencies**: Steps 12, 13, 24, 26
  - **User Instructions**: None
  - **Git Commit**: `feat: create frontend JavaScript for AJAX interactions`

## Phase 6: Payment Processing & Webhook Integration

- [ ] Step 30: Complete Stripe webhook processing for registration
  - **Task**: Complete webhook handler to process checkout.session.completed events for new member registrations. Retrieve registration data from transient, create WordPress user, create member record, activate member, send welcome email, send admin notifications.
  - **Files**:
    - `smoketree-plugin/includes/api/class-smoketree-stripe-webhooks.php`: Complete `handle_checkout_session_completed()` method with full registration processing
    - `smoketree-plugin/includes/services/class-stsrc-payment-service.php`: Update `handle_payment_success()` method
  - **Step Dependencies**: Steps 10, 11, 7
  - **User Instructions**: Test webhook endpoint with Stripe CLI or webhook testing tool
  - **Git Commit**: `feat: complete Stripe webhook processing for member registration`

- [ ] Step 31: Implement guest pass purchase webhook processing
  - **Task**: Add webhook handling for guest pass purchases. Update balance, log transaction, send confirmation emails on successful payment.
  - **Files**:
    - `smoketree-plugin/includes/api/class-smoketree-stripe-webhooks.php`: Add `handle_guest_pass_purchase()` method
    - `smoketree-plugin/includes/services/class-stsrc-payment-service.php`: Add method for guest pass checkout session creation
  - **Step Dependencies**: Step 30
  - **User Instructions**: None
  - **Git Commit**: `feat: implement guest pass purchase webhook processing`

- [ ] Step 32: Implement extra member payment webhook processing
  - **Task**: Add webhook handling for extra member payments ($50 each). Activate extra member on successful payment.
  - **Files**:
    - `smoketree-plugin/includes/api/class-smoketree-stripe-webhooks.php`: Add `handle_extra_member_payment()` method
  - **Step Dependencies**: Step 30
  - **User Instructions**: None
  - **Git Commit**: `feat: implement extra member payment webhook processing`

- [ ] Step 33: Implement payment failure handling
  - **Task**: Add webhook handling for payment_intent.payment_failed events. Send failure notifications, keep member as pending, log failures.
  - **Files**:
    - `smoketree-plugin/includes/api/class-smoketree-stripe-webhooks.php`: Add `handle_payment_failed()` method
  - **Step Dependencies**: Step 30
  - **User Instructions**: None
  - **Git Commit**: `feat: implement payment failure handling in webhooks`

## Phase 7: Auto-Renewal System

- [ ] Step 34: Create auto-renewal service class
  - **Task**: Create service class for auto-renewal functionality with methods to process renewals, send renewal notifications, and handle bulk status updates.
  - **Files**:
    - `smoketree-plugin/includes/services/class-stsrc-auto-renewal-service.php`: Create class `STSRC_Auto_Renewal_Service` with methods: `process_renewals()`, `send_renewal_notifications()`, `bulk_update_status()`, `get_members_for_renewal()`
  - **Step Dependencies**: Steps 9, 11
  - **User Instructions**: None
  - **Git Commit**: `feat: create auto-renewal service class`

- [ ] Step 35: Add auto-renewal opt-in to member portal
  - **Task**: Add auto-renewal toggle checkbox to member portal dashboard with AJAX endpoint to update member preference.
  - **Files**:
    - `smoketree-plugin/public/partials/member-profile.php`: Add auto-renewal toggle checkbox
    - `smoketree-plugin/includes/api/class-stsrc-ajax-handler.php`: Add `toggle_auto_renewal()` method
  - **Step Dependencies**: Steps 26, 34
  - **User Instructions**: None
  - **Git Commit**: `feat: add auto-renewal opt-in to member portal`

- [ ] Step 36: Create WordPress cron jobs for auto-renewal
  - **Task**: Register WordPress cron events for renewal notification (7 days before) and renewal processing (on renewal date). Create cron handlers.
  - **Files**:
    - `smoketree-plugin/includes/services/class-stsrc-auto-renewal-service.php`: Add cron handler methods
    - `smoketree-plugin/includes/class-smoketree-plugin-activator.php`: Register cron events on activation
    - `smoketree-plugin/includes/class-smoketree-plugin-deactivator.php`: Clear cron events on deactivation
  - **Step Dependencies**: Step 34
  - **User Instructions**: Ensure WordPress cron is enabled (WP_CRON constant)
  - **Git Commit**: `feat: create WordPress cron jobs for auto-renewal`

- [ ] Step 37: Add bulk status management to admin
  - **Task**: Add admin interface for bulk status updates (mark all as inactive for new season) with confirmation dialog.
  - **Files**:
    - `smoketree-plugin/admin/pages/class-stsrc-members-page.php`: Add bulk status update method and UI
    - `smoketree-plugin/admin/partials/members-list.php`: Add bulk action checkboxes and dropdown
  - **Step Dependencies**: Steps 17, 34
  - **User Instructions**: None
  - **Git Commit**: `feat: add bulk status management to admin interface`

## Phase 8: Email Templates & Final Polish

- [ ] Step 38: Implement all email templates with proper HTML structure
  - **Task**: Create complete HTML email templates with proper styling, placeholders, and responsive design for all 13 email templates.
  - **Files**:
    - `smoketree-plugin/templates/payment-success.php`: Complete email template
    - `smoketree-plugin/templates/subscription-update.php`: Complete email template
    - `smoketree-plugin/templates/password-reset.php`: Complete email template
    - `smoketree-plugin/templates/payment-reminder.php`: Complete email template
    - `smoketree-plugin/templates/thank-you-pay-later.php`: Complete email template
    - `smoketree-plugin/templates/welcome.php`: Complete email template
    - `smoketree-plugin/templates/welcome-civic.php`: Complete email template
    - `smoketree-plugin/templates/treasurer-pay-later.php`: Complete email template
    - `smoketree-plugin/templates/notify-admin-of-member.php`: Complete email template
    - `smoketree-plugin/templates/notify-admin-of-guest-pass.php`: Complete email template
    - `smoketree-plugin/templates/notify-admin-guest-pass-was-used.php`: Complete email template
    - `smoketree-plugin/templates/notify-admins-of-failed-registration.php`: Complete email template
    - `smoketree-plugin/templates/guest-pass-purchase.php`: Complete email template
  - **Step Dependencies**: Step 7
  - **User Instructions**: Customize email templates with club branding and content
  - **Git Commit**: `feat: implement all email templates with HTML structure`

- [ ] Step 39: Add security hardening and validation
  - **Task**: Add comprehensive input validation, sanitization, output escaping, nonce verification, and capability checks throughout the plugin. Add rate limiting for forms.
  - **Files**:
    - `smoketree-plugin/includes/api/class-stsrc-ajax-handler.php`: Add validation and sanitization to all methods
    - `smoketree-plugin/admin/pages/*.php`: Add capability checks to all admin pages
    - `smoketree-plugin/public/templates/*.php`: Add output escaping to all templates
    - `smoketree-plugin/includes/services/class-stsrc-captcha-service.php`: Add rate limiting helper methods
  - **Step Dependencies**: All previous steps
  - **User Instructions**: None
  - **Git Commit**: `feat: add comprehensive security hardening and validation`

- [ ] Step 40: Add error handling and logging
  - **Task**: Add error logging throughout the plugin using WordPress error logging. Handle edge cases and provide user-friendly error messages.
  - **Files**:
    - `smoketree-plugin/includes/services/class-stsrc-logger.php`: Create logger utility class
    - Update all service classes and AJAX handlers to use logger for errors
  - **Step Dependencies**: All previous steps
  - **User Instructions**: None
  - **Git Commit**: `feat: add error handling and logging throughout plugin`

- [ ] Step 41: Create uninstall cleanup functionality
  - **Task**: Update uninstall.php to clean up database tables, options, user roles, cron events, and transients on plugin uninstall.
  - **Files**:
    - `smoketree-plugin/uninstall.php`: Complete uninstall cleanup logic
    - `smoketree-plugin/includes/database/class-stsrc-database.php`: Add `drop_tables()` method
  - **Step Dependencies**: Step 2
  - **User Instructions**: None
  - **Git Commit**: `feat: create uninstall cleanup functionality`

- [ ] Step 42: Final testing and documentation
  - **Task**: Create README.txt with installation instructions, update plugin header, add inline code documentation, create changelog entry.
  - **Files**:
    - `smoketree-plugin/README.txt`: Complete plugin readme with installation, configuration, and usage instructions
    - `smoketree-plugin/CHANGELOG.md`: Create changelog file
    - Update all PHP files with proper PHPDoc comments
  - **Step Dependencies**: All previous steps
  - **User Instructions**: Review and test all functionality before deployment
  - **Git Commit**: `docs: add comprehensive documentation and changelog`

---

## Summary

This implementation plan breaks down the Smoketree Swim and Recreation Club WordPress Plugin into 42 manageable steps organized into 8 phases:

1. **Phase 1 (Steps 1-6)**: Core infrastructure and database setup
2. **Phase 2 (Steps 7-11)**: Service layer and core functionality
3. **Phase 3 (Steps 12-15)**: AJAX handlers and API endpoints
4. **Phase 4 (Steps 16-23)**: Admin interface
5. **Phase 5 (Steps 24-29)**: Frontend templates and member portal
6. **Phase 6 (Steps 30-33)**: Payment processing and webhook integration
7. **Phase 7 (Steps 34-37)**: Auto-renewal system
8. **Phase 8 (Steps 38-42)**: Email templates and final polish

Each step is designed to be atomic and implementable in a single iteration, with clear dependencies and file lists. The plan follows WordPress Plugin Boilerplate architecture, uses the `stsrc_` prefix consistently, and adheres to WordPress coding standards and security best practices.

**Key Considerations:**
- All database operations use `$wpdb->prepare()` for SQL injection prevention
- All form submissions include nonce verification
- All admin functions include capability checks
- All output is properly escaped
- Stripe SDK is bundled directly (no Composer)
- ACF Pro is used for admin configuration fields
- Custom CSS is used (no Tailwind) to avoid WordPress admin conflicts
- Dark mode is supported for member portal UI
- Email templates are fully customizable
- Webhook security is implemented with signature verification
- Rate limiting is included for forms and batch operations

