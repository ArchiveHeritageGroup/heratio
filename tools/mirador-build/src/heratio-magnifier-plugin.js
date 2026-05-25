/**
 * Heratio Mirador 4 magnifier (loupe) plugin.
 *
 * Renders a circular zoom loupe that follows the cursor over the
 * canvas. Toggled from the window top menu. Implementation strategy
 * mirrors mirador-image-tools: we don't depend on a third-party OSD
 * magnifier extension - we draw the loupe by sampling the OSD canvas
 * (drawer: canvas) directly. OSD's WebGL drawer would need a separate
 * read-back path; the bundle pins drawer: canvas in ahg-iiif-viewer.js
 * so this is safe.
 */
import React, { useEffect, useState, useCallback } from 'react';
import MenuItem from '@mui/material/MenuItem';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Switch from '@mui/material/Switch';
import SearchIcon from '@mui/icons-material/Search';

const LOUPE_RADIUS = 90;        // px
const LOUPE_MAGNIFICATION = 3;  // x times the current canvas zoom

function injectStyles() {
  if (typeof document === 'undefined') return;
  if (document.getElementById('heratio-magnifier-styles')) return;
  const s = document.createElement('style');
  s.id = 'heratio-magnifier-styles';
  s.textContent =
    '.heratio-loupe{position:absolute;border-radius:50%;border:2px solid #fff;box-shadow:0 0 8px rgba(0,0,0,.6);pointer-events:none;z-index:1200;background:#000;overflow:hidden;}' +
    '.heratio-loupe canvas{display:block;}';
  document.head.appendChild(s);
}

function attachLoupe(osdViewer) {
  if (!osdViewer || !osdViewer.element) return null;
  injectStyles();
  const host = osdViewer.element;
  const loupe = document.createElement('div');
  loupe.className = 'heratio-loupe';
  loupe.style.width = (LOUPE_RADIUS * 2) + 'px';
  loupe.style.height = (LOUPE_RADIUS * 2) + 'px';
  loupe.style.display = 'none';
  const cvs = document.createElement('canvas');
  cvs.width = LOUPE_RADIUS * 2;
  cvs.height = LOUPE_RADIUS * 2;
  loupe.appendChild(cvs);
  host.appendChild(loupe);
  const ctx = cvs.getContext('2d');

  function findSourceCanvas() {
    // OSD canvas drawer puts a single .openseadragon-canvas wrapping
    // a single <canvas>. WebGL drawer has the same DOM shape but the
    // canvas is unreadable via 2d context. We force canvas drawer in
    // the viewer config.
    return host.querySelector('.openseadragon-canvas canvas');
  }

  function onMove(e) {
    const rect = host.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    if (x < 0 || y < 0 || x > rect.width || y > rect.height) {
      loupe.style.display = 'none';
      return;
    }
    const src = findSourceCanvas();
    if (!src) return;
    loupe.style.display = 'block';
    loupe.style.left = (x - LOUPE_RADIUS) + 'px';
    loupe.style.top = (y - LOUPE_RADIUS) + 'px';
    // Source rect: a (2R / M) x (2R / M) window centred on the
    // pointer location in source-canvas coords. Pin to canvas bounds.
    const m = LOUPE_MAGNIFICATION;
    const srcW = (LOUPE_RADIUS * 2) / m;
    const srcH = (LOUPE_RADIUS * 2) / m;
    const sx = Math.max(0, Math.min(src.width - srcW, x * (src.width / rect.width) - srcW / 2));
    const sy = Math.max(0, Math.min(src.height - srcH, y * (src.height / rect.height) - srcH / 2));
    ctx.clearRect(0, 0, cvs.width, cvs.height);
    ctx.drawImage(src, sx, sy, srcW, srcH, 0, 0, cvs.width, cvs.height);
  }
  function onLeave() {
    loupe.style.display = 'none';
  }

  host.addEventListener('mousemove', onMove);
  host.addEventListener('mouseleave', onLeave);

  return {
    detach() {
      host.removeEventListener('mousemove', onMove);
      host.removeEventListener('mouseleave', onLeave);
      loupe.remove();
    },
  };
}

function HeratioMagnifierMenuItem(props) {
  const { targetProps } = props;
  const windowId = targetProps && targetProps.windowId;
  const [enabled, setEnabled] = useState(() => {
    if (typeof window !== 'undefined' && window.AHG_IIIF_STATE && window.AHG_IIIF_STATE.magnifier) {
      return !!window.AHG_IIIF_STATE.magnifier[windowId];
    }
    return false;
  });

  const apply = useCallback(() => {
    if (typeof window === 'undefined') return;
    window.AHG_IIIF_STATE = window.AHG_IIIF_STATE || {};
    window.AHG_IIIF_STATE.magnifier = window.AHG_IIIF_STATE.magnifier || {};
    window.AHG_IIIF_STATE.magnifierHandles = window.AHG_IIIF_STATE.magnifierHandles || {};
    window.AHG_IIIF_STATE.magnifier[windowId] = enabled;
    const handle = window.AHG_IIIF_STATE.magnifierHandles[windowId];
    if (handle && handle.detach) handle.detach();
    window.AHG_IIIF_STATE.magnifierHandles[windowId] = null;
    if (!enabled) return;
    const osdViewer = window.__heratioMiradorOsdRegistry && window.__heratioMiradorOsdRegistry[windowId];
    if (!osdViewer) return;
    window.AHG_IIIF_STATE.magnifierHandles[windowId] = attachLoupe(osdViewer);
  }, [enabled, windowId]);

  useEffect(() => { apply(); }, [apply]);

  return React.createElement(
    MenuItem,
    { onClick: () => setEnabled((v) => !v) },
    React.createElement(ListItemIcon, null,
      React.createElement(SearchIcon, null)
    ),
    React.createElement(ListItemText, { primary: 'Magnifier' }),
    React.createElement(Switch, {
      edge: 'end',
      checked: enabled,
      onChange: () => setEnabled((v) => !v),
      inputProps: { 'aria-label': 'Toggle magnifier' },
    })
  );
}

const heratioMagnifierPlugin = [{
  target: 'WindowTopMenu',
  mode: 'add',
  component: HeratioMagnifierMenuItem,
}];

export { heratioMagnifierPlugin, HeratioMagnifierMenuItem, attachLoupe };
export default heratioMagnifierPlugin;
