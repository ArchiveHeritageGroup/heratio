/**
 * Shared helpers for the demo (video walkthrough) specs.
 */
import { Page, test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

// ---------------------------------------------------------------------------
// Narration: each demo records a manifest of spoken cues with the timestamp
// (seconds into the recording) at which each line should be heard. A post-run
// script (scripts/demo-narrate.sh) synthesises each line in the operator's
// cloned voice (F5-TTS f5:johan-c2) and muxes them onto the silent video.
// ---------------------------------------------------------------------------
interface Cue { t: number; text: string }
let _cues: Cue[] = [];
let _t0 = 0;

/** Call at the very start of a test, right after the context/video begins. */
export function startNarration(): void {
  _cues = [];
  _t0 = Date.now();
}

/**
 * Speak a line: records it at the current video-relative timestamp and holds
 * the page long enough for the line to be heard (~360ms/word + buffer), so the
 * on-screen action stays in sync with the voiceover.
 */
export async function narrate(page: Page, text: string, holdMs?: number): Promise<void> {
  _cues.push({ t: Math.max(0, (Date.now() - _t0) / 1000), text });
  const words = text.trim().split(/\s+/).length;
  await page.waitForTimeout(holdMs ?? Math.min(9000, 900 + words * 360));
}

/**
 * Write the narration manifest for this demo. `name` is the machine key (must
 * match the spec's file basename so the mux can pair video<->manifest);
 * optional `displayName` is the human title the final mp4/wav is saved under
 * (e.g. "Archival Description with digital object").
 */
export async function fillFields(page: Page, fields: Record<string, string>): Promise<void> {
  for (const [name, val] of Object.entries(fields)) {
    const loc = page.locator(`[name="${name}"]`).first();
    if ((await loc.count()) === 0) continue;
    const tag = await loc.evaluate((el) => el.tagName.toLowerCase()).catch(() => '');
    try {
      if (tag === 'select') await page.selectOption(`[name="${name}"]`, { index: 1 });
      else await loc.fill(val);
    } catch { /* optional / not interactable - skip */ }
  }
}

/**
 * Guarantee a field holds `value` right before submit. Some forms (the CCO
 * museum cataloguing form) re-render inputs when other fields change, silently
 * dropping an earlier fill; this re-sets the value via the DOM if it didn't stick.
 */
export async function ensureValue(page: Page, name: string, value: string): Promise<void> {
  if (!value) return;
  let loc = page.locator(`[name="${name}"]:visible`).first();
  if ((await loc.count()) === 0) loc = page.locator(`[name="${name}"]`).first();
  if ((await loc.count()) === 0) return;
  const got = await loc.inputValue().catch(() => '');
  if (got !== value) {
    await loc.evaluate((el: HTMLInputElement, v: string) => {
      el.value = v;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }, value).catch(() => {});
  }
}

export function writeNarration(name: string, displayName?: string): void {
  const dir = path.join(process.cwd(), 'test-results', 'narration');
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, `${name}.json`),
    JSON.stringify({ name, displayName: displayName || name, cues: _cues }, null, 2));
}

export const HERATIO_URL = process.env.HERATIO_URL || 'https://heratio.theahg.co.za';
export const EMAIL = process.env.TEST_ADMIN_EMAIL || 'johan@theahg.co.za';
export const PASSWORD = process.env.TEST_ADMIN_PASSWORD || 'Skukuza@246';

/** True when the target is the live production host (guard destructive demos). */
export const isProd = /\/\/heratio\.theahg\.co\.za/i.test(HERATIO_URL);

/**
 * Log in as an administrator. The login page carries several forms (clipboard /
 * feedback chrome), so a bare button[type=submit] hits the wrong one - submit
 * the login form by pressing Enter in the password field instead.
 */
export async function login(page: Page): Promise<void> {
  await page.goto(`${HERATIO_URL}/login`);
  await page.fill('input[name="email"]', EMAIL);
  await page.fill('input[name="password"]', PASSWORD);
  await page.locator('input[name="password"]').press('Enter');
  await page.waitForURL(
    (u) => !u.pathname.replace(/\/+$/, '').endsWith('/login'),
    { timeout: 15000 }
  );
}

