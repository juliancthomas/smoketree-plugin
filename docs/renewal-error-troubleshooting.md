# Renewal Error Troubleshooting Guide

Quick reference for admin when a member reports an error during renewal.

---

## Rejection Errors (409)

These block the renewal from being submitted.

### `renewal_disabled`

**User sees:** "The renewal window is currently closed."

**Cause:** The "Member Renewal Enabled" checkbox is unchecked in plugin settings.

**Fix:**
1. Go to **Smoketree → Settings → General**
2. Check **Member Renewal Enabled**
3. Save

**Reply to user:** "Renewals are now open — please try again."

---

### `member_not_found`

**User sees:** "We could not locate your member record."

**Cause:** The logged-in WordPress user has no matching row in `wp_stsrc_members` (email mismatch or deleted record).

**Fix:**
1. Go to **Smoketree → Members** and search by the user's email
2. If missing, re-create the member record
3. If the email differs from their WP account, update one to match

**Reply to user:** "We've linked your account — please log out, log back in, and try again."

---

### `member_not_eligible`

**User sees:** "Your account is not currently eligible for renewal. This may be due to a cancelled membership."

**Cause:** Member status is `cancelled`. The eligibility check only blocks cancelled members.

**Fix:**
1. Go to **Smoketree → Members → [Member]**
2. Check the **Status** field — if `cancelled`, change it to `active` or `expired` as appropriate
3. Save

**Reply to user:** "Your account has been updated — you should be able to renew now."

---

### `already_completed`

**User sees:** "Your renewal for this season has already been completed."

**Cause:** A completed renewal record already exists for this member + season. No action needed.

**Fix:** None required — this is working as intended.

**Reply to user:** "Your renewal is already on file! No further action needed. Check your email for the confirmation we sent."

---

### `already_in_progress`

**User sees:** "A renewal for this season is already in progress."

**Cause:** A renewal intent exists with status `initiated` or `pending_payment` — the member started but didn't finish a previous attempt.

**Fix:**
1. Go to **Smoketree Club → Members → [Member]**
2. Look for the **Renewal Status** section — it will show the stuck renewal with its status, method, and amount
3. Click **Cancel This Renewal**
4. The member can then retry

**Reply to user:** "We've cleared the previous attempt — please try your renewal again."

---

### `duplicate_submission`

**User sees:** "A renewal submission for this season already exists."

**Cause:** Same as `already_in_progress` — an existing renewal record is blocking a new one.

**Fix:** Same steps as `already_in_progress` above (cancel from the member edit page).

---

### `invalid_quote`

**User sees:** "We were unable to calculate pricing for your selected plan."

**Cause:** The membership type transition failed validation (e.g., missing family members for a household plan, or the selected membership type was deleted/disabled).

**Fix:**
1. Go to **Smoketree → Membership Types** and verify the target type exists and has a valid price
2. If the member is switching to household/duo, ensure they have the required family members on file

**Reply to user:** "There was a configuration issue with that plan — please try again or choose a different option."

---

### `not_eligible`

**User sees:** "Your account is not eligible for renewal at this time."

**Cause:** Generic eligibility failure from the renewal database check (e.g., season key mismatch or missing eligibility record).

**Fix:**
1. Verify the **Season Renewal Date** is set correctly in **Smoketree → Settings**
2. Check that the member's record isn't corrupted (valid `member_id`, `membership_type_id`)

**Reply to user:** "We've looked into it and updated your account — please try again."

---

## Server Errors (500)

These indicate something failed during processing.

### `member_not_found` (error variant)

**User sees:** "We could not locate your member record. Please refresh the page and try again."

**Cause:** Member existed during auth check but disappeared before intent creation (rare race condition or DB issue).

**Fix:** Verify the member record exists in the database. If the DB had a hiccup, the member can simply retry.

---

### `intent_create_failed`

**User sees:** "We encountered a problem saving your renewal request."

**Cause:** The `wp_stsrc_renewals` table insert failed — usually a database error.

**Fix:**
1. Check the PHP error log for DB errors
2. Verify the `wp_stsrc_renewals` table exists and has the expected schema
3. If the table is missing, deactivate and reactivate the plugin to trigger table creation

**Reply to user:** "We've resolved the issue on our end — please try again."

---

### `stripe_checkout_create_failed`

**User sees:** "We were unable to set up the payment checkout."

**Cause:** Stripe API rejected the checkout session request.

**Fix:**
1. Check the PHP error log — the Stripe exception details are logged
2. Common causes:
   - **Invalid/expired API keys** → Update in **Smoketree → Settings → Stripe**
   - **Demo account without test keys** → Add test keys in settings
   - **Zero-amount checkout** → Verify the membership type has a price > $0
   - **Stripe account issue** → Check the Stripe Dashboard for alerts

**Reply to user:** "We've fixed the payment setup issue — please try again. If you'd prefer, you can also select Zelle or Check as a payment method."

---

## Unknown Reason Code

**User sees:** "Renewal could not be processed (reason: `<code>`). Please contact the club."

**Fix:** Search the codebase for the reason code to trace its origin, or check the PHP error log for context. This indicates a new failure path was added without a corresponding user message.
