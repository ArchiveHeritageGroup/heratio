/**
 * DEMO WALKTHROUGH - Digital Asset (DAM): full CRUD, FULL requirement set (narrated)
 *
 * Creates a digital asset completing the descriptive, technical and IPTC/rights
 * fields, then views, edits and deletes it. Non-prod only.
 */
import { test, expect } from '@playwright/test';
import { HERATIO_URL, isProd, fillFields, startNarration, narrate, writeNarration } from './demo-helpers';

const NAME = 'dam-asset-crud';

test.describe('Demo: Digital Asset (DAM) - full CRUD (complete record)', () => {
  test.skip(isProd, 'Demo CRUD must run against a non-prod target - set HERATIO_URL to the dev box.');

  test('Browse, create a complete asset, view, edit and delete', async ({ page }) => {
    startNarration();
    const title = `Demo Asset ${Date.now()}`;
    const updatedTitle = `${title} (edited)`;
    let recordUrl = '';
    const saveBtn = () => page.locator('form:has(input[name="title"]) button[type="submit"]').first();

    await test.step('Browse the DAM', async () => {
      await narrate(page, 'In this walkthrough we create a complete digital asset in the Digital Asset Manager, then view, edit and delete it.', 5600);
      await page.goto(`${HERATIO_URL}/dam/browse`);
      await expect(page.locator('body')).toContainText(/digital asset|DAM|assets/i, { timeout: 15000 });
      await narrate(page, 'This is the Digital Asset Manager browse, showing the media assets in the repository.', 4600);
    });

    await test.step('Open the create form', async () => {
      await narrate(page, 'We open the create form, which captures descriptive, technical and rights metadata.', 4400);
      await page.goto(`${HERATIO_URL}/dam/create`);
      await page.locator('input[name="title"]').waitFor({ state: 'visible', timeout: 15000 });
    });

    await test.step('Complete the descriptive fields', async () => {
      await narrate(page, 'First the descriptive fields: reference, title, asset type, extent and genre.', 4800);
      await fillFields(page, {
        identifier: `DEMO-${Date.now()}`,
        title,
        asset_type: '',
        extent_and_medium: 'One digital image, JPEG',
        genre: 'Photograph',
        remarks: 'A demonstration digital asset created by the walkthrough.',
      });
    });

    await test.step('Complete the IPTC and rights fields', async () => {
      await narrate(page, 'Then the IPTC and rights metadata: caption, creator, copyright, and location.', 5200);
      await fillFields(page, {
        iptc_caption: 'Demonstration image for the Heratio walkthrough.',
        iptc_creator: 'Heratio Demo',
        iptc_copyright_notice: 'Public domain - demonstration only.',
        iptc_city: 'Cape Town',
        iptc_country: 'South Africa',
        iptc_artwork_title: 'Demo Asset',
        iptc_artwork_creator: 'Heratio Demo',
      });
    });

    await test.step('Create the asset', async () => {
      await narrate(page, 'With the metadata complete, we save the asset.', 2800);
      await saveBtn().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(title, { timeout: 15000 });
      recordUrl = page.url();
      await narrate(page, 'The complete digital asset has been created and is now displayed.', 3600);
    });

    await test.step('Edit the asset', async () => {
      await narrate(page, 'Next we edit the asset and change its title.', 2800);
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
      await narrate(page, 'Finally we delete the asset and confirm.', 2600);
      page.on('dialog', (d) => d.accept());
      await page.getByRole('button', { name: 'Delete', exact: true }).first().click();
      await page.waitForLoadState('networkidle');
    });

    await test.step('Confirm gone', async () => {
      await page.goto(recordUrl);
      await expect(page.locator('body')).not.toContainText(updatedTitle);
      await narrate(page, 'The asset has been removed. That completes the full digital asset lifecycle.', 4000);
    });

    writeNarration(NAME);
  });
});
