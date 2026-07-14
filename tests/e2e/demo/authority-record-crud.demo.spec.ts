/**
 * DEMO WALKTHROUGH - Authority Record: full CRUD
 *
 * Records a watchable, narrated screen video of the complete lifecycle of an
 * authority (agent) record: Browse -> Add new -> View -> Edit -> Delete.
 * Part of the "one video per function" how-to library.
 *
 * Runs ONLY against a non-prod target (guarded below). Produces one video via
 * the 'demo' Playwright project (video: 'on', 1080p, slowMo). Run with:
 *   HERATIO_URL=http://192.168.0.112:8090 npx playwright test --project=demo --workers=1
 */
import { test, expect, Page } from '@playwright/test';

const HERATIO_URL = process.env.HERATIO_URL || 'https://heratio.theahg.co.za';
const EMAIL = process.env.TEST_ADMIN_EMAIL || 'johan@theahg.co.za';
const PASSWORD = process.env.TEST_ADMIN_PASSWORD || 'Skukuza@246';

// The ISAAR edit form's areas are Bootstrap accordions that start collapsed, so
// their fields are in the DOM but not visible. Expand the Identity area first.
async function expandIdentity(page: Page): Promise<void> {
  const btn = page.locator('[data-bs-target="#identity-collapse"]').first();
  if ((await btn.count()) === 0) return;
  if ((await btn.getAttribute('aria-expanded')) !== 'true') {
    await btn.click();
    await page.waitForTimeout(700);
  }
}

test.describe('Demo: Authority Record - full CRUD', () => {
  // Never create/delete against live production.
  test.skip(
    /\/\/heratio\.theahg\.co\.za/i.test(HERATIO_URL),
    'Demo CRUD must run against a non-prod target - set HERATIO_URL to the dev box.'
  );

  test('Browse, create, view, edit and delete an authority record', async ({ page }) => {
    const uniqueName = `Demo Person ${Date.now()}`;
    const updatedName = `${uniqueName} (edited)`;
    let recordUrl = '';

    await test.step('Log in as an administrator', async () => {
      await page.goto(`${HERATIO_URL}/login`);
      await page.fill('input[name="email"]', EMAIL);
      await page.fill('input[name="password"]', PASSWORD);
      // The page has several forms (clipboard/feedback chrome), so a bare
      // button[type=submit] hits the wrong one. Submit the login form itself by
      // pressing Enter in the password field.
      await page.locator('input[name="password"]').press('Enter');
      await page.waitForURL(
        (u) => !u.pathname.replace(/\/+$/, '').endsWith('/login'),
        { timeout: 15000 }
      );
      // Verify we are actually authenticated (browse pages are public, so a
      // failed login would still pass later reads). An admin-only page must load.
      await page.goto(`${HERATIO_URL}/actor/add`);
      await expect(page.locator('body')).not.toContainText(/must log in|Sign in to/i, { timeout: 15000 });
    });

    await test.step('Browse Authority Records (facets: Entity type, Maintained by, Language)', async () => {
      await page.goto(`${HERATIO_URL}/actor/browse`);
      await expect(page.locator('body')).toContainText(/Entity type|Maintained by|authority/i, { timeout: 15000 });
      await page.waitForTimeout(1500);
    });

    await test.step('Add new - open the create form', async () => {
      await page.goto(`${HERATIO_URL}/actor/add`);
      await expect(page.locator('select[name="entity_type_id"]')).toBeAttached({ timeout: 15000 });
      await expandIdentity(page);
      await expect(page.locator('select[name="entity_type_id"]')).toBeVisible({ timeout: 15000 });
      await page.waitForTimeout(800);
    });

    await test.step('Fill the required fields (Entity type + Authorized form of name)', async () => {
      await page.selectOption('select[name="entity_type_id"]', { label: 'Person' }).catch(async () => {
        // fall back to the first real option if labels differ
        await page.selectOption('select[name="entity_type_id"]', { index: 1 });
      });
      await page.fill('input[name="authorized_form_of_name"]', uniqueName);
      await page.waitForTimeout(1000);
    });

    await test.step('Create the record', async () => {
      await page.locator('input[type="submit"][value="Create"]').first().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(uniqueName, { timeout: 15000 });
      recordUrl = page.url();
      await page.waitForTimeout(1500);
    });

    await test.step('Edit the record - change the name', async () => {
      await page.getByRole('link', { name: 'Edit', exact: true }).first().click();
      await page.waitForLoadState('networkidle');
      await expandIdentity(page);
      const nameField = page.locator('input[name="authorized_form_of_name"]');
      await nameField.waitFor({ state: 'visible', timeout: 15000 });
      await nameField.fill(updatedName);
      await page.waitForTimeout(1000);
      await page.locator('input[type="submit"][value="Save"]').first().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(updatedName, { timeout: 15000 });
      await page.waitForTimeout(1500);
    });

    await test.step('Delete the record (with confirmation)', async () => {
      await page.getByRole('link', { name: 'Delete', exact: true }).first().click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(800);
      // Confirmation page: the delete form uses <input type=submit value="Delete">
      // (POST + @method DELETE). Target it specifically, not the chrome buttons.
      await page.locator('input[type="submit"][value="Delete"]').first().click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1200);
    });

    await test.step('Confirm the record is gone', async () => {
      // Visiting the deleted record's own URL should no longer show it.
      await page.goto(recordUrl);
      await page.waitForTimeout(1200);
      await expect(page.locator('body')).not.toContainText(updatedName);
    });
  });
});
