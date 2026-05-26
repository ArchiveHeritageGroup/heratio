/**
 * Heratio Mirador 4 A/V (video + audio) plugin (issue #701).
 *
 * Mirador 4 ships with a primary image viewer (OSD); A/V handling is
 * not in the core distribution and the existing third-party
 * `mirador-video-extension` package is unstable / not on the npm
 * registry under that exact name. Rather than pull a moving target
 * into the production bundle we wrap the small surface ourselves:
 *
 *   1. Detect A/V canvases on the active window. A canvas is "A/V"
 *      when ANY of its painting annotations has a body whose `type`
 *      (or @type) is "Video", "Sound" or "Audio" - matching the IIIF
 *      Presentation 3 conventions.
 *
 *   2. When the active canvas is A/V, paint a `<video>` or `<audio>`
 *      element over the (empty) OSD viewport. Source URL comes from
 *      the painting body's `id`; format from `format` (e.g.
 *      `video/mp4`, `audio/wav`).
 *
 *   3. A transcript side-panel reads `iiif_ocr_text` for the canvas
 *      from the Heratio backend (/api/iiif/transcript?canvasId=...).
 *      Each row is an AnnotationPage whose annotations target the
 *      canvas with a `MediaFragmentSelector` of the form `t=12.3,15.6`.
 *      Clicking a word seeks the `<video>`/`<audio>` element to that
 *      timestamp.
 *
 * Backend contract (already present for image OCR; we re-use it for
 * A/V transcripts):
 *
 *   GET /api/iiif/transcript?canvasId=<urlencoded canvas id>
 *     -> { ok: true, items: [
 *            { id, text, target: { selector: { value: 't=12.3,15.6' }}}
 *          ] }
 *     -> 404 / { ok:false } when no transcript exists; the side-
 *        panel then shows a quiet empty-state.
 *
 * If the backend endpoint isn't shipped yet the plugin still loads -
 * the panel just stays empty.
 *
 * State + handle bookkeeping mirrors the magnifier/comparison
 * plugins: window.AHG_IIIF_STATE.av[windowId] holds the latest
 * `<video>`/`<audio>` element + panel, so toggling the menu item
 * idempotently swaps them in/out.
 */
import React, { useEffect, useState, useCallback } from 'react';
import MenuItem from '@mui/material/MenuItem';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Switch from '@mui/material/Switch';
import MovieIcon from '@mui/icons-material/Movie';

const TRANSCRIPT_ENDPOINT = '/api/iiif/transcript';

function injectStyles() {
  if (typeof document === 'undefined') return;
  if (document.getElementById('heratio-av-styles')) return;
  const s = document.createElement('style');
  s.id = 'heratio-av-styles';
  s.textContent =
    '.heratio-av-overlay{position:absolute;inset:0;z-index:1150;display:flex;align-items:center;justify-content:center;background:#000;}' +
    '.heratio-av-overlay video{max-width:100%;max-height:100%;width:100%;height:100%;outline:none;}' +
    '.heratio-av-overlay audio{width:90%;}' +
    '.heratio-av-transcript{position:absolute;top:0;right:0;bottom:0;width:300px;background:#fafafa;border-left:1px solid #ccc;z-index:1160;overflow-y:auto;padding:8px 10px;font:13px sans-serif;color:#222;box-shadow:-2px 0 8px rgba(0,0,0,.15);}' +
    '.heratio-av-transcript h4{margin:0 0 6px;font:bold 13px sans-serif;color:#444;border-bottom:1px solid #ddd;padding-bottom:4px;}' +
    '.heratio-av-transcript .row{padding:3px 0;cursor:pointer;border-bottom:1px dotted #eee;line-height:1.4;}' +
    '.heratio-av-transcript .row:hover{background:#fff5cc;}' +
    '.heratio-av-transcript .row.active{background:#ffd84d;color:#222;}' +
    '.heratio-av-transcript .t{font:11px monospace;color:#888;margin-right:6px;}' +
    '.heratio-av-transcript .empty{color:#888;font-style:italic;padding:12px 0;}';
  document.head.appendChild(s);
}

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
 *
 *   kind   : 'video' | 'audio'
 *   url    : body.id
 *   format : body.format (MIME)
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

