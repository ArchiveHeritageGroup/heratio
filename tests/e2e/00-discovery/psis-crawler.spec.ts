/**
 * PSIS (AtoM) Discovery Crawler
 * 
 * Layer A: Discovers pages, links, forms, buttons on the baseline PSIS system.
 * This provides the ground truth of what functionality exists in the original system.
 * 
 * Run: npx playwright test tests/e2e/00-discovery/psis-crawler.spec.ts
 */

import { test, expect, Page, Response } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

// Load fixtures
const seedUrls = JSON.parse(fs.readFileSync(path.join(path.dirname(new URL(import.meta.url).pathname), '../fixtures/seed-urls.json'), 'utf-8'));
const credentials = JSON.parse(fs.readFileSync(path.join(path.dirname(new URL(import.meta.url).pathname), '../fixtures/role-credentials.json'), 'utf-8'));

interface CrawlResult {
  url: string;
  finalUrl: string;
  status: number;
  statusText: string;
  title: string;
  h1: string | null;
  links: string[];
  forms: FormInfo[];
  buttons: ButtonInfo[];
  consoleErrors: string[];
  jsErrors: string[];
}

interface FormInfo {
  action: string;
  method: string;
  fields: { name: string; type: string; required: boolean }[];
}

interface ButtonInfo {
  text: string;
  type: string;
  disabled: boolean;
}

const PSIS_BASE = process.env.PSID_URL || 'https://psis.theahg.co.za';
const OUTPUT_DIR = process.env.OUTPUT_DIR || 'tests/e2e/reports/psis-inventory';
const MAX_DEPTH = 1;
const MAX_PAGES_PER_ROLE = 5;

