import { test, expect } from '@playwright/test';
import { loginAsMember, loginAsAdmin, logout, expectLoginError } from '../utils/login';
import { TEST_MEMBERS } from '../fixtures/test-members';

test.describe('Authentication', () => {

  test.describe('Login', () => {

    test('shows login page with correct elements', async ({ page }) => {
      await page.goto('/login');
      await expect(page.locator('#stsrc-login-form')).toBeVisible();
      await expect(page.locator('#user_login')).toBeVisible();
      await expect(page.locator('#user_password')).toBeVisible();
      await expect(page.locator('#stsrc-submit-login')).toBeVisible();
      await expect(page.locator('a[href*="/forgot-password"]')).toBeVisible();
      await expect(page.locator('a[href*="/register"]')).toBeVisible();
    });

    test('logs in with valid member credentials', async ({ page }) => {
      const { email, password } = TEST_MEMBERS.individual;
      await loginAsMember(page, email, password);
      await expect(page).toHaveURL(/member-portal/);
      await expect(page.locator('h1')).toContainText('Member Portal');
    });

    test('shows error for invalid password', async ({ page }) => {
      await page.goto('/login');
      await page.locator('#user_login').fill(TEST_MEMBERS.individual.email);
      await page.locator('#user_password').fill('WrongPassword999!');
      await page.locator('#stsrc-submit-login').click();
      await expectLoginError(page, 'Invalid email or password');
    });

    test('shows error for non-existent email', async ({ page }) => {
      await page.goto('/login');
      await page.locator('#user_login').fill('nonexistent@example.com');
      await page.locator('#user_password').fill('SomePassword123!');
      await page.locator('#stsrc-submit-login').click();
      await expectLoginError(page, 'Invalid email or password');
    });

    test('shows error for empty fields', async ({ page }) => {
      await page.goto('/login');
      await page.locator('#stsrc-submit-login').click();
      // Browser validation prevents submission, or AJAX returns error
      const loginField = page.locator('#user_login');
      await expect(loginField).toHaveAttribute('required', '');
    });

    test('password toggle shows/hides password', async ({ page }) => {
      await page.goto('/login');
      const passwordInput = page.locator('#user_password');
      await passwordInput.fill('testpassword');
      await expect(passwordInput).toHaveAttribute('type', 'password');

      await page.locator('#stsrc-toggle-password').click();
      await expect(passwordInput).toHaveAttribute('type', 'text');

      await page.locator('#stsrc-toggle-password').click();
      await expect(passwordInput).toHaveAttribute('type', 'password');
    });

    test('remember me checkbox is present', async ({ page }) => {
      await page.goto('/login');
      await expect(page.locator('#rememberme')).toBeVisible();
    });
  });

  test.describe('Logout', () => {

    test('logout redirects to login with loggedout message', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      // Click the Log Out link
      await page.locator('a:has-text("Log Out")').click();
      // WordPress shows a confirm link on some configs
      const confirmLink = page.locator('a[href*="action=logout"]');
      if (await confirmLink.isVisible({ timeout: 3_000 }).catch(() => false)) {
        await confirmLink.click();
      }
      await page.waitForURL('**/login**', { timeout: 15_000 });
      await expect(page).toHaveURL(/loggedout=true/);
      await expect(page.locator('.stsrc-notice.success')).toContainText('logged out');
    });
  });

  test.describe('Forgot Password', () => {

    test('shows forgot password form', async ({ page }) => {
      await page.goto('/forgot-password');
      await expect(page.locator('#stsrc-forgot-password-form')).toBeVisible();
      await expect(page.locator('#email')).toBeVisible();
      await expect(page.locator('#stsrc-submit-forgot-password')).toBeVisible();
    });

    test('submitting valid email shows success message', async ({ page }) => {
      await page.goto('/forgot-password');
      await page.locator('#email').fill(TEST_MEMBERS.individual.email);
      await page.locator('#stsrc-submit-forgot-password').click();

      const notice = page.locator('#stsrc-form-messages .stsrc-notice.success').first();
      await expect(notice).toBeVisible({ timeout: 10_000 });
      await expect(notice).toContainText('password reset link has been sent');
    });

    test('submitting non-existent email still shows generic success (no leak)', async ({ page }) => {
      await page.goto('/forgot-password');
      await page.locator('#email').fill('nonexistent-nobody@example.com');
      await page.locator('#stsrc-submit-forgot-password').click();

      const notice = page.locator('#stsrc-form-messages .stsrc-notice').first();
      await expect(notice).toBeVisible({ timeout: 10_000 });
      // Should NOT reveal whether the email exists
      await expect(notice).toContainText('password reset link has been sent');
    });

    test('has back to login link', async ({ page }) => {
      await page.goto('/forgot-password');
      const backLink = page.locator('a[href*="/login"]');
      await expect(backLink).toBeVisible();
    });
  });

  test.describe('Reset Password', () => {

    test('invalid token shows error', async ({ page }) => {
      await page.goto('/reset-password?token=invalidtoken123&email=test@example.com');
      // Should show error about invalid/expired token
      const errorNotice = page.locator('.stsrc-notice.error, .stsrc-error, [class*="error"]').first();
      await expect(errorNotice).toBeVisible({ timeout: 10_000 });
    });

    test('missing token redirects or shows error', async ({ page }) => {
      await page.goto('/reset-password');
      const errorNotice = page.locator('.stsrc-notice.error, .stsrc-error, [class*="error"]').first();
      await expect(errorNotice).toBeVisible({ timeout: 10_000 });
    });
  });

  test.describe('Redirects', () => {

    test('wp-login.php redirects to custom login', async ({ page }) => {
      await page.goto('/wp-login.php');
      await page.waitForURL('**/login**', { timeout: 15_000 });
    });

    test('member portal redirects unauthenticated users to login', async ({ page }) => {
      await page.goto('/member-portal');
      await page.waitForURL('**/login**', { timeout: 15_000 });
      await expect(page).toHaveURL(/redirect_to/);
    });

    test('guest pass portal redirects unauthenticated users to login', async ({ page }) => {
      await page.goto('/guest-pass-portal');
      await page.waitForURL('**/login**', { timeout: 15_000 });
    });

    test('logged-in user visiting /login redirects to member portal', async ({ page }) => {
      await loginAsMember(page, TEST_MEMBERS.individual.email, TEST_MEMBERS.individual.password);
      await page.goto('/login');
      await page.waitForURL('**/member-portal**', { timeout: 15_000 });
    });
  });
});
