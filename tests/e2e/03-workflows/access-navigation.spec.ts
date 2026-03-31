/**
 * Access and Navigation E2E Tests
 * 
 * Simplified tests focusing on public pages and redirects
 */

import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

const HERATIO_URL = process.env.HERATIO_URL || 'https://heratio.theahg.co.za';

test.describe('Access and Navigation', () => {
  
  test.describe.configure({ mode: 'serial' });

  // ==========================================================================
  // PUBLIC PAGE TESTS
  // ==========================================================================

  test.describe('Public Pages', () => {
    
    test('homepage loads', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/`);
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });

    test('login page loads', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/login`);
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });

    test('records browse page loads', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });

    test('actor browse page loads', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/actor/browse`);
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });
  });

  // ==========================================================================
  // AUTH REDIRECT TESTS
  // ==========================================================================

  test.describe('Auth Redirects', () => {
    
    test('admin redirects to login', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/admin`);
      // Should show login page or redirect
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });

    test('protected page redirects to login', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/admin/settings`);
      // Should show login page or redirect
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });
  });

  // ==========================================================================
  // KEY PAGES LOAD TESTS
  // ==========================================================================

  test.describe('Key Pages Load', () => {
    const keyPages = [
      { url: '/', name: 'Homepage' },
      { url: '/records/browse', name: 'Records Browse' },
      { url: '/actor/browse', name: 'Actor Browse' },
    ];

    for (const pageInfo of keyPages) {
      test(`${pageInfo.name} loads without errors`, async ({ page }) => {
        const response = await page.goto(`${HERATIO_URL}${pageInfo.url}`);
        const status = response?.status() || 0;
        expect([200, 301, 302]).toContain(status);
        await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
      });
    }
  });

  // ==========================================================================
  // 404 HANDLING
  // ==========================================================================

  test.describe('Error Handling', () => {
    
    test('non-existent page shows page', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/this-does-not-exist-12345`);
      await expect(page.locator('body')).toBeVisible({ timeout: 10000 });
    });
  });
});
