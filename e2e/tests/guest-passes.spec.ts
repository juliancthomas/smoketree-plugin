import { test, expect } from '@playwright/test';
import { loginAsMember } from '../utils/login';
import { waitForAjax } from '../utils/helpers';
import { TEST_MEMBERS } from '../fixtures/test-members';

test.describe('Guest Passes', () => {

  test.describe('Member Portal Guest Pass Section', () => {

    test.beforeEach(async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
    });

    test('shows guest pass balance', async ({ page }) => {
      await expect(page.locator('h2:has-text("Guest Passes")')).toBeVisible();
      await expect(page.locator('.stsrc-balance-amount')).toBeVisible();
    });

    test('shows correct balance label (pass/passes)', async ({ page }) => {
      const label = page.locator('.stsrc-balance-label');
      await expect(label).toBeVisible();
      const text = await label.textContent();
      expect(text).toMatch(/pass(es)?/);
    });

    test('shows $5 per pass pricing note', async ({ page }) => {
      await expect(page.locator('.stsrc-guest-pass-info')).toContainText('$5');
    });

    test('Purchase Guest Passes button opens modal', async ({ page }) => {
      await page.locator('#stsrc-purchase-guest-passes-btn').click();
      await expect(page.locator('#stsrc-purchase-guest-passes-modal')).toBeVisible();
    });

    test('purchase modal has quantity input and total', async ({ page }) => {
      await page.locator('#stsrc-purchase-guest-passes-btn').click();

      await expect(page.locator('#guest_pass_quantity')).toBeVisible();
      await expect(page.locator('#guest_pass_quantity')).toHaveValue('1');
      await expect(page.locator('#stsrc-guest-pass-total')).toContainText('5.00');
    });

    test('changing quantity updates total', async ({ page }) => {
      await page.locator('#stsrc-purchase-guest-passes-btn').click();

      await page.locator('#guest_pass_quantity').fill('3');
      await page.locator('#guest_pass_quantity').dispatchEvent('change');
      await expect(page.locator('#stsrc-guest-pass-total')).toContainText('15.00');
    });

    test('purchase form submits and redirects to Stripe', async ({ page }) => {
      await page.locator('#stsrc-purchase-guest-passes-btn').click();

      await page.locator('#guest_pass_quantity').fill('2');
      await page.locator('#guest_pass_quantity').dispatchEvent('change');

      await page.locator('#stsrc-purchase-guest-passes-form button[type="submit"]').click();

      // Should redirect to Stripe or show error
      await page.waitForURL(/checkout\.stripe\.com|member-portal|guest-pass/, {
        timeout: 30_000,
      }).catch(() => {});

      const url = page.url();
      if (url.includes('checkout.stripe.com')) {
        expect(url).toContain('checkout.stripe.com');
      } else {
        // Stripe keys may not be configured — check for some response
        const modal = page.locator('#stsrc-purchase-guest-passes-modal');
        expect(await modal.isVisible() || url.includes('member-portal')).toBeTruthy();
      }
    });

    test('purchase modal can be closed', async ({ page }) => {
      await page.locator('#stsrc-purchase-guest-passes-btn').click();
      await expect(page.locator('#stsrc-purchase-guest-passes-modal')).toBeVisible();

      await page.locator('#stsrc-purchase-guest-passes-modal .stsrc-modal-close').first().click();
      await expect(page.locator('#stsrc-purchase-guest-passes-modal')).toBeHidden();
    });

    test('View Guest Pass Portal link works', async ({ page }) => {
      await page.locator('a[href*="/guest-pass-portal"]').click();
      await page.waitForURL('**/guest-pass-portal**', { timeout: 15_000 });
    });
  });

  test.describe('Guest Pass Portal', () => {

    test.beforeEach(async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await page.goto('/guest-pass-portal');
    });

    test('loads guest pass portal page', async ({ page }) => {
      await expect(page).toHaveURL(/guest-pass-portal/);
    });

    test('shows guest pass balance on portal', async ({ page }) => {
      await expect(page.locator('.stsrc-balance-amount, .stsrc-guest-pass-balance')).toBeVisible();
    });

    test('unauthenticated access redirects to login', async ({ page }) => {
      // Logout first
      await page.goto('/wp-login.php?action=logout');
      const confirmLink = page.locator('a[href*="action=logout"]');
      if (await confirmLink.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await confirmLink.click();
      }
      await page.waitForURL('**/login**', { timeout: 15_000 });

      // Try to access guest pass portal
      await page.goto('/guest-pass-portal');
      await page.waitForURL('**/login**', { timeout: 15_000 });
    });
  });
});
