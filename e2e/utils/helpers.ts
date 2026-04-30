import { Page, expect, Locator } from '@playwright/test';

let counter = 0;

export function uniqueEmail(prefix = 'test'): string {
  counter++;
  const ts = Date.now();
  return `${prefix}+${ts}${counter}@example.com`;
}

export function uniqueName(): { first: string; last: string } {
  const id = Date.now().toString(36).slice(-4);
  return { first: `Test${id}`, last: `User${counter++}` };
}

export async function fillRegistrationForm(
  page: Page,
  overrides: Partial<{
    firstName: string;
    lastName: string;
    email: string;
    phone: string;
    street: string;
    city: string;
    state: string;
    zip: string;
    password: string;
    referralSource: string;
    waiverName: string;
    membershipTypeIndex: number;
    paymentType: string;
  }> = {}
): Promise<{ email: string; password: string }> {
  const email = overrides.email || uniqueEmail('reg');
  const password = overrides.password || 'TestPass123!';
  const { first, last } = uniqueName();

  await page.locator('#first_name').fill(overrides.firstName || first);
  await page.locator('#last_name').fill(overrides.lastName || last);
  await page.locator('#email').fill(email);
  await page.locator('#phone').fill(overrides.phone || '(555) 555-1234');

  // #street_1 may be hidden by the Google Places autocomplete widget — set directly
  const streetVal = overrides.street || '123 Test Street';
  const streetVisible = await page.locator('#street_1').isVisible().catch(() => false);
  if (streetVisible) {
    await page.locator('#street_1').fill(streetVal);
  } else {
    await page.evaluate((val) => {
      const el = document.getElementById('street_1') as HTMLInputElement;
      if (el) { el.value = val; el.dispatchEvent(new Event('change', { bubbles: true })); }
    }, streetVal);
  }
  await page.locator('#city').fill(overrides.city || 'Tucker');
  await page.locator('#state').selectOption(overrides.state || 'GA');
  await page.locator('#zip').fill(overrides.zip || '30084');

  const cardIndex = overrides.membershipTypeIndex ?? 2; // 0=Household, 1=Duo, 2=Single, 3=Civic
  await page.locator('.stsrc-membership-card').nth(cardIndex).click();

  await page.locator('#password').fill(password);
  await page.locator('#password_confirm').fill(password);

  await page.locator('#referral_source').selectOption(overrides.referralSource || 'other');
  await page.locator('#waiver_full_name').fill(
    overrides.waiverName || `${overrides.firstName || first} ${overrides.lastName || last}`
  );

  if (overrides.paymentType) {
    await page.locator(`input[name="payment_type"][value="${overrides.paymentType}"]`).check();
  }

  return { email, password };
}

export async function expectToastOrNotice(
  page: Page,
  type: 'success' | 'error',
  textMatch?: string | RegExp
): Promise<void> {
  const selector = `.stsrc-notice.${type}, .stsrc-form-message.${type}, #stsrc-form-messages .${type}`;
  const notice = page.locator(selector).first();
  await expect(notice).toBeVisible({ timeout: 10_000 });
  if (textMatch) {
    await expect(notice).toContainText(textMatch);
  }
}

// Trigger jQuery's submit handler directly, bypassing native browser validation.
// Needed because the form's submit button is type="submit" and native validation
// may block the jQuery handler (e.g. when Google Places hides required inputs).
export async function submitRegistrationForm(page: Page): Promise<void> {
  await page.evaluate(() => {
    (window as any).jQuery('#stsrc-registration-form').trigger('submit');
  });
}

export async function waitForAjax(page: Page, timeout = 10_000): Promise<void> {
  await page.waitForFunction(
    () => (window as any).jQuery?.active === 0,
    { timeout }
  );
}

export async function clickAndWaitForAjax(locator: Locator): Promise<void> {
  const page = locator.page();
  await locator.click();
  await waitForAjax(page);
}

export async function expectModalVisible(page: Page, modalSelector: string): Promise<void> {
  await expect(page.locator(modalSelector)).toBeVisible({ timeout: 5_000 });
}

export async function expectModalHidden(page: Page, modalSelector: string): Promise<void> {
  await expect(page.locator(modalSelector)).toBeHidden({ timeout: 5_000 });
}
