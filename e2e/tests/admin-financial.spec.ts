import { test, expect } from '@playwright/test';
import { loginAsAdmin } from '../utils/login';
import { dbQuery, dbInsert } from '../utils/db';
import { TEST_MEMBERS } from '../fixtures/test-members';

const SITE_URL = process.env.SITE_URL || 'https://smoketree-ai.local';

function memberEditUrl(memberId: string): string {
  return `${SITE_URL}/wp-admin/admin.php?page=stsrc-members&action=edit&member_id=${memberId}`;
}

function nowMysql(): string {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

test.describe('Admin Financial Actions', () => {
  let balanceMemberId: string;
  let householdMemberId: string;
  let householdTypeId: string;

  test.beforeAll(async () => {
    balanceMemberId = dbQuery(
      `SELECT member_id FROM wp_stsrc_members WHERE email = '${TEST_MEMBERS.withBalance.email}' LIMIT 1`
    );
    householdMemberId = dbQuery(
      `SELECT member_id FROM wp_stsrc_members WHERE email = '${TEST_MEMBERS.household.email}' LIMIT 1`
    );
    householdTypeId = dbQuery(
      `SELECT membership_type_id FROM wp_stsrc_membership_types WHERE name = 'Household' LIMIT 1`
    );
  });

  test.describe('Adjust Balance', () => {
    test.beforeEach(async ({ page }) => {
      dbQuery(`UPDATE wp_stsrc_members SET balance_owed = 75.00 WHERE member_id = ${balanceMemberId}`);
      await loginAsAdmin(page);
      await page.goto(memberEditUrl(balanceMemberId));
    });

    test('apply a discount reduces the balance', async ({ page }) => {
      await page.getByRole('button', { name: 'Adjust Balance' }).click();
      await expect(page.getByRole('heading', { name: 'Adjust Member Balance' })).toBeVisible();

      await page.getByLabel('Adjustment Type').selectOption('discount');
      await page.getByLabel('Amount').fill('25.00');
      await page.getByLabel('Description').fill('Scholarship discount');
      await page.getByRole('button', { name: 'Continue' }).click();

      await expect(page.getByText('$50.00')).toBeVisible();

      await page.getByRole('button', { name: 'Confirm Adjustment' }).click();
      await expect(page.getByRole('heading', { name: 'Balance Adjusted Successfully!' })).toBeVisible();

      await page.getByRole('button', { name: 'Close' }).click();
      await page.waitForLoadState('networkidle');

      await expect(page.getByText('$50.00')).toBeVisible();
    });

    test('apply a fee increases the balance', async ({ page }) => {
      await page.getByRole('button', { name: 'Adjust Balance' }).click();
      await page.getByLabel('Adjustment Type').selectOption('fee');
      await page.getByLabel('Amount').fill('15.00');
      await page.getByLabel('Description').fill('Late registration fee');
      await page.getByRole('button', { name: 'Continue' }).click();

      await expect(page.getByText('$90.00')).toBeVisible();

      await page.getByRole('button', { name: 'Confirm Adjustment' }).click();
      await expect(page.getByRole('heading', { name: 'Balance Adjusted Successfully!' })).toBeVisible();
    });

    test('discount exceeding balance results in overpaid status', async ({ page }) => {
      await page.getByRole('button', { name: 'Adjust Balance' }).click();
      await page.getByLabel('Adjustment Type').selectOption('discount');
      await page.getByLabel('Amount').fill('100.00');
      await page.getByLabel('Description').fill('Full season scholarship');
      await page.getByRole('button', { name: 'Continue' }).click();
      await page.getByRole('button', { name: 'Confirm Adjustment' }).click();
      await expect(page.getByRole('heading', { name: 'Balance Adjusted Successfully!' })).toBeVisible();

      await page.getByRole('button', { name: 'Close' }).click();
      await page.waitForLoadState('networkidle');

      await expect(page.getByText('Overpaid')).toBeVisible();
    });

    test('Continue is disabled until description is filled', async ({ page }) => {
      await page.getByRole('button', { name: 'Adjust Balance' }).click();
      await page.getByLabel('Adjustment Type').selectOption('discount');
      await page.getByLabel('Amount').fill('10.00');

      await expect(page.getByRole('button', { name: 'Continue' })).toBeDisabled();
    });

    test('Continue is disabled until amount is filled', async ({ page }) => {
      await page.getByRole('button', { name: 'Adjust Balance' }).click();
      await page.getByLabel('Adjustment Type').selectOption('discount');
      await page.getByLabel('Description').fill('Test adjustment');

      await expect(page.getByRole('button', { name: 'Continue' })).toBeDisabled();
    });
  });

  test.describe('Record Manual Payment', () => {
    test.beforeEach(async ({ page }) => {
      dbQuery(`UPDATE wp_stsrc_members SET balance_owed = 75.00 WHERE member_id = ${balanceMemberId}`);
      await loginAsAdmin(page);
      await page.goto(memberEditUrl(balanceMemberId));
    });

    test('record a cash payment reduces the balance', async ({ page }) => {
      await page.getByRole('button', { name: 'Record Manual Payment' }).click();
      await expect(page.getByRole('heading', { name: 'Record Manual Payment' })).toBeVisible();

      await page.getByLabel('Payment Method').selectOption('cash');
      await page.getByLabel('Amount').fill('50.00');
      await page.getByLabel('Description').fill('Cash received at front desk');
      await page.getByRole('button', { name: 'Record Payment' }).click();

      await expect(page.getByRole('heading', { name: 'Record Manual Payment' })).toBeHidden();
      await expect(page.getByText('$25.00')).toBeVisible();
    });

    test('record a check payment with check number', async ({ page }) => {
      await page.getByRole('button', { name: 'Record Manual Payment' }).click();
      await page.getByLabel('Payment Method').selectOption('check');
      await expect(page.getByLabel('Check Number')).toBeVisible();

      await page.getByLabel('Check Number').fill('1042');
      await page.getByLabel('Amount').fill('75.00');
      await page.getByLabel('Description').fill('Check received');
      await page.getByRole('button', { name: 'Record Payment' }).click();

      await expect(page.getByRole('heading', { name: 'Record Manual Payment' })).toBeHidden();
      await expect(page.getByText('Paid in Full')).toBeVisible();
    });

    test('record a Zelle payment', async ({ page }) => {
      await page.getByRole('button', { name: 'Record Manual Payment' }).click();
      await page.getByLabel('Payment Method').selectOption('zelle');
      await page.getByLabel('Amount').fill('75.00');
      await page.getByLabel('Description').fill('Zelle transfer confirmed');
      await page.getByRole('button', { name: 'Record Payment' }).click();

      await expect(page.getByRole('heading', { name: 'Record Manual Payment' })).toBeHidden();
      await expect(page.getByText('Paid in Full')).toBeVisible();
    });

    test('check number field only appears for check payments', async ({ page }) => {
      await page.getByRole('button', { name: 'Record Manual Payment' }).click();
      await expect(page.getByLabel('Check Number')).toBeHidden();

      await page.getByLabel('Payment Method').selectOption('check');
      await expect(page.getByLabel('Check Number')).toBeVisible();

      await page.getByLabel('Payment Method').selectOption('cash');
      await expect(page.getByLabel('Check Number')).toBeHidden();
    });

    test('Record Payment button is disabled until required fields are filled', async ({ page }) => {
      await page.getByRole('button', { name: 'Record Manual Payment' }).click();
      await expect(page.getByRole('button', { name: 'Record Payment' })).toBeDisabled();
    });
  });

  test.describe('Confirm Offline Renewal Payment', () => {
    test.beforeEach(async ({ page }) => {
      const now = nowMysql();
      dbInsert('wp_stsrc_member_renewals', {
        member_id: householdMemberId,
        season_key: '2026',
        old_membership_type_id: householdTypeId,
        new_membership_type_id: householdTypeId,
        payment_method: 'check',
        payment_context: 'renewal',
        status: 'pending_payment',
        subtotal_amount: 575.00,
        processing_fee_amount: 0.00,
        total_amount: 575.00,
        previous_balance_amount: 0.00,
        transition_snapshot_json: '{}',
        created_at: now,
        updated_at: now,
      });

      await loginAsAdmin(page);
      await page.goto(memberEditUrl(householdMemberId));
    });

    test.afterEach(async () => {
      dbQuery(`DELETE FROM wp_stsrc_member_renewals WHERE member_id = ${householdMemberId}`);
    });

    test('confirms offline payment and activates membership', async ({ page }) => {
      await expect(page.getByRole('heading', { name: 'Renewal Status' })).toBeVisible();
      await expect(page.getByText('Pending Payment (awaiting offline confirmation)')).toBeVisible();

      page.once('dialog', dialog => dialog.accept());
      await page.getByRole('button', { name: 'Confirm Payment & Activate' }).click();
      await page.waitForLoadState('networkidle');

      await expect(page.getByText('Complete')).toBeVisible();
      await expect(page.getByRole('button', { name: 'Confirm Payment & Activate' })).toHaveCount(0);
    });

    test('optional notes can be added before confirming', async ({ page }) => {
      await page.getByPlaceholder('Optional confirmation notes').fill('Check #4821 received 2026-05-05');

      page.once('dialog', dialog => dialog.accept());
      await page.getByRole('button', { name: 'Confirm Payment & Activate' }).click();
      await page.waitForLoadState('networkidle');

      await expect(page.getByText('Complete')).toBeVisible();
    });
  });

  test.describe('Admin Cancel Renewal', () => {
    test.beforeEach(async ({ page }) => {
      const now = nowMysql();
      dbInsert('wp_stsrc_member_renewals', {
        member_id: householdMemberId,
        season_key: '2026',
        old_membership_type_id: householdTypeId,
        new_membership_type_id: householdTypeId,
        payment_method: 'check',
        payment_context: 'renewal',
        status: 'initiated',
        subtotal_amount: 575.00,
        processing_fee_amount: 0.00,
        total_amount: 575.00,
        previous_balance_amount: 0.00,
        transition_snapshot_json: '{}',
        created_at: now,
        updated_at: now,
      });

      await loginAsAdmin(page);
      await page.goto(memberEditUrl(householdMemberId));
    });

    test.afterEach(async () => {
      dbQuery(`DELETE FROM wp_stsrc_member_renewals WHERE member_id = ${householdMemberId}`);
    });

    test('cancels an in-progress renewal', async ({ page }) => {
      await expect(page.getByText('In Progress')).toBeVisible();

      page.once('dialog', dialog => dialog.accept());
      await page.getByRole('button', { name: 'Cancel This Renewal' }).click();
      await page.waitForLoadState('networkidle');

      await expect(page.getByRole('button', { name: 'Cancel This Renewal' })).toHaveCount(0);
      await expect(page.getByText('In Progress')).toHaveCount(0);
    });

    test('dismissing the cancel dialog leaves the renewal intact', async ({ page }) => {
      await expect(page.getByText('In Progress')).toBeVisible();

      page.once('dialog', dialog => dialog.dismiss());
      await page.getByRole('button', { name: 'Cancel This Renewal' }).click();

      await expect(page.getByText('In Progress')).toBeVisible();
      await expect(page.getByRole('button', { name: 'Cancel This Renewal' })).toBeVisible();
    });
  });
});
