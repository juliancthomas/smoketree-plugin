import { test, expect } from '@playwright/test';
import { loginAsMember } from '../utils/login';
import { waitForAjax } from '../utils/helpers';
import { TEST_MEMBERS } from '../fixtures/test-members';

test.describe('Member Portal', () => {

  test.describe('Profile Display', () => {

    test('shows membership information for Individual member', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);

      await expect(page.locator('h2:has-text("Membership Information")')).toBeVisible();
      await expect(page.locator('.stsrc-member-info')).toContainText(TEST_MEMBERS.individual.first);
      await expect(page.locator('.stsrc-member-info')).toContainText(TEST_MEMBERS.individual.last);
      await expect(page.locator('.stsrc-member-info')).toContainText(TEST_MEMBERS.individual.email);
      await expect(page.locator('.stsrc-member-info')).toContainText('Individual');
    });

    test('shows status badge', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      const badge = page.locator('.stsrc-status-badge').first();
      await expect(badge).toBeVisible();
      await expect(badge).toContainText(/Active|Pending/i);
    });

    test('shows Edit Profile and Change Password buttons', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await expect(page.locator('#stsrc-edit-profile-btn')).toBeVisible();
      await expect(page.locator('#stsrc-change-password-btn')).toBeVisible();
    });

    test('shows auto-renewal toggle', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await expect(page.locator('.stsrc-auto-renewal-row')).toBeVisible();
    });
  });

  test.describe('Edit Profile', () => {

    test('opens edit profile modal', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await page.locator('#stsrc-edit-profile-btn').click();
      await expect(page.locator('#stsrc-edit-profile-modal')).toBeVisible();
    });

    test('modal has pre-filled member data', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await page.locator('#stsrc-edit-profile-btn').click();

      await expect(page.locator('#edit_first_name')).toHaveValue(TEST_MEMBERS.individual.first);
      await expect(page.locator('#edit_last_name')).toHaveValue(TEST_MEMBERS.individual.last);
      await expect(page.locator('#edit_email')).toHaveValue(TEST_MEMBERS.individual.email);
    });

    test('can update profile successfully', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await page.locator('#stsrc-edit-profile-btn').click();

      await page.locator('#edit_phone').fill('(555) 999-8888');
      await page.locator('#stsrc-edit-profile-form button[type="submit"]').click();
      await waitForAjax(page);

      // Should show success message or close modal
      const successMsg = page.locator('#stsrc-portal-messages .stsrc-notice.success, .stsrc-notice.success').first();
      await expect(successMsg).toBeVisible({ timeout: 10_000 });

      // Verify updated phone on page
      await page.reload();
      await expect(page.locator('.stsrc-member-info')).toContainText('(555) 999-8888');
    });

    test('close button closes modal', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await page.locator('#stsrc-edit-profile-btn').click();
      await expect(page.locator('#stsrc-edit-profile-modal')).toBeVisible();

      await page.locator('#stsrc-edit-profile-modal .stsrc-modal-close').first().click();
      await expect(page.locator('#stsrc-edit-profile-modal')).toBeHidden();
    });
  });

  test.describe('Change Password', () => {

    test('opens change password modal', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await page.locator('#stsrc-change-password-btn').click();
      await expect(page.locator('#stsrc-change-password-modal')).toBeVisible();
    });

    test('modal has required password fields', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await page.locator('#stsrc-change-password-btn').click();

      await expect(page.locator('#current_password')).toBeVisible();
      await expect(page.locator('#new_password')).toBeVisible();
      await expect(page.locator('#confirm_password')).toBeVisible();
    });

    test('rejects wrong current password', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await page.locator('#stsrc-change-password-btn').click();

      await page.locator('#current_password').fill('WrongPassword999!');
      await page.locator('#new_password').fill('NewTestPass456!');
      await page.locator('#confirm_password').fill('NewTestPass456!');
      await page.locator('#stsrc-change-password-form button[type="submit"]').click();
      await waitForAjax(page);

      const errorMsg = page.locator('.stsrc-notice.error').first();
      await expect(errorMsg).toBeVisible({ timeout: 10_000 });
      await expect(errorMsg).toContainText(/incorrect|wrong|invalid|current password/i);
    });

    test('new password minimum length is enforced', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await page.locator('#stsrc-change-password-btn').click();

      const newPassField = page.locator('#new_password');
      await expect(newPassField).toHaveAttribute('minlength', '8');
    });
  });

  test.describe('Access Codes', () => {

    test('Individual member (with pool access) sees all access codes', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);

      const codesSection = page.locator('.stsrc-portal-section:has(h2:has-text("Access Codes"))');
      await expect(codesSection).toBeVisible();

      // Should see both general and premium codes
      await expect(codesSection).toContainText('TESTCODE2026');
      await expect(codesSection).toContainText('POOLCODE2026');
    });

    test('Civic member (no pool access) only sees non-premium codes', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.civic.email, TEST_MEMBERS.civic.password);

      // Should see general code
      const pageContent = page.locator('.stsrc-member-portal');
      await expect(pageContent).toContainText('TESTCODE2026');

      // Should NOT see premium pool code
      await expect(pageContent).not.toContainText('POOLCODE2026');
    });

    test('Active member with outstanding balance does not see access codes', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.withBalance.email, TEST_MEMBERS.withBalance.password);

      const pageContent = page.locator('.stsrc-member-portal');
      await expect(pageContent).not.toContainText('TESTCODE2026');
      await expect(pageContent).not.toContainText('POOLCODE2026');
    });

    test('Pending member does not see access codes', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.inactiveReferrer.email, TEST_MEMBERS.inactiveReferrer.password);

      const pageContent = page.locator('.stsrc-member-portal');
      await expect(pageContent).not.toContainText('TESTCODE2026');
      await expect(pageContent).not.toContainText('POOLCODE2026');
    });
  });

  test.describe('Balance Display', () => {

    test('member with balance sees balance card', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.withBalance.email, TEST_MEMBERS.withBalance.password);

      const balanceCard = page.locator('.stsrc-balance-card, .stsrc-outstanding-balance, [class*="balance-card"]').first();
      await expect(balanceCard).toBeVisible();
      await expect(balanceCard).toContainText('$75.00');
    });

    test('member with balance sees Pay Balance button', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.withBalance.email, TEST_MEMBERS.withBalance.password);
      await expect(page.locator('.stsrc-pay-balance-btn, button:has-text("Pay Balance")').first()).toBeVisible();
    });

    test('member without balance does not see balance card', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);

      const balanceCard = page.locator('.stsrc-balance-card, .stsrc-outstanding-balance');
      // Should be hidden or not present
      const count = await balanceCard.count();
      if (count > 0) {
        await expect(balanceCard.first()).toBeHidden();
      }
    });
  });

  test.describe('Guest Pass Section', () => {

    test('shows guest passes section', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await expect(page.locator('h2:has-text("Guest Passes")')).toBeVisible();
      await expect(page.locator('.stsrc-balance-amount')).toBeVisible();
    });

    test('shows Purchase Guest Passes button', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await expect(page.locator('#stsrc-purchase-guest-passes-btn')).toBeVisible();
    });

    test('shows View Guest Pass Portal link', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await expect(page.locator('a[href*="/guest-pass-portal"]')).toBeVisible();
    });
  });

  test.describe('Transaction History', () => {

    test('shows transaction history section', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      const section = page.locator('.stsrc-portal-section:has(h2:has-text("Transaction History"))');
      await expect(section).toBeVisible();
    });
  });

  test.describe('Membership-Specific Sections', () => {

    test('Household member sees family members section', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.household.email, TEST_MEMBERS.household.password);
      await expect(page.locator('h2:has-text("Family Members")')).toBeVisible();
    });

    test('Household member sees extra members section', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.household.email, TEST_MEMBERS.household.password);
      await expect(page.locator('h2:has-text("Extra Members")')).toBeVisible();
    });

    test('Duo member sees family members section', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.duo.email, TEST_MEMBERS.duo.password);
      await expect(page.locator('h2:has-text("Family Members")')).toBeVisible();
    });

    test('Duo member does NOT see extra members section', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.duo.email, TEST_MEMBERS.duo.password);
      // Extra members should not be present for Duo
      const extraSection = page.locator('h2:has-text("Extra Members")');
      await expect(extraSection).toHaveCount(0);
    });

    test('Single member does NOT see family or extra members', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await expect(page.locator('h2:has-text("Family Members")')).toHaveCount(0);
      await expect(page.locator('h2:has-text("Extra Members")')).toHaveCount(0);
    });

    test('Civic member does NOT see family or extra members', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.civic.email, TEST_MEMBERS.civic.password);
      await expect(page.locator('h2:has-text("Family Members")')).toHaveCount(0);
      await expect(page.locator('h2:has-text("Extra Members")')).toHaveCount(0);
    });
  });
});
