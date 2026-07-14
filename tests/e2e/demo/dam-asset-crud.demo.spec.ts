/**
 * DEMO WALKTHROUGH - Digital Asset (DAM): full CRUD (narrated)
 *
 * Narrated video of a digital asset's lifecycle in the DAM: Browse -> Create ->
 * View -> Edit -> Delete. Non-prod only. Run with:
 *   HERATIO_URL=http://192.168.0.112:8090 npx playwright test --project=demo --workers=1
 * then: scripts/demo-narrate.py
 */
import { test, expect } from '@playwright/test';
import { HERATIO_URL, isProd, login, startNarration, narrate, writeNarration } from './demo-helpers';

const NAME = 'dam-asset-crud';

test.describe('Demo: Digital Asset (DAM) - full CRUD', () => {
  test.skip(isProd, 'Demo CRUD must run against a non-prod target - set HERATIO_URL to the dev box.');

  test('Browse, create, view, edit and delete a digital asset', async ({ page }) => {
    startNarration();
    const title = `Demo Asset ${Date.now()}`;
    const updatedTitle = `${title} (edited)`;
    let recordUrl = '';
    // The DAM create/edit form's Save button, scoped to the form that holds the
    // title field (so we never hit the chrome's clipboard/feedback buttons).
    const saveBtn = () => page.locator('form:has(input[name="title"]) button[type="submit"]').first();

    await test.step('Log in', async () => {
      await narrate(page, 'In this walkthrough we will create, edit and delete a digital asset in the Digital Asset Manager.', 4200);
      await login(page);
      await page.goto(`${HERATIO_URL}/dam/create`);
      await expect(page.locator('body')).not.toContainText(/must log in|Sign in to/i, { timeout: 15000 });
    });

    await test.step('Browse the DAM', async () => {
      await page.goto(`${HERATIO_URL}/dam/browse`);
      await expect(page.locator('body')).toContainText(/digital asset|DAM|assets/i, { timeout: 15000 });
      await narrate(page, 'This is the Digital Asset Manager browse, showing the media assets in the repository.', 4600);
    });

    await test.step('Create a digital asset', async () => {
      await narrate(page, 'To create an asset, we open the create form and enter the asset details.', 4000);
      await page.goto(`${HERATIO_URL}/dam/create`);
      await page.locator('input[name="title"]').waitFor({ state: 'visible', timeout: 15000 });
      await page.fill('input[name="title"]', title);
      await page.fill('input[name="identifier"]', `DEMO-${Date.now()}`).catch(() => {});
      await page.fill('[name="remarks"]', 'A demonstration digital asset created by the walkthrough.').catch(() => {});
      await narrate(page, 'Now we save the asset.', 1800);
      await saveBtn().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(title, { timeout: 15000 });
      recordUrl = page.url();
      await narrate(page, 'The new digital asset has been created and is now displayed.', 3200);
    });

    await test.step('Edit the asset', async () => {
      await narrate(page, 'Next we edit the asset and change its title.', 2600);
      await page.getByRole('link', { name: 'Edit', exact: true }).first().click();
      await page.waitForLoadState('networkidle');
      const titleField = page.locator('input[name="title"]');
      await titleField.waitFor({ state: 'visible', timeout: 15000 });
      await titleField.fill(updatedTitle);
      await saveBtn().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(updatedTitle, { timeout: 15000 });
      await narrate(page, 'The change has been saved.', 2000);
    });

    await test.step('Delete the asset', async () => {
      await narrate(page, 'Finally, we delete the asset and confirm.', 2600);
      page.on('dialog', (d) => d.accept());
      await page.getByRole('button', { name: 'Delete', exact: true }).first().click();
      await page.waitForLoadState('networkidle');
    });

    await test.step('Confirm gone', async () => {
      await page.goto(recordUrl);
      await expect(page.locator('body')).not.toContainText(updatedTitle);
      await narrate(page, 'The asset has been removed. That completes the digital asset lifecycle.', 3600);
    });

    writeNarration(NAME);
  });
});
