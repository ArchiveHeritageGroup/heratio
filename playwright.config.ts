import { defineConfig, devices } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Playwright configuration for Heratio E2E and Parity Testing
 * 
 * Run tests: npx playwright test
 * Open UI: npx playwright test --ui
 * Run specific suite: npx playwright test tests/e2e/00-discovery
 * 
 * @see https://playwright.dev/docs/test-configuration
 */

const testAccounts = {
  admin: {
    email: process.env.TEST_ADMIN_EMAIL || 'admin@test.ahg.co.za',
    password: process.env.TEST_ADMIN_PASSWORD || 'Admin@123'
  },
  editor: {
    email: process.env.TEST_EDITOR_EMAIL || 'editor@test.ahg.co.za',
    password: process.env.TEST_EDITOR_PASSWORD || 'Editor@123'
  },
  authenticated: {
    email: process.env.TEST_USER_EMAIL || 'user@test.ahg.co.za',
    password: process.env.TEST_USER_PASSWORD || 'TestUser@123'
  },
  researcher: {
    email: process.env.TEST_RESEARCHER_EMAIL || 'researcher@test.ahg.co.za',
    password: process.env.TEST_RESEARCHER_PASSWORD || 'Researcher@123'
  }
};

// Ensure output directories exist - use /tmp for artifacts due to permission issues
const artifactsDir = '/tmp/playwright-artifacts';
const reportsDir = 'tests/e2e/reports';
[artifactsDir, reportsDir].forEach(dir => {
  try {
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }
  } catch (e) {
    // Ignore permission errors
  }
});

export default defineConfig({
  testDir: './tests/e2e',
  // The 'demo' project (video walkthroughs) lives under tests/e2e/demo and is
  // excluded from the normal browser projects so it never runs in CI matrices.
  testIgnore: ['**/demo/**'],
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  
  // Reporter configuration - minimal due to permission issues
  reporter: [
    ['line']
  ],
  
  use: {
    // Base URL for Heratio (the system under test)
    baseURL: process.env.HERATIO_URL || 'https://heratio.theahg.co.za',
    
    // Tracing for debugging
    trace: 'on-first-retry',
    
    // Screenshot and video on failure
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    
    // Ignore HTTPS errors in development
    ignoreHTTPSErrors: true,
    
    // Navigation timeout
    navigationTimeout: 30000,
    
    // Action timeout
    actionTimeout: 10000,
  },

  // Global test timeout
  timeout: 60000,

  /* Configure projects for major browsers */
  projects: [
    // Demo walkthroughs: full-CRUD videos of each screen, for the how-to library.
    // Chromium only, 1080p, slowed down + single worker so the recording is
    // watchable. Run against a NON-PROD target:
    //   HERATIO_URL=http://192.168.0.112:8090 npx playwright test --project=demo --workers=1
    {
      name: 'demo',
      testDir: './tests/e2e/demo',
      testIgnore: [],
      fullyParallel: false,
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1920, height: 1080 },
        video: { mode: 'on', size: { width: 1920, height: 1080 } },
        screenshot: 'on',
        launchOptions: { slowMo: 350 },
        actionTimeout: 20000,
        navigationTimeout: 45000,
      },
    },

    // Primary test on Chromium (fastest for CI)
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    
    // Firefox for cross-browser testing
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    
    // WebKit for macOS/iOS compatibility
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },

    /* Test against mobile viewports. */
    {
      name: 'Mobile Chrome',
      use: { ...devices['Pixel 5'] },
    },
    {
      name: 'Mobile Safari',
      use: { ...devices['iPhone 12'] },
    },
  ],

  /* Run local dev server before starting the tests (development only) */
  // Disabled: Testing against remote URLs (HERATIO_URL, PSID_URL) not local dev server
  // webServer: process.env.CI ? undefined : {
  //   command: 'npm run dev',
  //   url: 'http://localhost:8000',
  //   reuseExistingServer: !process.env.CI,
  //   timeout: 120 * 1000,
  //   stdout: 'pipe',
  //   stderr: 'pipe',
  // },

});

/**
 * Export test accounts for use in tests
 */
export { testAccounts };
