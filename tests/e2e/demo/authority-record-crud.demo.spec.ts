/**
 * DEMO WALKTHROUGH - Authority Record: full CRUD (voice-narrated)
 *
 * Records a narrated screen video of the complete lifecycle of an authority
 * (agent) record: Browse -> Add new -> View -> Edit -> Delete. Part of the
 * "one video per function" how-to library. Non-prod only. Run with:
 *   HERATIO_URL=http://192.168.0.112:8090 npx playwright test --project=demo --workers=1
 * then: scripts/demo-narrate.sh  (synthesise the voiceover + mux to mp4)
 */
import { test, expect } from '@playwright/test';
import { HERATIO_URL, isProd, login, expandAccordion, startNarration, narrate, writeNarration } from './demo-helpers';

const NAME = 'authority-record-crud';

test.describe('Demo: Authority Record - full CRUD', () => {
  test.skip(isProd, 'Demo CRUD must run against a non-prod target - set HERATIO_URL to the dev box.');

  test('Browse, create, view, edit and delete an authority record', async ({ page }) => {
    startNarration();
    const uniqueName = `Demo Person ${Date.now()}`;
    const updatedName = `${uniqueName} (edited)`;
    let recordUrl = '';

    await test.step('Log in', async () => {
      await narrate(page, 'In this walkthrough we will create, edit and delete an authority record in Heratio.', 3800);
      await login(page);
      await page.goto(`${HERATIO_URL}/actor/add`);
      await expect(page.locator('body')).not.toContainText(/must log in|Sign in to/i, { timeout: 15000 });
    });

    await test.step('Browse Authority Records', async () => {
      await page.goto(`${HERATIO_URL}/actor/browse`);
      await expect(page.locator('body')).toContainText(/Entity type|Maintained by|authority/i, { timeout: 15000 });
      await narrate(page, 'This is the Authority Records browse. On the left you can narrow results by entity type, maintained by, and language.', 5200);
    });

    await test.step('Add new', async () => {
      await narrate(page, 'To create a new record, we open the Add new form.', 2600);
      await page.goto(`${HERATIO_URL}/actor/add`);
      await expect(page.locator('select[name="entity_type_id"]')).toBeAttached({ timeout: 15000 });
      await expandAccordion(page, 'identity-collapse');
      await expect(page.locator('select[name="entity_type_id"]')).toBeVisible({ timeout: 15000 });
    });

    await test.step('Fill required fields', async () => {
      await narrate(page, 'In the Identity area we choose the entity type and enter the authorized form of name.', 4200);
      await page.selectOption('select[name="entity_type_id"]', { label: 'Person' }).catch(async () => {
        await page.selectOption('select[name="entity_type_id"]', { index: 1 });
      });
      await page.fill('input[name="authorized_form_of_name"]', uniqueName);
    });

    await test.step('Create the record', async () => {
      await narrate(page, 'Now we save the record by clicking Create.', 2400);
      await page.locator('input[type="submit"][value="Create"]').first().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(uniqueName, { timeout: 15000 });
      recordUrl = page.url();
      await narrate(page, 'The new authority record has been created and is now displayed.', 3200);
    });

    await test.step('Edit the record', async () => {
      await narrate(page, 'Next we edit the record and change its name.', 2600);
      await page.getByRole('link', { name: 'Edit', exact: true }).first().click();
      await page.waitForLoadState('networkidle');
      await expandAccordion(page, 'identity-collapse');
      const nameField = page.locator('input[name="authorized_form_of_name"]');
      await nameField.waitFor({ state: 'visible', timeout: 15000 });
      await nameField.fill(updatedName);
      await page.locator('input[type="submit"][value="Save"]').first().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(updatedName, { timeout: 15000 });
      await narrate(page, 'The change has been saved.', 2000);
    });

    await test.step('Delete the record', async () => {
      await narrate(page, 'Finally, we delete the record and confirm.', 2600);
      await page.getByRole('link', { name: 'Delete', exact: true }).first().click();
      await page.waitForLoadState('networkidle');
      await page.locator('input[type="submit"][value="Delete"]').first().click();
      await page.waitForLoadState('networkidle');
    });

    await test.step('Confirm gone', async () => {
      await page.goto(recordUrl);
      await expect(page.locator('body')).not.toContainText(updatedName);
      await narrate(page, 'The record has been removed. That completes the authority record lifecycle.', 3600);
    });

    writeNarration(NAME);
  });
});
