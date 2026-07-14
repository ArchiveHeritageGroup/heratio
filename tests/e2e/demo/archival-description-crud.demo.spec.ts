/**
 * DEMO WALKTHROUGH - Archival Description (Information Object): full CRUD (narrated)
 *
 * Narrated video of the full lifecycle of an archival description: GLAM browse
 * -> Add new -> View -> Edit -> Delete. Non-prod only. Run with:
 *   HERATIO_URL=http://192.168.0.112:8090 npx playwright test --project=demo --workers=1
 * then: scripts/demo-narrate.sh
 */
import { test, expect } from '@playwright/test';
import { HERATIO_URL, isProd, login, expandAccordion, startNarration, narrate, writeNarration } from './demo-helpers';

const NAME = 'archival-description-crud';

test.describe('Demo: Archival Description - full CRUD', () => {
  test.skip(isProd, 'Demo CRUD must run against a non-prod target - set HERATIO_URL to the dev box.');

  test('Browse, create, view, edit and delete an archival description', async ({ page }) => {
    startNarration();
    const title = `Demo Fonds ${Date.now()}`;
    const updatedTitle = `${title} (edited)`;
    let recordUrl = '';

    await test.step('Log in', async () => {
      await narrate(page, 'In this walkthrough we will create, edit and delete an archival description in Heratio.', 3800);
      await login(page);
      await page.goto(`${HERATIO_URL}/informationobject/add`);
      await expect(page.locator('body')).not.toContainText(/must log in|Sign in to/i, { timeout: 15000 });
    });

    await test.step('Browse Archival Descriptions', async () => {
      await page.goto(`${HERATIO_URL}/glam/browse`);
      await expect(page.locator('body')).toContainText(/Narrow your results|Level of description|Creator|Repository/i, { timeout: 15000 });
      await narrate(page, 'This is the archival description browse. On the left you can narrow results by GLAM type, creator, place, subject, level of description and repository.', 6800);
    });

    await test.step('Add new', async () => {
      await narrate(page, 'To create a description, we open the Add new form.', 2600);
      await page.goto(`${HERATIO_URL}/informationobject/add`);
      await expect(page.locator('input[name="title"]')).toBeAttached({ timeout: 15000 });
      await expandAccordion(page, 'identity-collapse');
      const titleField = page.locator('input[name="title"]');
      await titleField.waitFor({ state: 'visible', timeout: 15000 });
      await narrate(page, 'In the Identity area we enter the title and choose the level of description.', 4000);
      await titleField.fill(title);
      await page.selectOption('select[name="level_of_description_id"]', { index: 1 }).catch(() => {});
    });

    await test.step('Create the record', async () => {
      await narrate(page, 'Now we save the description by clicking Create.', 2400);
      await page.locator('input[type="submit"][value="Create"]').first().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(title, { timeout: 15000 });
      recordUrl = page.url();
      await narrate(page, 'The new archival description has been created and is now displayed.', 3200);
    });

    await test.step('Edit the record', async () => {
      await narrate(page, 'Next we edit the description and change its title.', 2600);
      await page.getByRole('link', { name: 'Edit', exact: true }).first().click();
      await page.waitForLoadState('networkidle');
      await expandAccordion(page, 'identity-collapse');
      const titleField = page.locator('input[name="title"]');
      await titleField.waitFor({ state: 'visible', timeout: 15000 });
      await titleField.fill(updatedTitle);
      await page.locator('input[type="submit"][value="Save"]').first().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(updatedTitle, { timeout: 15000 });
      await narrate(page, 'The change has been saved.', 2000);
    });

    await test.step('Delete the record', async () => {
      await narrate(page, 'Finally, we delete the description and confirm.', 2600);
      page.on('dialog', (d) => d.accept());
      await page.getByRole('button', { name: 'Delete', exact: true }).first().click();
      await page.waitForLoadState('networkidle');
    });

    await test.step('Confirm gone', async () => {
      await page.goto(recordUrl);
      await expect(page.locator('body')).not.toContainText(updatedTitle);
      await narrate(page, 'The description has been removed. That completes the archival description lifecycle.', 3600);
    });

    writeNarration(NAME);
  });
});
