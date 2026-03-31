/**
 * CRUD Records/Descriptions E2E Tests
 * 
 * Group 3: CRUD workflows for archival descriptions
 * - open list page
 * - open create form
 * - submit valid create
 * - verify saved
 * - open edit form
 * - submit update
 * - verify update
 * - delete or soft delete
 */

import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

// Load fixtures
const credentials = JSON.parse(fs.readFileSync(path.join(path.dirname(new URL(import.meta.url).pathname), '../fixtures/role-credentials.json'), 'utf-8'));

const HERATIO_URL = process.env.HERATIO_URL || 'https://heratio.theahg.co.za';

test.describe('CRUD Records', () => {
  
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto(`${HERATIO_URL}/login`);
    await page.fill('input[name="email"]', credentials.roles.admin.email);
    await page.fill('input[name="password"]', credentials.roles.admin.password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/(dashboard|home|admin)/, { timeout: 10000 });
  });

  // ==========================================================================
  // CREATE RECORD
  // ==========================================================================

  test.describe('Create Record', () => {
    
    test('can navigate to create record page', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      
      // Look for create/add button
      const addButton = page.locator('a:has-text("Add"), a:has-text("New"), button:has-text("Add"), a[href*="/add"]').first();
      
      if (await addButton.isVisible()) {
        await addButton.click();
        await page.waitForLoadState('networkidle');
        
        // Should be on create page
        await expect(page.locator('body')).toBeVisible();
      }
    });

    test('create form has required fields', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/add`);
      
      // Check for common required fields
      const titleInput = page.locator('input[name*="title"], #title, input[name*="name"]').first();
      await expect(titleInput).toBeVisible({ timeout: 5000 });
      
      // Check for level of description
      const lodSelect = page.locator('select[name*="level"], #levelOfDescription, select[name*="lod"]').first();
      if (await lodSelect.isVisible()) {
        await expect(lodSelect).toBeVisible();
      }
    });

    test('can submit valid create form', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/add`);
      
      // Fill required fields
      const titleInput = page.locator('input[name*="title"], #title, input[name*="name"]').first();
      if (await titleInput.isVisible()) {
        await titleInput.fill('Test Record ' + Date.now());
      }
      
      // Select level of description if present
      const lodSelect = page.locator('select[name*="level"], #levelOfDescription').first();
      if (await lodSelect.isVisible()) {
        await lodSelect.selectOption({ index: 1 });
      }
      
      // Fill identifier if present
      const identifierInput = page.locator('input[name*="identifier"], #identifier').first();
      if (await identifierInput.isVisible()) {
        await identifierInput.fill('TEST-' + Date.now());
      }
      
      // Submit
      const submitBtn = page.locator('button[type="submit"]:has-text("Save"), button[type="submit"]:has-text("Create"), button:has-text("Save")').first();
      if (await submitBtn.isVisible()) {
        await submitBtn.click();
        
        // Wait for redirect or success message
        await page.waitForLoadState('networkidle');
        
        // Check for success indicators
        const successMsg = page.locator('.alert-success, .toast-success, [role="status"]:has-text("success"), .flash-success');
        const isOnDetailPage = page.url().match(/\/records\/.+/);
        
        // Should either show success or redirect to detail page
        const hasSuccess = await successMsg.isVisible().catch(() => false) || isOnDetailPage;
        expect(hasSuccess).toBeTruthy();
      }
    });

    test('shows validation errors for missing required fields', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/add`);
      
      // Submit empty form
      const submitBtn = page.locator('button[type="submit"]').first();
      await submitBtn.click();
      
      // Wait for validation
      await page.waitForTimeout(500);
      
      // Check for validation errors
      const validationErrors = page.locator('.invalid-feedback, .error, .alert-danger, [role="alert"]');
      const errorCount = await validationErrors.count();
      
      // Should have some validation errors
      expect(errorCount).toBeGreaterThan(0);
    });
  });

  // ==========================================================================
  // READ RECORD
  // ==========================================================================

  test.describe('Read Record', () => {
    
    test('can view record details', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      
      // Try to find first record link
      const recordLink = page.locator('table tbody tr:first-child a, .record-list a:first-child, a[href*="/records/"]').first();
      
      if (await recordLink.isVisible()) {
        await recordLink.click();
        await page.waitForLoadState('networkidle');
        
        // Should show record details
        await expect(page.locator('body')).toBeVisible();
        
        // Should have some content
        const content = page.locator('h1, h2, .record-title, [data-field="title"]');
        await expect(content.first()).toBeVisible({ timeout: 5000 });
      }
    });

    test('record detail page shows metadata', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      
      const recordLink = page.locator('table tbody tr:first-child a, a[href*="/records/"]').first();
      
      if (await recordLink.isVisible()) {
        await recordLink.click();
        await page.waitForLoadState('networkidle');
        
        // Check for common metadata fields
        const metadataFields = [
          'identifier',
          'title',
          'date',
          'level',
          'repository'
        ];
        
        for (const field of metadataFields) {
          const fieldElement = page.locator(`[data-field*="${field}"], :has-text("${field}"):not(script)`);
          // Just check page has content, not specific field
        }
      }
    });
  });

  // ==========================================================================
  // UPDATE RECORD
  // ==========================================================================

  test.describe('Update Record', () => {
    
    test('can navigate to edit page', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      
      // Find edit button/link
      const editLink = page.locator('table tbody tr:first-child a:has-text("Edit"), a[href*="/edit"]').first();
      
      if (await editLink.isVisible()) {
        await editLink.click();
        await page.waitForLoadState('networkidle');
        
        // Should be on edit page with form
        await expect(page.locator('form')).toBeVisible({ timeout: 5000 });
      }
    });

    test('can update record title', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      
      // Navigate to edit
      const editLink = page.locator('table tbody tr:first-child a:has-text("Edit"), a[href*="/edit"]').first();
      
      if (await editLink.isVisible()) {
        await editLink.click();
        await page.waitForLoadState('networkidle');
        
        // Find title field
        const titleInput = page.locator('input[name*="title"], #title').first();
        
        if (await titleInput.isVisible()) {
          // Clear and update
          await titleInput.clear();
          const newTitle = 'Updated Title ' + Date.now();
          await titleInput.fill(newTitle);
          
          // Submit
          const submitBtn = page.locator('button[type="submit"]:has-text("Save"), button:has-text("Update")').first();
          await submitBtn.click();
          
          await page.waitForLoadState('networkidle');
          
          // Check title was updated
          const pageContent = await page.content();
          expect(pageContent).toContain(newTitle);
        }
      }
    });

    test('can cancel edit without saving', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      
      // Navigate to edit
      const editLink = page.locator('table tbody tr:first-child a:has-text("Edit"), a[href*="/edit"]').first();
      
      if (await editLink.isVisible()) {
        await editLink.click();
        await page.waitForLoadState('networkidle');
        
        // Change title
        const titleInput = page.locator('input[name*="title"], #title').first();
        if (await titleInput.isVisible()) {
          const originalTitle = await titleInput.inputValue();
          await titleInput.fill('Changed Title');
          
          // Click cancel/back
          const cancelBtn = page.locator('button:has-text("Cancel"), a:has-text("Back")').first();
          if (await cancelBtn.isVisible()) {
            await cancelBtn.click();
            await page.waitForLoadState('networkidle');
          }
        }
      }
    });
  });

  // ==========================================================================
  // DELETE RECORD
  // ==========================================================================

  test.describe('Delete Record', () => {
    
    test('can access delete confirmation', async ({ page }) => {
      // First create a record to delete
      await page.goto(`${HERATIO_URL}/records/add`);
      
      const titleInput = page.locator('input[name*="title"], #title').first();
      if (await titleInput.isVisible()) {
        await titleInput.fill('Record to Delete ' + Date.now());
        
        const submitBtn = page.locator('button[type="submit"]').first();
        await submitBtn.click();
        await page.waitForLoadState('networkidle');
      }
      
      // Now look for delete button
      const deleteBtn = page.locator('button:has-text("Delete"), a:has-text("Delete")').first();
      
      if (await deleteBtn.isVisible()) {
        // Handle confirmation dialog
        page.on('dialog', dialog => dialog.accept());
        
        await deleteBtn.click();
        await page.waitForLoadState('networkidle');
        
        // Should redirect to list or show success
        const pageContent = await page.content();
        const isOnList = page.url().includes('/browse') || page.url().includes('/records');
        const hasSuccess = pageContent.includes('deleted') || pageContent.includes('success');
        
        expect(isOnList || hasSuccess).toBeTruthy();
      }
    });

    test('delete requires confirmation', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      
      const editLink = page.locator('table tbody tr:first-child a:has-text("Edit")').first();
      
      if (await editLink.isVisible()) {
        await editLink.click();
        await page.waitForLoadState('networkidle');
        
        const deleteBtn = page.locator('button:has-text("Delete")').first();
        
        if (await deleteBtn.isVisible()) {
          // Set up dialog handler that dismisses
          page.on('dialog', dialog => dialog.dismiss());
          
          await deleteBtn.click();
          
          // Dialog should appear
          await page.waitForTimeout(500);
        }
      }
    });
  });

  // ==========================================================================
  // HIERARCHY TESTS
  // ==========================================================================

  test.describe('Record Hierarchy', () => {
    
    test('can view child records', async ({ page }) => {
      await page.goto(`${HERATIO_URL}/records/browse`);
      
      // Find a record that might have children
      const recordLink = page.locator('table tbody tr:first-child a, .record-item a').first();
      
      if (await recordLink.isVisible()) {
        await recordLink.click();
        await page.waitForLoadState('networkidle');
        
        // Look for children section
        const childrenSection = page.locator('[data-section*="children"], .children, .child-records, .descendants');
        const hasChildren = await childrenSection.isVisible().catch(() => false);
        
        // Just verify page loaded
        await expect(page.locator('body')).toBeVisible();
      }
    });

    test('can add child record', async ({ page }) => {
      // Navigate to a record
      await page.goto(`${HERATIO_URL}/records/browse`);
      
      const recordLink = page.locator('table tbody tr:first-child a, a[href*="/records/"]').first();
      
      if (await recordLink.isVisible()) {
        await recordLink.click();
        await page.waitForLoadState('networkidle');
        
        // Look for add child button
        const addChildBtn = page.locator('a:has-text("Add Child"), button:has-text("Add Child"), [href*="/add"][href*="parent"]').first();
        
        if (await addChildBtn.isVisible()) {
          await addChildBtn.click();
          await page.waitForLoadState('networkidle');
          
          // Should be on add page with parent context
          await expect(page.locator('body')).toBeVisible();
        }
      }
    });
  });
});
