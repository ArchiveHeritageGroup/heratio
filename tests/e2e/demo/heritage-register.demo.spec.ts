/**
 * DEMO WALKTHROUGH - Heritage Assets accounting register (IPSAS). Non-prod only.
 *
 * The heritage-accounting store() is a stub (no persistence yet), so this is a
 * guided tour of the register and the IPSAS capture form rather than a full CRUD.
 */
import { test, expect } from '@playwright/test';
import { HERATIO_URL, isProd, startNarration, narrate, writeNarration } from './demo-helpers';

const NAME = 'heritage-register';

test.describe('Demo: Heritage Assets register (IPSAS)', () => {
  test.skip(isProd, 'Demo must run against a non-prod target - set HERATIO_URL to the dev box.');

  test('Tour the heritage accounting register and IPSAS capture form', async ({ page }) => {
    startNarration();

    await test.step('Open the heritage accounting register', async () => {
      await narrate(page, 'Heratio keeps a heritage assets register for public-sector IPSAS accounting.', 4600);
      await page.goto(`${HERATIO_URL}/heritage/accounting/browse`);
      await page.waitForLoadState('networkidle').catch(() => {});
      await expect(page.locator('body')).toContainText(/Heritage|Asset|Valuation|Register|IPSAS/i, { timeout: 15000 });
      await narrate(page, 'The register lists heritage assets with their valuations, impairments and movements.', 5200);
    });

    await test.step('Open the IPSAS capture form', async () => {
      await narrate(page, 'The capture form records an asset against the IPSAS heritage-accounting fields.', 4800);
      await page.goto(`${HERATIO_URL}/heritage/accounting/add`);
      await page.waitForLoadState('networkidle').catch(() => {});
      await expect(page.locator('form, input, select').first()).toBeVisible({ timeout: 15000 });
      await narrate(page, 'These cover identification, recognition, measurement basis, valuation and impairment.', 5400);
      await page.evaluate(() => window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }));
      await page.waitForTimeout(2500);
      await narrate(page, 'That is the heritage assets accounting register in Heratio.', 3200);
    });

    writeNarration(NAME, 'Heritage Assets Register');
  });
});
