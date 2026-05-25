/**
 * Heratio Mirador 4 scalebar plugin.
 *
 * Wraps the OpenSeadragon scalebar pattern - reads physical-pixel
 * metadata from the IIIF v3 "PhysicalDimensions" service block on the
 * canvas resource and renders a real-world distance scale in the
 * viewer toolbar.
 *
 * The IIIF physical-dimensions service shape (https://iiif.io/api/annex/services/#physical-dimensions):
 *
 *   "service": [{
 *     "@context": "http://iiif.io/api/annex/services/physdim/1/context.json",
 *     "profile":  "http://iiif.io/api/annex/services/physdim",
 *     "physicalScale": 0.005,
 *     "physicalUnits": "mm"
 *   }]
 *
 * physicalScale is the size of one IIIF pixel in physicalUnits.
 * The Mirador 4 plugin contract: we extend the OpenSeadragon viewer
 * instance Mirador exposes via the redux store. The scalebar then
 * paints a 1px-high overlay anchored bottom-left.
 *
 * Toggleable from the window toolbar via the wrapping React component
 * (HeratioScalebarMenuItem) we register as a Mirador window menu item.
 */
import React, { useState, useEffect, useCallback } from 'react';
import MenuItem from '@mui/material/MenuItem';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import StraightenIcon from '@mui/icons-material/Straighten';
import Switch from '@mui/material/Switch';

// Pixel-units the human-readable scale will choose between. Selection
// is greedy: smallest unit that keeps the bar between 32 and 256 px.
const UNIT_LADDER_MM = [
  { label: 'um', factor: 0.001 },
  { label: 'mm', factor: 1 },
  { label: 'cm', factor: 10 },
  { label: 'm',  factor: 1000 },
];

function nicePhysicalLength(unitsPerPx, viewerWidthPx) {
  // Target 1/6th of the viewer width; clamp to the unit ladder.
  const targetUnits = unitsPerPx * (viewerWidthPx / 6);
  let pick = UNIT_LADDER_MM[1];
  for (const u of UNIT_LADDER_MM) {
    if (targetUnits / u.factor >= 1 && targetUnits / u.factor < 1000) {
      pick = u;
    }
  }
  const value = targetUnits / pick.factor;
  // Round to 1, 2, 5 x 10^n for a tidy bar
  const mag = Math.pow(10, Math.floor(Math.log10(value)));
  const norm = value / mag;
  let rounded;
  if (norm < 1.5) rounded = 1 * mag;
  else if (norm < 3.5) rounded = 2 * mag;
  else if (norm < 7.5) rounded = 5 * mag;
  else rounded = 10 * mag;
  return {
    units: pick.label,
    physical: rounded,
    pixels: (rounded * pick.factor) / unitsPerPx,
  };
}

function getPhysDimService(canvas) {
  if (!canvas) return null;
  // Canvas in Mirador 4 may be a CanvasObject; .__jsonld is the raw
  // JSON-LD or canvas.id. We try several shapes.
  const sources = [
    canvas.__jsonld,
    canvas.jsonld,
    canvas,
  ].filter(Boolean);
  for (const src of sources) {
    const svc = src.service || (src.items && src.items[0] && src.items[0].items && src.items[0].items[0] && src.items[0].items[0].body && src.items[0].items[0].body.service);
    if (!svc) continue;
    const arr = Array.isArray(svc) ? svc : [svc];
    for (const s of arr) {
      const profile = s.profile || s['@profile'] || '';
      const type = s.type || s['@type'] || '';
      if (
        (typeof profile === 'string' && profile.indexOf('physdim') >= 0) ||
        type === 'PhysicalDimensions' ||
        (s.physicalScale && s.physicalUnits)
      ) {
        return s;
      }
    }
  }
  return null;
}

function renderScalebar(osdViewer, service, enabled) {
  if (!osdViewer || !osdViewer.element) return;
  const containerId = 'heratio-scalebar-' + osdViewer.hash;
  let bar = osdViewer.element.querySelector('#' + containerId);
  if (!enabled || !service) {
    if (bar) bar.remove();
    return;
  }
  if (!bar) {
    bar = document.createElement('div');
    bar.id = containerId;
    bar.className = 'heratio-scalebar';
    osdViewer.element.appendChild(bar);
  }

  const update = () => {
    const physicalScale = parseFloat(service.physicalScale);
    const physicalUnits = (service.physicalUnits || 'mm').toLowerCase();
    if (!physicalScale || !isFinite(physicalScale)) return;
    // Convert physicalUnits to mm-equivalent before computing the bar.
    const unitToMm = { um: 0.001, mm: 1, cm: 10, m: 1000, in: 25.4 };
    const mmPerImagePx = physicalScale * (unitToMm[physicalUnits] || 1);

    const containerWidth = osdViewer.viewport.getContainerSize().x;
    const viewportZoom = osdViewer.viewport.getZoom(true);
    const imageZoom = osdViewer.viewport.viewportToImageZoom(viewportZoom);
    // mm per screen pixel = mm-per-image-px / screen-px-per-image-px
    const mmPerScreenPx = mmPerImagePx / imageZoom;
    const pick = nicePhysicalLength(mmPerScreenPx, containerWidth);
    bar.innerHTML =
      '<div class="heratio-scalebar-bar" style="width:' + Math.round(pick.pixels) + 'px"></div>' +
      '<div class="heratio-scalebar-label">' + pick.physical + ' ' + pick.units + '</div>';
  };

  osdViewer.addHandler('animation', update);
  osdViewer.addHandler('zoom', update);
  osdViewer.addHandler('resize', update);
  osdViewer.addHandler('open', update);
  update();
}

