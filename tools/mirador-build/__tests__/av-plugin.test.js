/**
 * Heratio Mirador A/V plugin - unit tests for manifest-shape detection.
 *
 * Issue #701.
 *
 * Runs under Node's built-in test runner (no jest, no babel, no deps):
 *
 *   cd tools/mirador-build
 *   node --test __tests__/av-plugin.test.js
 *
 * The plugin source (`src/heratio-av-plugin.js`) imports React / MUI / icons
 * that require a webpack build to resolve, so we can't `require()` it directly
 * from a plain Node process. Instead the two pure helpers we want to test
 * (`detectAvBody` and `parseMediaFragment`) are mirrored verbatim from the
 * source below. Any change to those functions in `heratio-av-plugin.js` MUST
 * be applied here in lockstep - this file doubles as a contract test.
 *
 * @copyright Plain Sailing Information Systems
 * @author Johan Pieterse
 * @license AGPL-3.0-or-later
 */

const test = require('node:test');
const assert = require('node:assert/strict');

// --- BEGIN inlined helpers (verbatim from src/heratio-av-plugin.js) ---

/**
 * Parse "t=12.3,15.6" or "t=12.3" Media-Fragment selectors.
 * https://www.w3.org/TR/media-frags/
 */
function parseMediaFragment(value) {
  if (!value || typeof value !== 'string') return null;
  const m = /t=([0-9]*\.?[0-9]+)(?:,([0-9]*\.?[0-9]+))?/.exec(value);
  if (!m) return null;
  return {
    start: parseFloat(m[1]),
    end: m[2] !== undefined ? parseFloat(m[2]) : null,
  };
}

/**
 * Walk a canvas JSON-LD object and return the first painting body
 * whose type indicates A/V. Returns `{ kind, url, format }` or null.
 */
function detectAvBody(canvas) {
  if (!canvas) return null;
  const candidates = [canvas.__jsonld, canvas.jsonld, canvas].filter(Boolean);
  for (const src of candidates) {
    const pages = src.items;
    if (!Array.isArray(pages)) continue;
    for (const page of pages) {
      const anns = page && page.items;
      if (!Array.isArray(anns)) continue;
      for (const ann of anns) {
        if (!ann || ann.motivation !== 'painting') continue;
        const bodies = Array.isArray(ann.body) ? ann.body : [ann.body];
        for (const body of bodies) {
          if (!body) continue;
          const t = body.type || body['@type'] || '';
          const kind = t === 'Video' ? 'video' : (t === 'Sound' || t === 'Audio') ? 'audio' : null;
          if (kind && body.id) {
            return { kind, url: body.id, format: body.format || null };
          }
        }
      }
    }
  }
  return null;
}

// --- END inlined helpers ---

/**
 * Build a synthetic canvas with a single painting annotation. Helper to keep
 * the assertions below short. Pass `null` to `bodyType` for a body with no
 * type at all (negative test).
 */
function canvasWith(bodyType, opts = {}) {
  const body = {
    id: opts.id || 'https://example.test/media/file',
    format: opts.format || null,
  };
  if (bodyType !== null) body.type = bodyType;
  return {
    id: opts.canvasId || 'https://example.test/canvas/1',
    type: 'Canvas',
    items: [{
      type: 'AnnotationPage',
      items: [{
        type: 'Annotation',
        motivation: 'painting',
        body,
      }],
    }],
  };
}

// =================================================================
// detectAvBody - positive paths
// =================================================================

test('detectAvBody recognises a Video painting body', () => {
  const canvas = canvasWith('Video', {
    id: 'https://example.test/media/clip.mp4',
    format: 'video/mp4',
  });
  const av = detectAvBody(canvas);
  assert.deepEqual(av, {
    kind: 'video',
    url: 'https://example.test/media/clip.mp4',
    format: 'video/mp4',
  });
});

test('detectAvBody recognises a Sound painting body as audio', () => {
  const canvas = canvasWith('Sound', {
    id: 'https://example.test/media/track.mp3',
    format: 'audio/mpeg',
  });
  const av = detectAvBody(canvas);
  assert.equal(av.kind, 'audio');
  assert.equal(av.url, 'https://example.test/media/track.mp3');
  assert.equal(av.format, 'audio/mpeg');
});

test('detectAvBody recognises an Audio painting body as audio', () => {
  const canvas = canvasWith('Audio', {
    id: 'https://example.test/media/voice.wav',
    format: 'audio/wav',
  });
  const av = detectAvBody(canvas);
  assert.equal(av.kind, 'audio');
  assert.equal(av.url, 'https://example.test/media/voice.wav');
});

test('detectAvBody walks the __jsonld alias when present', () => {
  const inner = canvasWith('Video', {
    id: 'https://example.test/media/inner.mp4',
    format: 'video/mp4',
  });
  // Wrap in the Mirador parsed-canvas shape: items is missing on the outer
  // object; the real data is on __jsonld.
  const wrapped = { __jsonld: inner };
  const av = detectAvBody(wrapped);
  assert.equal(av.kind, 'video');
  assert.equal(av.url, 'https://example.test/media/inner.mp4');
});

test('detectAvBody walks the jsonld (no underscore) alias when present', () => {
  const inner = canvasWith('Sound', { id: 'https://example.test/a.mp3' });
  const wrapped = { jsonld: inner };
  const av = detectAvBody(wrapped);
  assert.equal(av.kind, 'audio');
});

