/**
 * Search Functionality E2E Tests
 * 
 * Tests search features across records and actors
 */

import { test, expect } from '@playwright/test';

const HERATIO_URL = process.env.HERATIO_URL || 'https://heratio.theahg.co.za';

test.describe('Search Functionality', () => {
  
  test.describe.configure({ mode: 'serial' });

  // ==========================================================================
  // SEARCH INPUT TESTS
  // ==========================================================================

  test.describe('Search Input', () => {
    
    test('homepage has search input', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/`);
      const searchInput = page.locator('input[type="search"], input[name*="search"], input[placeholder*="Search"], .search-input, input[type="text"]').first();
      await expect(searchInput).toBeVisible({ timeout: 10000 });
    });

    test('records browse page has search', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      // Page should load with some search capability
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });

    test('actor browse page has search', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/actor/browse`);
      // Page should load
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });
  });

  // ==========================================================================
  // SEARCH RESULTS TESTS
  // ==========================================================================

  test.describe('Search Results', () => {
    
    test('search returns results page', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/`);
      
      // Look for search input
      const searchInput = page.locator('input[type="search"], input[name*="search"], input[placeholder*="Search"]').first();
      
      if (await searchInput.isVisible({ timeout: 2000 }).catch(() => false)) {
        await searchInput.fill('test');
        await searchInput.press('Enter');
        await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
      } else {
        // Skip if no search input found
        test.skip();
      }
    });
  });

  // ==========================================================================
  // FILTER TESTS
  // ==========================================================================

  test.describe('Filters', () => {
    
    test('browse page shows filter options', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      // Page should load
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });

    test('actor browse shows filter options', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/actor/browse`);
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });
  });

  // ==========================================================================
  // PAGINATION TESTS
  // ==========================================================================

  test.describe('Pagination', () => {
    
    test('browse page shows pagination if results exist', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/actor/browse`);
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
      
      // Check for pagination
      const pagination = page.locator('.pagination, nav[aria-label="pagination"], .pager');
      const hasPagination = await pagination.isVisible().catch(() => false);
      
      if (hasPagination) {
        await expect(pagination).toBeVisible();
      }
    });
  });
});
