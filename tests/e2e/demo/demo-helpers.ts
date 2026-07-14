/**
 * Shared helpers for the demo (video walkthrough) specs.
 */
import { Page } from '@playwright/test';
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
