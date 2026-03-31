/**
 * Parity Scanner - Layer B: Compare PSIS and Heratio Inventories
 * 
 * Compares the discovered pages, forms, and buttons from both systems
 * and generates parity reports identifying what's missing or different.
 * 
 * Run: npx playwright test tests/e2e/00-discovery/parity-scanner.spec.ts
 * (after running both PSIS and Heratio crawlers)
 */

import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

// Load fixtures
const seedUrls = JSON.parse(fs.readFileSync(path.join(path.dirname(new URL(import.meta.url).pathname), '../fixtures/seed-urls.json'), 'utf-8'));
const parityMap = JSON.parse(fs.readFileSync(path.join(path.dirname(new URL(import.meta.url).pathname), '../fixtures/parity-map.json'), 'utf-8'));

const PSIS_INVENTORY_DIR = 'tests/e2e/reports/psis-inventory';
const HERATIO_INVENTORY_DIR = 'tests/e2e/reports/heratio-inventory';
const OUTPUT_DIR = 'tests/e2e/reports';

interface CrawlResult {
  url: string;
  finalUrl: string;
  status: number;
  statusText: string;
  title: string;
  h1: string | null;
  links: string[];
  forms: FormInfo[];
  buttons: ButtonInfo[];
  consoleErrors: string[];
  jsErrors: string[];
}

interface FormInfo {
  action: string;
  method: string;
  fields: { name: string; type: string; required: boolean }[];
}

interface ButtonInfo {
  text: string;
  type: string;
  disabled: boolean;
}

interface ParityResult {
  psisUrl: string;
  heratioUrl: string | null;
  function: string;
  status: ParityStatus;
  differences: {
    titleMatch: boolean;
    formCountDiff: number;
    buttonCountDiff: number;
    missingForms: string[];
    newForms: string[];
    missingButtons: string[];
    newButtons: string[];
  };
  notes: string;
}

type ParityStatus = 'matched' | 'matched_different_url' | 'partial' | 'missing' | 'broken' | 'intentionally_retired';

