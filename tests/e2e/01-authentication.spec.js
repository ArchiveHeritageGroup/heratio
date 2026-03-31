import { test, expect } from '@playwright/test';

/**
 * Heratio Authentication E2E Tests
 * 
 * Tests for login, logout, session management, and access control
 */

test.describe('Authentication', () => {
  
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
  });

  test('login page loads correctly', async ({ page }) => {
    await expect(page.locator('h1, h2')).toContainText(/login|sign in/i);
    await expect(page.locator('input[type="email"], input[name="email"]')).toBeVisible();
    await expect(page.locator('input[type="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('can log in with valid credentials', async ({ page }) => {
    await page.fill('input[name="email"], input[type="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // Should redirect to dashboard
    await expect(page).toHaveURL(/\/(dashboard|home|admin)/);
  });

  test('shows error with invalid credentials', async ({ page }) => {
    await page.fill('input[name="email"], input[type="email"]', 'invalid@example.com');
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');
    
    // Should show error message
    await expect(page.locator('.alert, .error, .invalid-feedback, [role="alert"]'))
      .toContainText(/invalid|incorrect|failed|error/i);
  });

  test('can log out', async ({ page }) => {
    // First login
    await page.fill('input[name="email"], input[type="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    
    // Find and click logout
    await page.click('button:has-text("Logout"), a:has-text("Logout"), [href*="logout"]');
    
    // Should redirect to login
    await expect(page).toHaveURL(/\/login/);
  });

  test('unauthenticated users are redirected to login', async ({ page }) => {
    await page.goto('/admin');
    
    await expect(page).toHaveURL(/\/login/);
  });

  test('remember me functionality', async ({ page }) => {
    await page.fill('input[name="email"], input[type="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.check('input[name="remember"]');
    await page.click('button[type="submit"]');
    
    // Should set remember cookie
    const cookies = await page.context().cookies();
    expect(cookies.some(c => c.name.includes('remember'))).toBeTruthy();
  });

  test('password visibility toggle works', async ({ page }) => {
    const passwordInput = page.locator('input[name="password"]');
    
    // Initially hidden
    await expect(passwordInput).toHaveAttribute('type', 'password');
    
    // Click toggle
    await page.click('button[aria-label*="show"], button[aria-label*="password"], .password-toggle');
    
    // Should be visible
    await expect(passwordInput).toHaveAttribute('type', 'text');
  });

});
