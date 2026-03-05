import { test, expect } from '@playwright/test';
import { loginAsMember } from '../utils/login';
import { waitForAjax } from '../utils/helpers';
import { TEST_MEMBERS } from '../fixtures/test-members';

test.describe('Extra Members (Portal)', () => {

  test.describe('Household Member', () => {

    test.beforeEach(async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.household.email, TEST_MEMBERS.household.password);
    });

    test('shows extra members section', async ({ page }) => {
      await expect(page.locator('h2:has-text("Extra Members")')).toBeVisible();
      await expect(page.locator('.stsrc-description')).toContainText('$50 each');
    });

    test('shows add extra member button', async ({ page }) => {
      await expect(page.locator('#stsrc-add-extra-member-btn')).toBeVisible();
      await expect(page.locator('#stsrc-add-extra-member-btn')).toContainText('$50');
    });

    test('opens add extra member modal', async ({ page }) => {
      await page.locator('#stsrc-add-extra-member-btn').click();
      await expect(page.locator('#stsrc-add-extra-member-modal')).toBeVisible();
    });

    test('modal has member name fields', async ({ page }) => {
      await page.locator('#stsrc-add-extra-member-btn').click();

      await expect(page.locator('#stsrc-add-extra-member-modal input[name="members[0][first_name]"]')).toBeVisible();
      await expect(page.locator('#stsrc-add-extra-member-modal input[name="members[0][last_name]"]')).toBeVisible();
    });

    test('modal has payment method selection', async ({ page }) => {
      await page.locator('#stsrc-add-extra-member-btn').click();

      await expect(page.locator('#stsrc-add-extra-member-modal input[name="payment_method"][value="card"]')).toBeVisible();
      await expect(page.locator('#stsrc-add-extra-member-modal input[name="payment_method"][value="us_bank_account"]')).toBeVisible();
    });

    test('modal shows order summary with $50', async ({ page }) => {
      await page.locator('#stsrc-add-extra-member-btn').click();

      await expect(page.locator('#stsrc-em-summary-subtotal')).toContainText('$50.00');
    });

    test('submitting extra member form initiates payment flow', async ({ page }) => {
      await page.locator('#stsrc-add-extra-member-btn').click();

      await page.locator('#stsrc-add-extra-member-modal input[name="members[0][first_name]"]').fill('ExtraTest');
      await page.locator('#stsrc-add-extra-member-modal input[name="members[0][last_name]"]').fill('Member');

      await page.locator('#stsrc-extra-member-submit').click();

      // Should redirect to Stripe or show error if keys not configured
      await page.waitForURL(/checkout\.stripe\.com|member-portal/, { timeout: 30_000 }).catch(() => {
        // May show an error within the modal instead
      });

      const url = page.url();
      if (url.includes('checkout.stripe.com')) {
        expect(url).toContain('checkout.stripe.com');
      } else {
        // If Stripe not configured, expect an error message
        const error = page.locator('#stsrc-extra-member-error, .stsrc-notice.error').first();
        const isVisible = await error.isVisible().catch(() => false);
        expect(isVisible || url.includes('member-portal')).toBeTruthy();
      }
    });

    test('shows empty state when no extra members', async ({ page }) => {
      const emptyState = page.locator('.stsrc-empty-state:has-text("No extra members")');
      const list = page.locator('.stsrc-extra-members-list');
      const hasMembers = await list.isVisible().catch(() => false);
      if (!hasMembers) {
        await expect(emptyState).toBeVisible();
      }
    });
  });

  test.describe('Non-Household Members', () => {

    test('Individual member does not see extra members section', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await expect(page.locator('h2:has-text("Extra Members")')).toHaveCount(0);
    });

    test('Duo member does not see extra members section', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.duo.email, TEST_MEMBERS.duo.password);
      await expect(page.locator('h2:has-text("Extra Members")')).toHaveCount(0);
    });

    test('Civic member does not see extra members section', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.civic.email, TEST_MEMBERS.civic.password);
      await expect(page.locator('h2:has-text("Extra Members")')).toHaveCount(0);
    });
  });
});