/**
 * Mount the A/V element + transcript panel. Returns a detach handle.
 */
function mountAv(osdViewer, av, canvasId) {
  if (!osdViewer || !osdViewer.element || !av) return null;
  injectStyles();
  const host = osdViewer.element;
  if (!host.style.position || host.style.position === 'static') host.style.position = 'relative';

  // Hide OSD canvas while A/V is active - the IIIF Image API call for
  // the canvas thumbnail returns a poster frame, but we want the live
  // media element to be the sole visual.
  const osdCanvasWrap = host.querySelector('.openseadragon-canvas');
  const prevDisplay = osdCanvasWrap ? osdCanvasWrap.style.display : null;
  if (osdCanvasWrap) osdCanvasWrap.style.display = 'none';

  const overlay = document.createElement('div');
  overlay.className = 'heratio-av-overlay';
  const media = document.createElement(av.kind);    // video or audio
  media.src = av.url;
  media.controls = true;
  media.preload = 'metadata';
  if (av.format) media.setAttribute('type', av.format);
  // Allow CORS-attributed media so we can read currentTime from
  // cross-origin sources without tainting (paranoid; harmless).
  media.crossOrigin = 'anonymous';
  overlay.appendChild(media);
  host.appendChild(overlay);

  const panel = document.createElement('div');
  panel.className = 'heratio-av-transcript';
  panel.innerHTML = '<h4>Transcript</h4><div class="rows"><div class="empty">Loading...</div></div>';
  host.appendChild(panel);
  const rowsHost = panel.querySelector('.rows');

  let rowEls = [];
  let lines = [];

  function renderRows(items) {
    rowsHost.innerHTML = '';
    rowEls = [];
    lines = [];
    if (!items || !items.length) {
      const e = document.createElement('div');
      e.className = 'empty';
      e.textContent = 'No transcript for this canvas.';
      rowsHost.appendChild(e);
      return;
    }
    for (const it of items) {
      const sel = it && it.target && it.target.selector && it.target.selector.value;
      const frag = parseMediaFragment(sel);
      const start = frag ? frag.start : null;
      const end = frag ? frag.end : null;
      const row = document.createElement('div');
      row.className = 'row';
      const t = document.createElement('span');
      t.className = 't';
      t.textContent = start !== null ? formatTimestamp(start) : '--:--';
      const txt = document.createElement('span');
      txt.textContent = it.text || (it.body && (Array.isArray(it.body) ? it.body[0] && it.body[0].value : it.body.value)) || '';
      row.appendChild(t);
      row.appendChild(txt);
      row.addEventListener('click', () => {
        if (start !== null && !isNaN(start)) {
          try { media.currentTime = start; media.play && media.play().catch(() => {}); } catch (_) {}
        }
      });
      rowsHost.appendChild(row);
      rowEls.push(row);
      lines.push({ start, end });
    }
  }

  // Highlight the active line as the media plays.
  function onTimeUpdate() {
    const now = media.currentTime;
    let active = -1;
    for (let i = 0; i < lines.length; i++) {
      const ln = lines[i];
      if (ln.start === null) continue;
      const e = (ln.end !== null && !isNaN(ln.end)) ? ln.end : (i + 1 < lines.length ? lines[i + 1].start : ln.start + 5);
      if (now >= ln.start && now < e) { active = i; break; }
    }
    rowEls.forEach((row, i) => row.classList.toggle('active', i === active));
    if (active >= 0 && rowEls[active]) {
      const r = rowEls[active];
      const top = r.offsetTop;
      const visible = panel.scrollTop;
      const bottom = visible + panel.clientHeight;
      if (top < visible || top + r.clientHeight > bottom) {
        panel.scrollTop = top - panel.clientHeight / 3;
      }
    }
  }
  media.addEventListener('timeupdate', onTimeUpdate);

  // Best-effort transcript fetch. Endpoint may 404 if not shipped.
  if (canvasId) {
    fetch(TRANSCRIPT_ENDPOINT + '?canvasId=' + encodeURIComponent(canvasId), {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    }).then((r) => r.ok ? r.json() : null).then((data) => {
      if (!data || data.ok === false) {
        renderRows(null);
        return;
      }
      const items = Array.isArray(data.items) ? data.items : (Array.isArray(data.resources) ? data.resources : []);
      renderRows(items);
    }).catch(() => renderRows(null));
  } else {
    renderRows(null);
  }

  return {
    detach() {
      media.removeEventListener('timeupdate', onTimeUpdate);
      try { media.pause(); } catch (_) {}
      overlay.remove();
      panel.remove();
      if (osdCanvasWrap) osdCanvasWrap.style.display = prevDisplay || '';
    },
  };
}

