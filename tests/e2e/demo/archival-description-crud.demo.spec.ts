/**
 * DEMO WALKTHROUGH - Archival Description: full CRUD, FULL requirement set (narrated)
 *
 * Completes every ISAD(G) area (Identity, Context, Content & structure,
 * Conditions of access & use, Allied materials, Control) when creating a
 * description, then views, edits and deletes it. Non-prod only.
 */
import { test, expect } from '@playwright/test';
import { HERATIO_URL, isProd, expandAccordion, fillFields, startNarration, narrate, writeNarration } from './demo-helpers';

const NAME = 'archival-description-crud';

test.describe('Demo: Archival Description - full CRUD (complete record)', () => {
  test.skip(isProd, 'Demo CRUD must run against a non-prod target - set HERATIO_URL to the dev box.');

  test('Browse, create a complete description, view, edit and delete', async ({ page }) => {
    startNarration();
    const title = `Demo Fonds ${Date.now()}`;
    const updatedTitle = `${title} (edited)`;
    let recordUrl = '';

    await test.step('Browse Archival Descriptions', async () => {
      await narrate(page, 'In this walkthrough we create a complete archival description, filling every area, then view, edit and delete it.', 5400);
      await page.goto(`${HERATIO_URL}/glam/browse`);
      await expect(page.locator('body')).toContainText(/Narrow your results|Level of description|Creator|Repository/i, { timeout: 15000 });
      await narrate(page, 'This is the archival description browse, with facets for GLAM type, creator, place, subject, level and repository.', 6600);
    });

    await test.step('Open the Add new form', async () => {
      await narrate(page, 'We open the Add new form, which follows the ISAD General standard.', 3200);
      await page.goto(`${HERATIO_URL}/informationobject/add`);
      await expect(page.locator('input[name="title"]')).toBeAttached({ timeout: 15000 });
    });

    await test.step('Complete the Identity area', async () => {
      await narrate(page, 'The Identity area: reference code, title and level of description.', 4000);
      await expandAccordion(page, 'identity-collapse');
      await fillFields(page, { identifier: `DESC-${Date.now()}`, title, level_of_description_id: '' });
    });

    await test.step('Complete the Context area', async () => {
      await narrate(page, 'The Context area: the repository, archival history and immediate source of acquisition.', 5200);
      await expandAccordion(page, 'context-collapse');
      await fillFields(page, {
        repository_id: '',
        archival_history: 'Held by the creating body until transfer to the archive in 1978.',
        acquisition: 'Transferred under a deed of gift in 1978.',
      });
    });

    await test.step('Complete Content & structure', async () => {
      await narrate(page, 'Content and structure: scope and content, appraisal, accruals and system of arrangement.', 5600);
      await expandAccordion(page, 'content-collapse');
      await fillFields(page, {
        scope_and_content: 'Correspondence, minutes and reports documenting the body from 1901 to 1975.',
        appraisal: 'Retained in full; no appraisal destruction.',
        accruals: 'No further accruals expected.',
        arrangement: 'Arranged in six series by function.',
      });
    });

    await test.step('Complete Conditions of access & use', async () => {
      await narrate(page, 'Conditions of access and use: access and reproduction conditions, language, and physical characteristics.', 6000);
      await expandAccordion(page, 'conditions-collapse');
      await fillFields(page, {
        access_conditions: 'Open for research.',
        reproduction_conditions: 'Reproduction permitted for research and private study.',
        language_notes: 'English and Afrikaans.',
        physical_characteristics: 'Paper records in good condition.',
        finding_aids: 'Series-level finding aid available.',
      });
    });

    await test.step('Complete Allied materials & Control', async () => {
      await narrate(page, 'Allied materials and the Control area: related units, the description identifier and the rules applied.', 5600);
      await expandAccordion(page, 'allied-collapse');
      await fillFields(page, {
        location_of_originals: 'Originals held at the main repository.',
        related_units_of_description: 'See also the photographic collection.',
      });
      await expandAccordion(page, 'description-collapse');
      await fillFields(page, {
        description_identifier: `AD-${Date.now()}`,
        institution_responsible_identifier: 'ZA HAG',
        rules: 'ISAD(G), 2nd edition',
      });
    });

    await test.step('Create the record', async () => {
      await narrate(page, 'With every area complete, we save the description.', 2800);
      await page.locator('input[type="submit"][value="Create"]').first().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(title, { timeout: 15000 });
      recordUrl = page.url();
      await narrate(page, 'The complete archival description has been created and is displayed across all its areas.', 4200);
    });

    await test.step('Edit the record', async () => {
      await narrate(page, 'Next we edit the description and change its title.', 2800);
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
      await narrate(page, 'Finally we delete the description and confirm.', 2600);
      page.on('dialog', (d) => d.accept());
      await page.getByRole('button', { name: 'Delete', exact: true }).first().click();
      await page.waitForLoadState('networkidle');
    });

    await test.step('Confirm gone', async () => {
      await page.goto(recordUrl);
      await expect(page.locator('body')).not.toContainText(updatedTitle);
      await narrate(page, 'The description has been removed. That completes the full archival description lifecycle.', 4200);
    });

    writeNarration(NAME);
  });
});
