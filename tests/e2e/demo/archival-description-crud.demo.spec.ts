/**
 * DEMO WALKTHROUGH - Archival Description (Information Object): full CRUD
 *
 * Records a watchable, narrated video of the full lifecycle of an archival
 * description: Browse (GLAM) -> Add new -> View -> Edit -> Delete. Part of the
 * "one video per function" how-to library. Non-prod only. Run with:
 *   HERATIO_URL=http://192.168.0.112:8090 npx playwright test --project=demo --workers=1
 */
import { test, expect } from '@playwright/test';
import { HERATIO_URL, isProd, login, expandAccordion } from './demo-helpers';

test.describe('Demo: Archival Description - full CRUD', () => {
  test.skip(isProd, 'Demo CRUD must run against a non-prod target - set HERATIO_URL to the dev box.');

  test('Browse, create, view, edit and delete an archival description', async ({ page }) => {
    const title = `Demo Fonds ${Date.now()}`;
    const updatedTitle = `${title} (edited)`;
    let recordUrl = '';

    await test.step('Log in as an administrator', async () => {
      await login(page);
      await page.goto(`${HERATIO_URL}/informationobject/add`);
      await expect(page.locator('body')).not.toContainText(/must log in|Sign in to/i, { timeout: 15000 });
    });

    await test.step('Browse Archival Descriptions (GLAM) with facets', async () => {
      await page.goto(`${HERATIO_URL}/glam/browse`);
      await expect(page.locator('body')).toContainText(/Narrow your results|Level of description|Creator|Repository/i, { timeout: 15000 });
      await page.waitForTimeout(1500);
    });

    await test.step('Add new - open the create form and fill required fields', async () => {
      await page.goto(`${HERATIO_URL}/informationobject/add`);
      await expect(page.locator('input[name="title"]')).toBeAttached({ timeout: 15000 });
      await expandAccordion(page, 'identity-collapse');
      const titleField = page.locator('input[name="title"]');
      await titleField.waitFor({ state: 'visible', timeout: 15000 });
      await titleField.fill(title);
      // Level of description is optional; pick one for a fuller demo if present.
      await page.selectOption('select[name="level_of_description_id"]', { index: 1 }).catch(() => {});
      await page.waitForTimeout(1000);
    });

    await test.step('Create the record', async () => {
      await page.locator('input[type="submit"][value="Create"]').first().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(title, { timeout: 15000 });
      recordUrl = page.url();
      await page.waitForTimeout(1500);
    });

    await test.step('Edit the record - change the title', async () => {
      await page.getByRole('link', { name: 'Edit', exact: true }).first().click();
      await page.waitForLoadState('networkidle');
      await expandAccordion(page, 'identity-collapse');
      const titleField = page.locator('input[name="title"]');
      await titleField.waitFor({ state: 'visible', timeout: 15000 });
      await titleField.fill(updatedTitle);
      await page.waitForTimeout(1000);
      await page.locator('input[type="submit"][value="Save"]').first().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(updatedTitle, { timeout: 15000 });
      await page.waitForTimeout(1500);
    });

    await test.step('Delete the record (confirm dialog)', async () => {
      // The archival-description delete is a form-submit button with a JS
      // confirm() dialog (not a link to a confirmation page). Auto-accept it.
      page.on('dialog', (d) => d.accept());
      await page.getByRole('button', { name: 'Delete', exact: true }).first().click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1200);
    });

    await test.step('Confirm the record is gone', async () => {
      await page.goto(recordUrl);
      await page.waitForTimeout(1200);
      await expect(page.locator('body')).not.toContainText(updatedTitle);
    });
  });
});
