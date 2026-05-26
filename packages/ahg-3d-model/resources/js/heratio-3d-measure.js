/*!
 * heratio-3d-measure.js
 * Phase 4 of issue #666 - measurement tool for the <model-viewer> 3D viewer.
 *
 * Activates on a toolbar button (.ahg-3d-measure-toggle). Once active the
 * user clicks two points on the model surface; we read the world-space
 * position via model-viewer's positionAndNormalFromPoint() API, then anchor
 * two <button slot="hotspot-*"> elements at those positions so the label
 * tracks camera movement natively. Distance is the Euclidean distance
 * between the two world-space points, reported in model units (typically
 * metres for glTF / glb, mm or inches for FBX/OBJ exports - model-viewer
 * does not normalise this so we surface the raw value with a "units" hint
 * that defaults to "m" but can be overridden via data-measure-units).
 *
 * Esc cancels the in-progress measurement; clicking the toggle a second
 * time clears any existing measurement. Multiple viewers on the page are
 * supported - each toolbar is bound to its data-target viewer.
 *
 * Copyright (c) Johan Pieterse / Plain Sailing iSystems / AGPL-3.0-or-later.
 */
(function () {
  'use strict';

  if (window.__ahg3dMeasureInit) { return; }
  window.__ahg3dMeasureInit = true;

  // Hotspot slot names must be globally unique per page; counter avoids clashes
  // when several viewers each emit their own measurement pair.
  var hotspotCounter = 0;

  function nextHotspotSlot() {
    hotspotCounter += 1;
    return 'ahg-measure-' + hotspotCounter;
  }

  function distance(a, b) {
    var dx = a.x - b.x;
    var dy = a.y - b.y;
    var dz = a.z - b.z;
    return Math.sqrt(dx * dx + dy * dy + dz * dz);
  }

  function formatDistance(d, units) {
    // Show three significant figures; switch to scientific for tiny / huge.
    if (!isFinite(d)) { return '-'; }
    if (d === 0) { return '0 ' + units; }
    var abs = Math.abs(d);
    var fixed;
    if (abs >= 100) { fixed = d.toFixed(1); }
    else if (abs >= 1) { fixed = d.toFixed(3); }
    else if (abs >= 0.001) { fixed = d.toFixed(4); }
    else { fixed = d.toExponential(2); }
    return fixed + ' ' + units;
  }

  function makeHotspot(viewer, slotName, pos, normal, label) {
    var btn = document.createElement('button');
    btn.setAttribute('slot', 'hotspot-' + slotName);
    btn.setAttribute('class', 'ahg-3d-measure-hotspot');
    btn.setAttribute('data-position', pos.x + ' ' + pos.y + ' ' + pos.z);
    if (normal && typeof normal.x === 'number') {
      btn.setAttribute('data-normal', normal.x + ' ' + normal.y + ' ' + normal.z);
    }
    btn.setAttribute('data-visibility-attribute', 'visible');
    btn.style.cssText = [
      'position: absolute',
      'pointer-events: none',
      'background: #1a73e8',
      'color: #fff',
      'border: 2px solid #fff',
      'border-radius: 999px',
      'width: 14px',
      'height: 14px',
      'padding: 0',
      'font-size: 0',
      'box-shadow: 0 1px 3px rgba(0,0,0,.4)',
      'transform: translate(-50%, -50%)'
    ].join(';');
    if (label) {
      btn.setAttribute('aria-label', label);
      btn.setAttribute('title', label);
    }
    viewer.appendChild(btn);
    return btn;
  }

  function makeLabelHotspot(viewer, slotName, pos, text) {
    var btn = document.createElement('button');
    btn.setAttribute('slot', 'hotspot-' + slotName);
    btn.setAttribute('class', 'ahg-3d-measure-label');
    btn.setAttribute('data-position', pos.x + ' ' + pos.y + ' ' + pos.z);
    btn.setAttribute('data-visibility-attribute', 'visible');
    btn.style.cssText = [
      'background: rgba(26, 115, 232, 0.92)',
      'color: #fff',
      'border: none',
      'border-radius: 4px',
      'padding: 2px 6px',
      'font-size: 12px',
      'font-weight: 600',
      'white-space: nowrap',
      'pointer-events: none',
      'transform: translate(-50%, -150%)',
      'box-shadow: 0 1px 3px rgba(0,0,0,.4)'
    ].join(';');
    btn.textContent = text;
    viewer.appendChild(btn);
    return btn;
  }

  function midpoint(a, b) {
    return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2, z: (a.z + b.z) / 2 };
  }

  function viewerToScreen(viewer, evt) {
    var rect = viewer.getBoundingClientRect();
    return {
      x: evt.clientX - rect.left,
      y: evt.clientY - rect.top
    };
  }

  function setStatus(toolbar, msg) {
    var el = toolbar.querySelector('.ahg-3d-measure-status');
    if (el) { el.textContent = msg || ''; }
  }

  function wireMeasureToolbar(toolbar) {
    var viewerId = toolbar.getAttribute('data-target');
    var viewer = document.getElementById(viewerId);
    if (!viewer) { return; }

    var units = toolbar.getAttribute('data-measure-units') || 'm';
    var toggleBtn = toolbar.querySelector('.ahg-3d-measure-toggle');
    var clearBtn = toolbar.querySelector('.ahg-3d-measure-clear');
    if (!toggleBtn) { return; }

    var state = {
      active: false,
      first: null,
      hotspots: []
    };

    function clearMeasurement() {
      state.first = null;
      state.hotspots.forEach(function (el) {
        if (el && el.parentNode) { el.parentNode.removeChild(el); }
      });
      state.hotspots = [];
      setStatus(toolbar, '');
    }

    function deactivate() {
      state.active = false;
      state.first = null;
      toggleBtn.classList.remove('active', 'btn-primary');
      toggleBtn.classList.add('atom-btn-white');
      toggleBtn.setAttribute('aria-pressed', 'false');
      viewer.style.cursor = '';
      setStatus(toolbar, '');
    }

    function activate() {
      clearMeasurement();
      state.active = true;
      toggleBtn.classList.add('active', 'btn-primary');
      toggleBtn.classList.remove('atom-btn-white');
      toggleBtn.setAttribute('aria-pressed', 'true');
      viewer.style.cursor = 'crosshair';
      setStatus(toolbar, viewer.getAttribute('data-measure-prompt-first') || 'Click the first point on the model');
    }

    toggleBtn.addEventListener('click', function () {
      if (state.active) {
        deactivate();
        clearMeasurement();
      } else {
        activate();
      }
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        clearMeasurement();
        if (state.active) {
          setStatus(toolbar, viewer.getAttribute('data-measure-prompt-first') || 'Click the first point on the model');
        }
      });
    }

    // Esc cancels the in-progress measurement
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && state.active) {
        deactivate();
        clearMeasurement();
      }
    });

    // Capture click in the bubble phase. We use 'click' rather than 'pointerdown'
    // so model-viewer's own camera-controls (which use pointerdown) don't fight us.
    viewer.addEventListener('click', function (evt) {
      if (!state.active) { return; }
      if (typeof viewer.positionAndNormalFromPoint !== 'function') {
        setStatus(toolbar, 'This <model-viewer> build does not expose positionAndNormalFromPoint.');
        return;
      }
      var local = viewerToScreen(viewer, evt);
      var hit = null;
      try { hit = viewer.positionAndNormalFromPoint(local.x, local.y); }
      catch (e) { hit = null; }
      if (!hit || !hit.position) {
        setStatus(toolbar, viewer.getAttribute('data-measure-miss') || 'No model surface under cursor - try again');
        return;
      }
      // model-viewer returns Vector3-like objects with x/y/z accessors
      var pos = { x: hit.position.x, y: hit.position.y, z: hit.position.z };
      var nrm = hit.normal ? { x: hit.normal.x, y: hit.normal.y, z: hit.normal.z } : null;

      if (!state.first) {
        state.first = pos;
        var slotA = nextHotspotSlot();
        state.hotspots.push(makeHotspot(viewer, slotA, pos, nrm, 'Point A'));
        setStatus(toolbar, viewer.getAttribute('data-measure-prompt-second') || 'Click the second point');
        return;
      }

      var d = distance(state.first, pos);
      var slotB = nextHotspotSlot();
      var slotL = nextHotspotSlot();
      state.hotspots.push(makeHotspot(viewer, slotB, pos, nrm, 'Point B'));
      state.hotspots.push(makeLabelHotspot(viewer, slotL, midpoint(state.first, pos), formatDistance(d, units)));

      setStatus(toolbar, (viewer.getAttribute('data-measure-result') || 'Distance:') + ' ' + formatDistance(d, units));
      // One measurement at a time - leave active mode but reset for the next pair.
      state.first = null;
    }, false);
  }

  function init() {
    var toolbars = document.querySelectorAll('.ahg-3d-measure-toolbar');
    Array.prototype.forEach.call(toolbars, wireMeasureToolbar);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Expose for the inline blade bootstrapper in case DOMContentLoaded already fired
  // by the time this script is inlined further down the document.
  window.AhgHeratio3dMeasure = { init: init, wire: wireMeasureToolbar };
})();
