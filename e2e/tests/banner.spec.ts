import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsMember, logout } from '../utils/login';
import { TEST_MEMBERS } from '../fixtures/test-members';
import { waitForAjax } from '../utils/helpers';

const ADMIN_URL = '/wp-admin/admin.php?page=stsrc-banner';

async function saveBanner(
  page: any,
  opts: {
    enabled?: boolean;
    message?: string;
    size?: 'medium' | 'large';
    type?: 'info' | 'warning' | 'alert' | 'success';
    audience?: 'all' | 'members' | 'public';
    dismissible?: boolean;
    expiryDate?: string;
    linkLabel?: string;
    linkUrl?: string;
  } = {}
): Promise<void> {
  await loginAsAdmin(page);
  await page.goto(ADMIN_URL);

  const enabled = opts.enabled ?? true;
  const enabledCb = page.locator('#banner_enabled');
  if (enabled) {
    await enabledCb.check();
  } else {
    await enabledCb.uncheck();
  }

  if (opts.message !== undefined)    await page.locator('#banner_message').fill(opts.message);
  if (opts.size !== undefined)       await page.locator('#banner_size').selectOption(opts.size);
  if (opts.type !== undefined)       await page.locator('#banner_type').selectOption(opts.type);
  if (opts.audience !== undefined)   await page.locator('#banner_audience').selectOption(opts.audience);
  if (opts.expiryDate !== undefined) await page.locator('#banner_expiry_date').fill(opts.expiryDate);
  if (opts.linkLabel !== undefined)  await page.locator('#banner_link_label').fill(opts.linkLabel);
  if (opts.linkUrl !== undefined)    await page.locator('#banner_link_url').fill(opts.linkUrl);

  if (opts.dismissible !== undefined) {
    const dismissCb = page.locator('#banner_dismissible');
    if (opts.dismissible) {
      await dismissCb.check();
    } else {
      await dismissCb.uncheck();
    }
  }

  await page.locator('#stsrc-banner-submit').click();
  await waitForAjax(page);
  await expect(page.locator('#stsrc-banner-save-result .notice-success')).toBeVisible({ timeout: 5_000 });
}

async function cleanupBanner(page: any): Promise<void> {
  await saveBanner(page, { enabled: false, message: '' });
}

