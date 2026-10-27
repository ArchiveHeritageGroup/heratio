/**
 * Accessibility scans (heratio#1532)
 *
 * axe-core checks the main public and staff pages against WCAG 2.2 AA (axe tags
 * wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa). The run FAILS on a
 * REGRESSION: a critical or serious rule that a11y-baseline.json does not list
 * for the page, or more failing nodes than it lists. Every violation is
 * printed, so the baseline can be lowered as fixes land.
 *
 * Self-assessment only: automated tools find roughly a third of WCAG
 * failures. A public conformance claim still needs a manual / external audit.
 */

import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import * as fs from 'fs';
import * as path from 'path';

const HERATIO_URL = process.env.HERATIO_URL || 'https://heratio.theahg.co.za';
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const BLOCKING = ['critical', 'serious'];
const BASELINE: Record<string, Record<string, number>> = JSON.parse(
  fs.readFileSync(path.resolve(process.cwd(), 'tests/e2e/05-ui-components/a11y-baseline.json'), 'utf8')
);

// Staff pages, scanned signed in as the test administrator. They carry the
// most varied markup (forms, tables, badges, admin cards), which is where the
// staff-side failures of 27 Oct 2026 were.
const STAFF_PAGES = [
  { name: 'staff browse', path: '/glam/browse' },
  { name: 'staff authority browse', path: '/actor/browse' },
  { name: 'staff settings', path: '/admin/settings' },
  { name: 'staff export', path: '/export' },
  { name: 'staff data migration', path: '/admin/data-migration' },
  { name: 'staff error log', path: '/admin/errorLog' },
  { name: 'staff dropdowns', path: '/admin/dropdowns' },
];

const PAGES = [
  { name: 'home', path: '/' },
  { name: 'browse', path: '/glam/browse' },
  { name: 'login', path: '/login' },
  { name: 'accessibility statement', path: '/accessibility-statement' },
];

async function scan(page, name: string) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
  for (const v of results.violations) {
    console.log(`[a11y] ${name}: ${v.impact} ${v.id} (${v.nodes.length}) ${v.helpUrl}`);
  }
  const known = BASELINE[name] || {};
  return results.violations
    .filter((v) => BLOCKING.includes(v.impact || ''))
    .filter((v) => v.nodes.length > (known[v.id] ?? 0))
    .map((v) => `${v.impact} ${v.id}: ${v.nodes.length} node(s), baseline ${known[v.id] ?? 0} - ${v.help}`);
}

test.describe('Accessibility (WCAG 2.2 AA, automated)', () => {
  for (const p of PAGES) {
    test(`${p.name} has no new critical or serious violations`, async ({ page }) => {
      await page.goto(`${HERATIO_URL}${p.path}`, { waitUntil: 'domcontentloaded' });
      expect(await scan(page, p.name)).toEqual([]);
    });
  }

  test('a published record page has no new critical or serious violations', async ({ page }) => {
    await page.goto(`${HERATIO_URL}/glam/browse`, { waitUntil: 'domcontentloaded' });
    const first = page.locator('a[href*="/"]').filter({ hasText: /\S/ }).locator('xpath=ancestor-or-self::a[contains(@class,"record") or ancestor::*[contains(@class,"browse")]]').first();
    const href = await first.getAttribute('href').catch(() => null);
    test.skip(!href, 'no published record on this instance');
    await page.goto(href!.startsWith('http') ? href! : `${HERATIO_URL}${href}`, { waitUntil: 'domcontentloaded' });
    expect(await scan(page, 'record')).toEqual([]);
  });
});

test.describe('Accessibility (WCAG 2.2 AA, automated), staff pages', () => {
  test.beforeEach(async ({ page }) => {
    // The test administrator exists on the CI host; anywhere else, skip rather
    // than report a login failure as an accessibility one. Credentials are read
    // with fs (as the baseline is): importing the JSON fails under ESM.
    const roles = JSON.parse(fs.readFileSync(path.resolve(process.cwd(), 'tests/e2e/fixtures/role-credentials.json'), 'utf8')).roles;
    const admin = { email: process.env.TEST_ADMIN_EMAIL || roles.admin.email, password: process.env.TEST_ADMIN_PASSWORD || roles.admin.password };
    await page.goto(`${HERATIO_URL}/login`, { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="email"], input[type="email"]').first().fill(admin.email);
    await page.locator('input[name="password"]').first().fill(admin.password);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('domcontentloaded');
    // Signed in means a way to sign out, not merely "not on /login": a failed
    // login that lands elsewhere would scan the public pages instead.
    const signedIn = await page.locator('a[href*="logout"], form[action*="logout"]').count();
    test.skip(signedIn === 0, 'test administrator could not sign in on this host');
  });

  for (const p of STAFF_PAGES) {
    test(`${p.name} has no new critical or serious violations`, async ({ page }) => {
      await page.goto(`${HERATIO_URL}${p.path}`, { waitUntil: 'domcontentloaded' });
      expect(await scan(page, p.name)).toEqual([]);
    });
  }
});
