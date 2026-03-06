/*
 * Landing Page Simple Configuration (form-driven)
 *
 * This is intentionally minimal: it provides...
 */

(function () {
  'use strict';

  if (!window.LandingPageConfig) {
    return;
  }

  const cfg = window.LandingPageConfig;

  function toast(message, type) {
    // Re-use Bootstrap 5 toast if present; fallback to alert.
    try {
      const containerId = 'lp_simple_toast_container';
      let c = document.getElementById(containerId);
      if (!c) {
        c = document.createElement('div');
        c.id = containerId;
        c.style.position = 'fixed';
        c.style.top = '1rem';
        c.style.right = '1rem';
        c.style.zIndex = '1080';
        document.body.appendChild(c);
      }

      const t = document.createElement('div');
      t.className = `toast align-items-center text-bg-${type || 'secondary'} border-0 show`;
      t.setAttribute('role', 'alert');
      t.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">${escapeHtml(message)}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Close"></button>
        </div>
      `;
      c.appendChild(t);
      t.querySelector('button').addEventListener('click', () => t.remove());
      setTimeout(() => t.remove(), 3500);
    } catch (e) {
      alert(message);
    }
  }

  function escapeHtml(str) {
    return String(str)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function swapAccordionItems(a, b) {
    if (!a || !b) return;
    const parent = a.parentElement;
    if (!parent) return;
    const aNext = a.nextSibling === b ? a : a.nextSibling;
    parent.insertBefore(a, b);
    if (aNext) parent.insertBefore(b, aNext);
  }

  function bindReorderButtons() {
    document.querySelectorAll('.block-move-up').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const item = btn.closest('.accordion-item');
        const prev = item?.previousElementSibling;
        if (prev) swapAccordionItems(item, prev);
      });
    });

    document.querySelectorAll('.block-move-down').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const item = btn.closest('.accordion-item');
        const next = item?.nextElementSibling;
        if (next) swapAccordionItems(next, item);
      });
    });
  }

  function bindRangeLiveValues() {
    document.querySelectorAll('input[type="range"][data-range-id]').forEach(r => {
      const id = r.getAttribute('data-range-id');
      const out = document.querySelector(`[data-range-value-for="${id}"]`);
      r.addEventListener('input', () => {
        if (out) out.textContent = r.value;
      });
    });
  }

  function getBlockField(blockId, fieldName) {
    return document.querySelector(`[data-block-id="${blockId}"][data-block-field="${fieldName}"]`);
  }

  function collectScalarConfig(blockId) {
    const config = {};
    const els = document.querySelectorAll(`[data-block-id="${blockId}"][data-config-key]:not([data-config-type="repeater"])`);
    els.forEach(el => {
      const key = el.getAttribute('data-config-key');
      const type = el.getAttribute('data-config-type') || 'text';
      if (!key) return;

      if (el.type === 'checkbox') {
        config[key] = el.checked ? 1 : 0;
      } else if (type === 'number') {
        const n = parseInt(el.value, 10);
        config[key] = isNaN(n) ? 0 : n;
      } else {
        config[key] = el.value;
      }
    });
    return config;
  }

  function collectRepeaterConfig(blockId) {
    const repeaters = {};
    const els = document.querySelectorAll(`[data-block-id="${blockId}"][data-config-type="repeater"]`);
    els.forEach(el => {
      const rootKey = el.getAttribute('data-config-key');
      const idxStr = el.getAttribute('data-repeater-index');
      const subKey = el.getAttribute('data-sub-key');
      const subType = el.getAttribute('data-sub-type') || 'text';
      if (!rootKey || idxStr === null || !subKey) return;
      const idx = parseInt(idxStr, 10);
      if (isNaN(idx)) return;
      if (!repeaters[rootKey]) repeaters[rootKey] = [];
      if (!repeaters[rootKey][idx]) repeaters[rootKey][idx] = {};

      let value;
      if (el.type === 'checkbox') {
        value = el.checked ? 1 : 0;
      } else if (subType === 'number') {
        const n = parseInt(el.value, 10);
        value = isNaN(n) ? 0 : n;
      } else {
        value = el.value;
      }
      repeaters[rootKey][idx][subKey] = value;
    });

    // Compact indices (remove holes)
    Object.keys(repeaters).forEach(k => {
      repeaters[k] = repeaters[k].filter(v => v !== undefined && v !== null);
    });

    return repeaters;
  }

  function collectPayload() {
    const blocks = [];
    const items = document.querySelectorAll('#simpleBlocksAccordion .accordion-item[data-block-id]');

    items.forEach((item, position) => {
      const blockId = parseInt(item.getAttribute('data-block-id'), 10);
      if (!blockId) return;

      const visibleEl = document.querySelector(`#visible_${blockId}`);
      const isVisible = visibleEl ? (visibleEl.checked ? 1 : 0) : 1;

      const payload = {
        id: blockId,
        position: position,
        is_visible: isVisible,
        title: getBlockField(blockId, 'title')?.value || '',
        container_type: getBlockField(blockId, 'container_type')?.value || 'container',
        css_classes: getBlockField(blockId, 'css_classes')?.value || '',
        background_color: getBlockField(blockId, 'background_color')?.value || '',
        text_color: getBlockField(blockId, 'text_color')?.value || '',
        padding_top: getBlockField(blockId, 'padding_top')?.value || '3',
        padding_bottom: getBlockField(blockId, 'padding_bottom')?.value || '3',
        col_span: getBlockField(blockId, 'col_span')?.value || '1',
        config: {}
      };

      // config fields
      const scalar = collectScalarConfig(blockId);
      const reps = collectRepeaterConfig(blockId);
      payload.config = Object.assign({}, scalar, reps);

      blocks.push(payload);
    });

    return {
      page_id: cfg.pageId,
      blocks
    };
  }

  async function save() {
    const payload = collectPayload();
    const fd = new FormData();
    fd.append('payload', JSON.stringify(payload));

    try {
      const res = await fetch(cfg.urls.save, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      const json = await res.json();
      if (json?.success) {
        toast('Saved', 'success');
      } else {
        toast(json?.error || 'Save failed', 'danger');
      }
    } catch (e) {
      console.error(e);
      toast('Save failed (network error)', 'danger');
    }
  }

  async function applyPreset(preset) {
    const fd = new FormData();
    fd.append('page_id', String(cfg.pageId));
    fd.append('preset', preset);

    try {
      const res = await fetch(cfg.urls.applyPreset, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      const json = await res.json();
      if (json?.success) {
        toast('Preset applied. Reloading…', 'success');
        window.location.reload();
      } else {
        toast(json?.error || 'Preset failed', 'danger');
      }
    } catch (e) {
      console.error(e);
      toast('Preset failed (network error)', 'danger');
    }
  }

  function bindPresets() {
    document.querySelectorAll('.preset-apply').forEach(a => {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        const preset = a.getAttribute('data-preset');
        if (!preset) return;
        // Minimal guard: confirm replacement
        if (!confirm('Apply preset? This will replace the entire page layout (all blocks).')) {
          return;
        }
        applyPreset(preset);
      });
    });
  }

  function bindRepeaters() {
    // Remove item
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.repeater-remove');
      if (!btn) return;
      const item = btn.closest('.repeater-item');
      if (item) item.remove();
    });

    // Add item
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.repeater-add');
      if (!btn) return;
      const blockId = btn.getAttribute('data-block-id');
      const key = btn.getAttribute('data-repeater-key');
      if (!blockId || !key) return;

      const container = document.querySelector(`.repeater[data-block-id="${blockId}"][data-repeater-key="${key}"]`);
      if (!container) return;

      // Determine next index (max + 1)
      let max = -1;
      container.querySelectorAll('.repeater-item[data-index]').forEach(it => {
        const idx = parseInt(it.getAttribute('data-index'), 10);
        if (!isNaN(idx) && idx > max) max = idx;
      });
      const next = max + 1;

      // Clone shape from first item if present; else add a placeholder row.
      const first = container.querySelector('.repeater-item');
      let html;
      if (first) {
        const clone = first.cloneNode(true);
        clone.setAttribute('data-index', String(next));
        // Update all inputs/selects/checkboxes indexes
        clone.querySelectorAll('[data-config-type="repeater"]').forEach(el => {
          el.setAttribute('data-repeater-index', String(next));
          // Update name attribute [<old>]
          if (el.name) {
            el.name = el.name.replace(/\[\d+\](?=\[[^\]]+\]$)/, `[${next}]`);
          }
          if (el.type === 'checkbox') {
            el.checked = false;
          } else {
            el.value = '';
          }
        });
        clone.querySelector('small.text-muted')?.replaceChildren(document.createTextNode(`Item ${next + 1}`));
        html = clone;
      } else {
        const div = document.createElement('div');
        div.className = 'repeater-item border-bottom pb-2 mb-2';
        div.setAttribute('data-index', String(next));
        div.innerHTML = `
          <div class="d-flex justify-content-between align-items-center mb-2">
            <small class="text-muted">Item ${next + 1}</small>
            <button type="button" class="btn btn-link btn-sm text-danger p-0 repeater-remove" title="Remove"><i class="bi bi-x"></i></button>
          </div>
          <input type="text" class="form-control form-control-sm" value="" placeholder="value">
        `;
        html = div;
      }

      container.appendChild(html);
    });
  }

  function init() {
    bindReorderButtons();
    bindRangeLiveValues();
    bindPresets();
    bindRepeaters();

    const btnSave = document.getElementById('btn-simple-save');
    if (btnSave) {
      btnSave.addEventListener('click', (e) => {
        e.preventDefault();
        save();
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
