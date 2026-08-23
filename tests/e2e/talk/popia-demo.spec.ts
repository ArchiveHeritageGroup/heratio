/**
 * Screenshots for the "Trust by Design" talk (NARSSA / UNISA / SASA, 27 Aug 2026).
 *
 * Every screen is captured TWICE - once as an authenticated operator and once
 * as an anonymous visitor - because the contrast is the control. A slide that
 * shows only the staff view proves nothing about what the public can reach.
 *
 * Runs over the clearly-labelled synthetic records from
 * `php artisan ahg:privacy-seed-demo-pii`. Every identity number in them uses
 * citizenship digit 2, which South Africa never issues, so nothing here can
 * identify a living person even if a slide escapes the room.
 *
 * Run:
 *   HERATIO_URL=http://localhost:8090 npx playwright test tests/e2e/talk --project=talk --workers=1
 */
import { test } from '@playwright/test';
import type { Browser, Page } from '@playwright/test';
import { EMAIL, PASSWORD, HERATIO_URL } from '../demo/demo-helpers';
import * as fs from 'fs';

const OUT = 'test-results/talk';
const IDS = { hospital: 1275116, indigent: 1275118, personnel: 1275120, medical: 1275122 };
const VIEWPORT = { width: 1600, height: 1000 };

/** The pages worth putting on a slide, in narrative order. */
const TARGETS: Array<{ name: string; path: string; note: string }> = [
  { name: '01-record-with-pii',   path: `/admin/privacy/pii-scan-object?id=${IDS.hospital}`,
    note: 'Health record: names, ID numbers, address, clinical detail' },
  { name: '02-confidence-split',  path: `/admin/privacy/pii-scan-object?id=${IDS.indigent}`,
    note: 'Two ID-shaped strings, 0.90 and 0.30 - the same pattern routed differently' },
  { name: '03-special-category',  path: `/admin/privacy/pii-scan-object?id=${IDS.medical}`,
    note: 'Oncology diagnosis - POPIA special-category data' },
  { name: '04-payment-data',      path: `/admin/privacy/pii-scan-object?id=${IDS.personnel}`,
    note: 'Card number detected at 0.95 via Luhn' },
  { name: '05-pii-scan-overview', path: '/admin/privacy/pii-scan',
    note: 'Corpus-level PII picture' },
  { name: '06-review-queue',      path: '/admin/privacy/pii-review',
    note: 'AI proposes; a person decides' },
  { name: '07-redaction-panel',   path: `/admin/privacy/description/${IDS.hospital}/redaction`,
    note: 'Per-description redaction profile' },
  { name: '08-privacy-dashboard', path: '/admin/privacy/dashboard',
    note: 'POPIA compliance surface' },
  { name: '09-privacy-index',     path: '/admin/privacy/index',
    note: 'The surrounding controls' },
];

/**
 * Capture the content region rather than the whole page.
 *
 * A fullPage shot of a Heratio screen is mostly browser chrome, global nav and
 * a tall standards footer; dropped on a 16:9 slide the part that matters ends
 * up unreadable from the third row. Screenshotting the content container gives
 * a dense image that fills the frame. Falls back to fullPage where no container
 * matches - a login redirect, for instance.
 */
async function capture(page: Page, path: string, file: string) {
  await page.goto(`${HERATIO_URL}${path}`, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(900);

  const target = page.locator('.container-fluid.py-4, .container.py-4, main > .container-fluid, main > .container, main .card').first();
  const file_path = `${OUT}/${file}.png`;

  if ((await target.count()) > 0) {
    await target.screenshot({ path: file_path }).catch(async () => {
      await page.screenshot({ path: file_path, fullPage: true });
    });
  } else {
    await page.screenshot({ path: file_path, fullPage: true });
  }

  return page.url();
}

test.describe.configure({ mode: 'serial' });

test('capture every screen as operator and as anonymous visitor', async ({ browser }) => {
  test.setTimeout(300000);
  fs.mkdirSync(OUT, { recursive: true });

  // --- authenticated operator ---
  const adminCtx = await browser.newContext({ viewport: VIEWPORT });
  const admin = await adminCtx.newPage();
  await admin.goto(`${HERATIO_URL}/login`);
  await admin.fill('input[name="email"]', EMAIL);
  await admin.fill('input[name="password"]', PASSWORD);
  await admin.locator('input[name="password"]').press('Enter');
  await admin.waitForURL((u) => !u.pathname.replace(/\/+$/, '').endsWith('/login'), { timeout: 20000 });

  // --- anonymous visitor: a separate context, never logged in ---
  const anonCtx = await browser.newContext({ viewport: VIEWPORT });
  const anon = await anonCtx.newPage();

  const rows: string[] = [];
  for (const t of TARGETS) {
    const adminUrl = await capture(admin, t.path, `${t.name}-admin`);
    const anonUrl = await capture(anon, t.path, `${t.name}-anon`);
    // Where anonymous lands matters more than what it renders: a redirect to
    // /login is the access control doing its job, and is worth stating on the
    // slide rather than leaving the audience to infer it.
    const blocked = /\/login/.test(anonUrl);
    rows.push(`${t.name}\t${t.note}\tanon:${blocked ? 'REDIRECTED-TO-LOGIN' : anonUrl}`);
    console.log(`  ${t.name}  anon -> ${blocked ? 'login (blocked)' : anonUrl}`);
  }

  // The public record view, for the draft-gating contrast.
  await capture(admin, `/index.php/informationobject/browse?query=SYNTHETIC`, '10-browse-admin');
  await capture(anon, `/index.php/informationobject/browse?query=SYNTHETIC`, '10-browse-anon');

  fs.writeFileSync(`${OUT}/manifest.tsv`, rows.join('\n') + '\n');
  await adminCtx.close();
  await anonCtx.close();
});
