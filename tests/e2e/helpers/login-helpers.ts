/**
 * Login Helper Functions for Playwright E2E Tests
 * 
 * Provides reusable login/logout functions for different user roles
 */

import { Page } from '@playwright/test';
import credentials from '../fixtures/role-credentials.json';

export type UserRole = 'guest' | 'authenticated' | 'editor' | 'admin' | 'researcher';

interface LoginCredentials {
  email?: string;
  password?: string;
}

/**
 * Get credentials for a specific role
 */
export function getCredentials(role: UserRole): LoginCredentials {
  if (role === 'guest') {
    return {};
  }
  
  const roleConfig = credentials.roles[role];
  if (!roleConfig) {
    throw new Error(`Unknown role: ${role}`);
  }
  
  return {
    email: roleConfig.email,
    password: roleConfig.password
  };
}

/**
 * Navigate to login page
 */
export async function gotoLoginPage(page: Page): Promise<void> {
  await page.goto('/login');
  // Wait for the page to be fully loaded
  await page.waitForLoadState('networkidle');
}

/**
 * Perform login with given credentials
 */
export async function login(
  page: Page, 
  email: string, 
  password: string,
  options: { rememberMe?: boolean; expectSuccess?: boolean } = {}
): Promise<boolean> {
  const { rememberMe = false, expectSuccess = true } = options;
  
  await gotoLoginPage(page);
  
  // Fill in credentials
  const emailInput = page.locator('input[name="email"], input[type="email"]').first();
  const passwordInput = page.locator('input[name="password"]').first();
  
  await emailInput.fill(email);
  await passwordInput.fill(password);
  
  // Handle remember me checkbox if present
  if (rememberMe) {
    const rememberCheckbox = page.locator('input[name="remember"]').first();
    if (await rememberCheckbox.isVisible()) {
      await rememberCheckbox.check();
    }
  }
  
  // Submit the form
  await page.locator('button[type="submit"]').click();
  
  // Wait for navigation or error
  try {
    await page.waitForURL(/\/(dashboard|home|admin)/, { timeout: 5000 });
    if (expectSuccess) {
      return true;
    }
  } catch (e) {
    // Check for error message if we expected success
    if (expectSuccess) {
      const errorElement = page.locator('.alert-danger, .alert-error, .error-message, [role="alert"]');
      const errorText = await errorElement.textContent().catch(() => '');
      if (errorText) {
        console.error('Login failed with error:', errorText);
      }
      return false;
    }
  }
  
  return !expectSuccess;
}

/**
 * Login as a specific role
 */
export async function loginAs(
  page: Page,
  role: UserRole,
  options: { rememberMe?: boolean } = {}
): Promise<boolean> {
  const creds = getCredentials(role);
  
  if (!creds.email || !creds.password) {
    console.log(`Role '${role}' does not require credentials`);
    return true;
  }
  
  return login(page, creds.email, creds.password, options);
}

/**
 * Logout the current user
 */
export async function logout(page: Page): Promise<void> {
  // Try multiple selectors for logout button/link
  const logoutSelectors = [
    'button:has-text("Logout")',
    'a:has-text("Logout")',
    'a:has-text("Sign Out")',
    'button:has-text("Sign Out")',
    '[href*="logout"]',
    'form[action*="logout"] button[type="submit"]'
  ];
  
  for (const selector of logoutSelectors) {
    const element = page.locator(selector).first();
    if (await element.isVisible().catch(() => false)) {
      await element.click();
      await page.waitForURL(/\/login/, { timeout: 5000 }).catch(() => {
        // If not redirected to login, check current URL
      });
      return;
    }
  }
  
  // If no logout button found, try direct navigation
  await page.goto('/logout', { method: 'POST' }).catch(() => {});
}

/**
 * Check if user is currently logged in
 */
export async function isLoggedIn(page: Page): Promise<boolean> {
  // Check for user-related elements that appear when logged in
  const loggedInIndicators = [
    '.user-menu',
    '.user-name',
    '[data-user-menu]',
    'a:has-text("Profile")',
    'a:has-text("Logout")'
  ];
  
  for (const selector of loggedInIndicators) {
    if (await page.locator(selector).first().isVisible().catch(() => false)) {
      return true;
    }
  }
  
  return false;
}

/**
 * Assert that user is logged in
 */
export async function assertLoggedIn(page: Page): Promise<void> {
  const loggedIn = await isLoggedIn(page);
  if (!loggedIn) {
    throw new Error('User is not logged in but expected to be');
  }
}

/**
 * Assert that user is NOT logged in (guest)
 */
export async function assertGuest(page: Page): Promise<void> {
  const loggedIn = await isLoggedIn(page);
  if (loggedIn) {
    throw new Error('User is logged in but expected to be a guest');
  }
}

/**
 * Login and assert success
 */
export async function loginAndAssert(
  page: Page,
  role: UserRole,
  options: { rememberMe?: boolean } = {}
): Promise<void> {
  const success = await loginAs(page, role, options);
  if (!success) {
    throw new Error(`Failed to login as ${role}`);
  }
  await assertLoggedIn(page);
}

/**
 * Get the current user's role (if detectable from UI)
 */
export async function getCurrentRole(page: Page): Promise<UserRole | null> {
  // Check for admin indicators
  const adminIndicators = ['.admin-menu', '[data-admin]', 'a[href*="admin"]'];
  for (const selector of adminIndicators) {
    if (await page.locator(selector).first().isVisible().catch(() => false)) {
      return 'admin';
    }
  }
  
  // Check if logged in at all
  if (await isLoggedIn(page)) {
    return 'authenticated';
  }
  
  return 'guest';
}