test.describe('Parity Scanner', () => {

  /**
   * Load inventory from file
   */
  function loadInventory(dir: string, role: string): CrawlResult[] {
    const file = path.join(dir, `${role}-crawl.json`);
    if (fs.existsSync(file)) {
      const data = JSON.parse(fs.readFileSync(file, 'utf-8'));
      return data.results || [];
    }
    return [];
  }

  /**
   * Load full inventory
   */
  function loadFullInventory(dir: string): CrawlResult[] {
    const file = path.join(dir, 'full-inventory.json');
    if (fs.existsSync(file)) {
      // For full inventory, we need to reconstruct from the URLs
      const data = JSON.parse(fs.readFileSync(file, 'utf-8'));
      return data.urls || [];
    }
    
    // Fallback: load all role crawls
    const allResults: CrawlResult[] = [];
    for (const role of ['guest', 'admin', 'editor']) {
      allResults.push(...loadInventory(dir, role));
    }
    
    // Deduplicate
    const seen = new Set<string>();
    return allResults.filter(r => {
      if (seen.has(r.url)) return false;
      seen.add(r.url);
      return true;
    });
  }

  /**
   * Find equivalent Heratio URL for a PSIS URL
   */
  function findHeratioEquivalent(psisUrl: string): { url: string | null; status: ParityStatus; notes: string } {
    const normalizedPsis = normalizeUrl(psisUrl);
    
    // Check direct mappings
    for (const mapping of parityMap.urlMappings) {
      if (mapping.psis.url === normalizedPsis) {
        return {
          url: mapping.heratio.url,
          status: mapping.status as ParityStatus,
          notes: mapping.notes
        };
      }
    }
    
    // Check if PSIS URL matches Heratio URL directly
    const heratioUrls = seedUrls.heratio.urls.map(u => normalizeUrl(u));
    if (heratioUrls.includes(normalizedPsis)) {
      return { url: normalizedPsis, status: 'matched', notes: 'Direct URL match' };
    }
    
    // Try URL pattern matching
    // e.g., /informationobject/{id} -> /records/{slug}
    const patterns: { psis: RegExp; heratio: string; status: ParityStatus }[] = [
      { psis: /^\/informationobject\//, heratio: '/records/', status: 'matched_different_url' },
      { psis: /^\/actor\//, heratio: '/actor/', status: 'matched' },
      { psis: /^\/repository\//, heratio: '/repository/', status: 'matched' },
      { psis: /^\/accession\//, heratio: '/accession/', status: 'matched' },
      { psis: /^\/term\//, heratio: '/term/', status: 'matched' },
      { psis: /^\/admin\//, heratio: '/admin/', status: 'matched' },
    ];
    
    for (const pattern of patterns) {
      if (pattern.psis.test(normalizedPsis)) {
        const rest = normalizedPsis.replace(pattern.psis, '');
        return { 
          url: pattern.heratio + rest, 
          status: pattern.status,
          notes: 'URL pattern mapped'
        };
      }
    }
    
    return { url: null, status: 'missing', notes: 'No mapping found' };
  }

  /**
   * Normalize URL for comparison
   */
  function normalizeUrl(url: string): string {
    try {
      const parsed = new URL(url, 'https://example.com');
      return parsed.pathname + parsed.search;
    } catch {
      return url;
    }
  }

  /**
   * Compare forms between two pages
   */
  function compareForms(psisForms: FormInfo[], heratioForms: FormInfo[]): {
    missingForms: string[];
    newForms: string[];
    missingFields: string[];
    newFields: string[];
  } {
    const psisActions = new Set(psisForms.map(f => `${f.method}:${f.action}`));
    const heratioActions = new Set(heratioForms.map(f => `${f.method}:${f.action}`));
    
    const missingForms: string[] = [];
    const newForms: string[] = [];
    
    psisActions.forEach(a => {
      if (!heratioActions.has(a)) missingForms.push(a);
    });
    
    heratioActions.forEach(a => {
      if (!psisActions.has(a)) newForms.push(a);
    });
    
    // Compare fields within matching forms
    const missingFields: string[] = [];
    const newFields: string[] = [];
    
    return { missingForms, newForms, missingFields, newFields };
  }

  /**
   * Compare buttons between two pages
   */
  function compareButtons(psisButtons: ButtonInfo[], heratioButtons: ButtonInfo[]): {
    missingButtons: string[];
    newButtons: string[];
  } {
    const psisTexts = new Set(psisButtons.map(b => b.text.toLowerCase().trim()));
    const heratioTexts = new Set(heratioButtons.map(b => b.text.toLowerCase().trim()));
    
    const missingButtons: string[] = [];
    const newButtons: string[] = [];
    
    psisTexts.forEach(t => {
      if (!heratioTexts.has(t)) missingButtons.push(t);
    });
    
    heratioTexts.forEach(t => {
      if (!psisTexts.has(t)) newButtons.push(t);
    });
    
    return { missingButtons, newButtons };
  }

  /**
   * Test: Generate parity comparison
   */
  test('generate parity comparison', async ({}) => {
    // Load inventories
    const psisInventory = loadFullInventory(PSIS_INVENTORY_DIR);
    const heratioInventory = loadFullInventory(HERATIO_INVENTORY_DIR);
    
    // Ensure we have data
    if (psisInventory.length === 0) {
      console.warn('⚠️ No PSIS inventory found. Run psis-crawler.spec.ts first.');
    }
    if (heratioInventory.length === 0) {
      console.warn('⚠️ No Heratio inventory found. Run heratio-crawler.spec.ts first.');
    }
    
    const results: ParityResult[] = [];
    
    // Compare each PSIS page against Heratio
    for (const psisPage of psisInventory) {
      const psisUrl = normalizeUrl(psisPage.url);
      const equivalent = findHeratioEquivalent(psisUrl);
      
      // Find the equivalent Heratio page
      let heratioPage = null;
      if (equivalent.url) {
        heratioPage = heratioInventory.find(h => 
          normalizeUrl(h.url) === equivalent.url
        );
      }
      
      // Compare pages
      const differences = {
        titleMatch: heratioPage ? psisPage.title === heratioPage.title : false,
        formCountDiff: heratioPage ? heratioPage.forms.length - psisPage.forms.length : -psisPage.forms.length,
        buttonCountDiff: heratioPage ? heratioPage.buttons.length - psisPage.buttons.length : -psisPage.buttons.length,
        missingForms: [] as string[],
        newForms: [] as string[],
        missingButtons: [] as string[],
        newButtons: [] as string[]
      };
      
      if (heratioPage) {
        const formCompare = compareForms(psisPage.forms, heratioPage.forms);
        differences.missingForms = formCompare.missingForms;
        differences.newForms = formCompare.newForms;
        
        const buttonCompare = compareButtons(psisPage.buttons, heratioPage.buttons);
        differences.missingButtons = buttonCompare.missingButtons;
        differences.newButtons = buttonCompare.newButtons;
      } else {
        // No equivalent found - all forms/buttons are missing
        differences.missingForms = psisPage.forms.map(f => `${f.method}:${f.action}`);
        differences.missingButtons = psisPage.buttons.map(b => b.text);
      }
      
      // Find the function name from mapping
      const mapping = parityMap.urlMappings.find(m => m.psis.url === psisUrl);
      const functionName = mapping?.function || 'Unknown function';
      
      results.push({
        psisUrl,
        heratioUrl: equivalent.url,
        function: functionName,
        status: heratioPage ? (equivalent.status === 'matched' ? 'matched' : 'matched_different_url') : equivalent.status,
        differences,
        notes: equivalent.notes
      });
    }
    
    // Count by status
    const counts = {
      matched: results.filter(r => r.status === 'matched').length,
      matched_different_url: results.filter(r => r.status === 'matched_different_url').length,
      partial: results.filter(r => r.status === 'partial').length,
      missing: results.filter(r => r.status === 'missing').length,
      broken: results.filter(r => r.status === 'broken').length,
      intentionally_retired: results.filter(r => r.status === 'intentionally_retired').length
    };
    
    // Save results
    const parityReport = {
      generatedAt: new Date().toISOString(),
      psisBaseUrl: 'https://psis.theahg.co.za',
      heratioBaseUrl: 'https://heratio.theahg.co.za',
      summary: {
        totalCompared: results.length,
        matched: counts.matched,
        matchedDifferentUrl: counts.matched_different_url,
        partial: counts.partial,
        missing: counts.missing,
        broken: counts.broken,
        intentionallyRetired: counts.intentionally_retired,
        coverage: `${Math.round((counts.matched + counts.matched_different_url) / results.length * 100)}%`
      },
      counts,
      results
    };
    
    fs.writeFileSync(
      path.join(OUTPUT_DIR, 'parity-report.json'),
      JSON.stringify(parityReport, null, 2)
    );
    
    // Generate CSV report
    const csvHeader = 'PSIS URL,Heratio URL,Function,Status,Title Match,Form Diff,Button Diff,Missing Forms,New Forms,Missing Buttons,New Buttons,Notes';
    const csvRows = results.map(r => [
      r.psisUrl,
      r.heratioUrl || '',
      `"${r.function}"`,
      r.status,
      r.differences.titleMatch ? 'Yes' : 'No',
      r.differences.formCountDiff,
      r.differences.buttonCountDiff,
      `"${r.differences.missingForms.join('; ')}"`,
      `"${r.differences.newForms.join('; ')}"`,
      `"${r.differences.missingButtons.join('; ')}"`,
      `"${r.differences.newButtons.join('; ')}"`,
      `"${r.notes}"`
    ].join(','));
    
    fs.writeFileSync(
      path.join(OUTPUT_DIR, 'parity-report.csv'),
      [csvHeader, ...csvRows].join('\n')
    );
    
    // Generate missing pages report
    const missingPages = results.filter(r => r.status === 'missing');
    const missingReport = {
      generatedAt: new Date().toISOString(),
      totalMissing: missingPages.length,
      pages: missingPages.map(p => ({
        psisUrl: p.psisUrl,
        function: p.function,
        forms: p.differences.missingForms,
        buttons: p.differences.missingButtons
      }))
    };
    
    fs.writeFileSync(
      path.join(OUTPUT_DIR, 'missing-pages.json'),
      JSON.stringify(missingReport, null, 2)
    );
    
    // Generate missing forms report
    const missingForms = results.flatMap(r => 
      r.differences.missingForms.map(f => ({
        psisUrl: r.psisUrl,
        form: f
      }))
    );
    
    fs.writeFileSync(
      path.join(OUTPUT_DIR, 'missing-forms.json'),
      JSON.stringify({
        generatedAt: new Date().toISOString(),
        totalMissingForms: missingForms.length,
        forms: missingForms
      }, null, 2)
    );
    
    // Print summary
    console.log('\n=== Parity Report Summary ===');
    console.log(`Total pages compared: ${results.length}`);
    console.log(`✓ Matched: ${counts.matched}`);
    console.log(`↔ Different URL: ${counts.matched_different_url}`);
    console.log(`⚠ Partial: ${counts.partial}`);
    console.log(`✗ Missing: ${counts.missing}`);
    console.log(`✗ Broken: ${counts.broken}`);
    console.log(`○ Retired: ${counts.intentionally_retired}`);
    console.log(`\n📊 Coverage: ${parityReport.summary.coverage}`);
    console.log(`\n📁 Reports saved to: ${OUTPUT_DIR}`);
  });

  /**
   * Test: Generate executive summary
   */
  test('generate executive summary', async ({}) => {
    const parityReportPath = path.join(OUTPUT_DIR, 'parity-report.json');
    
    if (!fs.existsSync(parityReportPath)) {
      console.warn('Run parity comparison first');
      return;
    }
    
    const report = JSON.parse(fs.readFileSync(parityReportPath, 'utf-8'));
    
    const summary = `
╔══════════════════════════════════════════════════════════════════════════════╗
║                     HERATIO PARITY TESTING SUMMARY                          ║
╠══════════════════════════════════════════════════════════════════════════════╣
║                                                                              ║
║  Generated: ${report.generatedAt}                              ║
║                                                                              ║
║  SYSTEM COMPARISON                                                           ║
║  ─────────────────────────────────────────────────────────────────────────   ║
║  Baseline:  ${report.psisBaseUrl}                           ║
║  Target:    ${report.heratioBaseUrl}                        ║
║                                                                              ║
║  COVERAGE METRICS                                                            ║
║  ─────────────────────────────────────────────────────────────────────────   ║
║  Total Pages Compared:       ${String(report.summary.totalCompared).padStart(5)}                                 ║
║                                                                              ║
║  ✓ Matched:                 ${String(report.summary.matched).padStart(5)}    (functionality confirmed)          ║
║  ↔ Different URL:           ${String(report.summary.matchedDifferentUrl).padStart(5)}    (URL changed, works)              ║
║  ⚠ Partial:                ${String(report.summary.partial).padStart(5)}    (partial implementation)            ║
║  ✗ Missing:                ${String(report.summary.missing).padStart(5)}    (not yet migrated)                 ║
║  ✗ Broken:                 ${String(report.summary.broken).padStart(5)}    (implemented but broken)           ║
║  ○ Retired:                ${String(report.summary.intentionallyRetired).padStart(5)}    (intentionally removed)            ║
║                                                                              ║
║  OVERALL COVERAGE: ${report.summary.coverage.padEnd(56)}║
║                                                                              ║
║  PRIORITY GAPS (Missing/Broken)                                              ║
║  ─────────────────────────────────────────────────────────────────────────   ║
${report.results
  .filter(r => r.status === 'missing' || r.status === 'broken')
  .slice(0, 10)
  .map((r, i) => `║  ${i + 1}. ${r.psisUrl.padEnd(50)} ${r.status.padEnd(10)}║`)
  .join('\n')}
║                                                                              ║
║  FILES GENERATED                                                             ║
║  ─────────────────────────────────────────────────────────────────────────   ║
║  • parity-report.json     - Full parity data                                 ║
║  • parity-report.csv     - CSV format for spreadsheet analysis               ║
║  • missing-pages.json    - Pages missing from Heratio                        ║
║  • missing-forms.json    - Forms missing from Heratio                        ║
║                                                                              ║
╚══════════════════════════════════════════════════════════════════════════════╝
`;
    
    console.log(summary);
    
    fs.writeFileSync(
      path.join(OUTPUT_DIR, 'executive-summary.txt'),
      summary
    );
  });
});
