# Membership Renewal Portal

## Project Description
Add a season renewal section to the existing Smoketree member portal that allows current members to renew their membership for the upcoming season. Members can keep their current membership type, upgrade, or downgrade — with full visibility into pricing, benefits gained/lost, and family/extra member management. Supports both Stripe (card, ACH) and non-Stripe (Zelle, check) payment methods.

## Target Audience
Existing Smoketree members accessing their member portal to renew for the new season.

## Desired Features

### Admin Controls
- [ ] New ACF toggle field `stsrc_renewal_enabled` to enable/disable the renewal section portal-wide
- [ ] Renewal uses existing `stsrc_season_renewal_date` ACF field for expiration calculation

### Season/Year Tracking
- [ ] Add mechanism to track which season a member has renewed for (approach TBD — column vs. renewals table)
- [ ] Prevent double-renewal for the same season

### Renewal Section UI (Member Portal)
- [ ] Show renewal section when `stsrc_renewal_enabled` is on
- [ ] Display all membership types as cards (mirroring registration form style)
- [ ] Highlight the member's current/previous membership type ("You are currently a Household member")
- [ ] Show benefits per type so members understand what they gain/lose on upgrade/downgrade
- [ ] Pre-populate existing family members and extra members for the member's current type
- [ ] Allow members to keep, remove, or add family members based on the selected type
- [ ] Allow Household members to add optional extra members at $50 each
- [ ] Show downgrade warnings when switching to a type with fewer benefits (e.g., losing pool access)

### Membership Type Change Rules
- [ ] Any member can upgrade or downgrade to any type
- [ ] Civic → Household: must add at least 2 family members
- [ ] Civic → Duo: must add at least 1 family member
- [ ] Civic → Single: no family members needed
- [ ] Duo → Household: must add at least 1 more family member (total 2+)
- [ ] Duo → Civic: family member soft-deleted
- [ ] Duo → Single: family member soft-deleted
- [ ] Single → Household: must add at least 2 family members
- [ ] Single → Duo: must add 1 family member
- [ ] Single → Civic: no additional changes
- [ ] Household → Duo: user picks which 1 family member to keep, rest + all extra members soft-deleted
- [ ] Household → Single: all family members + extra members soft-deleted
- [ ] Household → Civic: all family members + extra members soft-deleted

### Pricing & Order Summary
- [ ] Membership price from DB (current prices: Household $575, Duo $475, Single $295, Civic $125)
- [ ] Extra members: $50 each (Household only, up to 3)
- [ ] Combine any existing `balance_owed` with new renewal amount (behavior TBD)
- [ ] Processing fees matching registration form:
    - [ ] Credit/Debit Card: 2.9% + $0.30
    - [ ] Bank Account (ACH): 0.8% (max $5.00)
    - [ ] Zelle: no fee
    - [ ] Check: no fee
- [ ] Live-updating order summary (membership + extra members + processing fee + existing balance = total)

### Payment Flow
- [ ] Payment method selection: Card, ACH, Zelle, Check
- [ ] Stripe payments (Card, ACH): create Stripe Checkout session, redirect to Stripe
- [ ] Non-Stripe payments (Zelle, Check): set balance_owed on member, status to pending
- [ ] On successful Stripe payment: update member record, activate for new season
- [ ] On non-Stripe selection: member has balance in portal, status = pending until admin confirms

### Member Record Updates (on renewal)
- [ ] Update `membership_type_id` to new selection
- [ ] Update `expiration_date` based on `stsrc_season_renewal_date` + `expiration_period`
- [ ] Update `balance_owed` (combine existing balance + renewal cost)
- [ ] Update `original_membership_price` with new renewal price
- [ ] Soft-delete removed family members and extra members
- [ ] Add new family members / extra members as needed
- [ ] Skip waiver re-signing (already signed at registration)

### Email Notifications
- [ ] **To member**: Renewal confirmation email thanking them, listing their membership type and benefits
    - [ ] New template: `renewal-confirmation.php` (pool-access types)
    - [ ] New template: `renewal-confirmation-civic.php` (Civic)
    - [ ] For non-Stripe: include payment instructions (similar to `thank-you-pay-later.php`)
- [ ] **To admins**: Renewal notice with member details, old type → new type, amount, payment method
    - [ ] New template: `renewal-admin-notice.php`
    - [ ] Recipients: Treasurer, Secretary (CC, comma-separated from `stsrc_secretary_email`), President, Vice President

## Design Requests
- [ ] Renewal section styled consistently with existing portal sections
- [ ] Membership type cards matching registration form card design
- [ ] Current membership type visually highlighted/badged
- [ ] Downgrade warning displayed as a notice/alert when losing benefits
- [ ] Order summary matching registration form summary style
- [ ] Payment method selection matching registration form style

## Other Notes
- Auto-renewal is assumed OFF for all members in this phase
- No Pay Later option for renewal (only Card, ACH, Zelle, Check) — confirm?
- Extra members are only available for Household memberships
- Family members are included in membership price (no extra charge)
- Processing fees are identical to the registration form
- Need to handle Stripe webhook for renewal payments (new metadata type `renewal`?)