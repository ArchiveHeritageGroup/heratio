/**
 * DEMO WALKTHROUGH - Authority Record: full CRUD, FULL requirement set (narrated)
 *
 * Completes every ISAAR area (Identity, Description, Control) when creating an
 * authority record, then views, edits and deletes it. Non-prod only.
 *   HERATIO_URL=http://192.168.0.112:8090 npx playwright test --project=demo --workers=1 authority-record-crud
 * then: scripts/demo-narrate.py
 */
import { test, expect } from '@playwright/test';
import { HERATIO_URL, isProd, expandAccordion, fillFields, startNarration, narrate, writeNarration } from './demo-helpers';

const NAME = 'authority-record-crud';

test.describe('Demo: Authority Record - full CRUD (complete record)', () => {
  test.skip(isProd, 'Demo CRUD must run against a non-prod target - set HERATIO_URL to the dev box.');

  test('Browse, create a complete record, view, edit and delete', async ({ page }) => {
    startNarration();
    const uniqueName = `Demo Person ${Date.now()}`;
    const updatedName = `${uniqueName} (edited)`;
    let recordUrl = '';

    await test.step('Browse Authority Records', async () => {
      await narrate(page, 'In this walkthrough we create a complete authority record, filling every area, then view, edit and delete it.', 5400);
      await page.goto(`${HERATIO_URL}/actor/browse`);
      await expect(page.locator('body')).toContainText(/Entity type|Maintained by|authority/i, { timeout: 15000 });
      await narrate(page, 'This is the Authority Records browse, with facets for entity type, maintained by and language.', 5000);
    });

    await test.step('Open the Add new form', async () => {
      await narrate(page, 'We open the Add new form, which follows the ISAAR standard.', 3000);
      await page.goto(`${HERATIO_URL}/actor/add`);
      await expect(page.locator('select[name="entity_type_id"]')).toBeAttached({ timeout: 15000 });
    });

    await test.step('Complete the Identity area', async () => {
      await narrate(page, 'In the Identity area we choose the entity type and enter the authorized form of name.', 4600);
      await expandAccordion(page, 'identity-collapse');
      await page.selectOption('select[name="entity_type_id"]', { label: 'Person' }).catch(async () => {
        await page.selectOption('select[name="entity_type_id"]', { index: 1 });
      });
      await page.fill('input[name="authorized_form_of_name"]', uniqueName);
    });

    await test.step('Complete the Description area', async () => {
      await narrate(page, 'In the Description area we record dates of existence, history, places, legal status, functions and mandates.', 6800);
      await expandAccordion(page, 'description-collapse');
      await fillFields(page, {
        dates_of_existence: '1901 - 1975',
        history: 'Founded in 1901, the body operated for over seven decades before its dissolution.',
        places: 'Cape Town, South Africa',
        legal_status: 'Registered corporate body',
        functions: 'Records management and archival administration',
        mandates: 'Established under the National Archives Act',
        internal_structures: 'Governed by an executive council and standing committees',
      });
    });

    await test.step('Complete the Control area', async () => {
      await narrate(page, 'And in the Control area, the description identifier, the maintaining institution, and the rules applied.', 5600);
      await expandAccordion(page, 'control-collapse');
      await fillFields(page, {
        description_identifier: `AUTH-${Date.now()}`,
        institution_responsible_identifier: 'ZA HAG',
        rules: 'ISAAR(CPF), 2nd edition',
        description_status_id: '',
      });
    });

    await test.step('Create the record', async () => {
      await narrate(page, 'With every area complete, we save the record.', 2800);
      await page.locator('input[type="submit"][value="Create"]').first().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(uniqueName, { timeout: 15000 });
      recordUrl = page.url();
      await narrate(page, 'The complete authority record has been created and is displayed across all its areas.', 4200);
    });

    await test.step('Edit the record', async () => {
      await narrate(page, 'Next we edit the record and change the authorized form of name.', 3000);
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
      await narrate(page, 'Finally we delete the record and confirm.', 2600);
      await page.getByRole('link', { name: 'Delete', exact: true }).first().click();
      await page.waitForLoadState('networkidle');
      await page.locator('input[type="submit"][value="Delete"]').first().click();
      await page.waitForLoadState('networkidle');
    });

    await test.step('Confirm gone', async () => {
      await page.goto(recordUrl);
      await expect(page.locator('body')).not.toContainText(updatedName);
      await narrate(page, 'The record has been removed. That completes the full authority record lifecycle.', 4000);
    });

    writeNarration(NAME);
  });
});
