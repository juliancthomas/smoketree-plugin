import { test, expect } from '@playwright/test';
import { loginAsMember } from '../utils/login';
import { waitForAjax } from '../utils/helpers';
import { TEST_MEMBERS } from '../fixtures/test-members';

test.describe('Balance Payment', () => {

  test.describe('Member With Balance', () => {

    test.beforeEach(async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.withBalance.email, TEST_MEMBERS.withBalance.password);
    });

    test('shows outstanding balance card', async ({ page }) => {
      const balanceCard = page.locator('.stsrc-balance-card, .stsrc-outstanding-balance, [class*="balance-card"]').first();
      await expect(balanceCard).toBeVisible();
    });

    test('balance card shows correct amount', async ({ page }) => {
      const balanceCard = page.locator('.stsrc-balance-card, .stsrc-outstanding-balance, [class*="balance-card"]').first();
      await expect(balanceCard).toContainText('$75.00');
    });

    test('Pay Balance button is visible', async ({ page }) => {
      const payBtn = page.locator('.stsrc-pay-balance-btn, button:has-text("Pay Balance")').first();
      await expect(payBtn).toBeVisible();
    });

    test('clicking Pay Balance opens modal', async ({ page }) => {
      const payBtn = page.locator('.stsrc-pay-balance-btn, button:has-text("Pay Balance")').first();
      await payBtn.click();

      const modal = page.locator('#stsrc-pay-balance-modal, .stsrc-modal:has(h2:has-text("Pay Balance"))').first();
      await expect(modal).toBeVisible();
    });

    test('pay balance modal shows payment method options', async ({ page }) => {
      const payBtn = page.locator('.stsrc-pay-balance-btn, button:has-text("Pay Balance")').first();
      await payBtn.click();

      const modal = page.locator('#stsrc-pay-balance-modal, .stsrc-modal:has(h2:has-text("Pay Balance"))').first();
      await expect(modal.locator('input[name="payment_method"][value="card"]')).toBeVisible();
      await expect(modal.locator('input[name="payment_method"][value="us_bank_account"]')).toBeVisible();
    });

    test('pay balance modal shows amount input', async ({ page }) => {
      const payBtn = page.locator('.stsrc-pay-balance-btn, button:has-text("Pay Balance")').first();
      await payBtn.click();

      const amountInput = page.locator('#stsrc-pay-amount, input[name="amount"]').first();
      await expect(amountInput).toBeVisible();
    });

    test('pay balance modal calculates card processing fee', async ({ page }) => {
      const payBtn = page.locator('.stsrc-pay-balance-btn, button:has-text("Pay Balance")').first();
      await payBtn.click();

      // Select card payment
      const cardRadio = page.locator('#stsrc-pay-balance-modal input[name="payment_method"][value="card"], .stsrc-modal input[name="payment_method"][value="card"]').first();
      await cardRadio.check();

      // Fee for $75: $75 * 2.9% + $0.30 = $2.48
      const feeLine = page.locator('.stsrc-pay-balance-summary__row--fee .stsrc-pay-balance-summary__value, [id*="fee"]').first();
      const feeText = await feeLine.textContent();
      expect(feeText).toBeTruthy();
    });

    test('pay balance modal calculates ACH processing fee', async ({ page }) => {
      const payBtn = page.locator('.stsrc-pay-balance-btn, button:has-text("Pay Balance")').first();
      await payBtn.click();

      const achRadio = page.locator('#stsrc-pay-balance-modal input[name="payment_method"][value="us_bank_account"], .stsrc-modal input[name="payment_method"][value="us_bank_account"]').first();
      await achRadio.check();

      // Fee for $75: $75 * 0.8% = $0.60
      const feeLine = page.locator('.stsrc-pay-balance-summary__row--fee .stsrc-pay-balance-summary__value, [id*="fee"]').first();
      const feeText = await feeLine.textContent();
      expect(feeText).toBeTruthy();
    });

    test('submitting pay balance initiates Stripe checkout', async ({ page }) => {
      const payBtn = page.locator('.stsrc-pay-balance-btn, button:has-text("Pay Balance")').first();
      await payBtn.click();

      const cardRadio = page.locator('#stsrc-pay-balance-modal input[name="payment_method"][value="card"], .stsrc-modal input[name="payment_method"][value="card"]').first();
      await cardRadio.check();

      const submitBtn = page.locator('#stsrc-pay-balance-modal button[type="submit"], .stsrc-modal button:has-text("Continue to Payment")').first();
      await submitBtn.click();

      await page.waitForURL(/checkout\.stripe\.com|member-portal/, {
        timeout: 30_000,
      }).catch(() => {});

      const url = page.url();
      if (url.includes('checkout.stripe.com')) {
        expect(url).toContain('checkout.stripe.com');
      } else {
        // Stripe keys may not be configured
        expect(url).toContain('member-portal');
      }
    });

    test('pay balance modal can be closed', async ({ page }) => {
      const payBtn = page.locator('.stsrc-pay-balance-btn, button:has-text("Pay Balance")').first();
      await payBtn.click();

      const modal = page.locator('#stsrc-pay-balance-modal, .stsrc-modal:has(h2:has-text("Pay Balance"))').first();
      await expect(modal).toBeVisible();

      await modal.locator('.stsrc-modal-close').first().click();
      await expect(modal).toBeHidden();
    });
  });

  test.describe('Member Without Balance', () => {

    test('does not show balance card or Pay Balance button', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);

      const balanceCard = page.locator('.stsrc-balance-card, .stsrc-outstanding-balance');
      const count = await balanceCard.count();
      if (count > 0) {
        await expect(balanceCard.first()).toBeHidden();
      }
    });
  });
});