test.describe('PSIS Discovery Crawler', () => {
  
  // Ensure output directory exists
  test.beforeAll(() => {
    if (!fs.existsSync(OUTPUT_DIR)) {
      fs.mkdirSync(OUTPUT_DIR, { recursive: true });
    }
  });

  test.describe.configure({ mode: 'serial' });

  /**
   * Helper: Extract forms from a page
   */
  async function extractForms(page: Page): Promise<FormInfo[]> {
    const forms: FormInfo[] = [];
    const formElements = await page.locator('form').all();
    
    for (const form of formElements) {
      const action = await form.getAttribute('action') || '';
      const method = (await form.getAttribute('method') || 'get').toUpperCase();
      
      const fields: { name: string; type: string; required: boolean }[] = [];
      const inputs = await form.locator('input, select, textarea').all();
      
      for (const input of inputs) {
        const tagName = await input.evaluate((el: Element) => el.tagName.toLowerCase());
        const name = await input.getAttribute('name');
        const type = (await input.getAttribute('type') || 'text').toLowerCase();
        const required = await input.getAttribute('required') !== null;
        
        if (name && !['_token', 'csrf_token', 'MAX_FILE_SIZE'].includes(name)) {
          fields.push({ name, type: `${tagName}:${type}`, required });
        }
      }
      
      forms.push({ action, method, fields });
    }
    
    return forms;
  }

  /**
   * Helper: Extract buttons from a page
   */
  async function extractButtons(page: Page): Promise<ButtonInfo[]> {
    const buttons: ButtonInfo[] = [];
    const elements = await page.locator('button, input[type="submit"], input[type="button"]').all();
    
    for (const btn of elements) {
      const text = (await btn.textContent())?.trim() || '';
      const type = await btn.getAttribute('type') || 'button';
      const disabled = await btn.isDisabled();
      
      if (text) {
        buttons.push({ text, type, disabled });
      }
    }
    
    return buttons;
  }

  /**
   * Helper: Extract internal links
   */
  async function extractLinks(page: Page): Promise<string[]> {
    const links: Set<string> = new Set();
    const anchors = await page.locator('a[href]').all();
    
    for (const anchor of anchors) {
      const href = await anchor.getAttribute('href');
      if (href) {
        try {
          const url = new URL(href, PSIS_BASE);
          if (url.hostname === new URL(PSIS_BASE).hostname) {
            const normalized = url.pathname + url.search;
            if (!normalized.startsWith('/#') && !normalized.startsWith('javascript:')) {
              links.add(normalized);
            }
          }
        } catch (e) {
          // Invalid URL, skip
        }
      }
    }
    
    return Array.from(links);
  }

  /**
   * Helper: Crawl a single page
   */
  async function crawlPage(page: Page, url: string): Promise<CrawlResult> {
    const consoleErrors: string[] = [];
    const jsErrors: string[] = [];
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });
    
    page.on('pageerror', error => {
      jsErrors.push(error.message);
    });
    
    let response: Response | null = null;
    try {
      response = await page.goto(url, { 
        waitUntil: 'networkidle',
        timeout: 15000 
      });
    } catch (e: any) {
      jsErrors.push(`Navigation failed: ${e.message}`);
    }
    
    const title = await page.title();
    const h1 = await page.locator('h1').first().textContent().catch(() => null);
    const links = await extractLinks(page);
    const forms = await extractForms(page);
    const buttons = await extractButtons(page);
    
    return {
      url,
      finalUrl: page.url(),
      status: response?.status() || 0,
      statusText: response?.statusText() || '',
      title,
      h1,
      links,
      forms,
      buttons,
      consoleErrors,
      jsErrors
    };
  }

  /**
   * Helper: Perform BFS crawl
   */
  async function crawlSite(
    page: Page, 
    startUrls: string[],
    role: string
  ): Promise<CrawlResult[]> {
    const visited = new Set<string>();
    const results: CrawlResult[] = [];
    const queue: { url: string; depth: number }[] = startUrls.map(u => ({
      url: u.startsWith('http') ? u : PSIS_BASE + u,
      depth: 0
    }));
    
    while (queue.length > 0 && results.length < MAX_PAGES_PER_ROLE) {
      const { url, depth } = queue.shift()!;
      
      if (visited.has(url) || depth > MAX_DEPTH) {
        continue;
      }
      
      visited.add(url);
      console.log(`[${role}] Crawling (${depth}): ${url}`);
      
      const result = await crawlPage(page, url);
      results.push(result);
      
      if (depth < MAX_DEPTH) {
        for (const link of result.links) {
          if (!visited.has(link)) {
            queue.push({ 
              url: link.startsWith('http') ? link : PSIS_BASE + link, 
              depth: depth + 1 
            });
          }
        }
      }
    }
    
    return results;
  }

  /**
   * Test: Crawl as Guest
   */
  test('crawl as guest', async ({ page }) => {
    test.info().annotations.push({
      type: 'crawl-role',
      description: 'Unauthenticated public user'
    });
    
    const urls = seedUrls.psis.urls.map(u => PSIS_BASE + u);
    const results = await crawlSite(page, urls, 'guest');
    
    // Generate inventory
    const inventory = {
      role: 'guest',
      timestamp: new Date().toISOString(),
      baseUrl: PSIS_BASE,
      pagesCrawled: results.length,
      results
    };
    
    // Save to file
    const outputFile = path.join(OUTPUT_DIR, 'guest-crawl.json');
    fs.writeFileSync(outputFile, JSON.stringify(inventory, null, 2));
    console.log(`Saved guest crawl to ${outputFile}`);
    
    // Generate summary
    const summary = {
      role: 'guest',
      totalPages: results.length,
      uniqueUrls: new Set(results.map(r => r.url)).size,
      pagesWith200: results.filter(r => r.status === 200).length,
      pagesWithErrors: results.filter(r => r.status >= 400).length,
      totalForms: results.reduce((sum, r) => sum + r.forms.length, 0),
      totalButtons: results.reduce((sum, r) => sum + r.buttons.length, 0),
      pagesWithJsErrors: results.filter(r => r.jsErrors.length > 0).length
    };
    
    fs.writeFileSync(
      path.join(OUTPUT_DIR, 'guest-summary.json'),
      JSON.stringify(summary, null, 2)
    );
    
    // Basic assertions
    expect(results.length).toBeGreaterThan(0);
    expect(summary.pagesWith200).toBeGreaterThan(0);
  });

  /**
   * Test: Crawl as Admin
   */
  test('crawl as admin', async ({ page }) => {
    test.info().annotations.push({
      type: 'crawl-role',
      description: 'Administrator with full access'
    });
    
    // First login - AtoM uses /user/login with modal dialog
    await page.goto(PSIS_BASE + '/user/login');
    await page.waitForLoadState('networkidle');
    
    // Wait for login form to be visible (may be in modal)
    await page.waitForTimeout(1000);
    
    // Use visible inputs only
    const emailInput = page.locator('input:visible[name="_username"], input:visible[name="email"]').first();
    const passwordInput = page.locator('input:visible[name="_password"], input:visible[name="password"]').first();
    
    await emailInput.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
    await emailInput.fill(credentials.roles.admin.email);
    await passwordInput.fill(credentials.roles.admin.password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/(dashboard|home|admin|search)/, { timeout: 15000 }).catch(() => {});
    
    const urls = seedUrls.psis.urls.map(u => PSIS_BASE + u);
    const results = await crawlSite(page, urls, 'admin');
    
    // Save results
    const outputFile = path.join(OUTPUT_DIR, 'admin-crawl.json');
    fs.writeFileSync(outputFile, JSON.stringify({
      role: 'admin',
      timestamp: new Date().toISOString(),
      baseUrl: PSIS_BASE,
      pagesCrawled: results.length,
      results
    }, null, 2));
    
    const summary = {
      role: 'admin',
      totalPages: results.length,
      uniqueUrls: new Set(results.map(r => r.url)).size,
      pagesWith200: results.filter(r => r.status === 200).length,
      totalForms: results.reduce((sum, r) => sum + r.forms.length, 0),
      totalButtons: results.reduce((sum, r) => sum + r.buttons.length, 0)
    };
    
    fs.writeFileSync(
      path.join(OUTPUT_DIR, 'admin-summary.json'),
      JSON.stringify(summary, null, 2)
    );
    
    expect(results.length).toBeGreaterThan(0);
  });

  /**
   * Test: Generate combined inventory report
   */
  test('generate combined inventory', async ({}) => {
    const inventoryFiles = [
      path.join(OUTPUT_DIR, 'guest-crawl.json'),
      path.join(OUTPUT_DIR, 'admin-crawl.json')
    ];
    
    const allResults: CrawlResult[] = [];
    
    for (const file of inventoryFiles) {
      if (fs.existsSync(file)) {
        const data = JSON.parse(fs.readFileSync(file, 'utf-8'));
        allResults.push(...data.results);
      }
    }
    
    // Deduplicate by URL
    const seen = new Set<string>();
    const uniqueResults = allResults.filter(r => {
      if (seen.has(r.url)) return false;
      seen.add(r.url);
      return true;
    });
    
    // Generate full inventory
    const fullInventory = {
      generatedAt: new Date().toISOString(),
      baseUrl: PSIS_BASE,
      totalUniquePages: uniqueResults.length,
      totalForms: uniqueResults.reduce((sum, r) => sum + r.forms.length, 0),
      totalButtons: uniqueResults.reduce((sum, r) => sum + r.buttons.length, 0),
      
      // Categorized counts
      byStatus: {
        '2xx': uniqueResults.filter(r => r.status >= 200 && r.status < 300).length,
        '3xx': uniqueResults.filter(r => r.status >= 300 && r.status < 400).length,
        '4xx': uniqueResults.filter(r => r.status >= 400 && r.status < 500).length,
        '5xx': uniqueResults.filter(r => r.status >= 500).length,
        'failed': uniqueResults.filter(r => r.status === 0).length
      },
      
      // All discovered URLs
      urls: uniqueResults.map(r => ({
        url: r.url,
        status: r.status,
        title: r.title,
        forms: r.forms.length,
        buttons: r.buttons.length
      })),
      
      // All discovered forms
      forms: uniqueResults.flatMap(r => 
        r.forms.map(f => ({
          page: r.url,
          action: f.action,
          method: f.method,
          fields: f.fields.map(fld => fld.name)
        }))
      ),
      
      // All discovered buttons
      buttons: uniqueResults.flatMap(r =>
        r.buttons.map(b => ({
          page: r.url,
          text: b.text,
          type: b.type
        }))
      )
    };
    
    fs.writeFileSync(
      path.join(OUTPUT_DIR, 'full-inventory.json'),
      JSON.stringify(fullInventory, null, 2)
    );
    
    console.log(`\n=== PSIS Inventory Summary ===`);
    console.log(`Total unique pages: ${fullInventory.totalUniquePages}`);
    console.log(`Total forms: ${fullInventory.totalForms}`);
    console.log(`Total buttons: ${fullInventory.totalButtons}`);
    console.log(`Status breakdown:`, fullInventory.byStatus);
    console.log(`Report saved to: ${path.join(OUTPUT_DIR, 'full-inventory.json')}`);
  });
});
