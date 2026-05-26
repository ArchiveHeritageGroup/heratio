/**
 * Heratio Mirador 4 comparison-glass / dual-pane slider plugin
 * (issue #700).
 *
 * Adds a window-level toolbar entry that, when toggled on, finds a
 * SECOND open Mirador window in the same workspace and renders a
 * vertical "comparison glass": the active window's canvas occupies
 * the whole viewport, with a draggable vertical divider that reveals
 * a clipped overlay of the OTHER window's canvas on the right-hand
 * side. Dragging the divider scrubs between the two canvases just
 * like a before/after slider on a news-paper restoration story.
 *
 * Strategy: we don't fight the redux store - we sample the OSD
 * `<canvas>` element of both viewers each animation tick and paint
 * the right-side clip onto an overlay `<canvas>` that sits above the
 * Mirador window. OSD's canvas drawer is forced in
 * ahg-iiif-viewer.js so the source canvases are readable via 2d ctx.
 *
 * Discovery rules:
 *   - "active" viewer  : window the menu was opened from
 *                        (window.__heratioMiradorOsdRegistry[windowId])
 *   - "partner" viewer : any other key in the registry whose viewer
 *                        is non-null. Selection: first non-self key
 *                        in DOM-order (Mirador renders windows in
 *                        declaration order, so this matches the
 *                        user's mental "the one I opened second").
 *
 * If no partner window is open the menu item is still shown but
 * toggling it surfaces a one-shot console hint and keeps itself off
 * - it doesn't paint a half-grey nothing.
 *
 * Designed to coexist with the magnifier + scalebar plugins: the
 * overlay canvas listens for the same OSD `animation` / `zoom` /
 * `resize` events so pan/zoom in either pane updates the seam.
 */
import React, { useEffect, useState, useCallback } from 'react';
import MenuItem from '@mui/material/MenuItem';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Switch from '@mui/material/Switch';
import CompareIcon from '@mui/icons-material/Compare';

const HANDLE_WIDTH = 4;          // px, the vertical divider rule
const HANDLE_HIT_WIDTH = 18;     // px, fat invisible drag zone
const HANDLE_BG = '#ffd84d';     // Heratio accent

function injectStyles() {
  if (typeof document === 'undefined') return;
  if (document.getElementById('heratio-comparison-styles')) return;
  const s = document.createElement('style');
  s.id = 'heratio-comparison-styles';
  s.textContent =
    '.heratio-compare-overlay{position:absolute;inset:0;z-index:1180;pointer-events:none;overflow:hidden;}' +
    '.heratio-compare-overlay canvas{position:absolute;inset:0;width:100%;height:100%;display:block;}' +
    '.heratio-compare-handle{position:absolute;top:0;bottom:0;width:' + HANDLE_HIT_WIDTH + 'px;' +
      'margin-left:-' + (HANDLE_HIT_WIDTH / 2) + 'px;cursor:ew-resize;pointer-events:auto;z-index:1190;' +
      'display:flex;align-items:center;justify-content:center;}' +
    '.heratio-compare-handle::before{content:"";width:' + HANDLE_WIDTH + 'px;height:100%;background:' + HANDLE_BG + ';' +
      'box-shadow:0 0 8px rgba(0,0,0,.5);}' +
    '.heratio-compare-knob{position:absolute;top:50%;transform:translate(-50%,-50%);width:32px;height:32px;' +
      'border-radius:50%;background:' + HANDLE_BG + ';color:#222;display:flex;align-items:center;justify-content:center;' +
      'font:bold 14px sans-serif;box-shadow:0 0 6px rgba(0,0,0,.6);pointer-events:none;}' +
    '.heratio-compare-label{position:absolute;top:8px;padding:2px 8px;font:11px sans-serif;color:#fff;' +
      'background:rgba(0,0,0,.6);border-radius:3px;pointer-events:none;}' +
    '.heratio-compare-label.left{left:8px;}.heratio-compare-label.right{right:8px;}';
  document.head.appendChild(s);
}

