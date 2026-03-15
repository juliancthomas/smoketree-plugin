# Promo Codes & Affiliate Referral System

## Project Description
Add a promo code system and an affiliate referral program to the Smoketree Club registration process. Admins create promotional codes with custom names, discount types (flat or percentage), expiration dates, usage limits, and membership type restrictions. Every active member receives an auto-generated affiliate referral code (format: REF-LASTNAME-####) displayed in their member portal with a shareable referral link. Members can share their referral URL (e.g., `https://smoketree.us/register/?ref=REF-THOMAS-8231`) on Facebook, Slack, text, etc. — the link auto-applies the discount on the registration page. New registrants can also manually enter either a promo code or an affiliate code (not both) in a "Discounts" section during registration. Codes are validated via AJAX and discounts appear as line items in the order summary. When a referral code is used, the treasurer receives an email notification to issue the referring member's credit manually. Admin reporting tracks promo code usage and affiliate referral history with payout status.

## Target Audience
- Club admins managing promotions and viewing reports
- Club treasurer tracking and paying out affiliate referral credits via email notifications
- New members registering and applying a discount code
- Existing active members sharing their referral link to earn credits

## Desired Features

### Promo Code Management (Admin)
- [ ] Admin interface to create, edit, delete, and deactivate promo codes
    - [ ] Custom code name (e.g., "TuckerDay100") — admin chooses the name
    - [ ] Discount type: flat dollar amount OR percentage
    - [ ] Discount value (e.g., $100 or 10%)
    - [ ] Expiration date
    - [ ] One-time use toggle — code can only be used once total (by anyone), then it's consumed
    - [ ] Usage limit: unlimited OR capped at a specific number of total uses (separate from one-time use)
    - [ ] Restrict to specific membership types (e.g., only Household) or allow all
    - [ ] Active/inactive toggle
- [ ] Promo codes stored in a custom database table (following existing plugin pattern)
- [ ] Promo codes are for initial registration only — not available during renewal

### Affiliate Code System (Member Portal)
- [ ] Every active member is automatically assigned an affiliate referral code
    - [ ] Format: REF-LASTNAME-#### (e.g., REF-JSMITH-4821)
    - [ ] Generated on member creation
    - [ ] Retroactively generated for all existing members (migration/backfill)
    - [ ] Collision avoidance: random #### portion must be checked against existing codes before saving
- [ ] Affiliate code displayed in the Membership Information section of the member portal
    - [ ] "Copy Referral Link" button that copies the full URL (e.g., `https://smoketree.us/register/?ref=REF-THOMAS-8231`)
    - [ ] Visual confirmation feedback on copy (e.g., "Copied!")
- [ ] Affiliate codes are reusable — multiple new members can use the same code
- [ ] Only active members' codes are valid
    - [ ] If a code belongs to an inactive/cancelled member, show: "This referral code is no longer active"

### Referral Link System (URL-Based Referral Flow)
- [ ] Referral URL structure: `https://smoketree.us/register/?ref=REF-LASTNAME-####`
    - [ ] Simple, shareable format for Facebook, Slack, text messages, etc.
- [ ] Auto-populate on page load
    - [ ] Check for `ref` GET parameter when registration page loads
    - [ ] If present, auto-fill the Affiliate Code field with the code from the URL
    - [ ] Immediately trigger AJAX validation
    - [ ] If valid, show "Discount Applied" state and lock the Promo Code field
    - [ ] Show a toast/banner at the top: "Referral discount from [Member Name] will be applied at checkout!"
- [ ] Cookie persistence (safety net for users who leave and come back)
    - [ ] When a user arrives with a `ref` parameter, store the code in a short-lived cookie (48 hours)
    - [ ] On the registration page, if the `ref` URL parameter is missing, check for the cookie
    - [ ] If cookie exists, auto-fill and validate the affiliate code as if it came from the URL
- [ ] Manual entry overrides URL parameter — if the user manually types a different code, that explicit choice takes priority
- [ ] OpenGraph meta tags (optional/nice-to-have)
    - [ ] When `ref` param is present, modify OG tags so link previews say something like "Join Smoketree Club with my discount!"
    - [ ] Use WordPress hooks to filter OG output

### Discount Section (Registration Form)
- [ ] "Discounts" section added to the registration form with two separate fields
    - [ ] One field for promo codes with its own "Apply" button
    - [ ] One field for affiliate/referral codes with its own "Apply" button
    - [ ] Positioned before the Order Summary section
- [ ] No stacking — only one discount (promo OR affiliate) can be applied per registration
    - [ ] Applying one disables the other field with a message (e.g., "Only one discount can be applied")
    - [ ] Removing an applied discount re-enables both fields
- [ ] AJAX validation on apply:
    - [ ] Promo code checks: exists, active, not expired, not already consumed (one-time use), usage limit not exceeded, membership type allowed
    - [ ] Affiliate code checks: exists, belongs to an active member
    - [ ] Success: shows discount details and adds line item to order summary
    - [ ] Error: shows contextual message (invalid code, expired, usage limit reached, "This referral code is no longer active", membership type not eligible)
- [ ] Ability to remove/clear an applied discount and enter a different code
- [ ] Discount reflected as a line item in the order summary
    - [ ] Promo: "Promo: TuckerDay100 — -$100" or "Promo: TuckerDay100 — -10%"
    - [ ] Affiliate: "Referral Discount — -$500"
- [ ] Discount applied to the payment total:
    - [ ] Stripe payments (card/ACH): reduced amount sent to Stripe Checkout
    - [ ] Zelle/Check/Pay Later: reduced balance_owed
    - [ ] If discount exceeds membership price, cap at $0 (free registration)

### Affiliate Referral Credit (Treasurer Email Notification)
- [ ] When a new member registers using an affiliate code, the system sends an email to the treasurer
    - [ ] Email sent to the address stored in stsrc_treasurer_email
    - [ ] Email includes: referring member name, new member name, credit amount ($50 — configurable via ACF)
    - [ ] Email uses existing plugin email template styling
- [ ] No automatic balance adjustments — treasurer handles credit payout manually
- [ ] Credit amount configurable via ACF field: stsrc_affiliate_referrer_credit (default: $50)

### Admin Reporting & Tracking
- [ ] Promo code usage report
    - [ ] Times each code has been used
    - [ ] Total discount amount given per code
    - [ ] Which members used which codes
- [ ] Affiliate referral log
    - [ ] Who referred whom
    - [ ] Date of referral
    - [ ] Credit amount owed to referrer
    - [ ] Payout status: pending or paid out (treasurer can toggle)
- [ ] Reporting accessible from a dedicated admin page or dashboard widget

### ACF Configuration Fields (Added to Existing Settings)
- [ ] stsrc_affiliate_new_member_discount — Number field, default $500 (discount for new member using referral code)
- [ ] stsrc_affiliate_referrer_credit — Number field, default $50 (credit owed to referring member)
- [ ] Both fields use existing wp_options fallback pattern

## Design Requests
- [ ] "Discounts" section in the registration form with two clearly labeled fields
    - [ ] Inline AJAX validation with success/error states
    - [ ] Applied discount shown with a remove/clear option
    - [ ] Disabled state for the other field when one discount is applied
- [ ] Referral link arrival experience
    - [ ] Toast or banner at top of registration page: "Referral discount from [Name] will be applied at checkout!"
    - [ ] Affiliate code field pre-filled and validated
- [ ] Discount line items in the order summary matching existing styling
- [ ] Affiliate referral link in member portal Membership Information section
    - [ ] "Copy Referral Link" button (copies full URL, not just the code)
    - [ ] Visual confirmation feedback (e.g., "Copied!")
- [ ] Admin promo code management page matching existing admin UI patterns
- [ ] Admin referral log with pending/paid status toggle
- [ ] Treasurer email notification using existing email template styling

## Other Notes
- No existing discount/coupon logic — this is entirely new functionality
- Promo codes are distinct from existing "access codes" (which control pool/area access) — naming and database tables must avoid confusion
- Stripe Checkout is used for card/ACH — discount must reduce the checkout session amount
- Zelle/Check/Pay Later flows use balance_owed — discount reduces that value
- ACF is already used for plugin settings with wp_options fallback pattern
- Treasurer email (stsrc_treasurer_email) already exists in plugin settings
- Existing members need affiliate codes generated retroactively (one-time migration with collision checking)
- One-time use promo codes: after a single use by anyone, the code is marked as consumed and cannot be used again
- Promo codes with a usage limit: track usage count, reject when limit is reached
- If a percentage discount results in a fractional cent, round to nearest cent
- Cookie for referral code persistence should be short-lived (48 hours) and HttpOnly
- OpenGraph meta tag customization for referral links is a nice-to-have, not a blocker
