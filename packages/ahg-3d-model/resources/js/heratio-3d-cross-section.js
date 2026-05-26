/*!
 * heratio-3d-cross-section.js
 * Phase 4 of issue #666 - cross-section / clipping-plane overlay for the
 * <model-viewer> 3D viewer.
 *
 * <model-viewer> does not expose a documented clipping-plane shader, and
 * reaching into its private Three.js scene to install renderer.clippingPlanes
 * is fragile across model-viewer releases. Per the Phase 4 spec we therefore
 * take the lightweight overlay approach:
 *
 *   1. A toolbar button (.ahg-3d-cross-toggle) activates cross-section mode.
 *   2. Axis toggle (X / Y / Z) picks the slice axis.
 *   3. A slider (.ahg-3d-cross-slider) moves the slice along the axis.
 *   4. While active, an HTML overlay rectangle is drawn over the viewer to
 *      indicate where the slice "would" cut, and the viewer's cameraTarget
 *      is shifted along the chosen axis to frame that depth - giving the
 *      user a focused interior view without requiring a real WebGL clip.
 *   5. A separate render path (window.HeratioCrossSectionAdvanced.enable())
 *      is provided for installations that have upgraded to a model-viewer
 *      build exposing the Three.js renderer; it installs a real
 *      THREE.Plane on renderer.clippingPlanes when available.
 *
 * Esc cancels cross-section mode and restores the original camera target.
 * Multiple viewers on the page are supported.
 *
 * Copyright (c) Johan Pieterse / Plain Sailing iSystems / AGPL-3.0-or-later.
 */
