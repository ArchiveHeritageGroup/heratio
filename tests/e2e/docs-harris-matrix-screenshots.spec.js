import { test, expect } from '@playwright/test';
import path from 'path';

const BASE = process.env.HERATIO_URL || 'https://heratio.org';
const OUT  = process.env.SHOT_DIR;
const EMAIL = process.env.SHOT_EMAIL;
const PASS  = process.env.SHOT_PASS;

// Blaauwbosch = the correctly recorded site; TEACH = the deliberate-problems site.
const SITE_OK = 2;
const SITE_BAD = 3;

test.use({ viewport: { width: 1440, height: 1000 } });

async function login(page) {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  // The page carries several forms (search, clipboard, feedback); scope to the
  // login form or the submit click lands on the wrong one and silently no-ops.
  // Several forms post to /login; pick the one that actually HAS the fields.
  const form = page.locator('form:has(input[name="password"])').first();
  await form.locator('input[name="email"]').first().fill(EMAIL);
  await form.locator('input[name="password"]').first().fill(PASS);
  await form.locator('button[type="submit"], input[type="submit"]').first().click();
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(1500);
}

async function shot(page, url, name, opts = {}) {
  const res = await page.goto(`${BASE}${url}`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(opts.wait || 1200);
  const status = res ? res.status() : 0;
  const title = await page.title();
  await page.screenshot({ path: path.join(OUT, `${name}.png`), fullPage: opts.full !== false });
  console.log(`SHOT ${name}  status=${status}  url=${page.url()}  title="${title}"`);
  return { status, url: page.url(), title };
}

test('capture harris matrix manual screenshots', async ({ page }) => {
  await login(page);
  // prove we are actually authenticated, not looking at a login page
  await page.goto(`${BASE}/archaeology`, { waitUntil: 'domcontentloaded' });
  const url = page.url();
  const title = await page.title();
  console.log(`AUTH-CHECK url=${url} title="${title}"`);
  // Fail loudly rather than quietly screenshotting the login page nine times.
  expect(url, 'login did not take - still on the login page').not.toContain('/login');
  expect(title, 'login did not take - title still says Log in').not.toContain('Log in');

  await shot(page, '/archaeology', '01-archaeology-dashboard');
  await shot(page, '/archaeology/sites', '02-sites-list');
  await shot(page, `/archaeology/site/${SITE_OK}`, '03-site-blaauwbosch');
  await shot(page, `/archaeology/site/${SITE_OK}/contexts`, '04-stratigraphy-contexts');
  await shot(page, `/archaeology/harris/site/${SITE_OK}/report`, '05-consistency-report-clean');
  await shot(page, `/archaeology/harris/site/${SITE_BAD}/report`, '06-consistency-report-findings');
  await shot(page, `/archaeology/harris/site/${SITE_OK}/import-lst`, '07-import-lst');
  await shot(page, `/archaeology/harris/site/${SITE_OK}/import-relationships`, '08-import-relationships');
  
  // A real preview: upload a deliberately flawed relationship CSV so the manual
  // can show what refusals actually look like, rather than an empty form.
  const fs = await import('fs');
  const os = await import('os');
  const tmp = path.join(os.tmpdir(), 'harris-demo-relationships.csv');
  fs.writeFileSync(tmp, [
    'siteCode,sourceID,stratRelationship,targetID',
    'BLB-2026,1005,overlies,1010',
    'BLB-2026,1001,contemporary with,1005',
    'BLB-2026,1001,above,9999',
    'BLB-2026,1001,frobnicates,1002',
    'OTHER-DIG,1,above,2',
    'BLB-2026,1002,above,1001',
    '',
  ].join('\n'));

  await page.goto(`${BASE}/archaeology/harris/site/${SITE_OK}/import-relationships`, { waitUntil: 'domcontentloaded' });
  // Scope to the upload form. A bare button[type=submit] click lands on the
  // page's SEARCH form and navigates to /glam/browse instead.
  const upload = page.locator('form:has(input[type="file"])').first();
  await upload.locator('input[type="file"]').setInputFiles(tmp);
  await upload.locator('button[type="submit"]').first().click();
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(1200);
  await page.screenshot({ path: path.join(OUT, '09-import-relationships-preview.png'), fullPage: true });
  console.log(`SHOT 09-import-relationships-preview  url=${page.url()}`);

  await shot(page, `/archaeology/site/${SITE_OK}/contexts/import`, '10-import-contexts-csv');

  // one context record + its printable sheet
  await page.goto(`${BASE}/archaeology/site/${SITE_OK}/contexts`, { waitUntil: 'domcontentloaded' });
  // Must be a real context id - a[href*="/context/"] also matches /context/create.
  const hrefs = await page.locator('a[href*="/archaeology/context/"]').evaluateAll(
    (as) => as.map((a) => a.getAttribute('href'))
  );
  const ctxHref = hrefs.find((h) => /\/archaeology\/context\/\d+(?:$|[?#])/.test(h || '')) || null;
  const id = ctxHref ? (ctxHref.match(/context\/(\d+)/) || [])[1] : null;
  console.log(`context link found: ${ctxHref}`);
  if (id) {
    await shot(page, `/archaeology/context/${id}`, '11-context-detail');
  }
});
