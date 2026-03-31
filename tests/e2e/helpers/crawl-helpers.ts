/**
 * Crawl Helper Functions for Discovery Testing
 * 
 * Provides utilities for URL discovery, form detection, and link extraction
 */

import { Page, BrowserContext } from '@playwright/test';
import seedUrls from '../fixtures/seed-urls.json';

export interface CrawlResult {
  url: string;
  finalUrl: string;
  status: number;
  title: string;
  h1: string | null;
  links: string[];
  forms: FormInfo[];
  buttons: ButtonInfo[];
  errors: string[];
  screenshot?: string;
}

export interface FormInfo {
  action: string;
  method: string;
  fields: FormField[];
}

export interface FormField {
  name: string;
  type: string;
  required: boolean;
}

export interface ButtonInfo {
  text: string;
  type: string;
  disabled: boolean;
}

export interface CrawlOptions {
  maxDepth?: number;
  maxPages?: number;
  followRedirects?: boolean;
  captureScreenshots?: boolean;
  captureErrors?: boolean;
}

/**
 * Extract all forms from a page
 */
export async function extractForms(page: Page): Promise<FormInfo[]> {
  const forms: FormInfo[] = [];
  
  const formElements = await page.locator('form').all();
  
  for (const form of formElements) {
    const action = await form.getAttribute('action') || '';
    const method = (await form.getAttribute('method') || 'get').toUpperCase();
    
    const fields: FormField[] = [];
    
    // Get all input fields
    const inputs = await form.locator('input, select, textarea').all();
    for (const input of inputs) {
      const tagName = await input.evaluate(el => el.tagName.toLowerCase());
      const name = await input.getAttribute('name');
      const type = (await input.getAttribute('type') || 'text').toLowerCase();
      const required = await input.getAttribute('required') !== null;
      
      if (name) {
        fields.push({ name, type: `${tagName}:${type}`, required });
      }
    }
    
    forms.push({ action, method, fields });
  }
  
  return forms;
}

/**
 * Extract all buttons from a page
 */
export async function extractButtons(page: Page): Promise<ButtonInfo[]> {
  const buttons: ButtonInfo[] = [];
  
  const buttonElements = await page.locator('button, input[type="submit"], input[type="button"]').all();
  
  for (const btn of buttonElements) {
    const text = await btn.textContent().then(t => t?.trim() || '');
    const type = await btn.getAttribute('type') || 'button';
    const disabled = await btn.isDisabled();
    
    buttons.push({ text, type, disabled });
  }
  
  return buttons;
}

/**
 * Extract all internal links from a page
 */
export async function extractLinks(page: Page, baseUrl: string): Promise<string[]> {
  const links: Set<string> = new Set();
  
  const anchorElements = await page.locator('a[href]').all();
  
  for (const anchor of anchorElements) {
    const href = await anchor.getAttribute('href');
    if (href) {
      // Normalize and filter internal links
      const normalized = normalizeUrl(href, baseUrl);
      if (isInternalLink(normalized, baseUrl)) {
        links.add(normalized);
      }
    }
  }
  
  return Array.from(links);
}

/**
 * Normalize a URL relative to base URL
 */
export function normalizeUrl(href: string, baseUrl: string): string {
  try {
    // Handle absolute URLs
    if (href.startsWith('http://') || href.startsWith('https://')) {
      const url = new URL(href);
      return url.pathname + url.search;
    }
    
    // Handle protocol-relative URLs
    if (href.startsWith('//')) {
      return new URL('https:' + href).pathname + new URL('https:' + href).search;
    }
    
    // Handle root-relative URLs
    if (href.startsWith('/')) {
      const base = new URL(baseUrl);
      return base.pathname.replace(/\/$/, '') + href + new URL(href, baseUrl).search;
    }
    
    // Handle relative URLs
    const base = new URL(baseUrl);
    return base.pathname.replace(/\/$/, '') + '/' + href;
  } catch (e) {
    return href;
  }
}

/**
 * Check if a link is internal (same domain)
 */
export function isInternalLink(url: string, baseUrl: string): boolean {
  try {
    const linkUrl = new URL(url, baseUrl);
    const base = new URL(baseUrl);
    return linkUrl.hostname === base.hostname;
  } catch {
    return false;
  }
}

