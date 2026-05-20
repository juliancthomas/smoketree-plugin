import { test, expect } from '@playwright/test';
import {
  fillRegistrationForm,
  submitRegistrationForm,
  uniqueEmail,
  clickAndWaitForAjax,
} from '../utils/helpers';
import { dbQuery } from '../utils/db';
import { TEST_MEMBERS } from '../fixtures/test-members';

const REFERRER_CODE = TEST_MEMBERS.referrer.affiliateCode;

function now() {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}
function nextYear() {
  return `${new Date().getFullYear() + 1}-12-31`;
}
function singleTypeId() {
  return dbQuery(`SELECT membership_type_id FROM wp_stsrc_membership_types WHERE name='Single' LIMIT 1`);
}

function seedMember(email: string, status: 'pending' | 'deleted') {
  const n = now();
  const typeId = singleTypeId();
  dbQuery(
    `INSERT IGNORE INTO wp_users (user_login, user_email, user_pass, user_nicename, display_name, user_registered)
     VALUES ('${email}', '${email}', '', '${email}', 'Test ${status}', '${n}')`
  );
  const userId = dbQuery(
    `SELECT ID FROM wp_users WHERE user_email='${email}' ORDER BY ID ASC LIMIT 1`
  );
  dbQuery(
    `INSERT INTO wp_stsrc_members
       (user_id, membership_type_id, status, payment_type, first_name, last_name, email, phone,
        street_1, city, state, zip, country, referral_source, waiver_full_name, waiver_signed_date,
        created_at, updated_at, expiration_date)
     VALUES
       (${userId}, ${typeId}, '${status}', 'check', 'Test', '${status}', '${email}', '5555550100',
        '123 Test St', 'Tucker', 'GA', '30084', 'US', 'other', 'Test ${status}',
        '${n.slice(0, 10)}', '${n}', '${n}', '${nextYear()}')`
  );
}

function cleanupMember(email: string) {
  dbQuery(`DELETE FROM wp_stsrc_members WHERE email='${email}'`);
  dbQuery(`DELETE FROM wp_users WHERE user_email='${email}'`);
}

// ---------------------------------------------------------------------------
// Stripe Abandonment
// ---------------------------------------------------------------------------

test.describe('Stripe Abandonment', () => {

  test('form submit with card creates a pending member before Stripe redirect', async ({ page }) => {
    const email = uniqueEmail('stripe-abandon');
    await page.goto('/register');
    await fillRegistrationForm(page, { email, paymentType: 'card' });
    await page.locator('#auto_renewal_acknowledged').check();
    await submitRegistrationForm(page);

    await page.waitForURL(/checkout\.stripe\.com/, { timeout: 30_000 });

    // Navigate away without completing payment
    await page.goto('/register');

    const status = dbQuery(
      `SELECT status FROM wp_stsrc_members WHERE email='${email}' LIMIT 1`
    );
    expect(status).toBe('pending');
  });

  test('user is auto-logged in before being sent to Stripe checkout', async ({ page }) => {
    const email = uniqueEmail('stripe-autologin');
    await page.goto('/register');
    await fillRegistrationForm(page, { email, paymentType: 'card' });
    await page.locator('#auto_renewal_acknowledged').check();
    await submitRegistrationForm(page);

    await page.waitForURL(/checkout\.stripe\.com/, { timeout: 30_000 });

    // Navigate back to the WordPress site — if auto-login fired, the portal loads directly.
    await page.goto('/member-portal');
    await expect(page).not.toHaveURL(/login/, { timeout: 10_000 });
    await expect(page.locator('.stsrc-member-portal')).toBeVisible({ timeout: 10_000 });
  });

  test('account-created email is sent when Stripe registration creates an account', async ({ page }) => {
    const email = uniqueEmail('stripe-email');
    await page.goto('/register');
    await fillRegistrationForm(page, { email, paymentType: 'card' });
    await page.locator('#auto_renewal_acknowledged').check();
    await submitRegistrationForm(page);

    await page.waitForURL(/checkout\.stripe\.com/, { timeout: 30_000 });

    const emailCount = dbQuery(
      `SELECT COUNT(*) FROM wp_stsrc_email_logs WHERE recipient_email='${email}' LIMIT 1`
    );
    expect(Number(emailCount)).toBeGreaterThanOrEqual(1);
  });

  test('stripe_abandoned notice shows on member portal after backing out of Stripe', async ({ page }) => {
    // Simulate landing on the cancel_url as a logged-in member with an outstanding balance.
    await page.goto(`/member-portal?registration=stripe_abandoned`, { waitUntil: 'domcontentloaded' });

    // If not logged in, log in as the balance test member first.
    if (page.url().includes('/login')) {
      await page.fill('#user_login', TEST_MEMBERS.withBalance.email);
      await page.fill('#user_pass', TEST_MEMBERS.withBalance.password);
      await page.locator('#wp-submit').click();
      await page.waitForURL(/member-portal/, { timeout: 15_000 });
      await page.goto(`/member-portal?registration=stripe_abandoned`);
    }

    const notice = page.locator('.stsrc-notice.warning').filter({ hasText: /account was created|didn.*t receive payment/i });
    await expect(notice).toBeVisible({ timeout: 10_000 });
  });

  test('re-registering with a pending email is blocked with a clear message', async ({ page }) => {
    const email = uniqueEmail('stripe-reregister');
    seedMember(email, 'pending');

    await page.goto('/register');
    await fillRegistrationForm(page, { email, paymentType: 'check' });
    await submitRegistrationForm(page);

    const error = page.locator('#stsrc-form-messages .stsrc-notice.error');
    await expect(error).toBeVisible({ timeout: 15_000 });
    await expect(error).toContainText(/already|exists/i);

    cleanupMember(email);
  });
});

