import { test, expect } from '@playwright/test';

/**
 * IIIF Demo - End-to-End Verification
 *
 * Drives the exact flow used in the "IIIF in Heratio" podcast demo so the
 * live shoot is de-risked. If these pass headless, the on-camera demo works.
 *
 * Hero record: the 1591 de Bry engraving "Ceremonies Performed by Saturioua"
 *   show page      /flowers-in-the-garden
 *   dedicated view /iiif-viewer/flowers-in-the-garden   (full Mirador)
 *   manifest       /iiif-manifest/flowers-in-the-garden (IIIF Presentation)
 *
 * Demo 2 side-by-side external source (verified reachable):
 *   https://iiif.archive.org/iiif/brevisnarratioeo00lemo_1/manifest.json
 *
 * Run against the public demo host:
 *   APP_URL=https://heratio.theahg.co.za npx playwright test 07-iiif-demo --project=chromium
 */

const SLUG = 'flowers-in-the-garden';
const IA_MANIFEST = 'https://iiif.archive.org/iiif/brevisnarratioeo00lemo_1/manifest.json';

test.describe('IIIF podcast demo - end to end', () => {

  test('record show page loads (cold open target)', async ({ page }) => {
    const resp = await page.goto(`/${SLUG}`);
    expect(resp?.status()).toBe(200);
    // The IIIF badge is rendered on the show page.
    await expect(page.locator('body')).toContainText(/IIIF/i);
  });

  test('IIIF image service serves the engraving (Cantaloupe live)', async ({ page }) => {
    // The record manifest declares the image service; hit it and confirm the
    // pipeline returns a real info.json with dimensions (not a 501).
    const manifestResp = await page.request.get(`/iiif-manifest/${SLUG}`);
    expect(manifestResp.status()).toBe(200);
    const manifest = await manifestResp.json();

    // Presentation 3.0 path: items(canvas)[0].items(page)[0].items(anno)[0].body.service[0].id
    const anno = manifest?.items?.[0]?.items?.[0]?.items?.[0];
    const service = anno?.body?.service?.[0]?.id
      || anno?.body?.service?.[0]?.['@id'];
    expect(service, 'manifest should reference an /iiif/ image service').toBeTruthy();
    expect(service).toContain('/iiif/');

    const infoResp = await page.request.get(`${service}/info.json`);
    expect(infoResp.status(), `info.json for ${service}`).toBe(200);
    const info = await infoResp.json();
    expect(info.width).toBeGreaterThan(500);
    expect(info.height).toBeGreaterThan(500);
    console.log(`  image service OK: ${info.width}x${info.height} @ ${service}`);
  });

  test('deep-zoom viewer renders in the browser (Demo 1)', async ({ page }) => {
    // Count successful IIIF tile responses - proves Cantaloupe actually served pixels.
    let tileHits = 0;
    page.on('response', r => {
      if (r.url().includes('/iiif/') && r.status() === 200
          && /\.(jpg|png)\b|default/.test(r.url())) tileHits++;
    });

    await page.goto(`/iiif-viewer/${SLUG}`, { waitUntil: 'networkidle' });

    const mount = page.locator('#mirador-mount');
    await expect(mount).toBeVisible();
    await expect(mount.locator('canvas').first()).toBeVisible({ timeout: 20000 });

    // Mirador nav settles to "1 of 1" once the canvas is loaded (not "1 of 0").
    await expect(mount).toContainText(/1 of 1/, { timeout: 20000 });

    // The decisive check: the largest canvas has actually painted (not blank).
    // A blank OSD canvas is all-zero pixels; a rendered engraving is not.
    await expect.poll(async () => page.evaluate(() => {
      const cs = [...document.querySelectorAll('#mirador-mount canvas')];
      let best = null, area = 0;
      for (const c of cs) { const a = c.width * c.height; if (a > area) { area = a; best = c; } }
      if (!best) return 0;
      try {
        const d = best.getContext('2d').getImageData(0, 0, best.width, best.height).data;
        let nonBlank = 0;
        for (let i = 0; i < d.length; i += 4) if (d[i] || d[i + 1] || d[i + 2]) nonBlank++;
        return Math.round(100 * nonBlank / (d.length / 4));
      } catch (e) { return -1; }
    }), { timeout: 20000, message: 'canvas should paint real image pixels' })
      .toBeGreaterThan(20);

    expect(tileHits, 'IIIF tiles should have been served 200').toBeGreaterThan(0);

    await page.screenshot({ path: test.info().outputPath('demo1-deepzoom.png') });
    await test.info().attach('demo1-deepzoom', {
      path: test.info().outputPath('demo1-deepzoom.png'),
      contentType: 'image/png',
    });
  });

  test('mirador shows the object window (Demo 2 base)', async ({ page }) => {
    await page.goto(`/iiif-viewer/${SLUG}`);
    // Mirador renders the manifest label in the window title bar.
    await expect(page.locator('#mirador-mount canvas').first())
      .toBeVisible({ timeout: 20000 });
    // Window chrome exists (Mirador window top bar).
    await expect(page.locator('#mirador-mount [class*="window"]').first())
      .toBeVisible({ timeout: 20000 });
  });

  test('external Internet Archive manifest loads (Demo 2 side-by-side dependency)', async ({ page }) => {
    // The on-camera side-by-side pastes this manifest into a second Mirador
    // window. Verify the network path from a browser context resolves and the
    // payload is a valid IIIF Presentation 3.0 manifest with real canvases.
    const resp = await page.request.get(IA_MANIFEST);
    expect(resp.status(), 'Internet Archive manifest').toBe(200);
    const m = await resp.json();
    const ctx = JSON.stringify(m['@context'] || '');
    expect(ctx).toContain('presentation/3');
    expect(Array.isArray(m.items)).toBeTruthy();
    expect(m.items.length).toBeGreaterThan(10);
    console.log(`  IA manifest OK: ${m.items.length} canvases`);
  });
});