test.describe('Announcement Banner', () => {

  test.describe('Admin form', () => {

    test.beforeEach(async ({ page }) => {
      await loginAsAdmin(page);
      await page.goto(ADMIN_URL);
    });

    test('renders all expected fields', async ({ page }) => {
      await expect(page.locator('#banner_enabled')).toBeVisible();
      await expect(page.locator('#banner_message')).toBeVisible();
      await expect(page.locator('#banner_size')).toBeVisible();
      await expect(page.locator('#banner_type')).toBeVisible();
      await expect(page.locator('#banner_audience')).toBeVisible();
      await expect(page.locator('#banner_dismissible')).toBeVisible();
      await expect(page.locator('#banner_expiry_date')).toBeVisible();
      await expect(page.locator('#banner_link_label')).toBeVisible();
      await expect(page.locator('#banner_link_url')).toBeVisible();
    });

    test('size select defaults to medium', async ({ page }) => {
      await expect(page.locator('#banner_size')).toHaveValue('medium');
    });

    test('size select has medium and large options', async ({ page }) => {
      const options = page.locator('#banner_size option');
      await expect(options).toHaveCount(2);
      await expect(options.nth(0)).toHaveValue('medium');
      await expect(options.nth(1)).toHaveValue('large');
    });

    test('saving shows success notice', async ({ page }) => {
      await page.locator('#banner_message').fill('Test save notice');
      await page.locator('#stsrc-banner-submit').click();
      await waitForAjax(page);
      await expect(page.locator('#stsrc-banner-save-result .notice-success')).toBeVisible({ timeout: 5_000 });

      // cleanup
      await cleanupBanner(page);
    });

    test('saved values persist on page reload', async ({ page }) => {
      await page.locator('#banner_enabled').check();
      await page.locator('#banner_message').fill('Persistent message');
      await page.locator('#banner_size').selectOption('large');
      await page.locator('#banner_type').selectOption('warning');
      await page.locator('#stsrc-banner-submit').click();
      await waitForAjax(page);

      await page.reload();

      await expect(page.locator('#banner_enabled')).toBeChecked();
      await expect(page.locator('#banner_message')).toHaveValue('Persistent message');
      await expect(page.locator('#banner_size')).toHaveValue('large');
      await expect(page.locator('#banner_type')).toHaveValue('warning');

      // cleanup
      await cleanupBanner(page);
    });

  });

  test.describe('Frontend display', () => {

    test.afterEach(async ({ page }) => {
      await cleanupBanner(page);
    });

    test('banner is visible on homepage when enabled', async ({ page }) => {
      await saveBanner(page, { message: 'Pool opens June 1st' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toBeVisible();
      await expect(page.locator('#stsrc-banner')).toContainText('Pool opens June 1st');
    });

    test('banner is not rendered when disabled', async ({ page }) => {
      await saveBanner(page, { enabled: false, message: 'Should not show' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).not.toBeVisible();
    });

    test('applies correct type class for info', async ({ page }) => {
      await saveBanner(page, { message: 'Info notice', type: 'info' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toHaveClass(/stsrc-banner--info/);
    });

    test('applies correct type class for warning', async ({ page }) => {
      await saveBanner(page, { message: 'Warning notice', type: 'warning' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toHaveClass(/stsrc-banner--warning/);
    });

    test('applies correct type class for alert', async ({ page }) => {
      await saveBanner(page, { message: 'Alert notice', type: 'alert' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toHaveClass(/stsrc-banner--alert/);
    });

    test('applies correct type class for success', async ({ page }) => {
      await saveBanner(page, { message: 'Success notice', type: 'success' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toHaveClass(/stsrc-banner--success/);
    });

    test('applies medium size class by default', async ({ page }) => {
      await saveBanner(page, { message: 'Medium banner', size: 'medium' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toHaveClass(/stsrc-banner--medium/);
    });

    test('applies large size class when selected', async ({ page }) => {
      await saveBanner(page, { message: 'Large banner', size: 'large' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toHaveClass(/stsrc-banner--large/);
    });

    test('shows CTA link when label and URL are set', async ({ page }) => {
      await saveBanner(page, {
        message: 'Registration open',
        linkLabel: 'Register now',
        linkUrl: 'https://smoketree.us/register',
      });
      await page.goto('/');
      const link = page.locator('#stsrc-banner .stsrc-banner-link');
      await expect(link).toBeVisible();
      await expect(link).toContainText('Register now');
      await expect(link).toHaveAttribute('href', 'https://smoketree.us/register');
    });

    test('no CTA link when label is blank', async ({ page }) => {
      await saveBanner(page, {
        message: 'No link here',
        linkLabel: '',
        linkUrl: 'https://smoketree.us/register',
      });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner .stsrc-banner-link')).not.toBeVisible();
    });

    test('banner does not render after expiry date has passed', async ({ page }) => {
      await saveBanner(page, { message: 'Expired notice', expiryDate: '2020-01-01' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).not.toBeVisible();
    });

  });

  test.describe('Audience targeting', () => {

    test.afterEach(async ({ page }) => {
      await cleanupBanner(page);
    });

    test('members-only banner is hidden from logged-out visitors', async ({ page }) => {
      await saveBanner(page, { message: 'Members only', audience: 'members' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).not.toBeVisible();
    });

    test('members-only banner is visible to logged-in members', async ({ page }) => {
      await saveBanner(page, { message: 'Members only', audience: 'members' });
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toBeVisible();
      await expect(page.locator('#stsrc-banner')).toContainText('Members only');
    });

    test('public-only banner is hidden from logged-in members', async ({ page }) => {
      await saveBanner(page, { message: 'Public only', audience: 'public' });
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).not.toBeVisible();
    });

    test('public-only banner is visible to logged-out visitors', async ({ page }) => {
      await saveBanner(page, { message: 'Public only', audience: 'public' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toBeVisible();
      await expect(page.locator('#stsrc-banner')).toContainText('Public only');
    });

    test('all-audience banner is visible to both logged-out and logged-in users', async ({ page }) => {
      await saveBanner(page, { message: 'Everyone sees this', audience: 'all' });

      // logged out
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toBeVisible();

      // logged in
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toBeVisible();
    });

  });

  test.describe('Dismiss behaviour', () => {

    test.afterEach(async ({ page }) => {
      await cleanupBanner(page);
    });

    test('dismiss button is visible on dismissible banners', async ({ page }) => {
      await saveBanner(page, { message: 'Dismissible notice', dismissible: true });
      await page.goto('/');
      await expect(page.locator('.stsrc-banner-dismiss')).toBeVisible();
    });

    test('clicking dismiss hides the banner immediately', async ({ page }) => {
      await saveBanner(page, { message: 'Click to dismiss', dismissible: true });
      await page.goto('/');
      await page.locator('.stsrc-banner-dismiss').click();
      await expect(page.locator('#stsrc-banner')).not.toBeVisible();
    });

    test('dismissed banner stays hidden after page reload', async ({ page }) => {
      await saveBanner(page, { message: 'Stays gone', dismissible: true });
      await page.goto('/');
      await page.locator('.stsrc-banner-dismiss').click();

      // Verify localStorage key was written
      const stored = await page.evaluate(() =>
        Object.keys(localStorage).some(k => k.startsWith('stsrc_banner_'))
      );
      expect(stored).toBe(true);

      await page.reload();
      await expect(page.locator('#stsrc-banner')).not.toBeVisible();
    });

    test('non-dismissible banner has no dismiss button', async ({ page }) => {
      await saveBanner(page, { message: 'Cannot dismiss', dismissible: false });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toBeVisible();
      await expect(page.locator('.stsrc-banner-dismiss')).not.toBeVisible();
    });

  });

});
