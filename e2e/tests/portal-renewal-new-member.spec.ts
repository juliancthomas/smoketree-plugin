/**
 * Regression test: renewal section must not appear for members who joined
 * mid-season (expiration_date > season_renewal_date).
 *
 * Bug: get_eligibility() only checked the renewals table for a blocking
 * record. New members have no renewal record, so they were incorrectly
 * flagged as eligible and shown the renewal section.
 *
 * Fix: is_member_eligible_for_current_season() now short-circuits when
 * the member's expiration_date already extends past the season renewal date.
 */
import { test, expect } from '@playwright/test';
import { execFileSync } from 'child_process';
import * as dotenv from 'dotenv';
import * as path from 'path';
import { loginAsMember } from '../utils/login';

dotenv.config({ path: path.resolve(__dirname, '../.env.test') });

const DB_HOST   = process.env.DB_HOST   || 'localhost';
const DB_PORT   = process.env.DB_PORT   || '10010';
const DB_NAME   = process.env.DB_NAME   || 'local';
const DB_USER   = process.env.DB_USER   || 'root';
const DB_PASS   = process.env.DB_PASS   || 'root';
const MYSQL_BIN = process.env.MYSQL_BIN || 'mysql';

const NEW_MEMBER_EMAIL = 'testnewmember@example.com';
const NEW_MEMBER_PASS  = 'TestPass123!';

// Season renewal date set in the past so renewal is "open", but the new
// member's expiration_date is well past it — they joined mid-season.
const SEASON_RENEWAL_DATE = '2026-03-01';
// Expiry is a full year after joining mid-season, clearly past the renewal date.
const MEMBER_EXPIRY_DATE  = '2027-05-01';

function mysql(sql: string): string {
  try {
    return execFileSync(
      MYSQL_BIN,
      ['-h', DB_HOST, '-P', DB_PORT, '-u', DB_USER, `-p${DB_PASS}`, DB_NAME,
       '-e', sql, '--skip-column-names', '-N'],
      { encoding: 'utf-8', timeout: 10_000 }
    ).trim();
  } catch {
    return '';
  }
}

function enableRenewalWithDate(date: string): void {
  mysql(`INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('stsrc_renewal_enabled', '1', 'yes') ON DUPLICATE KEY UPDATE option_value = '1'`);
  mysql(`INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('options_stsrc_renewal_enabled', '1', 'yes') ON DUPLICATE KEY UPDATE option_value = '1'`);
  // ACF stores dates without dashes (YYYYMMDD).
  const acfDate = date.replace(/-/g, '');
  mysql(`INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('stsrc_season_renewal_date', '${acfDate}', 'yes') ON DUPLICATE KEY UPDATE option_value = '${acfDate}'`);
  mysql(`INSERT INTO wp_options (option_name, option_value, autoload) VALUES ('options_stsrc_season_renewal_date', '${acfDate}', 'yes') ON DUPLICATE KEY UPDATE option_value = '${acfDate}'`);
}

function createNewMember(): void {
  const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

  // Ensure a WP user exists.
  mysql(
    `INSERT IGNORE INTO wp_users (user_login, user_email, user_pass, user_nicename, display_name, user_registered)
     VALUES ('testnewmember', '${NEW_MEMBER_EMAIL}', MD5('${NEW_MEMBER_PASS}'), 'testnewmember', 'New Member', '${now}')`
  );
  const userId = mysql(`SELECT ID FROM wp_users WHERE user_email='${NEW_MEMBER_EMAIL}' LIMIT 1`);
  if (!userId) return;

  // Grab any individual-type membership type ID.
  const typeId = mysql(`SELECT membership_type_id FROM wp_stsrc_membership_types WHERE LOWER(name) NOT IN ('household','duo') LIMIT 1`);
  if (!typeId) return;

  mysql(
    `INSERT INTO wp_stsrc_members
       (user_id, membership_type_id, status, payment_type, first_name, last_name, email, phone,
        street_1, city, state, zip, country, referral_source, waiver_full_name, waiver_signed_date,
        balance_owed, season_membership_price, created_at, updated_at, expiration_date)
     VALUES
       (${userId}, ${typeId}, 'active', 'card', 'New', 'Member', '${NEW_MEMBER_EMAIL}', '(555) 555-0199',
        '1 Test Ln', 'Tucker', 'GA', '30084', 'US', 'other', 'New Member', '${now.slice(0, 10)}',
        0, 0, '${now}', '${now}', '${MEMBER_EXPIRY_DATE}')
     ON DUPLICATE KEY UPDATE
       expiration_date = VALUES(expiration_date),
       status          = VALUES(status),
       balance_owed    = VALUES(balance_owed),
       updated_at      = VALUES(updated_at)`
  );

  // Ensure usermeta allows login.
  mysql(`INSERT IGNORE INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (${userId}, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}')`);
}

function deleteNewMember(): void {
  const userId = mysql(`SELECT ID FROM wp_users WHERE user_email='${NEW_MEMBER_EMAIL}' LIMIT 1`);
  if (userId) {
    mysql(`DELETE FROM wp_stsrc_member_renewals WHERE member_id IN (SELECT member_id FROM wp_stsrc_members WHERE email='${NEW_MEMBER_EMAIL}')`);
    mysql(`DELETE FROM wp_stsrc_members WHERE email='${NEW_MEMBER_EMAIL}'`);
    mysql(`DELETE FROM wp_usermeta WHERE user_id=${userId}`);
    mysql(`DELETE FROM wp_users WHERE ID=${userId}`);
  }
}

test.describe('Renewal section – new mid-season member', () => {

  test.beforeAll(() => {
    enableRenewalWithDate(SEASON_RENEWAL_DATE);
    createNewMember();
  });

  test.afterAll(() => {
    deleteNewMember();
  });

  test.beforeEach(async ({ page }) => {
    await loginAsMember(page, NEW_MEMBER_EMAIL, NEW_MEMBER_PASS);
  });

  test('renewal section is hidden for a member who joined after the season renewal date', async ({ page }) => {
    await page.goto('/member-portal');
    await expect(page.locator('.stsrc-member-portal')).toBeVisible();

    // The renewal section must not appear — this member's expiration_date
    // (MEMBER_EXPIRY_DATE) is past the configured season renewal date, meaning
    // they already paid for this season and are not due to renew.
    const renewalSection = page.locator('#stsrc-renewal-section');
    const count = await renewalSection.count();
    if (count > 0) {
      await expect(renewalSection.first()).toBeHidden();
    }
    // If count is 0, the section was never rendered — also correct.
  });

  test('portal renders member profile correctly for a new mid-season member', async ({ page }) => {
    await page.goto('/member-portal');
    await expect(page.locator('.stsrc-member-portal')).toBeVisible();

    // Sections that should always be present for an active member.
    await expect(page.locator('.stsrc-portal-header')).toBeVisible();
    await expect(page.locator('.stsrc-portal-greeting h1')).toContainText('New');
  });

  test('guest pass section is visible (not suppressed by renewal)', async ({ page }) => {
    await page.goto('/member-portal');
    await expect(page.locator('.stsrc-member-portal')).toBeVisible();

    // This section is hidden when $is_renewal_active is true.
    // A new mid-season member should see it.
    const guestPassSection = page.locator('.stsrc-portal-section').filter({ hasText: 'Guest Passes' });
    await expect(guestPassSection).toBeVisible();
  });
});
