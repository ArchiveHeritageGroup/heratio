/**
 * Combined Discovery Suite
 * Runs PSIS and Heratio crawlers together in one test run.
 * 
 * Run: npx playwright test tests/e2e/00-discovery/all-discovery.spec.ts
 */

import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

// Load fixtures
const fixturesDir = path.join(path.dirname(new URL(import.meta.url).pathname), '../fixtures');
const seedUrls = JSON.parse(fs.readFileSync(path.join(fixturesDir, 'seed-urls.json'), 'utf-8'));
const credentials = JSON.parse(fs.readFileSync(path.join(fixturesDir, 'role-credentials.json'), 'utf-8'));

const PSIS_BASE = process.env.PSID_URL || 'https://psis.theahg.co.za';
const HERATIO_BASE = process.env.HERATIO_URL || 'https://heratio.theahg.co.za';
const OUTPUT_DIR = process.env.OUTPUT_DIR || 'tests/e2e/reports/combined';

test.describe('Combined Discovery Suite', () => {
  
  test.beforeAll(() => {
    if (!fs.existsSync(OUTPUT_DIR)) {
      fs.mkdirSync(OUTPUT_DIR, { recursive: true });
    }
    if (!fs.existsSync(path.join(OUTPUT_DIR, 'psis'))) {
      fs.mkdirSync(path.join(OUTPUT_DIR, 'psis'), { recursive: true });
    }
    if (!fs.existsSync(path.join(OUTPUT_DIR, 'heratio'))) {
      fs.mkdirSync(path.join(OUTPUT_DIR, 'heratio'), { recursive: true });
    }
  });

  /**
   * Run both PSIS and Heratio guest crawls in parallel
   */
  test('crawl both systems as guest', async ({ browser }) => {
    const results: any = {};
    
    // Crawl PSIS as guest
    const psisPage = await browser.newPage();
    const psisResults = await crawlUrls(psisPage, seedUrls.psis.urls.map(u => PSIS_BASE + u), 'psis-guest');
    results.psisGuest = psisResults;
    fs.writeFileSync(path.join(OUTPUT_DIR, 'psis/guest-crawl.json'), JSON.stringify(psisResults, null, 2));
    await psisPage.close();
    
    // Crawl Heratio as guest
    const heratioPage = await browser.newPage();
    const heratioResults = await crawlUrls(heratioPage, seedUrls.heratio.urls.map(u => HERATIO_BASE + u), 'heratio-guest');
    results.heratioGuest = heratioResults;
    fs.writeFileSync(path.join(OUTPUT_DIR, 'heratio/guest-crawl.json'), JSON.stringify(heratioResults, null, 2));
    await heratioPage.close();
    
    // Log summary
    console.log('\n=== GUEST CRAWL SUMMARY ===');
    console.log(`PSIS: ${results.psisGuest.length} pages`);
    console.log(`Heratio: ${results.heratioGuest.length} pages`);
    
    expect(results.psisGuest.length).toBeGreaterThan(0);
    expect(results.heratioGuest.length).toBeGreaterThan(0);
  });

  /**
   * Run PSIS admin crawl
   */
  test('crawl PSIS as admin', async ({ page }) => {
    // Login to PSIS
    await page.goto(PSIS_BASE + '/user/login');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    
    const emailInput = page.locator('input:visible[name="_username"], input:visible[name="email"]').first();
    const passwordInput = page.locator('input:visible[name="_password"], input:visible[name="password"]').first();
    
    await emailInput.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
    await emailInput.fill(credentials.roles.admin.email);
    await passwordInput.fill(credentials.roles.admin.password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/(dashboard|home|admin|search)/, { timeout: 15000 }).catch(() => {});
    
    const results = await crawlUrls(page, seedUrls.psis.urls.map(u => PSIS_BASE + u), 'psis-admin');
    fs.writeFileSync(path.join(OUTPUT_DIR, 'psis/admin-crawl.json'), JSON.stringify(results, null, 2));
    
    console.log(`\n=== PSIS ADMIN: ${results.length} pages ===`);
    expect(results.length).toBeGreaterThan(0);
  });

  /**
   * Run Heratio admin crawl
   */
  test('crawl Heratio as admin', async ({ page }) => {
    // Login to Heratio
    await page.goto(HERATIO_BASE + '/login');
    await page.waitForLoadState('networkidle');
    
    const emailInput = page.locator('input[name="email"]').first();
    const passwordInput = page.locator('input[name="password"]').first();
    
    await emailInput.waitFor({ state: 'attached', timeout: 5000 }).catch(() => {});
    await emailInput.fill(credentials.roles.admin.email, { force: true });
    await passwordInput.fill(credentials.roles.admin.password, { force: true });
    
    const submitBtn = page.locator('button[type="submit"], input[type="submit"]').first();
    await submitBtn.click({ force: true });
    await page.waitForURL(/\/(dashboard|home|admin)/, { timeout: 15000 }).catch(() => {});
    
    const results = await crawlUrls(page, seedUrls.heratio.urls.map(u => HERATIO_BASE + u), 'heratio-admin');
    fs.writeFileSync(path.join(OUTPUT_DIR, 'heratio/admin-crawl.json'), JSON.stringify(results, null, 2));
    
    console.log(`\n=== HERATIO ADMIN: ${results.length} pages ===`);
    expect(results.length).toBeGreaterThan(0);
  });

  /**
   * Generate combined parity report
   */
  test('generate combined parity report', async () => {
    const psisGuest = JSON.parse(fs.readFileSync(path.join(OUTPUT_DIR, 'psis/guest-crawl.json'), 'utf-8'));
    const heratioGuest = JSON.parse(fs.readFileSync(path.join(OUTPUT_DIR, 'heratio/guest-crawl.json'), 'utf-8'));
    
    const report = {
      generatedAt: new Date().toISOString(),
      psis: {
        pages: psisGuest.length,
        forms: psisGuest.reduce((sum: number, r: any) => sum + r.forms.length, 0),
        buttons: psisGuest.reduce((sum: number, r: any) => sum + r.buttons.length, 0),
        okCount: psisGuest.filter((r: any) => r.status === 200).length
      },
      heratio: {
        pages: heratioGuest.length,
        forms: heratioGuest.reduce((sum: number, r: any) => sum + r.forms.length, 0),
        buttons: heratioGuest.reduce((sum: number, r: any) => sum + r.buttons.length, 0),
        okCount: heratioGuest.filter((r: any) => r.status === 200).length
      },
      comparison: {
        formsDiff: heratioGuest.reduce((sum: number, r: any) => sum + r.forms.length, 0) - 
                  psisGuest.reduce((sum: number, r: any) => sum + r.forms.length, 0),
        buttonsDiff: heratioGuest.reduce((sum: number, r: any) => sum + r.buttons.length, 0) -
                    psisGuest.reduce((sum: number, r: any) => sum + r.buttons.length, 0)
      }
    };
    
    fs.writeFileSync(path.join(OUTPUT_DIR, 'parity-report.json'), JSON.stringify(report, null, 2));
    
    console.log('\n=== PARITY REPORT ===');
    console.log(`PSIS: ${report.psis.pages} pages, ${report.psis.forms} forms, ${report.psis.buttons} buttons`);
    console.log(`Heratio: ${report.heratio.pages} pages, ${report.heratio.forms} forms, ${report.heratio.buttons} buttons`);
    console.log(`Forms diff: ${report.comparison.formsDiff > 0 ? '+' : ''}${report.comparison.formsDiff}`);
    console.log(`Buttons diff: ${report.comparison.buttonsDiff > 0 ? '+' : ''}${report.comparison.buttonsDiff}`);
    
    expect(report.psis.pages).toBeGreaterThan(0);
    expect(report.heratio.pages).toBeGreaterThan(0);
  });
});

/**
 * Helper: Crawl URLs and return results
 */
async function crawlUrls(page: any, urls: string[], prefix: string): Promise<any[]> {
  const results: any[] = [];
  const maxPages = 5;
  
  for (const url of urls.slice(0, maxPages)) {
    try {
      const response = await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 });
      const title = await page.title();
      const forms = await page.locator('form').count();
      const buttons = await page.locator('button, input[type="submit"]').count();
      
      results.push({
        url,
        status: response?.status() || 0,
        title,
        forms,
        buttons
      });
      console.log(`[${prefix}] ${response?.status()} ${url}`);
    } catch (e: any) {
      results.push({ url, status: 0, error: e.message });
      console.log(`[${prefix}] FAIL ${url}`);
    }
  }
  
  return results;
}
