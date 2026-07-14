/**
 * DEMO SCENARIO - "Archival Description with digital object" (narrated)
 *
 * Creates an archival description, links a digital object to it (uploading an
 * image), then views the object through the IIIF viewers: Deep Zoom -> Mirador
 * -> Image -> back to Mirador. Non-prod only. Run with:
 *   HERATIO_URL=http://192.168.0.112:8090 npx playwright test --project=demo --workers=1 archival-description-with-do
 * then: scripts/demo-narrate.py  ->  "Archival Description with digital object.mp4/.wav"
 */
import { test, expect } from '@playwright/test';
import * as path from 'path';
import { HERATIO_URL, isProd, expandAccordion, startNarration, narrate, writeNarration } from './demo-helpers';

const NAME = 'archival-description-with-do';
const DISPLAY = 'Archival Description with digital object';
const IMAGE = path.join(path.dirname(new URL(import.meta.url).pathname), 'fixtures', 'demo-asset.jpg');

test.describe('Demo: Archival Description with digital object', () => {
  test.skip(isProd, 'Demo CRUD must run against a non-prod target - set HERATIO_URL to the dev box.');

  test('Create a description, link a digital object, and switch IIIF viewers', async ({ page }) => {
    startNarration();
    const title = `Demo DO Fonds ${Date.now()}`;
    let slug = '';

    await test.step('Start', async () => {
      await narrate(page, 'In this scenario we create an archival description, link a digital object, and view it through the different viewers.', 5200);
      await page.goto(`${HERATIO_URL}/informationobject/add`);
      await expect(page.locator('body')).not.toContainText(/must log in|Sign in to/i, { timeout: 15000 });
    });

    await test.step('Create the archival description', async () => {
      await narrate(page, 'First we create the archival description with a title.', 3000);
      await expect(page.locator('input[name="title"]')).toBeAttached({ timeout: 15000 });
      await expandAccordion(page, 'identity-collapse');
      await page.locator('input[name="title"]').fill(title);
      await page.selectOption('select[name="level_of_description_id"]', { index: 1 }).catch(() => {});
      await page.locator('input[type="submit"][value="Create"]').first().click();
      await page.waitForLoadState('networkidle');
      await expect(page.locator('body')).toContainText(title, { timeout: 15000 });
      slug = new URL(page.url()).pathname.split('/').filter(Boolean).pop() || '';
    });

    await test.step('Link a digital object (upload an image)', async () => {
      await narrate(page, 'Now we link a digital object by uploading an image and completing the fields.', 4400);
      await page.goto(`${HERATIO_URL}/${slug}/object/addDigitalObject`);
      await page.locator('input[name="digital_object"]').setInputFiles(IMAGE);
      // fill any visible optional text fields for completeness
      for (const [name, val] of [['external_name', 'Demo asset'], ['caption', 'Demonstration image']]) {
        await page.fill(`[name="${name}"]`, val).catch(() => {});
      }
      await narrate(page, 'Then we save the digital object.', 2000);
      await page.locator('input[type="submit"][value="Create"]').first().click();
      await page.waitForLoadState('networkidle');
    });

    await test.step('View the digital object', async () => {
      await page.goto(`${HERATIO_URL}/${slug}`);
      await page.waitForLoadState('networkidle');
      await narrate(page, 'The digital object is now attached to the record and shown in the viewer.', 4000);
    });

    await test.step('Switch to Deep Zoom', async () => {
      await narrate(page, 'We can switch the viewer to Deep Zoom for high-resolution panning and zooming.', 4600);
      await page.locator('[id^="btn-osd-"]').first().click({ timeout: 10000 }).catch(() => {});
      await page.waitForTimeout(2500);
    });

    await test.step('Switch to Mirador', async () => {
      await narrate(page, 'Next, the Mirador viewer, the standard I I I F workspace.', 3800);
      await page.locator('[id^="btn-mirador-"]').first().click({ timeout: 10000 }).catch(() => {});
      await page.waitForTimeout(2500);
    });

    await test.step('Switch to Image', async () => {
      await narrate(page, 'And the simple image view.', 2200);
      await page.locator('[id^="btn-img-"]').first().click({ timeout: 10000 }).catch(() => {});
      await page.waitForTimeout(2500);
    });

    await test.step('Back to Mirador', async () => {
      await narrate(page, 'Finally we return to the Mirador viewer. That completes the digital object scenario.', 4600);
      await page.locator('[id^="btn-mirador-"]').first().click({ timeout: 10000 }).catch(() => {});
      await page.waitForTimeout(2500);
    });

    writeNarration(NAME, DISPLAY);
  });
});
