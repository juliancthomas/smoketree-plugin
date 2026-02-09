# Legacy Member Migration Guide

This guide explains how to migrate members from the old `wp_smoketree_members` table to the new plugin system.

## Overview

The migration system will:
1. **Create WordPress user accounts** for all old members
2. **Map old membership types** to new membership types (Household, Duo, Single, Civic)
3. **Convert statuses** from old system to new system
4. **Flag users for password reset** on first login
5. **Skip spam/test records** automatically
6. **Handle reactivation** for cancelled members who try to register again

## Pre-Migration Checklist

- [ ] **Backup your database** before running the migration
- [ ] Verify the old table `wp_smoketree_members` exists
- [ ] Verify the new membership types exist (Household, Duo, Single, Civic)
- [ ] Review the migration preview to understand what will be migrated
- [ ] Inform members that they will need to reset their password on first login

## Running the Migration

### Step 1: Access Migration Page

1. Log in to WordPress admin
2. Navigate to **Smoketree Plugin > Migration**
3. Review the migration preview

### Step 2: Review Preview Data

The migration preview shows:
- **Total Records**: Number of records in old table
- **Spam Records**: Records that will be skipped (last 6 entries with random data)
- **Already Migrated**: Members already migrated (will be skipped)
- **Ready to Migrate**: Number of members that will be migrated
- **Breakdown by Status**: Count of active, pending, and cancelled members
- **Breakdown by Type**: Count of each membership type

### Step 3: Run Migration

1. Click **"Run Migration"** button
2. Confirm the action in the popup dialog
3. Wait for migration to complete (may take several minutes for large datasets)
4. Review the results

## Migration Details

### Membership Type Mapping

Old membership IDs are mapped to new types:
- `1146` → **Household**
- `1164` → **Duo**
- `1165` → **Single**
- `1147` → **Civic**

### Status Mapping

Old system used two status fields: `payment_status` and `subscription_status`

New system uses single `status` field:
- `payment_status = "paid"` → **active**
- `isDeleted = 1` → **cancelled**
- All others → **pending**

### Payment Type Normalization

- `card` → `card`
- `us_bank_account` → `bank_account`
- `check` → `check`
- `cash` → `cash`
- `zelle` → `zelle`
- `payment_plan` → `payment_plan`
- All others → `other`

### Skipped Records

The following records will NOT be migrated:
- **Last 6 records** (IDs 139-144) - identified as spam/test data
- **Duplicate emails** - if email already exists in new system
- **Invalid membership types** - if membership type cannot be mapped

## Post-Migration

### Password Reset System

All migrated members have a special flag: `stsrc_legacy_password_needs_reset`

**How it works:**

1. **First Login Attempt**:
   - Member logs in with their OLD password
   - System verifies the old password hash
   - If correct, system updates WordPress password
   - Member is redirected to password reset page

2. **Password Reset Required**:
   - Member must create a new password
   - New password must be at least 8 characters
   - After reset, the legacy flag is removed
   - Member can now log in normally

3. **Member Portal Notice**:
   - Until password is reset, a warning banner shows in the member portal
   - Banner includes "Reset Password Now" button

### Reactivation Flow

If a cancelled member tries to register again:

1. **Registration Attempt**:
   - Member enters their email on registration form
   - System detects email belongs to cancelled account

2. **Reactivation Email Sent**:
   - Email contains reactivation link (24-hour expiration)
   - Link allows member to update their information and reactivate

3. **For Legacy Users**:
   - Email mentions password reset requirement
   - After reactivation, they're redirected to password reset
   - Must set new password before accessing member portal

### What Was NOT Migrated

The following data is NOT automatically migrated and must be migrated separately if needed:

- ❌ **Family Members** (`wp_smoketree_family_members`)
- ❌ **Extra Members** (`wp_smoketree_extra_members`)
- ❌ **Guest Pass Balances** (`wp_smoketree_guest_pass_count`)
- ❌ **Guest Pass Activity** (`wp_smoketree_guest_pass_activity`)
- ❌ **Expiration Dates** (all set to NULL - must be configured manually)