// Inject scalebar CSS once per page.
function injectStyles() {
  if (typeof document === 'undefined') return;
  if (document.getElementById('heratio-scalebar-styles')) return;
  const s = document.createElement('style');
  s.id = 'heratio-scalebar-styles';
  s.textContent =
    '.heratio-scalebar{position:absolute;bottom:10px;left:10px;z-index:1100;color:#fff;font-size:11px;font-family:sans-serif;text-shadow:0 0 3px rgba(0,0,0,.8);pointer-events:none;}' +
    '.heratio-scalebar-bar{height:4px;background:#fff;border:1px solid #000;}' +
    '.heratio-scalebar-label{margin-top:2px;text-align:center;}';
  document.head.appendChild(s);
}

// React component registered as a Mirador window menu item. Toggles
// per-window scalebar state, persists to window.AHG_IIIF_STATE so an
// embedder can recover it.
function HeratioScalebarMenuItem(props) {
  const { handleClose, targetProps } = props;
  const windowId = targetProps && targetProps.windowId;
  const [enabled, setEnabled] = useState(() => {
    if (typeof window !== 'undefined' && window.AHG_IIIF_STATE && window.AHG_IIIF_STATE.scalebar) {
      return !!window.AHG_IIIF_STATE.scalebar[windowId];
    }
    return false;
  });

  const refresh = useCallback(() => {
    // Locate the Mirador OSD viewer for this window. Mirador exposes
    // OSD viewer instances on the DOM node via a property; we walk
    // the DOM tree for the window root and look for the
    // OpenSeadragon viewer's container element.
    if (typeof document === 'undefined' || typeof window === 'undefined') return;
    if (!window.OpenSeadragon) return;
    // Mirador wraps the viewer in a div with data-test-id*=osd.
    const root = document.querySelector('[data-test-id="window-' + windowId + '"]') || document.body;
    const osdEl = root.querySelector('.openseadragon-canvas');
    if (!osdEl) return;
    // OSD attaches a reference back via the canvas element's parent.
    let osdViewer = null;
    if (window.__heratioMiradorOsdRegistry) {
      osdViewer = window.__heratioMiradorOsdRegistry[windowId];
    }
    if (!osdViewer) return;
    // Find the active canvas's JSON-LD to read its service block.
    let canvas = null;
    try {
      const store = window.__heratioMiradorStore;
      if (store) {
        const state = store.getState();
        const win = state.windows && state.windows[windowId];
        const manifestId = win && win.manifestId;
        const manifest = manifestId && state.manifests && state.manifests[manifestId];
        const json = manifest && manifest.json;
        if (json && json.items && json.items.length) {
          const canvasIdx = (win && win.canvasIndex) || 0;
          canvas = json.items[canvasIdx] || json.items[0];
        }
      }
    } catch (e) {
      // best-effort; fall through with null
    }
    const service = getPhysDimService(canvas);
    renderScalebar(osdViewer, service, enabled);
  }, [windowId, enabled]);

  useEffect(() => {
    injectStyles();
    refresh();
    if (typeof window !== 'undefined') {
      window.AHG_IIIF_STATE = window.AHG_IIIF_STATE || {};
      window.AHG_IIIF_STATE.scalebar = window.AHG_IIIF_STATE.scalebar || {};
      window.AHG_IIIF_STATE.scalebar[windowId] = enabled;
    }
  }, [enabled, refresh, windowId]);

  return React.createElement(
    MenuItem,
    { onClick: () => { setEnabled((v) => !v); } },
    React.createElement(ListItemIcon, null,
      React.createElement(StraightenIcon, null)
    ),
    React.createElement(ListItemText, { primary: 'Scalebar' }),
    React.createElement(Switch, {
      edge: 'end',
      checked: enabled,
      onChange: () => setEnabled((v) => !v),
      inputProps: { 'aria-label': 'Toggle scalebar' },
    })
  );
}

// Hook into the OSD viewer creation so we can map windowId <-> viewer.
// Mirador 4 emits 'mirador/RECEIVE_INFO_RESPONSE' and similar; for the
// minimum-viable surface we listen for OSD instance creation via a
// monkey-patch in the bundle entry (see index.js). That sets
// window.__heratioMiradorOsdRegistry[windowId] = viewer.

const heratioScalebarPlugin = [{
  target: 'WindowTopMenu',
  mode: 'add',
  component: HeratioScalebarMenuItem,
}];

export {
  heratioScalebarPlugin,
  HeratioScalebarMenuItem,
  getPhysDimService,
  renderScalebar,
  nicePhysicalLength,
};
export default heratioScalebarPlugin;