// ---------------------------------------------------------------------------
// Form Sanitization & Bad Input
// ---------------------------------------------------------------------------

test.describe('Form Sanitization & Bad Input', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/register');
    await expect(page.locator('#stsrc-registration-form')).toBeVisible();
  });

  test('XSS payload in first name does not execute on member portal', async ({ page }) => {
    let alertFired = false;
    page.on('dialog', async (dialog) => {
      alertFired = true;
      await dialog.dismiss();
    });

    await fillRegistrationForm(page, {
      firstName: '<script>alert(1)</script>',
      paymentType: 'zelle',
    });
    await submitRegistrationForm(page);
    await page.waitForURL(/member-portal/, { timeout: 30_000 });

    expect(alertFired).toBe(false);
    const html = await page.locator('body').innerHTML();
    expect(html).not.toContain('<script>alert(1)</script>');
  });

  test('HTML tags in waiver name are not rendered on member portal', async ({ page }) => {
    await fillRegistrationForm(page, {
      waiverName: '<b>Bold Name</b>',
      paymentType: 'zelle',
    });
    await submitRegistrationForm(page);
    await page.waitForURL(/member-portal/, { timeout: 30_000 });

    // <b> with "Bold Name" text should not exist as an actual element
    const boldCount = await page.locator('b:has-text("Bold Name")').count();
    expect(boldCount).toBe(0);
  });

  test('duplicate email check is case-insensitive', async ({ page }) => {
    const [user, domain] = TEST_MEMBERS.individual.email.split('@');
    const upperEmail = `${user.toUpperCase()}@${domain.toUpperCase()}`;

    await fillRegistrationForm(page, { email: upperEmail, paymentType: 'check' });
    await submitRegistrationForm(page);

    const error = page.locator('#stsrc-form-messages .stsrc-notice.error');
    await expect(error).toBeVisible({ timeout: 15_000 });
    await expect(error).toContainText(/already|exists|registered/i);
  });

  test('phone with parentheses-space format is accepted', async ({ page }) => {
    const email = uniqueEmail('phone-paren');
    await fillRegistrationForm(page, { email, phone: '(555) 123-4567', paymentType: 'zelle' });
    await submitRegistrationForm(page);
    await page.waitForURL(/member-portal/, { timeout: 30_000 });
    await expect(page.locator('h1')).toContainText('Member Portal');
  });

  test('phone with dashes format is accepted', async ({ page }) => {
    const email = uniqueEmail('phone-dash');
    await fillRegistrationForm(page, { email, phone: '555-123-4567', paymentType: 'zelle' });
    await submitRegistrationForm(page);
    await page.waitForURL(/member-portal/, { timeout: 30_000 });
    await expect(page.locator('h1')).toContainText('Member Portal');
  });

  test('zip code field blocks non-numeric input via native validation', async ({ page }) => {
    await fillRegistrationForm(page, { paymentType: 'zelle' });

    // Override zip with letters
    await page.locator('#zip').fill('ABCDE');

    // Click submit directly so browser native validation fires
    await page.locator('#stsrc-submit-registration').click();

    // Page should stay on /register (not navigate to member-portal)
    await page.waitForTimeout(2_000);
    expect(page.url()).not.toMatch(/member-portal/);
  });
});

// ---------------------------------------------------------------------------
// Extra Member Validation
// ---------------------------------------------------------------------------

test.describe('Extra Member Validation', () => {

  test('empty extra member slot blocks form submission', async ({ page }) => {
    await page.goto('/register');
    // Household is index 0
    await fillRegistrationForm(page, { membershipTypeIndex: 0, paymentType: 'zelle' });

    // Fill required family member slots so validateFamilyMinimums() passes
    const locked = page.locator('.stsrc-family-member-item--locked');
    for (let i = 0; i < await locked.count(); i++) {
      await locked.nth(i).locator('input[name*="first_name"]').fill('FamFirst');
      await locked.nth(i).locator('input[name*="last_name"]').fill('FamLast');
    }

    // Add one extra member slot — leave names blank
    await page.locator('#stsrc-add-extra-member').click();
    await expect(page.locator('.stsrc-extra-member-item')).toHaveCount(1);

    // Click submit button directly so browser native validation fires on the required field
    await page.locator('#stsrc-submit-registration').click();

    // Should not have navigated away
    await page.waitForTimeout(2_000);
    expect(page.url()).not.toMatch(/member-portal/);
  });

  test('removed optional family member slot does not block submission', async ({ page }) => {
    await page.goto('/register');
    const email = uniqueEmail('fam-remove');
    await fillRegistrationForm(page, { email, membershipTypeIndex: 0, paymentType: 'zelle' });

    // Fill both required (locked) family member slots
    const locked = page.locator('.stsrc-family-member-item--locked');
    for (let i = 0; i < await locked.count(); i++) {
      await locked.nth(i).locator('input[name*="first_name"]').fill(`Fam${i + 1}`);
      await locked.nth(i).locator('input[name*="last_name"]').fill('Member');
    }

    // Remove the first optional slot (slot 3)
    const optionalSlot = page.locator('.stsrc-family-member-item--optional').first();
    await optionalSlot.locator('.stsrc-toggle-family-slot').click();
    await expect(optionalSlot).toHaveClass(/stsrc-family-member-item--removed/);

    await submitRegistrationForm(page);
    await page.waitForURL(/member-portal/, { timeout: 30_000 });
    await expect(page.locator('h1')).toContainText('Member Portal');
  });
});

