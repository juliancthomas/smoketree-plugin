import { test, expect } from '@playwright/test';
import { loginAsAdmin, loginAsMember } from '../utils/login';
import { TEST_MEMBERS } from '../fixtures/test-members';
import { waitForAjax } from '../utils/helpers';

const ADMIN_URL = '/wp-admin/admin.php?page=stsrc-banner';

async function saveBanner(
  page: any,
  opts: {
    enabled?: boolean;
    message?: string;
    size?: 'small' | 'medium' | 'large' | 'xl' | 'fullscreen';
    type?: 'info' | 'warning' | 'alert' | 'success';
    audience?: 'all' | 'members' | 'public';
    dismissible?: boolean;
    resession?: boolean;
    expiryDate?: string;
    linkLabel?: string;
    linkUrl?: string;
    starText?: string;
    starBgColor?: string;
    starTextColor?: string;
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

  if (opts.message !== undefined)      await page.locator('#banner_message').fill(opts.message);
  if (opts.size !== undefined)         await page.locator('#banner_size').selectOption(opts.size);
  if (opts.type !== undefined)         await page.locator('#banner_type').selectOption(opts.type);
  if (opts.audience !== undefined)     await page.locator('#banner_audience').selectOption(opts.audience);
  if (opts.expiryDate !== undefined)   await page.locator('#banner_expiry_date').fill(opts.expiryDate);
  if (opts.linkLabel !== undefined)    await page.locator('#banner_link_label').fill(opts.linkLabel);
  if (opts.linkUrl !== undefined)      await page.locator('#banner_link_url').fill(opts.linkUrl);
  if (opts.starText !== undefined)     await page.locator('#banner_star_text').fill(opts.starText);
  if (opts.starBgColor !== undefined)  await page.locator('#banner_star_bg_color').fill(opts.starBgColor);
  if (opts.starTextColor !== undefined) await page.locator('#banner_star_text_color').fill(opts.starTextColor);

  if (opts.dismissible !== undefined) {
    const dismissCb = page.locator('#banner_dismissible');
    if (opts.dismissible) {
      await dismissCb.check();
    } else {
      await dismissCb.uncheck();
    }
  }

  if (opts.resession !== undefined) {
    const resessionCb = page.locator('#banner_resession');
    if (opts.resession) {
      await resessionCb.check();
    } else {
      await resessionCb.uncheck();
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

    test('renders all expected fields including star sticker', async ({ page }) => {
      await expect(page.locator('#banner_enabled')).toBeVisible();
      await expect(page.locator('#banner_message')).toBeVisible();
      await expect(page.locator('#banner_size')).toBeVisible();
      await expect(page.locator('#banner_type')).toBeVisible();
      await expect(page.locator('#banner_audience')).toBeVisible();
      await expect(page.locator('#banner_dismissible')).toBeVisible();
      await expect(page.locator('#banner_expiry_date')).toBeVisible();
      await expect(page.locator('#banner_link_label')).toBeVisible();
      await expect(page.locator('#banner_link_url')).toBeVisible();
      await expect(page.locator('#banner_star_text')).toBeVisible();
      await expect(page.locator('#banner_star_bg_color')).toBeVisible();
      await expect(page.locator('#banner_star_text_color')).toBeVisible();
    });

    test('size select defaults to small and has five options', async ({ page }) => {
      await expect(page.locator('#banner_size')).toHaveValue('small');
      const options = page.locator('#banner_size option');
      await expect(options).toHaveCount(5);
      await expect(options.nth(0)).toHaveValue('small');
      await expect(options.nth(1)).toHaveValue('medium');
      await expect(options.nth(2)).toHaveValue('large');
      await expect(options.nth(3)).toHaveValue('xl');
      await expect(options.nth(4)).toHaveValue('fullscreen');
    });

    test('saving shows success notice', async ({ page }) => {
      await page.locator('#banner_message').fill('Test save notice');
      await page.locator('#stsrc-banner-submit').click();
      await waitForAjax(page);
      await expect(page.locator('#stsrc-banner-save-result .notice-success')).toBeVisible({ timeout: 5_000 });
      await cleanupBanner(page);
    });

    test('saved values persist on page reload', async ({ page }) => {
      await page.locator('#banner_enabled').check();
      await page.locator('#banner_message').fill('Persistent message');
      await page.locator('#banner_size').selectOption('large');
      await page.locator('#banner_type').selectOption('warning');
      await page.locator('#banner_star_text').fill('NEW!');
      await page.locator('#stsrc-banner-submit').click();
      await waitForAjax(page);

      await page.reload();

      await expect(page.locator('#banner_enabled')).toBeChecked();
      await expect(page.locator('#banner_message')).toHaveValue('Persistent message');
      await expect(page.locator('#banner_size')).toHaveValue('large');
      await expect(page.locator('#banner_type')).toHaveValue('warning');
      await expect(page.locator('#banner_star_text')).toHaveValue('NEW!');

      await cleanupBanner(page);
    });

    test('live preview updates when message is typed', async ({ page }) => {
      await page.locator('#banner_message').fill('Preview test message');
      await expect(page.locator('#stsrc-preview-message')).toContainText('Preview test message');
    });

    test('live preview reflects banner type color change', async ({ page }) => {
      await page.locator('#banner_type').selectOption('warning');
      const preview = page.locator('#stsrc-banner-preview');
      await expect(preview).toHaveCSS('background-color', 'rgb(255, 237, 213)');
    });

    test('live preview shows star when star text is entered', async ({ page }) => {
      await page.locator('#banner_star_text').fill('HOT');
      await expect(page.locator('#stsrc-preview-star')).toBeVisible();
      await expect(page.locator('#stsrc-preview-star')).toContainText('HOT');
    });

    test('live preview hides star when star text is cleared', async ({ page }) => {
      await page.locator('#banner_star_text').fill('HOT');
      await page.locator('#banner_star_text').fill('');
      await expect(page.locator('#stsrc-preview-star')).not.toBeVisible();
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

    test('banner is outside the header element', async ({ page }) => {
      await saveBanner(page, { message: 'Positioned correctly' });
      await page.goto('/');
      const bannerInsideHeader = page.locator('header #stsrc-banner');
      await expect(bannerInsideHeader).toHaveCount(0);
      await expect(page.locator('#stsrc-banner')).toBeVisible();
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

    test('applies small size class by default', async ({ page }) => {
      await saveBanner(page, { message: 'Small banner', size: 'small' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toHaveClass(/stsrc-banner--small/);
    });

    test('applies medium size class when selected', async ({ page }) => {
      await saveBanner(page, { message: 'Medium banner', size: 'medium' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toHaveClass(/stsrc-banner--medium/);
    });

    test('applies large size class when selected', async ({ page }) => {
      await saveBanner(page, { message: 'Large banner', size: 'large' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toHaveClass(/stsrc-banner--large/);
    });

    test('applies xl size class when selected', async ({ page }) => {
      await saveBanner(page, { message: 'XL banner', size: 'xl' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toHaveClass(/stsrc-banner--xl/);
    });

    test('applies fullscreen size class when selected', async ({ page }) => {
      await saveBanner(page, { message: 'Full screen banner', size: 'fullscreen' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toHaveClass(/stsrc-banner--fullscreen/);
    });

    test('dismiss button is in top-right corner of banner', async ({ page }) => {
      await saveBanner(page, { message: 'Dismiss position test', dismissible: true });
      await page.goto('/');
      const banner = page.locator('#stsrc-banner');
      const dismiss = page.locator('.stsrc-banner-dismiss');
      await expect(dismiss).toBeVisible();
      await expect(dismiss).toHaveCSS('position', 'absolute');
      const bannerBox = await banner.boundingBox();
      const dismissBox = await dismiss.boundingBox();
      // dismiss button should be in the right half of the banner
      expect(dismissBox!.x).toBeGreaterThan(bannerBox!.x + bannerBox!.width / 2);
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

  test.describe('Star sticker', () => {

    test.afterEach(async ({ page }) => {
      await cleanupBanner(page);
    });

    test('star sticker is visible when star text is set', async ({ page }) => {
      await saveBanner(page, { message: 'Hot deal', starText: 'NEW!' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner .stsrc-banner-star')).toBeVisible();
      await expect(page.locator('#stsrc-banner .stsrc-banner-star')).toContainText('NEW!');
    });

    test('star sticker is not rendered when star text is empty', async ({ page }) => {
      await saveBanner(page, { message: 'No star', starText: '' });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner .stsrc-banner-star')).not.toBeVisible();
    });

    test('star sticker applies custom background and text colors', async ({ page }) => {
      await saveBanner(page, {
        message: 'Colored star',
        starText: 'HOT',
        starBgColor: '#ff0000',
        starTextColor: '#ffffff',
      });
      await page.goto('/');
      const star = page.locator('#stsrc-banner .stsrc-banner-star');
      await expect(star).toHaveCSS('background-color', 'rgb(255, 0, 0)');
      await expect(star).toHaveCSS('color', 'rgb(255, 255, 255)');
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

      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toBeVisible();

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

    test('X button writes to localStorage when resession is off', async ({ page }) => {
      await saveBanner(page, { message: 'Permanent dismiss', dismissible: true, resession: false });
      await page.goto('/');
      await page.locator('.stsrc-banner-dismiss').click();

      const stored = await page.evaluate(() =>
        Object.keys(localStorage).some(k => k.startsWith('stsrc_banner_'))
      );
      expect(stored).toBe(true);

      await page.reload();
      await expect(page.locator('#stsrc-banner')).not.toBeVisible();
    });

    test('X button writes to sessionStorage when resession is on', async ({ page }) => {
      await saveBanner(page, { message: 'Session dismiss', dismissible: true, resession: true });
      await page.goto('/');
      await page.locator('.stsrc-banner-dismiss').click();

      const inSession = await page.evaluate(() =>
        Object.keys(sessionStorage).some(k => k.startsWith('stsrc_banner_'))
      );
      const inLocal = await page.evaluate(() =>
        Object.keys(localStorage).some(k => k.startsWith('stsrc_banner_'))
      );
      expect(inSession).toBe(true);
      expect(inLocal).toBe(false);
    });

    test('non-dismissible banner has no dismiss button', async ({ page }) => {
      await saveBanner(page, { message: 'Cannot dismiss', dismissible: false });
      await page.goto('/');
      await expect(page.locator('#stsrc-banner')).toBeVisible();
      await expect(page.locator('.stsrc-banner-dismiss')).not.toBeVisible();
    });

  });

  test.describe("Don't show again", () => {

    test.afterEach(async ({ page }) => {
      await cleanupBanner(page);
    });

    test("'don't show again' button is visible when resession is enabled", async ({ page }) => {
      await saveBanner(page, { message: 'Resession on', dismissible: true, resession: true });
      await page.goto('/');
      await expect(page.locator('.stsrc-banner-no-show')).toBeVisible();
    });

    test("'don't show again' button is not shown when resession is disabled", async ({ page }) => {
      await saveBanner(page, { message: 'Resession off', dismissible: true, resession: false });
      await page.goto('/');
      await expect(page.locator('.stsrc-banner-no-show')).not.toBeVisible();
    });

    test("clicking 'don't show again' hides the banner", async ({ page }) => {
      await saveBanner(page, { message: 'Permanent close', dismissible: true, resession: true });
      await page.goto('/');
      await page.locator('.stsrc-banner-no-show').click();
      await expect(page.locator('#stsrc-banner')).not.toBeVisible();
    });

    test("'don't show again' always writes to localStorage", async ({ page }) => {
      await saveBanner(page, { message: 'Always local', dismissible: true, resession: true });
      await page.goto('/');
      await page.locator('.stsrc-banner-no-show').click();

      const inLocal = await page.evaluate(() =>
        Object.keys(localStorage).some(k => k.startsWith('stsrc_banner_'))
      );
      const inSession = await page.evaluate(() =>
        Object.keys(sessionStorage).some(k => k.startsWith('stsrc_banner_'))
      );
      expect(inLocal).toBe(true);
      expect(inSession).toBe(false);
    });

    test("banner stays hidden after reload when 'don't show again' was clicked", async ({ page }) => {
      await saveBanner(page, { message: 'Gone for good', dismissible: true, resession: true });
      await page.goto('/');
      await page.locator('.stsrc-banner-no-show').click();
      await page.reload();
      await expect(page.locator('#stsrc-banner')).not.toBeVisible();
    });

  });

});
