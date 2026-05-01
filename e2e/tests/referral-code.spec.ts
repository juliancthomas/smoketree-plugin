import { test, expect } from '@playwright/test';
import { fillRegistrationForm, submitRegistrationForm, uniqueEmail, waitForAjax, clickAndWaitForAjax } from '../utils/helpers';
import { TEST_MEMBERS } from '../fixtures/test-members';

const REFERRER_CODE = TEST_MEMBERS.referrer.affiliateCode;
const INACTIVE_CODE = TEST_MEMBERS.inactiveReferrer.affiliateCode;

test.describe('Referral Code', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/register');
    await expect(page.locator('#stsrc-registration-form')).toBeVisible();
    // Select Single so referral validation has a membership type context ($295 base)
    await page.locator('.stsrc-membership-card').nth(2).click();
  });

  test.describe('Field UI', () => {

    test('referral code field and apply button are visible', async ({ page }) => {
      await expect(page.locator('#stsrc_affiliate_code')).toBeVisible();
      await expect(page.locator('#apply-affiliate-btn')).toBeVisible();
    });

    test('referral banner is hidden on page load', async ({ page }) => {
      await expect(page.locator('#stsrc-referral-banner')).toBeHidden();
    });
  });

  test.describe('Code Validation', () => {

    test('invalid code shows error feedback', async ({ page }) => {
      await page.locator('#stsrc_affiliate_code').fill('NOTACODE');
      await clickAndWaitForAjax(page.locator('#apply-affiliate-btn'));
      await expect(page.locator('#affiliate-feedback')).toContainText(/invalid/i);
    });

    test('code from inactive member is rejected', async ({ page }) => {
      await page.locator('#stsrc_affiliate_code').fill(INACTIVE_CODE);
      await clickAndWaitForAjax(page.locator('#apply-affiliate-btn'));
      await expect(page.locator('#affiliate-feedback')).toContainText(/no longer active/i);
    });

    test('valid code shows success feedback', async ({ page }) => {
      await page.locator('#stsrc_affiliate_code').fill(REFERRER_CODE);
      await clickAndWaitForAjax(page.locator('#apply-affiliate-btn'));
      await expect(page.locator('#affiliate-feedback')).toContainText('✓');
    });

    test('code validation is case-insensitive', async ({ page }) => {
      await page.locator('#stsrc_affiliate_code').fill(REFERRER_CODE.toLowerCase());
      await clickAndWaitForAjax(page.locator('#apply-affiliate-btn'));
      await expect(page.locator('#affiliate-feedback')).toContainText('✓');
    });
  });

  test.describe('Discount Applied', () => {

    test.beforeEach(async ({ page }) => {
      await page.locator('#stsrc_affiliate_code').fill(REFERRER_CODE);
      await clickAndWaitForAjax(page.locator('#apply-affiliate-btn'));
      await expect(page.locator('#affiliate-feedback')).toContainText('✓');
    });

    test('discount row appears in order summary', async ({ page }) => {
      await expect(page.locator('#stsrc-discount-row')).toBeVisible();
    });

    test('discount row label contains "Referral"', async ({ page }) => {
      await expect(page.locator('#stsrc-discount-row')).toContainText(/referral/i);
    });

    test('referral banner is visible and shows referrer name', async ({ page }) => {
      const banner = page.locator('#stsrc-referral-banner');
      await expect(banner).toBeVisible();
      await expect(banner).toContainText(TEST_MEMBERS.referrer.first);
    });

    test('promo code field is disabled after referral applied', async ({ page }) => {
      await expect(page.locator('#stsrc_promo_code')).toBeDisabled();
    });

    test('order total reflects $25 discount with check payment', async ({ page }) => {
      await page.locator('input[name="payment_type"][value="check"]').check();
      // Single $295.00 - $25.00 referral discount = $270.00, no processing fee for check
      await expect(page.locator('#stsrc-total')).toContainText('$270.00');
    });
  });

  test.describe('URL ref= Parameter', () => {

    test('?ref= query param pre-populates the referral code field', async ({ page }) => {
      await page.goto(`/register?ref=${REFERRER_CODE}`);
      await expect(page.locator('#stsrc_affiliate_code')).toHaveValue(REFERRER_CODE);
    });

    test('code from ?ref= applies discount after selecting membership and clicking Apply', async ({ page }) => {
      await page.goto(`/register?ref=${REFERRER_CODE}`);
      await page.locator('.stsrc-membership-card').nth(2).click();
      await clickAndWaitForAjax(page.locator('#apply-affiliate-btn'));
      await expect(page.locator('#stsrc-discount-row')).toBeVisible();
      await expect(page.locator('#stsrc-referral-banner')).toBeVisible();
    });
  });

  test.describe('Full Registration Flow', () => {

    test('registration with referral code completes via check payment', async ({ page }) => {
      const email = uniqueEmail('referral');
      await fillRegistrationForm(page, { email, paymentType: 'check' });
      await page.locator('#stsrc_affiliate_code').fill(REFERRER_CODE);
      await clickAndWaitForAjax(page.locator('#apply-affiliate-btn'));
      await expect(page.locator('#affiliate-feedback')).toContainText('✓');
      await submitRegistrationForm(page);
      await page.waitForURL(/member-portal/, { timeout: 30_000 });
      await expect(page.locator('h1')).toContainText('Member Portal');
    });

    test('registration with referral code redirects to Stripe for card payment', async ({ page }) => {
      const email = uniqueEmail('referral-card');
      await fillRegistrationForm(page, { email, paymentType: 'card' });
      await page.locator('#stsrc_affiliate_code').fill(REFERRER_CODE);
      await clickAndWaitForAjax(page.locator('#apply-affiliate-btn'));
      await expect(page.locator('#affiliate-feedback')).toContainText('✓');
      await page.locator('#auto_renewal_acknowledged').check();
      await submitRegistrationForm(page);
      await page.waitForURL(/checkout\.stripe\.com|member-portal/, { timeout: 30_000 });
      expect(page.url()).toContain('checkout.stripe.com');
    });
  });
});
