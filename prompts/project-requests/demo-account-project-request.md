# Demo Account System

## Project Description
Add a "demo account" capability to the Smoketree Club plugin that allows administrators to flag member accounts as "demo" in production. Demo accounts are fully functional for demonstration, testing, and staff training purposes, but are completely invisible to real business metrics, reporting, billing, automated processes, and communications. Demo accounts route all Stripe interactions through test keys (even in live mode) and can never be converted back to real accounts.

## Target Audience
- **Plugin administrators** demoing the system to board members or prospective members
- **Developers** testing new features in production without affecting real data
- **Staff** being trained on registration, portal, and admin workflows

## Desired Features

### Demo Flag on Member Records
- [ ] Add an `is_demo` boolean column (default `0`) to the `wp_stsrc_members` table
- [ ] Add a toggle/checkbox on the admin Member Edit page to flag any member as demo
- [ ] Once flagged as demo, the flag is **permanent** — cannot be reversed
- [ ] No limit on number of demo accounts
- [ ] Database migration added to plugin activation/update hook

### Stripe Payment Isolation
- [ ] Demo account registrations route to Stripe **test** keys even when the site is in live/production mode
    - [ ] Swap Stripe publishable key client-side when admin creates a demo registration
    - [ ] Create checkout sessions using the Stripe test secret key for demo accounts
    - [ ] Store `is_demo` metadata on the Stripe checkout session for downstream identification
- [ ] Webhook handling recognizes demo-originated payments and processes them without affecting real data
- [ ] Demo accounts never interact with Stripe production APIs
- [ ] Demo users use Stripe test card numbers (e.g., `4242 4242 4242 4242`) during checkout

### Exclusion from Aggregate Counts & Reporting
- [ ] Exclude demo accounts from `get_active_member_count()` 
- [ ] Exclude demo accounts from all dashboard stats and widgets
- [ ] Exclude demo accounts from any member totals displayed in admin
- [ ] Exclude demo accounts from balance integrity checks and recalculations
- [ ] Exclude demo accounts from CSV/data exports

### Exclusion from Automated Processes
- [ ] Exclude demo accounts from auto-renewal processes
- [ ] Suppress **all** system emails for demo accounts:
    - [ ] Welcome emails
    - [ ] Payment confirmation emails
    - [ ] Guest pass purchase emails
    - [ ] Payment reminder emails
    - [ ] Admin notification emails triggered by demo account activity
- [ ] Exclude demo accounts from batch email sends

### Balance, Guest Pass & Family Member Isolation
- [ ] Demo account balances function normally in the member portal (full UX)
- [ ] Demo account balances excluded from aggregate balance reporting
- [ ] Demo account guest pass purchases and usage function normally in the portal
- [ ] Demo account guest pass counts excluded from any aggregate reporting
- [ ] Demo account family members and extra members function normally in the portal
- [ ] Demo account family/extra members excluded from any aggregate counts

### Admin UI — Members List
- [ ] Visual "DEMO" badge/pill displayed next to demo account names in the Members list
- [ ] Filter option in Members list to show: All / Real only / Demo only
- [ ] Demo badge visible on Member Edit/Detail page

### Member Portal (Demo User Experience)
- [ ] Demo user's portal looks and functions identically to a real member's portal
- [ ] No visible indicator to the demo user that the account is a demo
- [ ] All portal features work: profile editing, family members, guest passes, balance viewing, etc.

## Design Requests
- [ ] Demo badge should be a small colored pill/tag reading "DEMO"
    - [ ] Distinct but not obnoxious (e.g., a muted purple or orange pill)
    - [ ] Consistent styling with existing admin UI patterns
- [ ] Visible in Members list rows next to the member name
- [ ] Visible on Member Edit/Detail page header area

## Other Notes

### Technical Considerations
- The `is_demo` column should be added via the existing database migration pattern in `class-smoketree-plugin-activator.php`
- Need to audit **every** query that counts or aggregates members to add `WHERE is_demo = 0` (or equivalent)
- Stripe key swapping is the highest-complexity piece:
    - Client-side: registration form must use the test publishable key when the admin demo toggle is active
    - Server-side: `STSRC_Payment_Service` must check the member's `is_demo` flag and use test keys accordingly
    - Webhooks: test-mode Stripe events will arrive at the same webhook URL — must handle gracefully by checking metadata or event mode
- Email suppression should be implemented at a central point (e.g., a helper function that checks `is_demo` before any `wp_mail()` call) rather than scattered throughout individual email sends
- Batch email query must add `is_demo = 0` filter
- CSV export query must add `is_demo = 0` filter
- The demo flag being permanent avoids the problem of a "demo" account with test Stripe payment history being treated as a real account

### Scope Boundaries
- Demo accounts do **not** need a separate admin management page — they are managed inline via the existing Members list and edit pages
- No special "demo mode" for the entire site — this is per-account
- No changes to the public-facing registration form UX for non-admin users