(function () {
  'use strict';

  if (window.__ahg3dCrossSectionInit) { return; }
  window.__ahg3dCrossSectionInit = true;

  function parseTarget(s) {
    if (!s) { return { x: 0, y: 0, z: 0 }; }
    var parts = String(s).trim().split(/\s+/);
    if (parts.length < 3) { return { x: 0, y: 0, z: 0 }; }
    return {
      x: parseFloat(parts[0]) || 0,
      y: parseFloat(parts[1]) || 0,
      z: parseFloat(parts[2]) || 0
    };
  }

  function targetToString(t) {
    return t.x + 'm ' + t.y + 'm ' + t.z + 'm';
  }

  function readBoundingExtent(viewer, axis) {
    // model-viewer exposes a getDimensions() Vector3 once the model is loaded.
    try {
      var dims = viewer.getDimensions ? viewer.getDimensions() : null;
      if (dims && typeof dims[axis] === 'number' && isFinite(dims[axis])) {
        return Math.abs(dims[axis]);
      }
    } catch (e) {}
    return 1; // sensible default model-space unit when no extents are known
  }

  function ensureOverlay(viewer) {
    var existing = viewer.parentNode && viewer.parentNode.querySelector('.ahg-3d-cross-overlay');
    if (existing) { return existing; }
    var ov = document.createElement('div');
    ov.className = 'ahg-3d-cross-overlay';
    ov.style.cssText = [
      'position: absolute',
      'inset: 0',
      'pointer-events: none',
      'display: none',
      'z-index: 5'
    ].join(';');
    // Two thin lines drawn via gradient borders that we toggle by axis.
    ov.innerHTML = [
      '<div class="ahg-3d-cross-line" style="position:absolute;background:rgba(255,80,80,0.85);box-shadow:0 0 6px rgba(0,0,0,.45);"></div>',
      '<div class="ahg-3d-cross-badge" style="position:absolute;top:8px;left:8px;background:rgba(0,0,0,0.65);color:#fff;font-size:11px;font-weight:600;padding:2px 8px;border-radius:3px;letter-spacing:.04em;">CROSS-SECTION</div>'
    ].join('');
    var host = viewer.parentNode;
    if (host && getComputedStyle(host).position === 'static') {
      host.style.position = 'relative';
    }
    host.appendChild(ov);
    return ov;
  }

  function updateOverlayLine(overlay, axis) {
    var line = overlay.querySelector('.ahg-3d-cross-line');
    if (!line) { return; }
    // X axis slices a vertical plane => render a vertical line. Y slices
    // horizontal plane => horizontal line. Z slices "into the screen" => render
    // a centred crosshair box.
    if (axis === 'x') {
      line.style.cssText += ';left:50%;top:0;bottom:0;width:2px;transform:translateX(-50%);right:auto;height:auto;border-radius:0;';
    } else if (axis === 'y') {
      line.style.cssText += ';top:50%;left:0;right:0;height:2px;transform:translateY(-50%);bottom:auto;width:auto;border-radius:0;';
    } else {
      // z: small diamond outline at centre
      line.style.cssText += ';top:50%;left:50%;width:48px;height:48px;transform:translate(-50%,-50%) rotate(45deg);background:transparent;border:2px solid rgba(255,80,80,0.85);box-shadow:0 0 6px rgba(0,0,0,.45);border-radius:0;';
    }
  }

  function wireCrossSectionToolbar(toolbar) {
    var viewerId = toolbar.getAttribute('data-target');
    var viewer = document.getElementById(viewerId);
    if (!viewer) { return; }

    var toggleBtn = toolbar.querySelector('.ahg-3d-cross-toggle');
    var slider = toolbar.querySelector('.ahg-3d-cross-slider');
    var axisRadios = toolbar.querySelectorAll('input[name^="ahg-3d-cross-axis-"]');
    var label = toolbar.querySelector('.ahg-3d-cross-value');
    if (!toggleBtn || !slider) { return; }

    var state = {
      active: false,
      axis: 'x',
      originalTarget: null
    };

    function getAxis() {
      for (var i = 0; i < axisRadios.length; i++) {
        if (axisRadios[i].checked) { return axisRadios[i].value; }
      }
      return 'x';
    }

    function updateSlice() {
      if (!state.active) { return; }
      var axis = getAxis();
      state.axis = axis;
      var extent = readBoundingExtent(viewer, axis);
      var pct = parseFloat(slider.value) / 100;
      // -extent/2 .. +extent/2 around the original centre
      var offset = (pct - 0.5) * extent;
      if (!state.originalTarget) {
        try {
          var t = viewer.getCameraTarget ? viewer.getCameraTarget() : null;
          state.originalTarget = t ? { x: t.x, y: t.y, z: t.z } : parseTarget(viewer.cameraTarget);
        } catch (e) {
          state.originalTarget = { x: 0, y: 0, z: 0 };
        }
      }
      var next = {
        x: state.originalTarget.x + (axis === 'x' ? offset : 0),
        y: state.originalTarget.y + (axis === 'y' ? offset : 0),
        z: state.originalTarget.z + (axis === 'z' ? offset : 0)
      };
      try { viewer.cameraTarget = targetToString(next); } catch (e) {}
      if (label) {
        var sign = offset >= 0 ? '+' : '';
        label.textContent = axis.toUpperCase() + ': ' + sign + offset.toFixed(3) + ' m';
      }
      var overlay = ensureOverlay(viewer);
      updateOverlayLine(overlay, axis);
      overlay.style.display = 'block';

      // Optional: real clip plane on Three renderer if it's reachable.
      if (window.HeratioCrossSectionAdvanced && typeof window.HeratioCrossSectionAdvanced.update === 'function') {
        try { window.HeratioCrossSectionAdvanced.update(viewer, axis, offset); } catch (e) {}
      }
    }

    function activate() {
      state.active = true;
      state.originalTarget = null;
      toggleBtn.classList.add('active', 'btn-primary');
      toggleBtn.classList.remove('atom-btn-white');
      toggleBtn.setAttribute('aria-pressed', 'true');
      toolbar.querySelectorAll('.ahg-3d-cross-controls').forEach(function (el) {
        el.classList.remove('d-none');
        el.classList.add('d-flex');
      });
      updateSlice();
    }

    function deactivate() {
      state.active = false;
      toggleBtn.classList.remove('active', 'btn-primary');
      toggleBtn.classList.add('atom-btn-white');
      toggleBtn.setAttribute('aria-pressed', 'false');
      toolbar.querySelectorAll('.ahg-3d-cross-controls').forEach(function (el) {
        el.classList.add('d-none');
        el.classList.remove('d-flex');
      });
      if (state.originalTarget) {
        try { viewer.cameraTarget = targetToString(state.originalTarget); } catch (e) {}
      }
      state.originalTarget = null;
      var overlay = viewer.parentNode && viewer.parentNode.querySelector('.ahg-3d-cross-overlay');
      if (overlay) { overlay.style.display = 'none'; }
      if (window.HeratioCrossSectionAdvanced && typeof window.HeratioCrossSectionAdvanced.disable === 'function') {
        try { window.HeratioCrossSectionAdvanced.disable(viewer); } catch (e) {}
      }
    }

    toggleBtn.addEventListener('click', function () {
      if (state.active) { deactivate(); } else { activate(); }
    });

    slider.addEventListener('input', updateSlice);
    Array.prototype.forEach.call(axisRadios, function (r) {
      r.addEventListener('change', function () {
        // Reset slider to centre when axis changes to avoid lurches.
        slider.value = '50';
        state.originalTarget = null;
        updateSlice();
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && state.active) { deactivate(); }
    });
  }

  function init() {
    var toolbars = document.querySelectorAll('.ahg-3d-cross-toolbar');
    Array.prototype.forEach.call(toolbars, wireCrossSectionToolbar);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.AhgHeratio3dCrossSection = { init: init, wire: wireCrossSectionToolbar };
})();
