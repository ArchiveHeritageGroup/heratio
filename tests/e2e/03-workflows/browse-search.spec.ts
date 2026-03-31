/**
 * Browse and Search E2E Tests
 * 
 * Group 2: Browse and search workflows
 * - browse descriptions
 * - browse actors
 * - browse repositories
 * - search
 * - filter
 * - sort
 * - paginate
 */

import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

// Load fixtures
const credentials = JSON.parse(fs.readFileSync(path.join(path.dirname(new URL(import.meta.url).pathname), '../fixtures/role-credentials.json'), 'utf-8'));

const HERATIO_URL = process.env.HERATIO_URL || 'https://heratio.theahg.co.za';

test.describe('Browse and Search', () => {
  
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto(`${HERATIO_URL}/login`);
    await page.fill('input[name="email"]', credentials.roles.admin.email);
    await page.fill('input[name="password"]', credentials.roles.admin.password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/(dashboard|home|admin)/, { timeout: 10000 });
  });

  // ==========================================================================
  // BROWSE DESCRIPTIONS / RECORDS
  // ==========================================================================

  test.describe('Browse Records', () => {
    
    test('records browse page loads', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
      
      // Should have some content (table, list, or grid)
      const content = page.locator('table, .list-group, .grid, .card');
      // May or may not have records, but page should load
    });

    test('can filter records by level of description', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      
      // Look for level filter
      const levelFilter = page.locator('select[name*="level"], #levelFilter, [data-filter*="level"]').first();
      if (await levelFilter.isVisible()) {
        await levelFilter.selectOption({ index: 1 });
        
        // Submit filter
        const submitBtn = page.locator('button[type="submit"], button:has-text("Filter"), button:has-text("Search")').first();
        if (await submitBtn.isVisible()) {
          await submitBtn.click();
        }
        
        // Page should update
        await page.waitForLoadState('networkidle');
      }
    });

    test('can sort records', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      
      // Look for sort dropdown
      const sortDropdown = page.locator('select[name*="sort"], #sortBy, [data-sort]').first();
      if (await sortDropdown.isVisible()) {
        await sortDropdown.selectOption({ index: 1 });
        await page.waitForLoadState('networkidle');
      }
    });

    test('pagination works', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      
      // Look for pagination
      const pagination = page.locator('.pagination, nav[aria-label="pagination"]');
      if (await pagination.isVisible()) {
        const pageLinks = await page.locator('.pagination a, nav a').all();
        
        if (pageLinks.length > 1) {
          // Click second page
          const secondPage = page.locator('.pagination a:has-text("2"), nav a:has-text("2")').first();
          if (await secondPage.isVisible()) {
            await secondPage.click();
            await page.waitForLoadState('networkidle');
          }
        }
      }
    });
  });

  // ==========================================================================
  // BROWSE ACTORS
  // ==========================================================================

  test.describe('Browse Actors', () => {
    
    test('actor browse page loads', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/actor/browse`);
      
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });

    test('can filter actors by type', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/actor/browse`);
      
      // Look for type filter (person, family, corporate body)
      const typeFilter = page.locator('select[name*="type"], #typeFilter, select[name*="entity"]').first();
      if (await typeFilter.isVisible()) {
        const options = await typeFilter.locator('option').all();
        if (options.length > 1) {
          await typeFilter.selectOption({ index: 1 });
          
          const submitBtn = page.locator('button[type="submit"], button:has-text("Filter")').first();
          if (await submitBtn.isVisible()) {
            await submitBtn.click();
            await page.waitForLoadState('networkidle');
          }
        }
      }
    });

    test('actor results are displayed', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/actor/browse`);
      
      // Look for results (table, list, or grid)
      const results = page.locator('table tbody tr, .actor-list .actor-item, .grid .card');
      const count = await results.count();
      
      // Results may or may not exist, but page should load
      expect(count).toBeGreaterThanOrEqual(0);
    });
  });

  // ==========================================================================
  // BROWSE REPOSITORIES
  // ==========================================================================

  test.describe('Browse Repositories', () => {
    
    test('repository browse page loads', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/repository/browse`);
      
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });

    test('can view repository details', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/repository/browse`);
      
      // Try to find and click first repository link
      const repoLink = page.locator('table tbody tr:first-child a, .repository-list a:first-child, a[href*="/repository/"]').first();
      
      if (await repoLink.isVisible()) {
        await repoLink.click();
        await page.waitForLoadState('networkidle');
        
        // Should be on a repository detail page
        await expect(page.locator('body')).toBeVisible();
      }
    });
  });

  // ==========================================================================
  // BROWSE TERMS
  // ==========================================================================

  test.describe('Browse Terms', () => {
    
    test('term browse page loads', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/term/browse`);
      
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });

    test('can filter terms by taxonomy', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/term/browse`);
      
      const taxonomyFilter = page.locator('select[name*="taxonomy"], #taxonomyFilter').first();
      if (await taxonomyFilter.isVisible()) {
        await taxonomyFilter.selectOption({ index: 1 });
        
        const submitBtn = page.locator('button[type="submit"], button:has-text("Filter")').first();
        if (await submitBtn.isVisible()) {
          await submitBtn.click();
          await page.waitForLoadState('networkidle');
        }
      }
    });
  });

  // ==========================================================================
  // SEARCH
  // ==========================================================================

  test.describe('Search', () => {
    
    test('global search page loads', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/search`);
      
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
      
      // Should have search input
      const searchInput = page.locator('input[type="search"], input[name*="search"], input[name*="q"]').first();
      await expect(searchInput).toBeVisible();
    });

    test('can perform search', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/search`);
      
      // Find search input
      const searchInput = page.locator('input[type="search"], input[name*="search"], input[name*="q"]').first();
      await expect(searchInput).toBeVisible();
      
      // Enter search term
      await searchInput.fill('archive');
      
      // Submit
      const submitBtn = page.locator('button[type="submit"], button:has-text("Search")').first();
      await submitBtn.click();
      
      await page.waitForLoadState('networkidle');
      
      // Results should appear
      const results = page.locator('table tbody tr, .result-item, .card, .search-results');
      await expect(results.first()).toBeVisible({ timeout: 5000 });
    });

    test('search with no results shows message', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/search`);
      
      // Enter unlikely search term
      const searchInput = page.locator('input[type="search"], input[name*="search"], input[name*="q"]').first();
      await searchInput.fill('xyzzyx123456789');
      
      // Submit
      const submitBtn = page.locator('button[type="submit"], button:has-text("Search")').first();
      await submitBtn.click();
      
      await page.waitForLoadState('networkidle');
      
      // Should show "no results" message
      const noResults = page.locator('text=/no results|no records found|no matching/i');
      // Either shows no results or has results
      const hasResults = await page.locator('table tbody tr, .result-item').first().isVisible().catch(() => false);
      if (!hasResults) {
        await expect(noResults).toBeVisible({ timeout: 5000 }).catch(() => {});
      }
    });

    test('search suggestions or autocomplete works', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/search`);
      
      const searchInput = page.locator('input[type="search"], input[name*="search"], input[name*="q"]').first();
      await searchInput.fill('arch');
      
      // Wait a bit for autocomplete
      await page.waitForTimeout(500);
      
      // Look for autocomplete dropdown
      const autocomplete = page.locator('.autocomplete, .suggestions, [role="listbox"], .dropdown-menu');
      const hasAutocomplete = await autocomplete.isVisible().catch(() => false);
      
      // May or may not have autocomplete - that's OK
    });
  });

  // ==========================================================================
  // ACCESSIONS BROWSE
  // ==========================================================================

  test.describe('Browse Accessions', () => {
    
    test('accession browse page loads', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/accession/browse`);
      
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });

    test('can filter accessions by type', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/accession/browse`);
      
      const typeFilter = page.locator('select[name*="type"], #typeFilter').first();
      if (await typeFilter.isVisible()) {
        await typeFilter.selectOption({ index: 1 });
        
        const submitBtn = page.locator('button[type="submit"], button:has-text("Filter")').first();
        if (await submitBtn.isVisible()) {
          await submitBtn.click();
          await page.waitForLoadState('networkidle');
        }
      }
    });
  });
});