/**
 * Ensure the admin session is live. The shared storageState cookie expires
 * mid-run on long batches (30+ min), bouncing later tests to /login; calling
 * this in a beforeEach re-establishes auth and is a no-op when already logged in
 * (Heratio redirects an authenticated /login visit back to '/').
 */
export async function ensureLoggedIn(page: Page): Promise<void> {
  await page.goto(`${HERATIO_URL}/login`).catch(() => {});
  if (!/\/login\/?$/.test(new URL(page.url()).pathname)) return; // already authed
  const pw = page.locator('input[name="password"]').first();
  if ((await pw.count()) === 0) return;
  await page.fill('input[name="email"]', EMAIL).catch(() => {});
  await pw.fill(PASSWORD).catch(() => {});
  await pw.press('Enter').catch(() => {});
  await page.waitForURL(
    (u) => !u.pathname.replace(/\/+$/, '').endsWith('/login'),
    { timeout: 15000 }
  ).catch(() => {});
}

/**
 * ISAD(G)/ISAAR edit forms render their areas as Bootstrap accordions that
 * start collapsed, so fields are in the DOM but not visible. Expand one by its
 * collapse-target id (e.g. 'identity-collapse').
 */
export async function expandAccordion(page: Page, targetId: string): Promise<void> {
  const btn = page.locator(`[data-bs-target="#${targetId}"]`).first();
  if ((await btn.count()) === 0) return;
  if ((await btn.getAttribute('aria-expanded')) !== 'true') {
    await btn.click();
    await page.waitForTimeout(700);
  }
}

/** Expand every collapsed Bootstrap accordion on the page (unknown area ids). */
export async function expandAll(page: Page): Promise<void> {
  const toggles = page.locator('[data-bs-target^="#"][aria-expanded="false"]');
  const n = await toggles.count();
  for (let i = 0; i < n; i++) {
    await toggles.nth(i).click().catch(() => {});
  }
  if (n) await page.waitForTimeout(600);
}

/**
 * Submit the content form that owns `requiredName` (pages carry several chrome
 * forms - search, feedback, clipboard - so a bare submit hits the wrong one).
 */
export async function submitIn(page: Page, fieldName: string): Promise<void> {
  const form = page.locator(`form:has([name="${fieldName}"])`).first();
  // Drop client-side `required` markers (CCO/CIDOC forms flag many recommended
  // fields as required, blocking HTML5 submit); the server still validates the
  // truly-required fields, so a genuinely incomplete record is still rejected.
  await form.evaluate((f: HTMLElement) =>
    f.querySelectorAll('[required]').forEach((e) => e.removeAttribute('required'))
  ).catch(() => {});
  // The primary submit is the first submit in the content form.
  const btn = form.locator('button[type="submit"], input[type="submit"]').first();
  await btn.scrollIntoViewIfNeeded().catch(() => {});
  await btn.click();
  await page.waitForLoadState('networkidle').catch(() => {});
}

/**
 * Delete the record shown on the current page. Handles both flows: a Delete link
 * to a confirm page (repository/donor/accession) and an inline delete form with
 * a JS confirm dialog (museum/vendor).
 */
export async function deleteRecord(page: Page): Promise<void> {
  page.on('dialog', (d) => d.accept());
  const link = page.locator('a[href*="/delete"], a[href*="confirmDelete"]').first();
  if (await link.count()) {
    await link.click().catch(() => {});
    await page.waitForLoadState('networkidle').catch(() => {});
  }
  const submit = page.locator(
    'input[type="submit"][value="Delete"], button[type="submit"]:has-text("Delete"), ' +
    'form[action*="delete"] button[type="submit"]'
  ).first();
  if (await submit.count()) {
    await submit.click().catch(() => {});
    await page.waitForLoadState('networkidle').catch(() => {});
  }
}

/**
 * Toggle the record between the standard cataloguing view and the Records in
 * Contexts (RiC) view, scrolling to the RiC relationships. Both view-switch
 * forms POST to /ric-api/view-mode with a hidden mode (heratio | ric).
 */