test('detectAvBody honours @type as well as type', () => {
  const canvas = {
    items: [{
      items: [{
        motivation: 'painting',
        body: { '@type': 'Video', id: 'https://example.test/x.mp4' },
      }],
    }],
  };
  const av = detectAvBody(canvas);
  assert.equal(av.kind, 'video');
});

test('detectAvBody picks the first matching body in an array body', () => {
  const canvas = {
    items: [{
      items: [{
        motivation: 'painting',
        body: [
          { type: 'Choice', id: 'https://example.test/choice' },
          { type: 'Video', id: 'https://example.test/v.mp4', format: 'video/mp4' },
        ],
      }],
    }],
  };
  const av = detectAvBody(canvas);
  assert.equal(av.kind, 'video');
  assert.equal(av.url, 'https://example.test/v.mp4');
});

test('detectAvBody returns format=null when body has no format', () => {
  const canvas = canvasWith('Video', { id: 'https://example.test/n.mp4' });
  const av = detectAvBody(canvas);
  assert.equal(av.format, null);
});

// =================================================================
// detectAvBody - negative paths (refuses to mount on image-only)
// =================================================================

test('detectAvBody refuses an Image-only canvas (returns null)', () => {
  const canvas = canvasWith('Image', {
    id: 'https://example.test/page.jpg',
    format: 'image/jpeg',
  });
  assert.equal(detectAvBody(canvas), null);
});

test('detectAvBody refuses a canvas with no painting annotations', () => {
  const canvas = {
    items: [{
      items: [{
        motivation: 'commenting',
        body: { type: 'TextualBody', value: 'a comment' },
      }],
    }],
  };
  assert.equal(detectAvBody(canvas), null);
});

test('detectAvBody refuses an empty canvas', () => {
  assert.equal(detectAvBody({}), null);
  assert.equal(detectAvBody({ items: [] }), null);
  assert.equal(detectAvBody({ items: [{ items: [] }] }), null);
});

test('detectAvBody refuses a null / undefined / non-object canvas', () => {
  assert.equal(detectAvBody(null), null);
  assert.equal(detectAvBody(undefined), null);
  assert.equal(detectAvBody(false), null);
});

test('detectAvBody refuses a painting body with no id', () => {
  const canvas = {
    items: [{
      items: [{
        motivation: 'painting',
        body: { type: 'Video' /* no id */ },
      }],
    }],
  };
  assert.equal(detectAvBody(canvas), null);
});

test('detectAvBody ignores annotations on pages with non-array items', () => {
  const canvas = {
    items: [{
      items: 'not-an-array',
    }],
  };
  assert.equal(detectAvBody(canvas), null);
});

// =================================================================
// parseMediaFragment
// =================================================================

test('parseMediaFragment parses t=START,END', () => {
  assert.deepEqual(parseMediaFragment('t=12.3,15.6'), { start: 12.3, end: 15.6 });
});

test('parseMediaFragment parses t=START with no end', () => {
  assert.deepEqual(parseMediaFragment('t=12.3'), { start: 12.3, end: null });
});

test('parseMediaFragment parses integer seconds', () => {
  assert.deepEqual(parseMediaFragment('t=42'), { start: 42, end: null });
});

test('parseMediaFragment parses leading-decimal seconds', () => {
  assert.deepEqual(parseMediaFragment('t=.5'), { start: 0.5, end: null });
});

test('parseMediaFragment finds a t= directive inside a longer selector', () => {
  // Selectors can carry other params; we only care about t=
  const r = parseMediaFragment('xywh=0,0,100,100&t=8,9');
  assert.equal(r.start, 8);
  assert.equal(r.end, 9);
});

test('parseMediaFragment rejects empty / non-string / no-t input', () => {
  assert.equal(parseMediaFragment(''), null);
  assert.equal(parseMediaFragment(null), null);
  assert.equal(parseMediaFragment(undefined), null);
  assert.equal(parseMediaFragment(42), null);
  assert.equal(parseMediaFragment('xywh=0,0,100,100'), null);
});

// =================================================================
// Bundle smoke test - verify the compiled artifact exists and is
// the right shape. Acts as a guard against an accidental delete or
// a webpack build that wrote a zero-byte file.
// =================================================================

test('compiled mirador bundle exists and is non-trivial', () => {
  const fs = require('node:fs');
  const path = require('node:path');
  const bundle = path.resolve(
    __dirname,
    '../../../public/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js'
  );
  const stat = fs.statSync(bundle);
  assert.ok(stat.isFile(), 'mirador.min.js should be a regular file');
  // A real Mirador 4 + plugins bundle is well over 1 MB. Anything smaller
  // than 500 KB strongly suggests a broken/empty deploy.
  assert.ok(stat.size > 500_000, 'bundle should be > 500 KB; got ' + stat.size);
});

test('compiled bundle mentions the heratio-av overlay class', () => {
  const fs = require('node:fs');
  const path = require('node:path');
  const bundle = path.resolve(
    __dirname,
    '../../../public/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js'
  );
  const text = fs.readFileSync(bundle, 'utf8');
  // The plugin's CSS class is unique enough to be a reliable
  // needle that the A/V plugin is actually compiled into the bundle.
  assert.ok(
    text.indexOf('heratio-av-overlay') !== -1,
    'bundle should contain the heratio-av-overlay class; A/V plugin not deployed?'
  );
});