function formatTimestamp(s) {
  if (s === null || s === undefined || isNaN(s)) return '--:--';
  const m = Math.floor(s / 60);
  const sec = Math.floor(s % 60);
  return (m < 10 ? '0' : '') + m + ':' + (sec < 10 ? '0' : '') + sec;
}

function readActiveCanvas(windowId) {
  if (typeof window === 'undefined') return null;
  try {
    const store = window.__heratioMiradorStore;
    if (!store) return null;
    const state = store.getState();
    const win = state.windows && state.windows[windowId];
    if (!win) return null;
    const manifestId = win.manifestId;
    const manifest = manifestId && state.manifests && state.manifests[manifestId];
    const json = manifest && manifest.json;
    if (!json || !Array.isArray(json.items) || !json.items.length) return null;
    const idx = win.canvasIndex || 0;
    return json.items[idx] || json.items[0];
  } catch (e) {
    return null;
  }
}

function HeratioAvMenuItem(props) {
  const { targetProps } = props;
  const windowId = targetProps && targetProps.windowId;
  const [enabled, setEnabled] = useState(() => {
    if (typeof window !== 'undefined' && window.AHG_IIIF_STATE && window.AHG_IIIF_STATE.av) {
      return !!window.AHG_IIIF_STATE.av[windowId];
    }
    return false;
  });

  const apply = useCallback(() => {
    if (typeof window === 'undefined') return;
    window.AHG_IIIF_STATE = window.AHG_IIIF_STATE || {};
    window.AHG_IIIF_STATE.av = window.AHG_IIIF_STATE.av || {};
    window.AHG_IIIF_STATE.avHandles = window.AHG_IIIF_STATE.avHandles || {};

    const prior = window.AHG_IIIF_STATE.avHandles[windowId];
    if (prior && prior.detach) prior.detach();
    window.AHG_IIIF_STATE.avHandles[windowId] = null;
    window.AHG_IIIF_STATE.av[windowId] = enabled;
    if (!enabled) return;

    const canvas = readActiveCanvas(windowId);
    const av = detectAvBody(canvas);
    if (!av) {
      if (!window.AHG_IIIF_STATE.__avHintShown) {
        window.AHG_IIIF_STATE.__avHintShown = true;
        console.info('[HeratioAv] no Video/Sound body on the active canvas; A/V mode not applicable.');
      }
      setTimeout(() => setEnabled(false), 0);
      return;
    }
    const reg = window.__heratioMiradorOsdRegistry || {};
    const viewer = reg[windowId];
    if (!viewer) return;
    const canvasId = canvas && (canvas.id || canvas['@id']);
    window.AHG_IIIF_STATE.avHandles[windowId] = mountAv(viewer, av, canvasId);
  }, [enabled, windowId]);

  useEffect(() => { apply(); }, [apply]);

  return React.createElement(
    MenuItem,
    { onClick: () => setEnabled((v) => !v) },
    React.createElement(ListItemIcon, null,
      React.createElement(MovieIcon, null)
    ),
    React.createElement(ListItemText, { primary: 'A/V playback + transcript' }),
    React.createElement(Switch, {
      edge: 'end',
      checked: enabled,
      onChange: () => setEnabled((v) => !v),
      inputProps: { 'aria-label': 'Toggle A/V playback' },
    })
  );
}

const heratioAvPlugin = [{
  target: 'WindowTopMenu',
  mode: 'add',
  component: HeratioAvMenuItem,
}];

export {
  heratioAvPlugin,
  HeratioAvMenuItem,
  detectAvBody,
  parseMediaFragment,
  mountAv,
};
export default heratioAvPlugin;