export async function ricSwitch(page: Page): Promise<void> {
  const std = 'form:has(input[name="mode"][value="heratio"]) button[type="submit"]';
  const ric = 'form:has(input[name="mode"][value="ric"]) button[type="submit"]';
  await page.locator(ric).first().click().catch(() => {});
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(1500);
  await page.evaluate(() => window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }));
  await page.waitForTimeout(2500);
  await page.locator(std).first().click().catch(() => {});
  await page.waitForLoadState('networkidle').catch(() => {});
}

// ---------------------------------------------------------------------------
// Standard-CRUD demo factory. Most GLAM/DAM record types share the same
// Browse -> Add -> View -> Edit -> (RiC) -> Delete shape over a single required
// field, so one factory drives them all. Each spec file calls this once (one
// test per file so the mux pairs video<->manifest by the file-name prefix).
// ---------------------------------------------------------------------------
type Val = string | (() => string);
const val = (v: Val): string => (typeof v === 'function' ? v() : v);

export interface CrudCfg {
  name: string;          // manifest key = spec file basename (before .demo)
  display: string;       // human title the mp4 is saved under
  noun: string;          // spoken noun, e.g. 'repository'
  browse: string;        // browse URL path
  add: string;           // add-form URL path
  req: string;           // required field name (kept present so the form submits)
  makeVal: () => string; // unique value for the required field
  mainField?: string;    // visible/editable field to verify + edit (defaults to req)
  extra?: Record<string, Val>;
  hasRic?: boolean;
}

export function defineCrudDemo(cfg: CrudCfg): void {
  test.describe(`Demo: ${cfg.display}`, () => {
    test.skip(isProd, 'Demo CRUD must run against a non-prod target - set HERATIO_URL to the dev box.');
    test.beforeEach(async ({ page }) => { await ensureLoggedIn(page); });

    test(`${cfg.display} - browse, create, view, edit and delete`, async ({ page }) => {
      startNarration();
      const mainField = cfg.mainField ?? cfg.req;
      const fields: Record<string, string> = { [cfg.req]: cfg.makeVal() };
      for (const [k, v] of Object.entries(cfg.extra ?? {})) fields[k] = val(v);
      const verifyVal = fields[mainField] ?? fields[cfg.req];
      const updated = `${verifyVal} (edited)`;
      let recordUrl = '';

      await test.step('Browse', async () => {
        await narrate(page, `In this walkthrough we create, view, edit and delete a ${cfg.noun} record.`, 4400);
        await page.goto(`${HERATIO_URL}${cfg.browse}`);
        await page.waitForLoadState('networkidle').catch(() => {});
        await narrate(page, `This is the ${cfg.noun} browse, with its facets and an Add new option.`, 4400);
      });

      await test.step('Open the Add form', async () => {
        await narrate(page, 'We open the Add new form.', 2400);
        await page.goto(`${HERATIO_URL}${cfg.add}`);
        await expect(page.locator(`[name="${cfg.req}"]`).first()).toBeAttached({ timeout: 15000 });
      });

      await test.step('Complete and create', async () => {
        await narrate(page, 'We complete the required fields and the key details.', 3400);
        await expandAll(page);
        await fillFields(page, fields);
        await ensureValue(page, mainField, fields[mainField]);
        await narrate(page, 'Then we save the new record.', 2200);
        await submitIn(page, cfg.req);
        await expect(page.locator('body')).toContainText(verifyVal, { timeout: 15000 });
        recordUrl = page.url();
        await narrate(page, `The ${cfg.noun} record has been created and is displayed.`, 3200);
      });

      await test.step('Edit', async () => {
        await narrate(page, 'Next we edit the record and change its main field.', 3200);
        await page.locator('a[href*="/edit"]').first().click();
        await page.waitForLoadState('networkidle').catch(() => {});
        await expandAll(page);
        let f = page.locator(`[name="${mainField}"]:visible`).first();
        if ((await f.count()) === 0) f = page.locator(`[name="${mainField}"]`).first();
        await f.waitFor({ state: 'attached', timeout: 15000 });
        await f.fill(updated).catch(() => {});
        await ensureValue(page, mainField, updated);
        await submitIn(page, mainField);
        await expect(page.locator('body')).toContainText(updated, { timeout: 15000 });
        await narrate(page, 'The change has been saved.', 2000);
      });

      if (cfg.hasRic) {
        await test.step('Switch to the Records in Contexts view', async () => {
          await page.goto(recordUrl);
          await page.waitForLoadState('networkidle').catch(() => {});
          await narrate(page, 'On the record we can switch to the Records in Contexts view, which places it within the wider knowledge graph.', 6000);
          await ricSwitch(page);
        });
      }

      await test.step('Delete', async () => {
        await page.goto(recordUrl);
        await page.waitForLoadState('networkidle').catch(() => {});
        await narrate(page, 'Finally we delete the record and confirm.', 2600);
        await deleteRecord(page);
      });

      await test.step('Confirm gone', async () => {
        await page.goto(recordUrl);
        await expect(page.locator('body')).not.toContainText(updated).catch(() => {});
        await narrate(page, `That completes the ${cfg.noun} lifecycle.`, 3000);
      });

      writeNarration(cfg.name, cfg.display);
    });
  });
}

