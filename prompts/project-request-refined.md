# Smoketree Swim and Recreation Club - WordPress Plugin

## Project Description

A comprehensive WordPress membership management plugin for Smoketree Swim and Recreation Club. The plugin handles member registration, payment processing via Stripe, guest pass management, batch email communications, and provides both member and admin portals. Built with PHP 8.0.30, WordPress 6.9, following modern OOP principles and WordPress coding standards.

## Target Audience

- **Primary Users**: Club members (registration, portal access, guest passes)
- **Secondary Users**: Club administrators (member management, batch operations, analytics)
- **Technical Context**: WordPress 6.9, PHP 8.0.30, ACF Pro, WP Mail SMTP, Stripe PHP SDK

## Desired Features

### Core Infrastructure
- [ ] Plugin structure following WordPress Plugin Boilerplate architecture
- [ ] Unique prefixing for all functions, classes, constants (e.g., `stsrc_` or `smoketree_`)
- [ ] Object-oriented design with namespacing
- [ ] Strict type declarations (PHP 8.0+)
- [ ] Stripe PHP SDK included directly in plugin (no Composer support on shared host)
- [ ] Internationalization (i18n) support with text domain
- [ ] Activation/deactivation hooks for database setup/cleanup
- [ ] Security: input validation, sanitization, output escaping, nonces, capability checks

### Member Management
- [ ] Admin CRUD operations for member information
- [ ] Member self-service CRUD in portal (with validation)
- [ ] Member status management (active, pending, cancelled)
- [ ] Member export to CSV with filtering options
  - [ ] Filter by membership type
  - [ ] Filter by status
  - [ ] Filter by payment type
  - [ ] Filter by date range
- [ ] Member search and filtering in admin
- [ ] Member count display (only paid/active members)
- [ ] Password change functionality for members
- [ ] Forgot password flow with email reset link
- [ ] Password reset email template (password-reset.php)

### Membership Types & Configuration
- [ ] CRUD operations for membership types
- [ ] Membership type fields:
  - [ ] Name
  - [ ] Description
  - [ ] Price
  - [ ] Expiration date/period
  - [ ] Stripe Product ID
  - [ ] Is Selectable (show in registration)
  - [ ] Mark as Best Seller
  - [ ] Can Have Additional Members (family/extra)
  - [ ] Benefits selection (checkboxes):
    - [ ] Up to 5 people
    - [ ] 2 people
    - [ ] 1 person
    - [ ] Pool use for season
    - [ ] Lakefront and Dock
    - [ ] Playground
    - [ ] Tennis/Pickleball Court
    - [ ] Dog Run
    - [ ] Pavilion
    - [ ] Membership Voting Rights
- [ ] Default membership types:
  - [ ] Household (1 primary + 4 family, +3 extra @ $50 each)
  - [ ] Duo (1 primary + 1 family)
  - [ ] Single (1 primary)
  - [ ] Civic (1 primary, voting only, no pool)

### Family & Extra Members
- [ ] Add/remove Family Members (free for Household/Duo)
  - [ ] Household: up to 4 family members
  - [ ] Duo: up to 1 family member
  - [ ] Dynamic form fields based on membership type
  - [ ] Require unique names (no duplicates)
  - [ ] Optional email per family member
- [ ] Add/remove Extra Members (Household only, $50 each)
  - [ ] Maximum 3 extra members
  - [ ] Payment required before activation
  - [ ] Stripe integration for extra member payments
- [ ] Member portal CRUD for family/extra members
- [ ] Admin override for family/extra member management

### Member Registration
- [ ] Registration form at `/register`
- [ ] Registration toggle (ACF field to enable/disable registration page)
- [ ] Form fields:
  - [ ] First name (required)
  - [ ] Last name (required)
  - [ ] Email (required, unique validation)
  - [ ] Street 1 (required)
  - [ ] Street 2 (optional)
  - [ ] City (auto-fill: "Tucker")
  - [ ] State (auto-fill: "GA")
  - [ ] Zip (auto-fill: "30084")
  - [ ] Country (optional, default: "US")
  - [ ] Phone (required)
  - [ ] Membership selection (required, dynamic based on "Is Selectable")
  - [ ] Password (required, strength validation)
  - [ ] Confirm password (required, match validation)
  - [ ] Referral source (dropdown, ordered: "A current or previous member" first, then: social media, friend or family, search engine, news article, advertisement, event, other)
  - [ ] Waiver Full Name (required)
  - [ ] Waiver Signed Date (required, auto-fill current date)
  - [ ] Payment Type selection (Card, Bank Account, Zelle, Check, Pay Later)
