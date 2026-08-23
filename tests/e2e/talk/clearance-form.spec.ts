/**
 * End-to-end proof that the security-clearance form actually persists what it
 * collects - #1478.
 *
 * The form posts nine fields; AclService::setUserClearance() used to write
 * four, discarding expiry_date (column expires_at), notes, and all three
 * vetting fields. That was fixed in v1.154.643, but only ever verified by
 * calling the service directly - the controller path (validation, field names,
 * the expiry_date -> expires_at mapping) was never exercised through a browser.
 * This drives the real form and reads the row back.
 *
 * Run:
 *   HERATIO_URL=http://localhost:8090 npx playwright test tests/e2e/talk/clearance-form.spec.ts --project=talk --workers=1
 */
import { test, expect } from '@playwright/test';
import { EMAIL, PASSWORD, HERATIO_URL } from '../demo/demo-helpers';

test('the clearance form persists vetting evidence, expiry and notes', async ({ browser }) => {
  test.setTimeout(180000);

  const ctx = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
  const page = await ctx.newPage();

  await page.goto(`${HERATIO_URL}/login`);
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[name="password"]', PASSWORD);
  await page.locator('input[name="password"]').press('Enter');
  await page.waitForURL((u) => !u.pathname.replace(/\/+$/, '').endsWith('/login'), { timeout: 20000 });

  // Values distinctive enough that a stale row cannot be mistaken for a pass.
  const stamp = String(Date.now()).slice(-6);
  const vetAuthority = `State Security Agency ${stamp}`;
  const vetReference = `SSA/VET/${stamp}`;
  const vetDate = '2026-02-11';
  const expiry = '2027-06-30';
  const notes = `Renewed after periodic review ${stamp}.`;

  const userId = Number(process.env.CLEARANCE_USER_ID || 0);
  expect(userId, 'CLEARANCE_USER_ID must be set').toBeGreaterThan(0);

  await page.goto(`${HERATIO_URL}/admin/acl/user/${userId}/clearance`);

  // Locate by the vetting field itself. The route is NAMED acl.set-clearance
  // but its URL is /admin/acl/clearances, so matching the action on the route
  // name finds nothing - and there are three forms on this page.
  const vetting = page.locator('[name="vetting_authority"]');
  await expect(vetting, 'the clearance page did not render its grant form').toBeVisible({ timeout: 15000 });

  const form = page.locator('form').filter({ has: vetting }).first();

  await form.locator('[name="classification_id"]').selectOption({ index: 1 });
  await form.locator('[name="expiry_date"]').fill(expiry);
  await form.locator('[name="vetting_reference"]').fill(vetReference);
  await form.locator('[name="vetting_date"]').fill(vetDate);
  await form.locator('[name="vetting_authority"]').fill(vetAuthority);
  await form.locator('[name="notes"]').fill(notes);

  await Promise.all([
    page.waitForLoadState('networkidle'),
    form.locator('button[type="submit"], input[type="submit"]').first().click(),
  ]);

  // Read the values back off the rendered page rather than trusting the redirect.
  await page.goto(`${HERATIO_URL}/admin/acl/user/${userId}/clearance`);
  const html = await page.content();

  for (const [label, value] of Object.entries({ vetAuthority, vetReference, vetDate, expiry, notes })) {
    expect(html, `${label} did not survive the round trip`).toContain(value);
  }

  await ctx.close();
});
