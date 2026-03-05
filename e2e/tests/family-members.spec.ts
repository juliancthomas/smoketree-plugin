import { test, expect } from '@playwright/test';
import { loginAsMember } from '../utils/login';
import { waitForAjax } from '../utils/helpers';
import { TEST_MEMBERS } from '../fixtures/test-members';

test.describe('Family Members (Portal CRUD)', () => {

  test.describe('Household Member', () => {

    test.beforeEach(async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.household.email, TEST_MEMBERS.household.password);
    });

    test('shows family members section with add button', async ({ page }) => {
      await expect(page.locator('h2:has-text("Family Members")')).toBeVisible();
      await expect(page.locator('#stsrc-add-family-member-btn')).toBeVisible();
    });

    test('shows empty state when no family members', async ({ page }) => {
      const emptyState = page.locator('.stsrc-empty-state:has-text("No family members")');
      // May or may not be empty depending on test run order
      const familyList = page.locator('.stsrc-family-members-list');
      const hasMembers = await familyList.isVisible().catch(() => false);
      if (!hasMembers) {
        await expect(emptyState).toBeVisible();
      }
    });

    test('can add a family member', async ({ page }) => {
      await page.locator('#stsrc-add-family-member-btn').click();
      await expect(page.locator('#stsrc-family-member-modal')).toBeVisible();

      await page.locator('#family_first_name').fill('Jane');
      await page.locator('#family_last_name').fill('TestFamily');
      await page.locator('#family_email').fill('jane.testfamily@example.com');

      await page.locator('#stsrc-family-member-form button[type="submit"]').click();
      await waitForAjax(page);

      // Modal should close and page should update
      await page.reload();
      await expect(page.locator('.stsrc-family-members-list')).toContainText('Jane');
      await expect(page.locator('.stsrc-family-members-list')).toContainText('TestFamily');
    });

    test('can edit a family member', async ({ page }) => {
      // Ensure there's a family member to edit
      const editBtn = page.locator('.stsrc-edit-family-member').first();
      if (!(await editBtn.isVisible().catch(() => false))) {
        test.skip();
        return;
      }

      await editBtn.click();
      await expect(page.locator('#stsrc-family-member-modal')).toBeVisible();

      await page.locator('#family_first_name').fill('JaneEdited');
      await page.locator('#stsrc-family-member-form button[type="submit"]').click();
      await waitForAjax(page);

      await page.reload();
      await expect(page.locator('.stsrc-family-members-list')).toContainText('JaneEdited');
    });

    test('can delete a family member', async ({ page }) => {
      const deleteBtn = page.locator('.stsrc-delete-family-member').first();
      if (!(await deleteBtn.isVisible().catch(() => false))) {
        test.skip();
        return;
      }

      const countBefore = await page.locator('.stsrc-family-member-item').count();

      page.on('dialog', async (dialog) => {
        await dialog.accept();
      });
      await deleteBtn.click();
      await waitForAjax(page);

      await page.reload();
      const countAfter = await page.locator('.stsrc-family-member-item').count();
      expect(countAfter).toBeLessThan(countBefore);
    });

    test('add button disappears at family limit (4)', async ({ page }) => {
      // This is a structural check — if 4 members exist, button should be gone
      const items = page.locator('.stsrc-family-member-item');
      const count = await items.count();
      if (count >= 4) {
        await expect(page.locator('#stsrc-add-family-member-btn')).toHaveCount(0);
      }
    });

    test('description shows correct limit', async ({ page }) => {
      await expect(page.locator('.stsrc-description')).toContainText('4 family member');
    });
  });

  test.describe('Duo Member', () => {

    test.beforeEach(async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.duo.email, TEST_MEMBERS.duo.password);
    });

    test('shows family members section', async ({ page }) => {
      await expect(page.locator('h2:has-text("Family Members")')).toBeVisible();
    });

    test('description shows limit of 1', async ({ page }) => {
      await expect(page.locator('.stsrc-description')).toContainText('1 family member');
    });

    test('can add one family member', async ({ page }) => {
      const addBtn = page.locator('#stsrc-add-family-member-btn');
      if (!(await addBtn.isVisible().catch(() => false))) {
        // Already at limit
        test.skip();
        return;
      }

      await addBtn.click();
      await expect(page.locator('#stsrc-family-member-modal')).toBeVisible();

      await page.locator('#family_first_name').fill('DuoPartner');
      await page.locator('#family_last_name').fill('TestDuo');
      await page.locator('#stsrc-family-member-form button[type="submit"]').click();
      await waitForAjax(page);

      await page.reload();
      await expect(page.locator('.stsrc-family-members-list')).toContainText('DuoPartner');
    });
  });

  test.describe('Individual Member', () => {

    test('does not show family members section', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await expect(page.locator('h2:has-text("Family Members")')).toHaveCount(0);
    });
  });
});