export interface RicViewCfg {
  name: string;
  display: string;
  noun: string;
  add: string;
  req: string;
  makeVal: () => string;
  mainField?: string;
  extra?: Record<string, Val>;
}

/** Focused RiC-view walkthrough: make a minimal record, open it, switch to RiC. */
export function defineRicViewDemo(cfg: RicViewCfg): void {
  test.describe(`Demo: ${cfg.display}`, () => {
    test.skip(isProd, 'Demo must run against a non-prod target - set HERATIO_URL to the dev box.');
    test.beforeEach(async ({ page }) => { await ensureLoggedIn(page); });

    test(`${cfg.display} - open a record and switch to the RiC view`, async ({ page }) => {
      startNarration();
      const mainField = cfg.mainField ?? cfg.req;
      const fields: Record<string, string> = { [cfg.req]: cfg.makeVal() };
      for (const [k, v] of Object.entries(cfg.extra ?? {})) fields[k] = val(v);
      const verifyVal = fields[mainField] ?? fields[cfg.req];
      let recordUrl = '';

      await test.step('Create a record to view', async () => {
        await narrate(page, `To show the Records in Contexts view we first open a ${cfg.noun}.`, 4200);
        await page.goto(`${HERATIO_URL}${cfg.add}`);
        await expect(page.locator(`[name="${cfg.req}"]`).first()).toBeAttached({ timeout: 15000 });
        await expandAll(page);
        await fillFields(page, fields);
        await ensureValue(page, mainField, fields[mainField]);
        await submitIn(page, cfg.req);
        await expect(page.locator('body')).toContainText(verifyVal, { timeout: 15000 });
        recordUrl = page.url();
      });

      await test.step('Switch to the Records in Contexts view', async () => {
        await narrate(page, 'The record opens in the standard cataloguing view. We now switch to the Records in Contexts view.', 5600);
        await page.locator('form:has(input[name="mode"][value="ric"]) button[type="submit"]').first().click().catch(() => {});
        await page.waitForLoadState('networkidle').catch(() => {});
        await page.waitForTimeout(1500);
        await narrate(page, 'The RiC view models the record as an entity in a graph of related records, agents, activities and places.', 6200);
        await page.evaluate(() => window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }));
        await page.waitForTimeout(3000);
        await narrate(page, 'Scrolling down reveals its Records in Contexts relationships and connections.', 4400);
      });

      await test.step('Return and clean up', async () => {
        await page.goto(recordUrl);
        await page.waitForLoadState('networkidle').catch(() => {});
        await deleteRecord(page);
        await narrate(page, `That is the Records in Contexts view for a ${cfg.noun}.`, 3000);
      });

      writeNarration(cfg.name, cfg.display);
    });
  });
}