- [ ] CAPTCHA integration (free solution, e.g., Google reCAPTCHA v3 or hCaptcha)
- [ ] Form validation (prevent jibberish/spam entries)
- [ ] AJAX form submission with loading states
- [ ] Error handling and user feedback
- [ ] Failed registration handling (don't create account if payment fails)
- [ ] Duplicate email prevention (only create account on successful payment)

### Payment Processing
- [ ] Stripe integration via PHP SDK
- [ ] Payment type handling:
  - [ ] Card/Bank Account: Redirect to Stripe Checkout
    - [ ] Calculate total with fees
    - [ ] Credit card fee: Flat fees ($6 Single, $8 Duo, $10 Household)
    - [ ] Send exact amount to Stripe
  - [ ] Zelle/Check/Pay Later: Email notifications only
    - [ ] Email to admin (treasurer-pay-later.php template)
    - [ ] Email to member (thank-you-pay-later.php template)
- [ ] Stripe webhook handling
  - [ ] Payment success → activate member, send welcome email
  - [ ] Payment failure → keep as pending, send notification
  - [ ] Subscription updates → update member status
- [ ] Stripe Customer creation for each member
- [ ] Link to Stripe Customer Portal for payment method updates
- [ ] Payment plan toggle (ACF field to enable/disable)
- [ ] Payment reminder emails (payment-reminder.php template)

### Guest Passes
- [ ] Guest pass pricing: $5 each
- [ ] Member purchase guest passes (unlimited quantity)
- [ ] Admin add guest passes to member accounts
- [ ] Guest pass balance tracking per member
- [ ] Guest pass portal page (accessed via QR code scan at pool)
- [ ] Guest pass page display (show balance, even if 0)
- [ ] Login required to access guest pass portal (redirect to login if not authenticated)
- [ ] If balance is 0: Display "0 passes" with link to Stripe purchase page
- [ ] After Stripe purchase: Redirect back to guest pass page
- [ ] Guest pass usage logging
  - [ ] Date/time of use
  - [ ] Member who used it
  - [ ] Payment status (paid/owed)
  - [ ] Admin adjustments logged
- [ ] QR code URL provided for third-party QR code generation (links to guest pass portal)
- [ ] Guest pass purchase email (guest-pass-purchase.php template)
- [ ] Admin notification on purchase (notify-admin-of-guest-pass.php template)
- [ ] Admin notification on usage (notify-admin-guest-pass-was-used.php template)
- [ ] Guest pass analytics dashboard

### Member Portal
- [ ] Member authentication/login system
- [ ] Portal dashboard showing:
  - [ ] Membership details and status
  - [ ] Expiration date
  - [ ] Family members list
  - [ ] Extra members list
  - [ ] Guest pass balance
  - [ ] Recent activity
- [ ] Member profile editing (AJAX)
- [ ] Password change functionality
- [ ] Family member CRUD (AJAX)
- [ ] Extra member CRUD (AJAX, with payment)
- [ ] Guest pass purchase interface
- [ ] Link to Stripe Customer Portal
- [ ] View access codes
- [ ] Responsive design with custom CSS (no Tailwind - clashes with WordPress admin styling)
- [ ] Dark mode support for member UI

### Access Codes Management
- [ ] Admin CRUD for access codes
- [ ] Code display in member portal
- [ ] Secure code storage
- [ ] Code expiration/rotation support

### Batch Email System
- [ ] Admin email composer interface
- [ ] Member filtering for batch emails:
  - [ ] Membership type
  - [ ] Status (active, pending, cancelled)
  - [ ] Payment type
  - [ ] Date range
  - [ ] Custom criteria
- [ ] Email sending via WP Mail SMTP (no-reply@smoketree.us)
- [ ] Multiple attachment support
- [ ] Progress tracking during batch send
- [ ] Email logging (sent, failed, bounced)
- [ ] Email history/audit trail
- [ ] Rate limiting to prevent server overload

### Email Templates
- [ ] Template system for HTML emails
- [ ] Template files:
  - [ ] payment-success.php
  - [ ] subscription-update.php
  - [ ] password-reset.php
  - [ ] payment-reminder.php
  - [ ] thank-you-pay-later.php
  - [ ] welcome.php
  - [ ] welcome-civic.php
  - [ ] treasurer-pay-later.php
  - [ ] notify-admin-of-member.php (send to all admins + secretary email)
  - [ ] notify-admin-of-guest-pass.php
  - [ ] notify-admin-guest-pass-was-used.php
  - [ ] notify-admins-of-failed-registration.php
  - [ ] guest-pass-purchase.php
- [ ] Template variables/placeholders
- [ ] Admin ability to edit templates (optional)
- [ ] Secretary email configuration (receive new registration notifications)

### Admin Dashboard
- [ ] WordPress admin menu integration
- [ ] Dashboard widgets/shortcodes:
  - [ ] Total members count (paid/active only)
  - [ ] Recent signups/activity
  - [ ] Guest pass usage log (date-filterable)
  - [ ] Pending payments
  - [ ] Membership analytics
- [ ] Live metrics display
- [ ] Quick actions for common tasks

### Auto-Renewal System
- [ ] Member opt-in for auto-renewal in portal
- [ ] Custom renewal solution (not Stripe subscriptions - renews on set date regardless of signup date)
- [ ] Admin sets renewal date (season-wide date)
- [ ] Email notification to member before auto-renewal date
- [ ] Automatic payment processing on renewal date
- [ ] Pull existing account information for renewal
- [ ] Bulk status management (mark all as inactive for new season)

### Registration Form Enhancements
- [ ] Dynamic family member fields (based on membership selection)
- [ ] Clear visual separation for additional member sections
- [ ] Bold/emphasized instructions for required fields
- [ ] Form field validation with real-time feedback
- [ ] Conditional field display (show family fields only for applicable memberships)

### Security & Validation
- [ ] CAPTCHA on registration form
- [ ] CAPTCHA on email list signup (or replace with manual email instruction)
- [ ] Input validation (email format, phone format, etc.)
- [ ] Sanitization of all user input
- [ ] Output escaping for all displayed data
- [ ] Nonce verification for all form submissions
- [ ] Capability checks for admin functions
- [ ] Rate limiting on forms
- [ ] SQL injection prevention ($wpdb->prepare())

### WordPress Template Pages
- [ ] Registration page template (`/register`)
- [ ] Member portal page template
- [ ] Login/logout page template
- [ ] Forgot password page template
- [ ] Guest pass portal page template (accessed via QR code scan, requires login)
- [ ] Integration with theme or standalone styling

## Design Requests
- [ ] Modern, premium UI/UX feel
- [ ] Custom CSS styling (no Tailwind - avoids conflicts with WordPress admin)
- [ ] Dark mode for member portal UI
- [ ] AJAX interactions for seamless experience
- [ ] Loading states and animations
- [ ] Responsive design (mobile, tablet, desktop)
- [ ] Accessible forms and interfaces (WCAG compliance)
- [ ] Clear visual hierarchy
- [ ] Consistent color scheme and branding
- [ ] Professional email template designs

## Technical Considerations

### Database Schema
- [ ] Custom tables for members, memberships, guest passes, email logs (best practice approach)
- [ ] Database schema design following WordPress conventions
- [ ] Proper indexing for performance

### ACF Pro Integration
- [ ] Use ACF for admin configuration fields
- [ ] Payment plan toggle as ACF field
- [ ] Membership type configuration via ACF
- [ ] Settings pages using ACF

### Stripe Integration
- [ ] Stripe PHP SDK included directly in plugin (no Composer on shared host)
- [ ] Webhook endpoint for payment events
- [ ] Customer portal integration
- [ ] Product/Price management
- [ ] Custom payment handling for auto-renewal (not Stripe subscriptions)
- [ ] Guest pass purchase redirect back to guest pass page after Stripe checkout

### Performance
- [ ] Conditional asset loading
- [ ] Transient caching for expensive queries
- [ ] Optimized database queries
- [ ] Lazy loading for large member lists

## Other Notes

### Budget Constraints
- Free/open-source solutions preferred where possible
- Minimal third-party paid services

### Future Considerations (Not in Initial Build)
- [ ] WhatsApp integration (manual opt-in only)
- [ ] Mailchimp integration (for email list management)
- [ ] Digital sign-in process with QR code (separate from guest passes)
- [ ] Member check-in feature/log

### Clarifications Resolved
1. **Payment Fees**: Flat fees ($6 Single, $8 Duo, $10 Household) - NOT 3%
2. **Member Statuses**: active, pending, cancelled (3 statuses only)
3. **Auto-Renewal**: Member-initiated opt-in, custom solution (not Stripe subscriptions), renews on admin-set date regardless of signup date, email notification before renewal
4. **Database Approach**: Custom tables (best practice)
5. **Referral Sources**: "A current or previous member" (first), then: social media, friend or family, search engine, news article, advertisement, event, other
6. **QR Codes**: Generated by third-party website, printed and placed at pool; when scanned, takes users to guest pass portal (login required)
7. **Email Notifications**: Secretary email should receive new registration emails (in addition to existing admin notifications)
8. **Registration Toggle**: ACF field to enable/disable registration page (for season management)
9. **Season Management**: Bulk status updates via admin interface (mark all as inactive for new season)
10. **Composer**: Not available on shared host - Stripe SDK included directly in plugin
11. **Forgot Password**: Email with reset link flow implemented
12. **Guest Passes**: Show balance (even if 0) with purchase link, redirect back after Stripe purchase; QR codes generated by third-party website, printed at pool, link to guest pass portal (login required)
13. **Styling**: Custom CSS (no Tailwind to avoid WordPress admin conflicts)
14. **Dark Mode**: Support for member portal UI

### Development Standards
- Follow attached "best practices.md" document
- PHP 8.0.30 compatibility
- WordPress 6.9 compatibility
- WordPress Coding Standards (PHPCS)
- Object-oriented architecture
- Security-first approach
- Professional code quality for public GitHub

