@extends('theme::layouts.1col')

@section('title', 'FS Overlay Annotate')
@section('body-class', 'admin ai htr')

@push('css')
<style>
  .ba-wrap { position: relative; overflow: scroll; background: #1a1a2e; border-radius: 4px; height: 75vh; }
  .ba-wrap.tool-hand { cursor: grab; }
  .ba-wrap.tool-hand.panning { cursor: grabbing; }
  .ba-wrap.tool-draw { cursor: crosshair; }
  .ba-wrap.tool-select { cursor: default; }
  .ba-wrap canvas { display: block; }
  .ba-sidebar { max-height: 75vh; overflow-y: auto; }
  .ba-field { padding: 6px 10px; border-left: 3px solid #ccc; margin-bottom: 4px; cursor: pointer; }
  .ba-field.active { border-left-color: #0d6efd; background: #e8f0fe; }
  .ba-field.done { border-left-color: #198754; background: #d1e7dd; }
  .ba-field .ba-label { font-weight: 600; font-size: 0.8rem; color: #666; }
  .ba-field .ba-value { font-size: 0.95rem; }
  .ba-field .ba-coords { font-size: 0.7rem; color: #999; }
  .ba-field.skipped { border-left-color: #adb5bd; background: #f8f9fa; opacity: 0.6; }
  .ba-field.skipped .ba-value { text-decoration: line-through; }
  .ba-field .ba-skip-btn { float: right; font-size: 0.7rem; padding: 0 6px; }
  .ba-field .ba-edit-input { font-size: 0.85rem; padding: 2px 4px; width: 100%; border: 1px solid #dee2e6; border-radius: 3px; }
  .ba-field .ba-edit-input:focus { border-color: #0d6efd; outline: none; }
  .ba-wrap.dragging { cursor: move !important; }
  .ba-progress { height: 4px; }
  kbd { font-size: 0.75rem; }
  .ba-auto-badge { display: inline-block; font-size: 0.65rem; font-weight: 700; background: #198754; color: #fff; padding: 1px 8px; border-radius: 3px; animation: baPulse 1.5s ease-in-out infinite; }
  @keyframes baPulse { 0%,100%{opacity:1} 50%{opacity:0.5} }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-0"><i class="fas fa-layer-group me-2"></i>FS Overlay Annotate</h1>
    <span class="small text-muted">Position field labels on document images — drag boxes to correct locations</span>
  </div>
  <div class="btn-group btn-group-sm">
    <a href="{{ route('admin.ai.htr.bulkAnnotate') }}" class="btn atom-btn-white"><i class="fas fa-th me-1"></i>Bulk Annotate</a>
    <a href="{{ route('admin.ai.htr.annotate') }}" class="btn atom-btn-white"><i class="fas fa-pencil-alt me-1"></i>Manual</a>
  </div>
</div>

{{-- Folder + Spreadsheet Selection --}}
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="row align-items-end">
      <div class="col-md-5">
        <label class="form-label small fw-bold">Folder with images + CSV</label>
        <input type="text" id="ba-folder" class="form-control form-control-sm" value="/usr/share/nginx/heratio/FamilySearch/" placeholder="/path/to/images">
      </div>
      <div class="col-md-5">
        <label class="form-label small fw-bold">Spreadsheet (in same folder)</label>
        <select id="ba-spreadsheet" class="form-select form-select-sm">
          <option value="">Select spreadsheet...</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-bold">Form type</label>
        <select id="ba-form-type" class="form-select form-select-sm">
          <option value="auto">Auto-detect</option>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label small fw-bold">&nbsp;</label>
        <button id="ba-load-btn" class="btn btn-sm atom-btn-outline-success w-100" onclick="loadBulkData()">
          <i class="fas fa-upload me-1"></i>Load
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Main workspace --}}
<div class="row g-3" id="ba-workspace" style="display:none;">
  {{-- Image canvas --}}
  <div class="col-md-8">
    <div class="card">
      <div class="card-header py-1 d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <span id="ba-image-name" class="small">No image loaded</span>
        <span>
          <span id="ba-counter" class="badge bg-light text-dark me-2">0/0</span>
          <span id="ba-auto-status" class="ba-auto-badge d-none">AUTO</span>
          <div class="btn-group btn-group-sm me-2">
            <button class="btn btn-light" id="ba-tool-hand" title="Pan (H)" onclick="baSetTool('hand')"><i class="fas fa-hand-paper"></i></button>
            <button class="btn btn-light" id="ba-tool-draw" title="Draw (R)" onclick="baSetTool('draw')"><i class="fas fa-vector-square"></i></button>
            <button class="btn btn-light" id="ba-tool-select" title="Select/Move (V)" onclick="baSetTool('select')"><i class="fas fa-mouse-pointer"></i></button>
          </div>
          <button class="btn btn-sm btn-light" onclick="baZoomIn()"><i class="fas fa-search-plus"></i></button>
          <button class="btn btn-sm btn-light" onclick="baZoomOut()"><i class="fas fa-search-minus"></i></button>
          <button class="btn btn-sm btn-light" onclick="baZoomFit()"><i class="fas fa-expand"></i></button>
        </span>
      </div>
      <div class="ba-wrap" id="ba-wrap">
        <canvas id="ba-canvas"></canvas>
      </div>
    </div>
    <div class="d-flex justify-content-between mt-2">
      <button class="btn btn-sm atom-btn-white" onclick="baPrev()" id="ba-prev-btn" disabled><i class="fas fa-arrow-left me-1"></i>Previous</button>
      <div>
        <button class="btn btn-sm btn-outline-info" onclick="ocrAndPlace(images[imgIdx]); redraw();" title="OCR the form to detect printed labels"><i class="fas fa-eye me-1"></i>Detect labels</button>
        <button class="btn btn-sm btn-outline-primary" onclick="baAutoPlace()" id="ba-autoplace-btn" title="Re-apply saved positions"><i class="fas fa-magic me-1"></i>Auto-place</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="baResetPositions()" title="Clear saved positions"><i class="fas fa-undo me-1"></i>Reset</button>
        <button class="btn btn-sm btn-outline-warning" onclick="baMigrateToServer()" id="ba-migrate-btn" title="Push browser positions to server"><i class="fas fa-cloud-upload-alt me-1"></i>Sync to server</button>
        <button class="btn btn-sm atom-btn-white" onclick="baSkip()"><i class="fas fa-forward me-1"></i>Skip</button>
      </div>
      <button class="btn btn-sm atom-btn-outline-success" onclick="baSaveAndNext()" id="ba-save-btn" disabled><i class="fas fa-save me-1"></i>Save & Next</button>
    </div>
  </div>

  {{-- Field list sidebar --}}
  <div class="col-md-4">
    <div class="card">
      <div class="card-header py-1" style="background:var(--ahg-primary);color:#fff">
        <span class="small">Fields — drag boxes to position on image</span>
      </div>
      <div class="card-body p-2 ba-sidebar" id="ba-fields"></div>
    </div>
    <div class="progress ba-progress mt-2">
      <div class="progress-bar bg-success" id="ba-progress" style="width:0%"></div>
    </div>
    <div class="mt-2 small text-muted">
      <kbd>V</kbd> select & drag boxes to correct positions · Positions are <strong>remembered</strong> for next images · <kbd>R</kbd> draw new box · <kbd>Ctrl+S</kbd> save & next
    </div>

    {{-- Session stats --}}
    <div class="card mt-3">
      <div class="card-header py-1" style="background:var(--ahg-primary);color:#fff">
        <span class="small">Session</span>
      </div>
      <div class="card-body p-2">
        <div class="d-flex justify-content-between">
          <span>Images done</span>
          <span class="badge bg-success" id="ba-done-count">0</span>
        </div>
        <div class="d-flex justify-content-between mt-1">
          <span>Remaining</span>
          <span class="badge bg-warning text-dark" id="ba-remaining-count">0</span>
        </div>
        <div class="d-flex justify-content-between mt-1">
          <span>Fields annotated</span>
          <span class="badge bg-primary" id="ba-fields-count">0</span>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('js')
<script>
(function() {
  let COLUMNS = [];
  const COLORS = ['#ff6b6b','#4ecdc4','#45b7d1','#96ceb4','#ffeaa7','#dfe6e9','#fd79a8','#6c5ce7','#00b894'];

  let images = [];
  let imgIdx = -1;
  let fieldIdx = 0;
  let annotations = [];
  let img = null;
  let scale = 1;
  let currentTool = 'hand'; // default to pan
  let drawing = false, sx = 0, sy = 0;
  let dragging = false, dragIdx = -1, dragOffX = 0, dragOffY = 0;
  let resizing = false, resizeIdx = -1, resizeHandle = '';
  let panning = false, panStartX = 0, panStartY = 0, panScrollX = 0, panScrollY = 0;
  let offsetX = 0, offsetY = 0;
  let skipped = [];
  let sessionDone = 0, sessionFields = 0;

  // Saved positions — per form type
  let savedPositions = {};
  let currentFolder = '';
  let currentFormType = '';

  // Fields to always skip
  // Only these 5 fields are used — everything else is skipped
  const ALLOWED_FIELDS = ['Name', 'Sex', 'Age', 'Event Date', 'Residence Place'];
  function shouldSkip(col) { return !ALLOWED_FIELDS.includes(col); }

  // ── Known form templates (positions as % of image width/height) ──
  // Form templates — positions as % of image width/height
  // Calibrated from actual scanned SA death certificates
  // Form templates with anchor-relative positioning:
  // - anchor: keywords to find the form title via OCR
  // - anchorRef: expected position of the anchor on the reference/calibration image (as % of image)
  // - fields: positions relative to the anchor reference
  // On each image, OCR finds the anchor, computes offset + scale vs anchorRef, then shifts all fields
  const FORM_TEMPLATES = {
    'sa-death-1923': {
      label: 'SA Death — Informasievorm (1923 Act, EN/AF bilingual)',
      detect: ['informasievorm', 'sterfgeval'],
      anchor: ['informasievorm', 'sterfgeval'],
      anchorRef: { x: 0.22, y: 0.04, w: 0.56, h: 0.02 }, // title position on reference image
      fields: {
        'Name':           { x: 0.25, y: 0.08, w: 0.45, h: 0.06 },
        'Residence Place':{ x: 0.25, y: 0.15, w: 0.55, h: 0.04 },
        'Sex':            { x: 0.25, y: 0.22, w: 0.15, h: 0.03 },
        'Age':            { x: 0.25, y: 0.25, w: 0.20, h: 0.03 },
        'Event Date':     { x: 0.25, y: 0.38, w: 0.45, h: 0.03 },
      }
    },
    'sa-death-1894': {
      label: 'SA Death — Form of Information / Kennisgewing (Act/Wet 7 of 1894)',
      detect: ['1894', 'act no', 'deceased', 'kennisgewing', 'oorledene', 'wet no'],
      anchor: ['death:', 'act'],  // "DEATH: ACT No. 7 OF 1894" — compact, consistent
      anchorRef: { x: 0.63, y: 0.09, w: 0.19, h: 0.01 },
      fields: {
        'Name':           { x: 0.51, y: 0.19, w: 0.42, h: 0.04 },  // 1. Christian Names and Surname
        'Residence Place':{ x: 0.51, y: 0.22, w: 0.42, h: 0.03 },  // 2. Usual place of Residence
        'Age':            { x: 0.51, y: 0.24, w: 0.20, h: 0.02 },  // 3. Age
        'Sex':            { x: 0.51, y: 0.26, w: 0.15, h: 0.02 },  // 4. Race(a) — Sex
        'Event Date':     { x: 0.51, y: 0.32, w: 0.40, h: 0.03 },  // 8. Date of Death
      }
    },
    'sa-death-generic': {
      label: 'SA Death — Generic (fallback)',
      detect: ['death', 'dood', 'form of information'],
      anchor: ['form', 'death'],
      anchorRef: { x: 0.22, y: 0.04, w: 0.56, h: 0.02 },
      fields: {
        'Name':           { x: 0.25, y: 0.08, w: 0.45, h: 0.06 },
        'Residence Place':{ x: 0.25, y: 0.15, w: 0.55, h: 0.04 },
        'Sex':            { x: 0.25, y: 0.22, w: 0.15, h: 0.03 },
        'Age':            { x: 0.25, y: 0.25, w: 0.20, h: 0.03 },
        'Event Date':     { x: 0.25, y: 0.38, w: 0.45, h: 0.03 },
      }
    },
    'manual': {
      label: 'Manual positioning (no template)',
      detect: [],
      fields: {}
    }
  };

  // Populate form type dropdown + change handler
  (function() {
    const sel = document.getElementById('ba-form-type');
    for (const [key, tpl] of Object.entries(FORM_TEMPLATES)) {
      const opt = document.createElement('option');
      opt.value = key;
      opt.textContent = tpl.label;
      sel.appendChild(opt);
    }
    sel.addEventListener('change', function() {
      if (img && images[imgIdx]) {
        const newType = this.value === 'auto' ? 'sa-death-generic' : this.value;
        currentFormType = newType;
        loadSavedPositions(() => {
          applyFormTemplate(newType);
          redraw();
        });
      }
    });
  })();

  // Auto-detect form type from first image OCR
  function detectFormType(ocrWords) {
    const allText = ocrWords.map(w => w.toLowerCase()).join(' ');
    let bestType = 'sa-death-generic';
    let bestScore = 0;

    for (const [key, tpl] of Object.entries(FORM_TEMPLATES)) {
      if (!tpl.detect.length) continue;
      let score = 0;
      for (const kw of tpl.detect) {
        if (allText.includes(kw)) score++;
      }
      if (score > bestScore) { bestScore = score; bestType = key; }
    }

    if (bestScore === 0) {
      document.getElementById('ba-image-name').textContent += ' — UNRECOGNISED FORM (using generic, select form type manually)';
    } else {
      const tpl = FORM_TEMPLATES[bestType];
      document.getElementById('ba-image-name').textContent += ' — Detected: ' + (tpl ? tpl.label : bestType);
    }

    return bestType;
  }

  // Current anchor detection result (set by OCR)
  let detectedAnchor = null;

  // Apply form template — positions fields relative to detected anchor
  function applyFormTemplate(templateKey, anchor) {
    const tpl = FORM_TEMPLATES[templateKey];
    if (!tpl) return;

    currentFormType = templateKey;
    document.getElementById('ba-form-type').value = templateKey;

    // If we have a detected anchor AND the template has an anchor reference,
    // compute offset + scale to adjust all field positions
    // Anchor-relative adjustment: shift field positions based on where the title was found
    let offsetXPct = 0, offsetYPct = 0, scaleX = 1, scaleY = 1;
    if (anchor && tpl.anchorRef && anchor.w_pct > 0.1) {
      // Only trust anchor if it's wide enough (>10% of image = real title, not a stray word)
      offsetXPct = anchor.x_pct - tpl.anchorRef.x;
      offsetYPct = anchor.y_pct - tpl.anchorRef.y;
      if (tpl.anchorRef.w > 0) {
        scaleX = anchor.w_pct / tpl.anchorRef.w;
        scaleY = scaleX;
      }
      // Sanity check: if offset or scale is too extreme, ignore anchor
      if (Math.abs(offsetXPct) > 0.3 || Math.abs(offsetYPct) > 0.3 || scaleX < 0.5 || scaleX > 2) {
        console.log('[FS Overlay] Anchor unreliable, ignoring. offset=(' + offsetXPct.toFixed(3) + ',' + offsetYPct.toFixed(3) + ') scale=' + scaleX.toFixed(3));
        offsetXPct = 0; offsetYPct = 0; scaleX = 1; scaleY = 1;
      } else {
        console.log('[FS Overlay] Anchor adjustment: offset=(' + offsetXPct.toFixed(3) + ',' + offsetYPct.toFixed(3) + ') scale=' + scaleX.toFixed(3));
      }
    }

    const entry = images[imgIdx];
    annotations = [];
    skipped = [];

    COLUMNS.forEach(function(col, i) {
      const val = entry.fields[col] || '';
      if (shouldSkip(col)) { skipped.push(i); annotations.push(null); return; }

      // Priority: 1) server-saved positions, 2) anchor-adjusted template, 3) raw template, 4) default
      if (savedPositions[col]) {
        annotations.push({ label: col, value: val, x: savedPositions[col].x, y: savedPositions[col].y, w: savedPositions[col].w, h: savedPositions[col].h });
      } else if (tpl.fields[col]) {
        const f = tpl.fields[col];
        // Apply anchor offset + scale
        const adjX = (f.x + offsetXPct) * scaleX + (1 - scaleX) * tpl.anchorRef.x;
        const adjY = (f.y + offsetYPct) * scaleY + (1 - scaleY) * tpl.anchorRef.y;
        const adjW = f.w * scaleX;
        const adjH = f.h * scaleY;
        annotations.push({
          label: col, value: val,
          x: Math.round(adjX * img.width),
          y: Math.round(adjY * img.height),
          w: Math.round(adjW * img.width),
          h: Math.round(adjH * img.height),
        });
      } else {
        // No template position — stack below others
        const activeIdx = annotations.filter(a => a).length;
        annotations.push({
          label: col, value: val,
          x: Math.round(img.width * 0.05),
          y: Math.round(img.height * 0.08) + activeIdx * Math.round(img.height / 15),
          w: Math.round(img.width * 0.45),
          h: Math.round(img.height * 0.04),
        });
      }
    });

    highlightField();
    updateProgress();
    annotations.forEach(function(ann, i) {
      if (!ann) return;
      const c = document.getElementById('ba-coords-' + i);
      if (c) c.textContent = Math.round(ann.x) + ',' + Math.round(ann.y) + ' ' + Math.round(ann.w) + '×' + Math.round(ann.h);
    });
  }

  window.baSetTool = function(t) {
    currentTool = t;
    document.getElementById('ba-tool-hand').classList.toggle('active', t === 'hand');
    document.getElementById('ba-tool-draw').classList.toggle('active', t === 'draw');
    document.getElementById('ba-tool-select').classList.toggle('active', t === 'select');
    cvs.style.setProperty('cursor', t === 'hand' ? 'grab' : (t === 'draw' ? 'crosshair' : 'default'), 'important');
  };

  function hitTest(px, py) {
    for (let i = annotations.length - 1; i >= 0; i--) {
      const a = annotations[i];
      if (!a) continue;
      if (px >= a.x && px <= a.x + a.w && py >= a.y && py <= a.y + a.h) return i;
    }
    return -1;
  }

  function hitResize(px, py) {
    const margin = 8 / scale;
    for (let i = annotations.length - 1; i >= 0; i--) {
      const a = annotations[i];
      if (!a) continue;
      if (Math.abs(px - (a.x + a.w)) < margin && Math.abs(py - (a.y + a.h)) < margin) return {idx: i, handle: 'br'};
      if (Math.abs(px - (a.x + a.w)) < margin && py >= a.y && py <= a.y + a.h) return {idx: i, handle: 'right'};
      if (Math.abs(py - (a.y + a.h)) < margin && px >= a.x && px <= a.x + a.w) return {idx: i, handle: 'bottom'};
    }
    return null;
  }

  const cvs = document.getElementById('ba-canvas');
  const ctx = cvs.getContext('2d');
  const wrap = document.getElementById('ba-wrap');

  // ── Auto-refresh spreadsheet dropdown when folder changes ──
  let folderRefreshTimer = null;
  document.getElementById('ba-folder').addEventListener('change', refreshSpreadsheets);
  document.getElementById('ba-folder').addEventListener('blur', refreshSpreadsheets);
  document.getElementById('ba-folder').addEventListener('keyup', function() {
    clearTimeout(folderRefreshTimer);
    folderRefreshTimer = setTimeout(refreshSpreadsheets, 500);
  });

  function refreshSpreadsheets() {
    const folder = document.getElementById('ba-folder').value.trim();
    if (!folder) return;
    const ssSelect = document.getElementById('ba-spreadsheet');

    fetch('{{ route("admin.ai.htr.fsOverlayLoad") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({ folder: folder }),
    })
    .then(r => r.json())
    .then(data => {
      if (data.needsSelection && data.spreadsheets) {
        ssSelect.innerHTML = '<option value="">Select spreadsheet...</option>';
        data.spreadsheets.forEach(name => {
          const opt = document.createElement('option');
          opt.value = name;
          opt.textContent = name;
          ssSelect.appendChild(opt);
        });
        // Auto-select if only one
        if (data.spreadsheets.length === 1) {
          ssSelect.value = data.spreadsheets[0];
        }
      }
    })
    .catch(() => {});
  }

  // Load spreadsheets on page load
  refreshSpreadsheets();

  // ── Load data (two-step: list spreadsheets → select → load) ──
  window.loadBulkData = function() {
    const folder = document.getElementById('ba-folder').value.trim();
    if (!folder) { alert('Enter folder path'); return; }

    const btn = document.getElementById('ba-load-btn');
    const ssSelect = document.getElementById('ba-spreadsheet');
    const selectedSS = ssSelect.value;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading...';

    fetch('{{ route("admin.ai.htr.fsOverlayLoad") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({ folder: folder, spreadsheet: selectedSS }),
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-upload me-1"></i>Load';
      if (!data.success) { alert(data.error || 'Load failed'); return; }

      // Step 1: populate spreadsheet dropdown
      if (data.needsSelection) {
        ssSelect.innerHTML = '<option value="">Select spreadsheet...</option>';
        (data.spreadsheets || []).forEach(name => {
          const opt = document.createElement('option');
          opt.value = name;
          opt.textContent = name;
          ssSelect.appendChild(opt);
        });
        // Auto-select if only one
        if (data.spreadsheets.length === 1) {
          ssSelect.value = data.spreadsheets[0];
          loadBulkData(); // re-call with selection
        }
        return;
      }

      // Step 2: data loaded
      images = data.images;
      COLUMNS = data.columns || [];
      imgIdx = -1;
      document.getElementById('ba-workspace').style.display = '';
      document.getElementById('ba-remaining-count').textContent = images.length;

      if (COLUMNS.length === 0 && images.length > 0) {
        COLUMNS = Object.keys(images[0].fields);
      }

      // Ensure all template fields are in COLUMNS (even if not in CSV)
      // So user can always draw boxes for Name, Cause of Death, etc.
      const selFormType = document.getElementById('ba-form-type').value;
      const tplKey = (selFormType && selFormType !== 'auto') ? selFormType : 'sa-death-generic';
      const tpl = FORM_TEMPLATES[tplKey];
      if (tpl && tpl.fields) {
        for (const fieldName of Object.keys(tpl.fields)) {
          if (shouldSkip(fieldName)) continue;
          if (!COLUMNS.includes(fieldName)) {
            COLUMNS.push(fieldName);
            // Add empty field value to all images
            images.forEach(img => { if (!img.fields[fieldName]) img.fields[fieldName] = ''; });
          }
        }
      }

      currentFolder = folder;

      // Form type: use dropdown selection or default
      const selType = document.getElementById('ba-form-type').value;
      if (selType && selType !== 'auto') {
        currentFormType = selType;
      } else {
        currentFormType = 'sa-death-generic'; // will be refined by auto-detect
      }

      // Load saved positions for current form type from server
      loadSavedPositions(() => {
        console.log('[FS Overlay] Starting with form type:', currentFormType, 'saved fields:', Object.keys(savedPositions));
        nextImage();
        baSetTool('hand');
      });
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-upload me-1"></i>Load';
      alert(err.message);
    });
  };

  function nextImage() {
    imgIdx++;
    if (imgIdx >= images.length) { alert('All images annotated!'); return; }
    fieldIdx = 0;
    annotations = [];
    skipped = [];
    loadImage();
    buildFieldList();
    updateCounters();
  }

  function loadImage() {
    const entry = images[imgIdx];
    document.getElementById('ba-image-name').textContent = entry.fname + ' (' + (imgIdx + 1) + '/' + images.length + ')';
    document.getElementById('ba-counter').textContent = (imgIdx + 1) + '/' + images.length;

    img = new Image();
    img.onload = function() {
      offsetX = 0; offsetY = 0; cvs.style.transform = '';
      scale = wrap.clientWidth / img.width;
      cvs.width = img.width * scale;
      cvs.height = img.height * scale;

      const selectedType = document.getElementById('ba-form-type').value;

      if (selectedType && selectedType !== 'auto') {
        // User selected a specific form type — use its template
        applyFormTemplate(selectedType);
        redraw();
      } else if (COLUMNS.some(col => savedPositions[col])) {
        // Have saved positions from previous manual adjustments
        autoPlaceFields();
        redraw();
      } else {
        // Auto-detect: quick OCR to identify form type, then apply template
        document.getElementById('ba-image-name').textContent += ' — detecting form type...';
        fetch('{{ route("admin.ai.htr.fsOverlayOcr") }}', {
          method: 'POST',
          headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
          body: JSON.stringify({ image_path: entry.path, fields: [] }),
        })
        .then(r => r.json())
        .then(data => {
          if (data.success && data.words) {
            const formType = detectFormType(data.words);
            detectedAnchor = data.anchor || null;
            currentFormType = formType;
            loadSavedPositions(() => {
              applyFormTemplate(formType, detectedAnchor);
              redraw();
            });
          } else {
            currentFormType = 'sa-death-generic';
            detectedAnchor = null;
            loadSavedPositions(() => {
              applyFormTemplate('sa-death-generic');
              redraw();
            });
          }
        })
        .catch(() => { applyFormTemplate('sa-death-generic'); redraw(); });
      }
    };
    img.src = '{{ route("admin.ai.htr.serveImage") }}?path=' + encodeURIComponent(entry.path);
  }

  // ── Auto-place: position all field boxes on the image ──
  // Priority: 1) saved positions from previous images, 2) smart defaults
  function autoPlaceFields() {
    const entry = images[imgIdx];
    annotations = [];

    // Count non-empty fields to calculate spacing
    const activeFields = COLUMNS.filter(col => entry.fields[col]);
    const fieldCount = activeFields.length;

    // Smart default sizing — spread fields vertically across the document
    const imgW = img.width;
    const imgH = img.height;
    const boxW = Math.round(imgW * 0.45);  // ~45% of image width
    const boxH = Math.round(Math.min(60, imgH / (fieldCount + 2))); // tall enough to read
    const startX = Math.round(imgW * 0.05); // 5% margin from left
    const startY = Math.round(imgH * 0.08); // start 8% from top
    const spacing = Math.round((imgH * 0.85) / Math.max(fieldCount, 1)); // even vertical spacing
    let activeIdx = 0;

    COLUMNS.forEach(function(col, i) {
      const val = entry.fields[col] || '';
      if (shouldSkip(col)) { skipped.push(i); annotations.push(null); return; }

      // Use saved position if we have one for this column
      if (savedPositions[col]) {
        annotations.push({
          label: col,
          value: val,
          x: savedPositions[col].x,
          y: savedPositions[col].y,
          w: savedPositions[col].w,
          h: savedPositions[col].h,
        });
      } else {
        // Smart default: distribute evenly down the page
        annotations.push({
          label: col,
          value: val,
          x: startX,
          y: startY + activeIdx * spacing,
          w: boxW,
          h: boxH,
        });
      }
      activeIdx++;
    });

    highlightField();
    updateProgress();

    // Update coord displays
    annotations.forEach(function(ann, i) {
      if (!ann) return;
      const c = document.getElementById('ba-coords-' + i);
      if (c) c.textContent = Math.round(ann.x) + ',' + Math.round(ann.y) + ' ' + Math.round(ann.w) + '×' + Math.round(ann.h);
    });
  }

  // ── OCR labels: detect printed form labels and position boxes there ──
  function ocrAndPlace(entry) {
    document.getElementById('ba-image-name').textContent += ' — OCR detecting labels...';

    const activeFields = COLUMNS.filter(col => entry.fields[col]);

    fetch('{{ route("admin.ai.htr.fsOverlayOcr") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({
        image_path: entry.path,
        fields: activeFields,
      }),
    })
    .then(r => r.json())
    .then(data => {
      if (data.success && data.positions) {
        // Apply OCR-detected positions
        const positions = data.positions;
        annotations = [];
        COLUMNS.forEach(function(col, i) {
          const val = entry.fields[col] || '';
          if (shouldSkip(col)) { skipped.push(i); annotations.push(null); return; }

          if (positions[col]) {
            const p = positions[col];
            const ann = {
              label: col,
              value: val,
              x: p.x,
              y: p.y,
              w: p.w,
              h: p.h,
            };
            annotations.push(ann);
            // Save as reference for next images
            savedPositions[col] = { x: p.x, y: p.y, w: p.w, h: p.h };
          } else {
            // No OCR match — use default position
            const activeIdx = annotations.filter(a => a).length;
            annotations.push({
              label: col,
              value: val,
              x: 20,
              y: 50 + activeIdx * Math.round(img.height / (activeFields.length + 2)),
              w: Math.round(img.width * 0.45),
              h: 40,
            });
          }
        });

        highlightField();
        updateProgress();
        annotations.forEach(function(ann, i) {
          if (!ann) return;
          const c = document.getElementById('ba-coords-' + i);
          if (c) c.textContent = Math.round(ann.x) + ',' + Math.round(ann.y) + ' ' + Math.round(ann.w) + '×' + Math.round(ann.h);
        });
        redraw();

        document.getElementById('ba-image-name').textContent = entry.fname + ' (' + (imgIdx + 1) + '/' + images.length + ') — ' + Object.keys(positions).length + ' labels detected';
      } else {
        // OCR failed — fall back to default placement
        autoPlaceFields();
        redraw();
      }
    })
    .catch(() => {
      autoPlaceFields();
      redraw();
    });
  }

  // Reset saved positions (start fresh)
  // Migrate all localStorage positions to server (one-time sync)
  window.baMigrateToServer = function() {
    let count = 0;
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (!key.startsWith('fs-overlay-pos-')) continue;
      try {
        const positions = JSON.parse(localStorage.getItem(key));
        // Extract form type from key: fs-overlay-pos-sa-death-1894 → sa-death-1894
        const formType = key.replace('fs-overlay-pos-', '').replace(/_/g, '-');
        fetch('{{ route("admin.ai.htr.fsOverlaySavePositions") }}', {
          method: 'POST',
          headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
          body: JSON.stringify({ form_type: formType, positions }),
        });
        count++;
      } catch(e) {}
    }
    // Also save current positions
    if (currentFormType && Object.keys(savedPositions).length) {
      fetch('{{ route("admin.ai.htr.fsOverlaySavePositions") }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        body: JSON.stringify({ form_type: currentFormType, positions: savedPositions }),
      });
      count++;
    }
    const btn = document.getElementById('ba-migrate-btn');
    btn.innerHTML = '<i class="fas fa-check me-1"></i>Synced (' + count + ')';
    btn.disabled = true;
    alert('Synced ' + count + ' form type position(s) to server.');
  };

  window.baResetPositions = function() {
    if (!confirm('Clear all saved field positions? You will need to reposition on the next image.')) return;
    savedPositions = {};
    persistPositions(); // clear on server too
    autoPlaceFields();
    redraw();
  };

  window.baAutoPlace = function() {
    autoPlaceFields();
    redraw();
  };

  function buildFieldList() {
    const entry = images[imgIdx];
    const container = document.getElementById('ba-fields');
    container.innerHTML = '';

    COLUMNS.forEach(function(col, i) {
      const val = entry.fields[col] || '';
      const div = document.createElement('div');
      div.className = 'ba-field' + (i === fieldIdx ? ' active' : '');
      div.dataset.idx = i;

      const skipBtn = '<button class="btn btn-sm btn-outline-secondary ba-skip-btn" onclick="event.stopPropagation(); baSkipField(' + i + ')" title="Skip / unskip"><i class="fas fa-forward"></i></button>';
      const escapedVal = (val || '').replace(/"/g, '&quot;');

      div.innerHTML = skipBtn +
        '<div class="ba-label" style="color:' + COLORS[i % COLORS.length] + '">' + (i + 1) + '. ' + col + '</div>' +
        '<input class="ba-edit-input" type="text" value="' + escapedVal + '" data-field-idx="' + i + '" placeholder="Type value..." onclick="event.stopPropagation()">' +
        '<div class="ba-coords" id="ba-coords-' + i + '"></div>';

      const input = div.querySelector('.ba-edit-input');
      input.addEventListener('change', function() {
        const idx = parseInt(this.dataset.fieldIdx);
        entry.fields[COLUMNS[idx]] = this.value;
        if (annotations[idx]) annotations[idx].value = this.value;
        redraw();
      });
      input.addEventListener('keydown', function(e) {
        e.stopPropagation();
        if (e.key === 'Enter') { this.blur(); advanceToNextField(); highlightField(); }
      });
      div.onclick = function() {
        if (!skipped.includes(i)) { fieldIdx = i; highlightField(); }
      };
      container.appendChild(div);
    });

    // Auto-skip non-allowed fields
    COLUMNS.forEach(function(col, i) {
      if (shouldSkip(col)) skipped.push(i);
    });

    highlightField();
    updateProgress();
  }

  window.baSkipField = function(idx) {
    if (!skipped.includes(idx)) {
      skipped.push(idx);
      annotations[idx] = null;
    } else {
      skipped = skipped.filter(i => i !== idx);
    }
    if (skipped.includes(fieldIdx)) advanceToNextField();
    highlightField();
    updateProgress();
    redraw();
  };

  function advanceToNextField() {
    let next = fieldIdx + 1;
    while (next < COLUMNS.length && skipped.includes(next)) next++;
    if (next < COLUMNS.length) fieldIdx = next;
  }

  function highlightField() {
    document.querySelectorAll('.ba-field').forEach(function(el, i) {
      el.classList.toggle('active', i === fieldIdx && !skipped.includes(i));
      el.classList.toggle('done', annotations[i] && annotations[i] !== null);
      el.classList.toggle('skipped', skipped.includes(i));
    });
    const active = document.querySelector('.ba-field.active');
    if (active) active.scrollIntoView({ block: 'nearest' });
  }

  function updateProgress() {
    const active = COLUMNS.length - skipped.length;
    const done = annotations.filter(a => a !== null && a !== undefined).length;
    const pct = active > 0 ? (done / active * 100) : 100;
    document.getElementById('ba-progress').style.width = pct + '%';
    document.getElementById('ba-save-btn').disabled = done < 1;
  }

  function updateCounters() {
    document.getElementById('ba-done-count').textContent = sessionDone;
    document.getElementById('ba-remaining-count').textContent = Math.max(0, images.length - imgIdx - 1);
    document.getElementById('ba-fields-count').textContent = sessionFields;
    document.getElementById('ba-prev-btn').disabled = imgIdx <= 0;
  }

  // ── Drawing ──
  function pos(e) {
    const r = cvs.getBoundingClientRect();
    return { x: (e.clientX - r.left) / scale, y: (e.clientY - r.top) / scale };
  }

  cvs.addEventListener('mousedown', function(e) {
    if (e.button !== 0) return;
    const p = pos(e);

    if (currentTool === 'hand') {
      panning = true;
      panStartX = e.clientX; panStartY = e.clientY;
      panScrollX = offsetX; panScrollY = offsetY;
      cvs.style.cursor = 'grabbing';
      e.preventDefault();
      return;
    }

    if (currentTool === 'select') {
      const rh = hitResize(p.x, p.y);
      if (rh) { resizing = true; resizeIdx = rh.idx; resizeHandle = rh.handle; return; }
      const hit = hitTest(p.x, p.y);
      if (hit >= 0) {
        dragging = true; dragIdx = hit;
        dragOffX = p.x - annotations[hit].x;
        dragOffY = p.y - annotations[hit].y;
        fieldIdx = hit;
        highlightField();
        return;
      }
      return;
    }

    if (currentTool === 'draw') { drawing = true; sx = p.x; sy = p.y; }
  });

  document.addEventListener('mousemove', function(e) {
    if (panning) {
      offsetX = panScrollX + (e.clientX - panStartX);
      offsetY = panScrollY + (e.clientY - panStartY);
      cvs.style.transform = 'translate(' + offsetX + 'px, ' + offsetY + 'px)';
    }
  });
  document.addEventListener('mouseup', function(e) {
    if (panning) { panning = false; cvs.style.cursor = 'grab'; }
  });

  cvs.addEventListener('mousemove', function(e) {
    if (panning) return;
    const p = pos(e);

    if (resizing && annotations[resizeIdx]) {
      const a = annotations[resizeIdx];
      if (resizeHandle === 'right' || resizeHandle === 'br') a.w = Math.max(10, p.x - a.x);
      if (resizeHandle === 'bottom' || resizeHandle === 'br') a.h = Math.max(10, p.y - a.y);
      redraw(); return;
    }

    if (dragging && annotations[dragIdx]) {
      annotations[dragIdx].x = Math.max(0, p.x - dragOffX);
      annotations[dragIdx].y = Math.max(0, p.y - dragOffY);
      redraw(); return;
    }

    if (drawing) {
      redraw();
      ctx.save();
      ctx.strokeStyle = COLORS[fieldIdx % COLORS.length];
      ctx.lineWidth = 2 / scale;
      ctx.setLineDash([4 / scale, 4 / scale]);
      ctx.strokeRect(sx * scale, sy * scale, (p.x - sx) * scale, (p.y - sy) * scale);
      ctx.restore();
      return;
    }

    if (currentTool === 'select') {
      const rh = hitResize(p.x, p.y);
      if (rh) { cvs.style.cursor = rh.handle === 'right' ? 'ew-resize' : (rh.handle === 'bottom' ? 'ns-resize' : 'nwse-resize'); }
      else if (hitTest(p.x, p.y) >= 0) { cvs.style.cursor = 'move'; }
      else { cvs.style.cursor = 'default'; }
    }
  });

  cvs.addEventListener('mouseup', function(e) {
    if (panning) { panning = false; return; }

    if (resizing) {
      resizing = false;
      const a = annotations[resizeIdx];
      if (a) {
        savedPositions[a.label] = { x: a.x, y: a.y, w: a.w, h: a.h };
        const c = document.getElementById('ba-coords-' + resizeIdx);
        if (c) c.textContent = Math.round(a.x) + ',' + Math.round(a.y) + ' ' + Math.round(a.w) + '×' + Math.round(a.h);
      }
      redraw(); return;
    }

    if (dragging) {
      dragging = false;
      const a = annotations[dragIdx];
      if (a) {
        a.x = Math.round(a.x); a.y = Math.round(a.y);
        savedPositions[a.label] = { x: a.x, y: a.y, w: a.w, h: a.h };
        const c = document.getElementById('ba-coords-' + dragIdx);
        if (c) c.textContent = Math.round(a.x) + ',' + Math.round(a.y) + ' ' + Math.round(a.w) + '×' + Math.round(a.h);
      }
      redraw(); return;
    }

    if (!drawing) return;
    drawing = false;
    const p = pos(e);
    let x = Math.min(sx, p.x), y = Math.min(sy, p.y);
    let w = Math.abs(p.x - sx), h = Math.abs(p.y - sy);
    if (w < 5 || h < 5) { redraw(); return; }

    const entry = images[imgIdx];
    const col = COLUMNS[fieldIdx];
    const val = entry.fields[col] || '';

    annotations[fieldIdx] = { label: col, value: val, x: Math.round(x), y: Math.round(y), w: Math.round(w), h: Math.round(h) };
    savedPositions[col] = { x: Math.round(x), y: Math.round(y), w: Math.round(w), h: Math.round(h) };

    const coordsEl = document.getElementById('ba-coords-' + fieldIdx);
    if (coordsEl) coordsEl.textContent = Math.round(x) + ',' + Math.round(y) + ' ' + Math.round(w) + '×' + Math.round(h);

    sessionFields++;
    advanceToNextField();
    highlightField();
    updateProgress();
    updateCounters();
    redraw();
  });

  function persistPositions() {
    if (!currentFormType) return;
    fetch('{{ route("admin.ai.htr.fsOverlaySavePositions") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({ form_type: currentFormType, positions: savedPositions }),
    }).catch(() => {});
  }

  function loadSavedPositions(cb) {
    if (!currentFormType) { savedPositions = {}; if (cb) cb(); return; }
    fetch('{{ route("admin.ai.htr.fsOverlayLoadPositions") }}?form_type=' + encodeURIComponent(currentFormType), {
      credentials: 'same-origin',
    })
    .then(r => r.json())
    .then(data => {
      savedPositions = data.positions || {};
      console.log('[FS Overlay] Loaded server positions for:', currentFormType, 'fields:', Object.keys(savedPositions));
      if (cb) cb();
    })
    .catch(() => { savedPositions = {}; if (cb) cb(); });
  }

  function redraw() {
    if (!img) return;
    ctx.clearRect(0, 0, cvs.width, cvs.height);
    ctx.drawImage(img, 0, 0, img.width * scale, img.height * scale);

    annotations.forEach(function(ann, i) {
      if (!ann) return;
      const color = COLORS[i % COLORS.length];
      const isActive = i === fieldIdx;
      ctx.save();

      // Box fill (semi-transparent)
      ctx.fillStyle = color;
      ctx.globalAlpha = isActive ? 0.15 : 0.08;
      ctx.fillRect(ann.x * scale, ann.y * scale, ann.w * scale, ann.h * scale);

      // Box border
      ctx.globalAlpha = 1;
      ctx.strokeStyle = color;
      ctx.lineWidth = isActive ? 3 : 1.5;
      ctx.strokeRect(ann.x * scale, ann.y * scale, ann.w * scale, ann.h * scale);

      // Label background (bigger, bolder)
      const labelText = (i + 1) + '. ' + ann.label;
      ctx.font = (isActive ? 'bold 14px' : '13px') + ' sans-serif';
      const tw = ctx.measureText(labelText).width + 12;
      ctx.fillStyle = color;
      ctx.globalAlpha = 0.92;
      ctx.fillRect(ann.x * scale, ann.y * scale - 20, tw, 20);

      // Label text
      ctx.globalAlpha = 1;
      ctx.fillStyle = '#fff';
      ctx.fillText(labelText, ann.x * scale + 5, ann.y * scale - 5);

      // Value preview below box (bigger)
      if (ann.value) {
        ctx.font = 'bold 12px sans-serif';
        const vw = ctx.measureText(ann.value).width + 10;
        ctx.fillStyle = 'rgba(255,255,255,0.92)';
        ctx.fillRect(ann.x * scale, (ann.y + ann.h) * scale + 2, vw, 16);
        ctx.fillStyle = '#333';
        ctx.fillText(ann.value, ann.x * scale + 4, (ann.y + ann.h) * scale + 14);
      }

      ctx.restore();
    });
  }

  // ── Keyboard shortcuts ──
  document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (e.key === 'Enter') { advanceToNextField(); highlightField(); e.preventDefault(); }
    if (e.key === 'Backspace') {
      if (annotations[fieldIdx]) { annotations[fieldIdx] = null; highlightField(); updateProgress(); redraw(); }
      e.preventDefault();
    }
    if (e.key === 'ArrowRight') {
      if (!skipped.includes(fieldIdx)) { skipped.push(fieldIdx); annotations[fieldIdx] = null; }
      advanceToNextField(); highlightField(); updateProgress(); redraw(); e.preventDefault();
    }
    if (e.key === 'ArrowLeft') { fieldIdx = Math.max(0, fieldIdx - 1); highlightField(); e.preventDefault(); }
    if (e.ctrlKey && e.key === 's') { e.preventDefault(); baSaveAndNext(); }
    if (e.key === 'h' || e.key === 'H') baSetTool('hand');
    if (e.key === 'r' || e.key === 'R') baSetTool('draw');
    if (e.key === 'v' || e.key === 'V') baSetTool('select');
  });

  // ── Zoom ──
  window.baZoomIn = function() { scale *= 1.2; cvs.width = img.width * scale; cvs.height = img.height * scale; redraw(); };
  window.baZoomOut = function() { scale = Math.max(0.1, scale / 1.2); cvs.width = img.width * scale; cvs.height = img.height * scale; redraw(); };
  window.baZoomFit = function() { scale = wrap.clientWidth / img.width; cvs.width = img.width * scale; cvs.height = img.height * scale; offsetX = 0; offsetY = 0; cvs.style.transform = ''; redraw(); };

  wrap.addEventListener('wheel', function(e) {
    if (!img) return;
    e.preventDefault();
    const rect = wrap.getBoundingClientRect();
    const mx = e.clientX - rect.left + wrap.scrollLeft;
    const my = e.clientY - rect.top + wrap.scrollTop;
    const oldScale = scale;
    scale = e.deltaY < 0 ? Math.min(scale * 1.15, 10) : Math.max(scale / 1.15, 0.1);
    cvs.width = img.width * scale; cvs.height = img.height * scale;
    redraw();
    const ratio = scale / oldScale;
    wrap.scrollLeft = mx * ratio - (e.clientX - rect.left);
    wrap.scrollTop = my * ratio - (e.clientY - rect.top);
  }, { passive: false });

  // ── Navigation ──
  window.baPrev = function() { if (imgIdx > 0) { imgIdx -= 2; nextImage(); } };
  window.baSkip = function() { nextImage(); };

  // ── Save ──
  window.baSaveAndNext = function() {
    const entry = images[imgIdx];
    const btn = document.getElementById('ba-save-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

    // Update savedPositions from ALL current annotations (coordinates + sizes)
    annotations.forEach(function(ann) {
      if (ann) {
        savedPositions[ann.label] = { x: ann.x, y: ann.y, w: ann.w, h: ann.h };
      }
    });

    // Save positions to server, then save annotation
    persistPositions();

    fetch('{{ route("admin.ai.htr.fsOverlaySave") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({
        image_path: entry.path,
        fname: entry.fname,
        fields: entry.fields,
        annotations: annotations.filter(a => a),
        folder: document.getElementById('ba-folder').value.trim(),
      }),
    })
    .then(r => r.json())
    .then(data => {
      btn.innerHTML = '<i class="fas fa-save me-1"></i>Save & Next';
      btn.disabled = false;
      if (data.success) {
        sessionDone++;
        updateCounters();
        nextImage();
      } else {
        alert(data.error || 'Save failed');
      }
    })
    .catch(err => {
      btn.innerHTML = '<i class="fas fa-save me-1"></i>Save & Next';
      btn.disabled = false;
      alert(err.message);
    });
  };
})();
</script>
@endpush