## Troubleshooting

### Migration Errors

If the migration fails or has errors:

1. **Check Error Messages**: Review the error messages displayed after migration
2. **Check Logs**: Look for entries in debug.log with `STSRC Migration:` prefix
3. **Common Issues**:
   - Duplicate email: Email already exists in WordPress users table
   - Invalid membership type: Old membership ID doesn't match expected values
   - Database connection: Check database connection settings

### Re-Running Migration

The migration can be run multiple times safely:
- Already migrated members (detected by email) are skipped
- No duplicate users or members will be created
- Only new/unmigrated members will be processed

### Manual Fixes

If a member was not migrated correctly:

1. **Check Old Data**: Query the old table to verify the member's data
2. **Check New Data**: Verify if WordPress user and member record were created
3. **Manual Creation**: Create user/member manually if migration failed for that member
4. **Set Legacy Flag**: Add user meta `stsrc_legacy_password_needs_reset = true` if needed

## Testing Recommendations

### Before Production Migration

1. **Test on Staging**:
   - Clone production database to staging
   - Run migration on staging first
   - Test login flow with migrated accounts
   - Verify data accuracy

2. **Test Password Reset**:
   - Log in as a migrated user with their old password
   - Verify password reset flow works
   - Confirm member can access portal after reset

3. **Test Reactivation**:
   - Mark a test member as cancelled
   - Try to register with same email
   - Verify reactivation email is sent
   - Complete reactivation flow

### After Production Migration

1. **Verify Member Count**: Compare total members before and after migration
2. **Spot Check**: Manually verify several random members were migrated correctly
3. **Test Login**: Test login with a few migrated accounts
4. **Monitor Logs**: Watch for any migration-related errors in first 24 hours

## Member Communication

### Sample Email to Members

Subject: **Important: Password Reset Required for Your Smoketree Account**

Dear [Member Name],

We've recently upgraded our membership system to provide you with a better experience. As part of this upgrade, we've migrated your account to our new system.

**Action Required: Password Reset**

For security reasons, you'll need to reset your password the first time you log in to the new system.

Here's what to do:
1. Go to [Member Portal URL]
2. Log in with your **current password**
3. You'll be redirected to a password reset page
4. Create a new password (at least 8 characters)
5. You're all set! Access your member portal with your new password

**Need Help?**

If you have any issues logging in or resetting your password, please contact us at [Support Email].

Thank you for your patience during this transition.

Best regards,
Smoketree Swim and Recreation Club

---

## Technical Details

### Database Tables

**Old System:**
- `wp_smoketree_members` - Main member table

**New System:**
- `wp_stsrc_members` - Main member table
- `wp_users` - WordPress user accounts (one per member)

### WordPress User Meta

Migrated users have these meta fields:
- `stsrc_legacy_password_needs_reset` (boolean) - Flag for password reset requirement
- `stsrc_legacy_password_hash` (string) - Stored temporarily for first login verification
- `stsrc_old_member_id` (int) - Reference to old member ID for tracking

### Authentication Hooks

The legacy auth system uses these WordPress hooks:
- `authenticate` (priority 30) - Verifies legacy passwords on login
- `login_redirect` (priority 10) - Redirects to password reset if needed
- `stsrc_member_portal_notices` - Shows password reset warning banner

### AJAX Endpoints

- `stsrc_legacy_password_reset` - Handles password reset form submission

### Security Considerations

- Legacy password hashes are deleted after first successful login
- Password reset links expire after 24 hours
- All password operations use WordPress's built-in security functions
- Nonce verification on all forms

---

## Support

For questions or issues with migration:
1. Check WordPress debug.log for detailed error messages
2. Review migration results for specific error messages
3. Contact plugin developer for assistance

## Version History

- **1.0.0** - Initial migration system implementation
