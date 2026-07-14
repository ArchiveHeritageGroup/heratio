/**
 * Shared helpers for the demo (video walkthrough) specs.
 */
import { Page } from '@playwright/test';

export const HERATIO_URL = process.env.HERATIO_URL || 'https://heratio.theahg.co.za';
export const EMAIL = process.env.TEST_ADMIN_EMAIL || 'johan@theahg.co.za';
export const PASSWORD = process.env.TEST_ADMIN_PASSWORD || 'Skukuza@246';

/** True when the target is the live production host (guard destructive demos). */
export const isProd = /\/\/heratio\.theahg\.co\.za/i.test(HERATIO_URL);

/**
 * Log in as an administrator. The login page carries several forms (clipboard /
 * feedback chrome), so a bare button[type=submit] hits the wrong one - submit
 * the login form by pressing Enter in the password field instead.
 */
export async function login(page: Page): Promise<void> {
  await page.goto(`${HERATIO_URL}/login`);
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[name="password"]', PASSWORD);
  await page.locator('input[name="password"]').press('Enter');
  await page.waitForURL(
    (u) => !u.pathname.replace(/\/+$/, '').endsWith('/login'),
    { timeout: 15000 }
  );
}

/**
 * ISAD(G)/ISAAR edit forms render their areas as Bootstrap accordions that
 * start collapsed, so fields are in the DOM but not visible. Expand one by its
 * collapse-target id (e.g. 'identity-collapse').
 */
export async function expandAccordion(page: Page, targetId: string): Promise<void> {
  const btn = page.locator(`[data-bs-target="#${targetId}"]`).first();
  if ((await btn.count()) === 0) return;
  if ((await btn.getAttribute('aria-expanded')) !== 'true') {
    await btn.click();
    await page.waitForTimeout(700);
  }
}
