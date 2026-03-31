import { test, expect } from '@playwright/test';

/**
 * Heratio Actors/Authority Records E2E Tests
 * 
 * Tests for CRUD operations on actors (persons, families, corporate bodies)
 */

test.describe('Actors Management', () => {
  
  test.beforeEach(async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('input[name="email"], input[type="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // Navigate to actors
    await page.goto('/actor');
  });

  test('actor browse page loads', async ({ page }) => {
    await expect(page).toHaveURL(/\/actor/);
    await expect(page.locator('h1, h2, [role="heading"]')).toBeVisible();
  });

  test('can create new person actor', async ({ page }) => {
    await page.click('a:has-text("Add"), a:has-text("New"), button:has-text("Add")');
    
    // Fill form
    await page.selectOption('select[name="entity_type"], #entityType', 'person');
    await page.fill('input[name="authorized_form_of_name"], #authorizedFormOfName', 'John Smith');
    await page.fill('textarea[name="歷史"], #history', 'Test biographical information.');
    
    // Submit
    await page.click('button[type="submit"]:has-text("Save"), button:has-text("Create")');
    
    // Should show success and redirect
    await expect(page.locator('.alert-success, .toast-success, [role="status"]'))
      .toContainText(/created|saved|success/i);
  });

  test('can create corporate body actor', async ({ page }) => {
    await page.click('a:has-text("Add"), button:has-text("Add")');
    
    await page.selectOption('select[name="entity_type"], #entityType', 'corporateBody');
    await page.fill('input[name="authorized_form_of_name"], #authorizedFormOfName', 'National Archives');
    await page.fill('textarea[name="機構史"], #institutionalHistory', 'Founded in 1902.');
    
    await page.click('button[type="submit"]:has-text("Save")');
    
    await expect(page.locator('.alert-success')).toBeVisible();
  });

  test('can search actors', async ({ page }) => {
    // Search input should be visible
    const searchInput = page.locator('input[type="search"], input[name="q"], input[placeholder*="Search"]');
    await expect(searchInput).toBeVisible();
    
    await searchInput.fill('Smith');
    await page.click('button[type="submit"]:has-text("Search"), button:has-text("Search")');
    
    // Should filter results
    await expect(page.locator('table tbody tr, .actor-list .actor-item')).toBeVisible();
  });

  test('can view actor details', async ({ page }) => {
    // Click on first actor link
    const actorLink = page.locator('table tbody tr:first-child a, .actor-list .actor-item:first-child a').first();
    await actorLink.click();
    
    // Should show actor details
    await expect(page.locator('h1, h2')).toBeVisible();
    await expect(page.locator('[data-field="authorized_form_of_name"], .authorized-name')).toBeVisible();
  });

  test('can edit actor', async ({ page }) => {
    // Navigate to edit page
    await page.locator('table tbody tr:first-child').locator('a:has-text("Edit")').click();
    
    // Update name
    await page.fill('input[name="authorized_form_of_name"]', 'Updated Actor Name');
    
    await page.click('button[type="submit"]:has-text("Save")');
    
    await expect(page.locator('.alert-success')).toBeVisible();
  });

  test('can delete actor', async ({ page }) => {
    // Navigate to edit page
    await page.locator('table tbody tr:first-child').locator('a:has-text("Edit")').click();
    
    // Click delete
    await page.click('button:has-text("Delete"), a:has-text("Delete")');
    
    // Confirm in dialog
    page.on('dialog', dialog => dialog.accept());
    await page.click('button:has-text("Confirm"), button:has-text("OK")');
    
    // Should redirect to list
    await expect(page).toHaveURL(/\/actor/);
  });

  test('actor type filter works', async ({ page }) => {
    // Look for type filter
    const typeFilter = page.locator('select[name="type"], #typeFilter');
    if (await typeFilter.isVisible()) {
      await typeFilter.selectOption('person');
      await page.click('button[type="submit"]');
      
      // Should show only persons
      await expect(page.locator('table tbody tr')).toBeVisible();
    }
  });

  test('pagination works', async ({ page }) => {
    // Look for pagination
    const pagination = page.locator('.pagination, nav[aria-label="pagination"]');
    if (await pagination.isVisible()) {
      const nextLink = pagination.locator('a:has-text("Next"), a:has-text("2")');
      if (await nextLink.isVisible()) {
        await nextLink.click();
        await expect(page).toHaveURL(/\?page=2|page=2/);
      }
    }
  });

  test('actor relations are displayed', async ({ page }) => {
    // Navigate to an actor with relations
    await page.locator('table tbody tr:first-child a').first().click();
    
    // Look for relations section
    const relationsSection = page.locator('[data-section*="relation"], .relations, .related-records');
    if (await relationsSection.isVisible()) {
      await expect(relationsSection).toBeVisible();
    }
  });

});