// ---------------------------------------------------------------------------
// pay_later Registration
// ---------------------------------------------------------------------------

test.describe('pay_later Registration', () => {

  test('pay_later registration completes and reaches member portal', async ({ page }) => {
    await page.goto('/register');
    const email = uniqueEmail('paylater');
    await fillRegistrationForm(page, { email, paymentType: 'pay_later' });
    await submitRegistrationForm(page);
    await page.waitForURL(/member-portal/, { timeout: 30_000 });
    await expect(page.locator('h1')).toContainText('Member Portal');
  });

  test('pay_later member record has pending status in DB', async ({ page }) => {
    await page.goto('/register');
    const email = uniqueEmail('paylater-db');
    await fillRegistrationForm(page, { email, paymentType: 'pay_later' });
    await submitRegistrationForm(page);
    await page.waitForURL(/member-portal/, { timeout: 30_000 });

    const status = dbQuery(
      `SELECT status FROM wp_stsrc_members WHERE email='${email}' LIMIT 1`
    );
    expect(status).toBe('pending');
  });
});

// ---------------------------------------------------------------------------
// Discount Edge Cases — Referral
// ---------------------------------------------------------------------------

test.describe('Discount Edge Cases — Referral', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/register');
    await expect(page.locator('#stsrc-registration-form')).toBeVisible();
    // Start with Single selected (index 2, $295)
    await page.locator('.stsrc-membership-card').nth(2).click();
  });

  test('referral discount recalculates when membership type changes', async ({ page }) => {
    await page.locator('#stsrc_affiliate_code').fill(REFERRER_CODE);
    await clickAndWaitForAjax(page.locator('#apply-affiliate-btn'));
    await expect(page.locator('#affiliate-feedback')).toContainText('✓');

    await page.locator('input[name="payment_type"][value="check"]').check();
    // Single $295 - $25 = $270
    await expect(page.locator('#stsrc-total')).toContainText('$270.00');

    // Switch to Household ($575)
    await page.locator('.stsrc-membership-card:has(.stsrc-membership-card__name:has-text("Household"))').first().click();

    // Discount row stays visible
    await expect(page.locator('#stsrc-discount-row')).toBeVisible();
    // Household $575 - $25 = $550
    await expect(page.locator('#stsrc-total')).toContainText('$550.00');
  });

  test('referral discount persists after switching from card to check', async ({ page }) => {
    await page.locator('#stsrc_affiliate_code').fill(REFERRER_CODE);
    await clickAndWaitForAjax(page.locator('#apply-affiliate-btn'));
    await expect(page.locator('#affiliate-feedback')).toContainText('✓');

    // Select card first (adds processing fee)
    await page.locator('input[name="payment_type"][value="card"]').check();

    // Switch to check — fee should disappear, discount stays
    await page.locator('input[name="payment_type"][value="check"]').check();

    await expect(page.locator('#stsrc-discount-row')).toBeVisible();
    // Single $295 - $25 = $270, no processing fee for check
    await expect(page.locator('#stsrc-total')).toContainText('$270.00');
    await expect(page.locator('#stsrc-transaction-fee-row')).toBeHidden();
  });
});

// ---------------------------------------------------------------------------
// Deleted / Trashed Member Email
// ---------------------------------------------------------------------------

test.describe('Deleted Member Email', () => {

  test('registration with a deleted-member email triggers restore flow, not a crash', async ({ page }) => {
    const email = uniqueEmail('deleted-member');
    seedMember(email, 'deleted');

    await page.goto('/register');
    await fillRegistrationForm(page, { email, paymentType: 'check' });
    await submitRegistrationForm(page);

    // Should not crash (no PHP fatal)
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).not.toMatch(/Fatal error|Call to undefined|Parse error/i);

    // Form messages area should appear with a restore/reactivation notice
    const messages = page.locator('#stsrc-form-messages');
    await expect(messages).toBeVisible({ timeout: 15_000 });
    const msgText = await messages.innerText();
    expect(msgText).toMatch(/restore|reactivat|previous account|found/i);

    cleanupMember(email);
  });
});
