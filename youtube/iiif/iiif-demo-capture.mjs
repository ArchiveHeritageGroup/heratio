// IIIF demo video capture - produces a clean, narratable webm of the
// deep-zoom walkthrough for the podcast. Not a test; a video producer.
//
//   APP_URL=https://heratio.theahg.co.za node youtube/iiif/iiif-demo-capture.mjs
//
// Output: youtube/iiif/iiif-deepzoom.webm
// Timed beats (see BEATS below) line up with a single-voice narration track.
import pkg from 'playwright';
const { chromium } = pkg;

const BASE = process.env.APP_URL || 'https://heratio.theahg.co.za';
const VIEWER_URL = `${BASE}/iiif-viewer/flowers-in-the-garden`;
const OUT_DIR = new URL('./', import.meta.url).pathname;
const W = 1280, H = 720;

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

async function waitPainted(page, min = 20, timeout = 20000) {
  const start = Date.now();
  while (Date.now() - start < timeout) {
    const pct = await page.evaluate(() => {
      const cs = [...document.querySelectorAll('#mirador-mount canvas')];
      let best = null, area = 0;
      for (const c of cs) { const a = c.width * c.height; if (a > area) { area = a; best = c; } }
      if (!best) return 0;
      try {
        const d = best.getContext('2d').getImageData(0, 0, best.width, best.height).data;
        let n = 0; for (let i = 0; i < d.length; i += 4) if (d[i] || d[i+1] || d[i+2]) n++;
        return Math.round(100 * n / (d.length / 4));
      } catch { return -1; }
    });
    if (pct >= min) return pct;
    await sleep(400);
  }
  return -1;
}

const b = await chromium.launch({ args: ['--force-color-profile=srgb'] });
const ctx = await b.newContext({
  viewport: { width: W, height: H },
  recordVideo: { dir: OUT_DIR, size: { width: W, height: H } },
});
const page = await ctx.newPage();

// ── BEAT 0: open + let the plate paint (narration: "This is a 1591 engraving…")
await page.goto(VIEWER_URL, { waitUntil: 'networkidle' });
const pct = await waitPainted(page);
console.log('painted %:', pct);
await sleep(3500);

// ── BEAT 1: deep zoom via mouse wheel (narration: "Watch this… down to the hatching")
// Wheel drives OpenSeadragon's viewport directly - reliable, and smooth on video.
const box = await page.locator('#mirador-mount canvas').first().boundingBox();
const cx = box.x + box.width / 2, cy = box.y + box.height * 0.42;
await page.mouse.move(cx, cy);
for (let i = 0; i < 14; i++) { await page.mouse.wheel(0, -260); await sleep(320); }
await sleep(2500);

// ── BEAT 2: pan across the detail (mouse drag on the canvas)
await page.mouse.move(cx, cy);
await page.mouse.down();
await page.mouse.move(cx - 240, cy - 100, { steps: 40 });
await page.mouse.up();
await sleep(3000);

// ── BEAT 3: reset to full view (narration: "the whole world gets the front-row seat")
const home = page.locator('#mirador-mount [aria-label="Reset zoom"]').first();
if (await home.count()) await home.click().catch(() => {});
await sleep(6000);

await ctx.close(); // finalises the webm
await b.close();

// Rename the auto-named webm to a stable filename.
const { readdirSync, renameSync } = await import('fs');
const files = readdirSync(OUT_DIR).filter(f => f.endsWith('.webm'));
if (files.length) {
  const latest = files.map(f => OUT_DIR + f).sort().pop();
  const stable = OUT_DIR + 'iiif-deepzoom.webm';
  renameSync(latest, stable);
  console.log('video:', stable);
}