function findPartnerWindowId(activeWindowId) {
  if (typeof window === 'undefined') return null;
  const reg = window.__heratioMiradorOsdRegistry || {};
  // DOM order: Mirador renders each window in a div whose
  // data-test-id is "window-<id>". Read them in document order so the
  // "first other window" matches the user's left-to-right scan.
  const els = document.querySelectorAll('[data-test-id^="window-"]');
  for (let i = 0; i < els.length; i++) {
    const wid = els[i].getAttribute('data-test-id').replace(/^window-/, '');
    if (!wid || wid === activeWindowId) continue;
    if (reg[wid]) return wid;
  }
  // Fallback: any key in the registry that isn't us.
  const keys = Object.keys(reg);
  for (const k of keys) {
    if (k !== activeWindowId && reg[k]) return k;
  }
  return null;
}

function findOsdSourceCanvas(osdViewer) {
  if (!osdViewer || !osdViewer.element) return null;
  return osdViewer.element.querySelector('.openseadragon-canvas canvas');
}

/**
 * Attach the comparison overlay to the active OSD viewer. Returns a
 * detach handle that the toggle uses on un-toggle / unmount.
 */
function attachComparison(activeViewer, partnerViewer) {
  if (!activeViewer || !activeViewer.element) return null;
  if (!partnerViewer) return null;
  injectStyles();

  const host = activeViewer.element;
  // host might already be position:relative (OSD's normal state); make
  // sure overlay positions resolve against it either way.
  const prevPosition = host.style.position;
  if (!prevPosition || prevPosition === 'static') host.style.position = 'relative';

  const overlay = document.createElement('div');
  overlay.className = 'heratio-compare-overlay';

  const cvs = document.createElement('canvas');
  // Pixel size is set on resize; CSS scales it to host bounds.
  overlay.appendChild(cvs);

  const labelL = document.createElement('div');
  labelL.className = 'heratio-compare-label left';
  labelL.textContent = 'A';
  const labelR = document.createElement('div');
  labelR.className = 'heratio-compare-label right';
  labelR.textContent = 'B';
  overlay.appendChild(labelL);
  overlay.appendChild(labelR);

  const handle = document.createElement('div');
  handle.className = 'heratio-compare-handle';
  const knob = document.createElement('div');
  knob.className = 'heratio-compare-knob';
  knob.textContent = '↔'; // left-right arrow
  handle.appendChild(knob);
  overlay.appendChild(handle);

  host.appendChild(overlay);

  let splitFrac = 0.5; // 0 = all-A on the right, 1 = all-B on the right
  const ctx = cvs.getContext('2d');

  function resize() {
    const rect = host.getBoundingClientRect();
    cvs.width = Math.max(1, Math.round(rect.width));
    cvs.height = Math.max(1, Math.round(rect.height));
  }

  function paint() {
    const src = findOsdSourceCanvas(partnerViewer);
    if (!src) return;
    const w = cvs.width;
    const h = cvs.height;
    ctx.clearRect(0, 0, w, h);
    const seamPx = Math.round(splitFrac * w);
    if (seamPx >= w) return;
    // Right of the seam: paint the partner's canvas content scaled to
    // fill the host. We sample the WHOLE partner canvas (so the user
    // is comparing the partner's current pan/zoom against the active
    // viewer's current pan/zoom - i.e. independent navigation, which
    // is the more useful mode for "look at the same painting at two
    // different exposures").
    ctx.save();
    ctx.beginPath();
    ctx.rect(seamPx, 0, w - seamPx, h);
    ctx.clip();
    try {
      ctx.drawImage(src, 0, 0, src.width, src.height, 0, 0, w, h);
    } catch (e) {
      // CORS-tainted canvas (cross-origin tile server without CORS
      // headers) will throw on drawImage. Swallow once per overlay -
      // chronic spam is worse than a missing pane.
      if (!overlay.__corsWarned) {
        overlay.__corsWarned = true;
        console.warn('[HeratioComparison] partner canvas not readable:', e && e.message);
      }
    }
    ctx.restore();
    // Position the divider relative to host width.
    handle.style.left = seamPx + 'px';
  }

  function tick() {
    paint();
  }

  // Drag handling: pointer-events on the seam handle only.
  let dragging = false;
  const onDown = (e) => {
    dragging = true;
    handle.setPointerCapture && e.pointerId !== undefined && handle.setPointerCapture(e.pointerId);
    e.preventDefault();
  };
  const onMove = (e) => {
    if (!dragging) return;
    const rect = host.getBoundingClientRect();
    let x = e.clientX - rect.left;
    if (x < 0) x = 0;
    if (x > rect.width) x = rect.width;
    splitFrac = rect.width ? (x / rect.width) : 0.5;
    paint();
  };
  const onUp = (e) => {
    dragging = false;
    handle.releasePointerCapture && e.pointerId !== undefined && (() => {
      try { handle.releasePointerCapture(e.pointerId); } catch (_) {}
    })();
  };
  handle.addEventListener('pointerdown', onDown);
  window.addEventListener('pointermove', onMove);
  window.addEventListener('pointerup', onUp);

  // Repaint on EITHER viewer's animation / zoom / resize.
  const handlers = [];
  function bind(viewer) {
    if (!viewer || !viewer.addHandler) return;
    const h1 = () => tick(); viewer.addHandler('animation', h1);
    const h2 = () => tick(); viewer.addHandler('zoom', h2);
    const h3 = () => { resize(); tick(); }; viewer.addHandler('resize', h3);
    const h4 = () => tick(); viewer.addHandler('open', h4);
    handlers.push([viewer, 'animation', h1], [viewer, 'zoom', h2], [viewer, 'resize', h3], [viewer, 'open', h4]);
  }
  bind(activeViewer);
  bind(partnerViewer);

  resize();
  // Initial paint after the partner canvas has had a render frame.
  requestAnimationFrame(tick);

  return {
    setLabels(a, b) {
      if (a) labelL.textContent = a;
      if (b) labelR.textContent = b;
    },
    detach() {
      handle.removeEventListener('pointerdown', onDown);
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('pointerup', onUp);
      handlers.forEach(([v, evt, fn]) => {
        try { v.removeHandler && v.removeHandler(evt, fn); } catch (_) {}
      });
      overlay.remove();
      if (prevPosition !== undefined) host.style.position = prevPosition;
    },
  };
}

