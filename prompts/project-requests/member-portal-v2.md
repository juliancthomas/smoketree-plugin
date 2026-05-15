# Member Portal UI/UX Improvements

## Project Description
A set of targeted UI/UX improvements to the member portal to reduce cold-open friction,
surface key account information earlier, fix confusing transaction history display, and
clean up the visual presentation of access codes and profile data.

## Target Audience
Swim club members managing their own membership accounts via the member portal.

## Desired Features

### Page Header & First Impression
- [ ] Replace the generic "Member Portal" heading with a personalized greeting
    - [ ] Display member's first name: "Welcome back, [First Name]"
    - [ ] Show membership status badge inline with or immediately below the greeting
    - [ ] Status badge should reflect Active / Pending / Inactive

### Member Profile Section
- [ ] Show current address in the profile info display (not just inside the Edit modal)
    - [ ] Display Street 1, Street 2 (if present), City, State, ZIP as a single formatted address row
- [ ] Remove the auto-renewal toggle and all related UI
    - [ ] Remove the "Enable automatic renewal" checkbox
    - [ ] Remove the "Manage Payment Methods" button
    - [ ] Remove the auto-renewal note/description text

### Transaction History
- [ ] Add a year selector to transaction history
    - [ ] Default to current year
    - [ ] Allow switching to prior years (back to earliest year with a transaction on record)
    - [ ] Reload transactions via AJAX when year changes — no full page reload
- [ ] Fix the transaction amount sign and color convention
    - [ ] Payments made by the member (money out) should display as negative (red/debit)
    - [ ] Credits, refunds, and adjustments in the member's favor should display as positive (green/credit)
    - [ ] Sign, color, and label must all tell the same story — no mixed signals between `+/-` and debit/credit classes

### Access Codes Section
- [ ] Redesign the access codes section visual treatment
    - [ ] Remove plain blue `<strong>` text styling for code values
    - [ ] Display each code in a styled chip or card — visually distinct, easy to read at a glance
    - [ ] Code value should be large and legible (monospace or similar)
    - [ ] Description text should sit below the code value in a secondary style

### Error Handling
- [ ] Replace `wp_die()` on missing member record with a graceful redirect
    - [ ] Redirect to home page (`home_url('/')`) instead of showing a raw WordPress error screen

## Design Requests
- [ ] Greeting should feel warm, not clinical — first name only, no "Dear" or formal salifiers
- [ ] Status badge in the greeting area should use the existing badge color conventions (green = active, yellow = pending, grey = inactive)
- [ ] Access code cards should feel like a credential display — not a generic list item
- [ ] Transaction history debit/credit colors should be consistent with common banking conventions (red = you paid, green = you received)

## Other Notes
- Auto-renewal removal is intentional — feature has no current operational benefit and creates UI dead-ends for members without a saved payment method
- Year selector for transactions only needs to go back as far as the earliest transaction year in the DB — no need to hardcode a floor year
- Address display in the profile section should be read-only; editing still happens via the existing Edit Profile modal
