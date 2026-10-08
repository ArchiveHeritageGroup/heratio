/**
 * Accessibility scans (heratio#1532)
 *
 * axe-core checks the main public pages against WCAG 2.2 AA (axe tags
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
