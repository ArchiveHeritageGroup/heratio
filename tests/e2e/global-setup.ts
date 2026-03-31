/**
 * Global setup for Playwright tests
 * 
 * This runs once before all tests to set up the test environment.
 */

import * as fs from 'fs';
import * as path from 'path';

async function globalSetup() {
  console.log('=== Heratio E2E Test Setup ===');
  console.log(`Target: ${process.env.HERATIO_URL || 'https://heratio.theahg.co.za'}`);
  console.log(`Baseline: ${process.env.PSID_URL || 'https://psis.theahg.co.za'}`);
  
  // Ensure output directories exist
  const dirs = [
    'tests/e2e/artifacts',
    'tests/e2e/reports',
    'tests/e2e/reports/psis-inventory',
    'tests/e2e/reports/heratio-inventory',
  ];
  
  for (const dir of dirs) {
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
      console.log(`Created directory: ${dir}`);
    }
  }
  
  console.log('=== Setup Complete ===\n');
}

export default globalSetup;
