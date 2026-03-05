import { Page, expect } from '@playwright/test';

export async function loginAsMember(
  page: Page,
  email: string,
  password: string
): Promise<void> {
  await page.goto('/login');
  await page.locator('#user_login').fill(email);
  await page.locator('#user_password').fill(password);
  await page.locator('#stsrc-submit-login').click();
  await page.waitForURL('**/member-portal**', { timeout: 15_000 });
}

export async function loginAsAdmin(page: Page): Promise<void> {
  const user = process.env.WP_ADMIN_USER || 'admin';
  const pass = process.env.WP_ADMIN_PASS || 'admin';

  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(user);
  await page.locator('#user_pass').fill(pass);
  await page.locator('#wp-submit').click();
  await page.waitForURL('**/wp-admin/**', { timeout: 15_000 });
}

export async function logout(page: Page): Promise<void> {
  await page.goto('/wp-login.php?action=logout');
  const confirmLink = page.locator('a[href*="action=logout"]');
  if (await confirmLink.isVisible({ timeout: 3_000 }).catch(() => false)) {
    await confirmLink.click();
  }
  await page.waitForURL('**/login**', { timeout: 15_000 });
}

export async function expectLoggedIn(page: Page): Promise<void> {
  await expect(page).toHaveURL(/member-portal/);
}

export async function expectLoginError(page: Page, message?: string): Promise<void> {
  const errorEl = page.locator('.stsrc-notice.error, .stsrc-form-message.error, #stsrc-form-messages .error');
  await expect(errorEl.first()).toBeVisible({ timeout: 10_000 });
  if (message) {
    await expect(errorEl.first()).toContainText(message);
  }
}
