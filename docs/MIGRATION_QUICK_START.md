# Migration Quick Start Guide

## Prerequisites ✓

- [ ] Old table `wp_smoketree_members` exists in database
- [ ] New membership types exist (Household, Duo, Single, Civic)
- [ ] **Database backup completed**
- [ ] Plugin is activated

## Migration Steps

### 1. Access Migration Page
Navigate to: **WordPress Admin → Smoketree Plugin → Migration**

### 2. Review Preview
Check the migration preview:
- Total records to migrate
- Breakdown by status (active, pending, cancelled)
- Breakdown by membership type

### 3. Run Migration
- Click **"Run Migration"** button
- Confirm the action
- Wait for completion (may take a few minutes)

### 4. Review Results
Check migration results:
- **Successful**: Number of members migrated
- **Skipped**: Already exist (normal)
- **Errors**: Review error messages if any

## Expected Results

For the sample data provided:
- **Total Records**: 144
- **Spam Records (skipped)**: 6 (IDs 139-144)
- **To Migrate**: 138 (minus any already migrated)

### Membership Type Distribution
- Household (1146): ~40%
- Duo (1164): ~35%  
- Single (1165): ~15%
- Civic (1147): ~10%

### Status Distribution
- Active (paid): ~90%
- Pending: ~5%
- Cancelled (isDeleted=1): ~5%

## Post-Migration Testing

### Test 1: Verify Member Count
```sql
-- Check new members table
SELECT COUNT(*) FROM wp_stsrc_members;

-- Should match old table (minus 6 spam records)
SELECT COUNT(*) FROM wp_smoketree_members WHERE id NOT IN (139,140,141,142,143,144);
```

### Test 2: Test Legacy Login
1. Pick a migrated member from old table
2. Note their old password (you won't know it, so use password reset)
3. Actually, better to test with a known test account

### Test 3: Check User Meta
```sql
-- Verify legacy flags were set
SELECT user_id, meta_key, meta_value 
FROM wp_usermeta 
WHERE meta_key = 'stsrc_legacy_password_needs_reset' 
LIMIT 5;
```

### Test 4: Verify Membership Types
```sql
-- Check membership type distribution
SELECT mt.name, COUNT(*) as count
FROM wp_stsrc_members m
JOIN wp_stsrc_membership_types mt ON m.membership_type_id = mt.membership_type_id
GROUP BY mt.name;
```

## Troubleshooting

### Migration Errors

**Error: "Old table wp_smoketree_members does not exist"**
- Solution: Check database name and table prefix

**Error: "Could not map membership type"**
- Solution: Verify membership types exist: Household, Duo, Single, Civic
- Check membership type IDs in `wp_stsrc_membership_types`

**Error: "Failed to create WordPress user"**
- Solution: Check if email already exists in `wp_users` table
- May indicate member was already migrated

### Re-Running Migration

Safe to run multiple times:
- Already migrated members (by email) are automatically skipped
- No duplicates will be created

## Member Communication

### Template Email

**Subject**: Password Reset Required After System Upgrade

**Body**:
```
Dear [Member Name],

We've upgraded our membership system. On your next login, you'll need to reset your password for security.

Steps:
1. Visit [Member Portal URL]
2. Log in with your current password
3. You'll be redirected to create a new password
4. That's it! Use your new password going forward

Questions? Contact us at [Support Email]

Best regards,
Smoketree Team
```

Send this email AFTER migration is complete and tested.

## Monitoring

### What to Monitor (First 24 Hours)

1. **Login Attempts**: Watch for login failures
2. **Password Resets**: Track password reset completions  
3. **Error Logs**: Check debug.log for migration errors
4. **Support Requests**: Note common questions from members

### Logs to Check

**WordPress Debug Log**:
```
wp-content/debug.log
```

Look for entries with:
- `STSRC Migration:`
- `STSRC: Failed to generate password reset key`

## Success Criteria ✓

- [ ] Migration completed without errors
- [ ] Member count matches expected
- [ ] Test login works with old password → password reset flow
- [ ] Warning banner shows in member portal for legacy users
- [ ] Password reset completes successfully
- [ ] After reset, login works with new password
- [ ] No errors in debug.log

## Rollback Plan (If Needed)

If migration fails badly:

1. **Restore Database Backup**
   - Stop any ongoing processes
   - Restore from backup created in Prerequisites

2. **Clean Up Partial Migration**
   ```sql
   -- Remove migrated WordPress users (if needed)
   DELETE FROM wp_users WHERE user_login IN (
     SELECT email FROM wp_smoketree_members 
     WHERE id NOT IN (139,140,141,142,143,144)
   );
   
   -- Remove member records
   DELETE FROM wp_stsrc_members WHERE member_id > [ID_before_migration];
   ```

3. **Review Errors**
   - Check migration error messages
   - Fix issues
   - Try again

## Timeline

**Recommended Schedule**:
- **Day 1**: Backup, test on staging
- **Day 2**: Run production migration during low-traffic period
- **Day 3**: Monitor logs, test member logins
- **Day 4-7**: Send member communication, handle support requests

## Need Help?

1. Check `MIGRATION_GUIDE.md` for detailed documentation
2. Check `MIGRATION_IMPLEMENTATION_SUMMARY.md` for technical details
3. Review WordPress debug.log for error messages
4. Check migration results page for specific errors

---

## Quick Reference

### Admin URLs
- Migration Page: `/wp-admin/admin.php?page=smoketree-migration`
- Members Page: `/wp-admin/admin.php?page=smoketree-members`

### Database Tables
- Old: `wp_smoketree_members`
- New: `wp_stsrc_members`, `wp_users`

### Membership Type IDs
- 1146 → Household
- 1164 → Duo
- 1165 → Single
- 1147 → Civic

### User Meta Keys
- `stsrc_legacy_password_needs_reset` - Password reset flag
- `stsrc_legacy_password_hash` - Temporary old password hash
- `stsrc_old_member_id` - Reference to old ID

---

**Last Updated**: [Current Date]
**Plugin Version**: 1.0.0