/**
 * Crawl a single page and extract all relevant information
 */
export async function crawlPage(
  page: Page,
  url: string,
  options: { captureErrors?: boolean; captureScreenshots?: boolean } = {}
): Promise<CrawlResult> {
  const { captureErrors = true, captureScreenshots = false } = options;
  
  const errors: string[] = [];
  let screenshot: string | undefined;
  
  // Capture console errors if requested
  if (captureErrors) {
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(`Console Error: ${msg.text()}`);
      }
    });
  }
  
  let status = 200;
  try {
    const response = await page.goto(url, { waitUntil: 'networkidle' });
    status = response?.status() || 0;
  } catch (e: any) {
    errors.push(`Navigation Error: ${e.message}`);
    if (captureScreenshots) {
      screenshot = await page.screenshot({ fullPage: true }) as string;
    }
  }
  
  const title = await page.title();
  const h1 = await page.locator('h1').first().textContent().catch(() => null);
  const links = await extractLinks(page, page.url());
  const forms = await extractForms(page);
  const buttons = await extractButtons(page);
  
  if (captureScreenshots && errors.length > 0) {
    screenshot = await page.screenshot({ fullPage: true }) as string;
  }
  
  return {
    url,
    finalUrl: page.url(),
    status,
    title,
    h1,
    links,
    forms,
    buttons,
    errors,
    screenshot
  };
}

/**
 * Perform a breadth-first crawl starting from seed URLs
 */
export async function crawlSite(
  page: Page,
  baseUrl: string,
  seedUrls: string[],
  options: CrawlOptions = {}
): Promise<CrawlResult[]> {
  const {
    maxDepth = 3,
    maxPages = 100,
    captureScreenshots = false,
    captureErrors = true
  } = options;
  
  const visited = new Set<string>();
  const results: CrawlResult[] = [];
  const queue: { url: string; depth: number }[] = seedUrls.map(u => ({ url: u, depth: 0 }));
  
  while (queue.length > 0 && results.length < maxPages) {
    const { url, depth } = queue.shift()!;
    
    if (visited.has(url) || depth > maxDepth) {
      continue;
    }
    
    visited.add(url);
    
    const result = await crawlPage(page, url, { captureErrors, captureScreenshots });
    results.push(result);
    
    // Add discovered links to queue
    if (depth < maxDepth) {
      for (const link of result.links) {
        if (!visited.has(link)) {
          queue.push({ url: link, depth: depth + 1 });
        }
      }
    }
  }
  
  return results;
}

/**
 * Compare two crawl results and identify differences
 */
export function comparePages(
  psisResult: CrawlResult,
  heratioResult: CrawlResult
): {
  titleMatch: boolean;
  h1Match: boolean;
  formCountDiff: number;
  buttonCountDiff: number;
  missingForms: FormInfo[];
  missingButtons: ButtonInfo[];
  newForms: FormInfo[];
  newButtons: ButtonInfo[];
} {
  const titleMatch = psisResult.title === heratioResult.title;
  const h1Match = psisResult.h1?.trim() === heratioResult.h1?.trim();
  
  // Compare forms
  const psisFormActions = new Set(psisResult.forms.map(f => f.action));
  const heratioFormActions = new Set(heratioResult.forms.map(f => f.action));
  
  const missingForms = psisResult.forms.filter(f => !heratioFormActions.has(f.action));
  const newForms = heratioResult.forms.filter(f => !psisFormActions.has(f.action));
  
  // Compare buttons
  const psisButtonTexts = new Set(psisResult.buttons.map(b => b.text.trim().toLowerCase()));
  const heratioButtonTexts = new Set(heratioResult.buttons.map(b => b.text.trim().toLowerCase()));
  
  const missingButtons = psisResult.buttons.filter(b => !psisButtonTexts.has(b.text.trim().toLowerCase()));
  const newButtons = heratioResult.buttons.filter(b => !heratioButtonTexts.has(b.text.trim().toLowerCase()));
  
  return {
    titleMatch,
    h1Match,
    formCountDiff: heratioResult.forms.length - psisResult.forms.length,
    buttonCountDiff: heratioResult.buttons.length - psisResult.buttons.length,
    missingForms,
    missingButtons,
    newForms,
    newButtons
  };
}
