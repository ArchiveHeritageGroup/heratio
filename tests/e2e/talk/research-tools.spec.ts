/**
 * The three Research Tools pages - #1481.
 *
 * Their views, tables and (for annotations) save routes all existed; none had
 * a route or controller action, so every link in the Research Tools sidebar
 * returned 404. This drives each page and, for Source Assessment, submits the
 * form and asserts the values survive a reload.
 *
 * Run:
 *   RESEARCH_OBJECT_ID=<io id> HERATIO_URL=http://localhost:8090 \
 *     npx playwright test tests/e2e/talk/research-tools.spec.ts --project=talk --workers=1
 */
import { test, expect } from '@playwright/test';
import { EMAIL, PASSWORD, HERATIO_URL } from '../demo/demo-helpers';

const OBJ = Number(process.env.RESEARCH_OBJECT_ID || 0);

test('the three Research Tools pages load, and Source Assessment saves', async ({ browser }) => {
  test.setTimeout(180000);
  expect(OBJ, 'RESEARCH_OBJECT_ID must be set').toBeGreaterThan(0);

  const ctx = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
  const page = await ctx.newPage();

  await page.goto(`${HERATIO_URL}/login`);
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[name="password"]', PASSWORD);
  await page.locator('input[name="password"]').press('Enter');
  await page.waitForURL((u) => !u.pathname.replace(/\/+$/, '').endsWith('/login'), { timeout: 20000 });

  // Each page must render its own heading - not a 404, and not a 500 whose
  // error page would also be "a page that loaded".
  for (const [path, heading] of [
    [`/research/source-assessment/${OBJ}`, 'Source Assessment'],
    [`/research/trust-score/${OBJ}`, 'Trust Score'],
    [`/research/annotation-studio/${OBJ}`, 'Annotation'],
  ] as const) {
    const res = await page.goto(`${HERATIO_URL}${path}`);
    expect(res?.status(), `${path} did not return 200`).toBe(200);
    await expect(page.locator('h1, h2').filter({ hasText: heading }).first(),
      `${path} did not render its heading`).toBeVisible({ timeout: 10000 });
  }

  // Source Assessment round trip.
  const stamp = String(Date.now()).slice(-6);
  const provenance = `Transferred from the provincial office ${stamp}.`;
  const authenticity = `Watermark consistent with the period ${stamp}.`;

  await page.goto(`${HERATIO_URL}/research/source-assessment/${OBJ}`);
  // Scope to the assessment form: the layout's sidebar carries submit buttons
  // of its own, and .first() on the page picks one of those.
  const form = page.locator('form').filter({ has: page.locator('[name="source_type"]') }).first();
  await expect(form).toBeVisible();
  await page.selectOption('[name="source_type"]', 'primary');
  await page.selectOption('[name="completeness"]', 'partial');
  await page.fill('[name="provenance"]', provenance);
  await page.fill('[name="authenticity_notes"]', authenticity);
  await page.fill('[name="reliability"]', '4');
  await page.selectOption('[name="bias"]', 'moderate');

  await Promise.all([
    page.waitForLoadState('networkidle'),
    form.locator('button[type="submit"]').first().click(),
  ]);

  await page.goto(`${HERATIO_URL}/research/source-assessment/${OBJ}`);
  await expect(page.locator('[name="provenance"]')).toHaveValue(provenance);
  await expect(page.locator('[name="authenticity_notes"]')).toHaveValue(authenticity);
  await expect(page.locator('[name="reliability"]')).toHaveValue('4');
  await expect(page.locator('[name="bias"]')).toHaveValue('moderate');
  await expect(page.locator('[name="source_type"]')).toHaveValue('primary');

  await ctx.close();
});
