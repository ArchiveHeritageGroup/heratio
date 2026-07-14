/**
 * Demo auth setup: log in once and save the session so the demo specs start
 * already authenticated - the login screen never appears on camera.
 * Runs as the 'demo-setup' project (no video); the 'demo' project depends on it.
 */
import { test as setup } from '@playwright/test';
import { login } from './demo-helpers';

setup('authenticate', async ({ page }) => {
  await login(page);
  await page.context().storageState({ path: 'test-results/demo-auth.json' });
});