function HeratioComparisonMenuItem(props) {
  const { targetProps } = props;
  const windowId = targetProps && targetProps.windowId;
  const [enabled, setEnabled] = useState(() => {
    if (typeof window !== 'undefined' && window.AHG_IIIF_STATE && window.AHG_IIIF_STATE.compare) {
      return !!window.AHG_IIIF_STATE.compare[windowId];
    }
    return false;
  });

  const apply = useCallback(() => {
    if (typeof window === 'undefined') return;
    window.AHG_IIIF_STATE = window.AHG_IIIF_STATE || {};
    window.AHG_IIIF_STATE.compare = window.AHG_IIIF_STATE.compare || {};
    window.AHG_IIIF_STATE.compareHandles = window.AHG_IIIF_STATE.compareHandles || {};

    const prior = window.AHG_IIIF_STATE.compareHandles[windowId];
    if (prior && prior.detach) prior.detach();
    window.AHG_IIIF_STATE.compareHandles[windowId] = null;
    window.AHG_IIIF_STATE.compare[windowId] = enabled;
    if (!enabled) return;

    const reg = window.__heratioMiradorOsdRegistry || {};
    const active = reg[windowId];
    const partnerId = findPartnerWindowId(windowId);
    const partner = partnerId && reg[partnerId];
    if (!active || !partner) {
      // One-shot console hint; auto-flip the switch off so the UI
      // doesn't lie about state.
      if (!window.AHG_IIIF_STATE.__compareHintShown) {
        window.AHG_IIIF_STATE.__compareHintShown = true;
        console.info('[HeratioComparison] open a second Mirador window in this workspace to compare against.');
      }
      // schedule an off-state on the next tick so React reconciles.
      setTimeout(() => setEnabled(false), 0);
      return;
    }
    window.AHG_IIIF_STATE.compareHandles[windowId] = attachComparison(active, partner);
  }, [enabled, windowId]);

  useEffect(() => { apply(); }, [apply]);

  return React.createElement(
    MenuItem,
    { onClick: () => setEnabled((v) => !v) },
    React.createElement(ListItemIcon, null,
      React.createElement(CompareIcon, null)
    ),
    React.createElement(ListItemText, { primary: 'Comparison glass' }),
    React.createElement(Switch, {
      edge: 'end',
      checked: enabled,
      onChange: () => setEnabled((v) => !v),
      inputProps: { 'aria-label': 'Toggle comparison glass' },
    })
  );
}

const heratioComparisonPlugin = [{
  target: 'WindowTopMenu',
  mode: 'add',
  component: HeratioComparisonMenuItem,
}];

export {
  heratioComparisonPlugin,
  HeratioComparisonMenuItem,
  attachComparison,
  findPartnerWindowId,
};
export default heratioComparisonPlugin;
