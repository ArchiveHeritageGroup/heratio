@extends('theme::layouts.1col')

@section('title', 'Bulk Annotate — HTR Training')
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
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-0"><i class="fas fa-magic me-2"></i>Bulk Annotate</h1>
    <span class="small text-muted">Draw boxes to map spreadsheet data to image regions</span>
  </div>
  <div class="btn-group btn-group-sm">
    <a href="{{ route('admin.ai.htr.annotate') }}" class="btn atom-btn-white"><i class="fas fa-pencil-alt me-1"></i>Manual Annotate</a>
    <a href="{{ route('admin.ai.htr.fsOverlay') }}" class="btn atom-btn-white"><i class="fas fa-layer-group me-1"></i>FS Overlay</a>
    <a href="{{ route('admin.ai.htr.training') }}" class="btn atom-btn-white"><i class="fas fa-graduation-cap me-1"></i>Training</a>
  </div>
</div>

{{-- Folder + Spreadsheet Selection --}}
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="row align-items-end">
      <div class="col-md-5">
        <label class="form-label small fw-bold">Folder with images</label>
        <input type="text" id="ba-folder" class="form-control form-control-sm" value="/usr/share/nginx/heratio/FamilySearch/stefan" placeholder="/path/to/images">
      </div>
      <div class="col-md-5">
        <label class="form-label small fw-bold">Spreadsheet (in same folder)</label>
        <select id="ba-spreadsheet" class="form-select form-select-sm">
          <option value="">Select spreadsheet...</option>
        </select>
      </div>
      <div class="col-md-2">
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
          <div class="btn-group btn-group-sm me-2">
            <button class="btn btn-light active" id="ba-tool-hand" title="Pan (H)" onclick="baSetTool('hand')"><i class="fas fa-hand-paper"></i></button>
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
      <button class="btn btn-sm atom-btn-white" onclick="baSkip()"><i class="fas fa-forward me-1"></i>Skip Image</button>
      <button class="btn btn-sm atom-btn-outline-success" onclick="baSaveAndNext()" id="ba-save-btn" disabled><i class="fas fa-save me-1"></i>Save & Next</button>
    </div>
  </div>

  {{-- Field list sidebar --}}
  <div class="col-md-4">
    <div class="card">
      <div class="card-header py-1" style="background:var(--ahg-primary);color:#fff">
        <span class="small">Fields — draw box for each in order</span>
      </div>
      <div class="card-body p-2 ba-sidebar" id="ba-fields"></div>
    </div>
    <div class="progress ba-progress mt-2">
      <div class="progress-bar bg-success" id="ba-progress" style="width:0%"></div>
    </div>
    <div class="mt-2 small text-muted">
      <kbd>Draw</kbd> box around field · <kbd>Enter</kbd> confirm & next · <kbd>→</kbd> skip field (not on form) · <kbd>Backspace</kbd> undo · <kbd>Ctrl+S</kbd> save & next image · Click <i class="fas fa-forward"></i> to toggle skip
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
  // Dynamic columns — populated from spreadsheet headers on load
  let COLUMNS = [];
  const HIDE_COLUMNS = ['Birth Year (Estimated)', 'Birth Year', 'Event Type'];
  const COLORS = ['#ff6b6b','#4ecdc4','#45b7d1','#96ceb4','#ffeaa7','#dfe6e9','#fd79a8','#6c5ce7','#00b894'];

  let images = [];       // [{fname, fields: {Name: 'x', Sex: 'y', ...}}]
  let imgIdx = -1;
  let fieldIdx = 0;      // current field being annotated
  let annotations = [];  // [{label, value, x, y, w, h}]
  let img = null;
  let scale = 1;
  let currentTool = 'hand'; // 'hand', 'draw', 'select'
  let drawing = false, sx = 0, sy = 0;
  let dragging = false, dragIdx = -1, dragOffX = 0, dragOffY = 0;
  let resizing = false, resizeIdx = -1, resizeHandle = '';
  let panning = false, panStartX = 0, panStartY = 0, panScrollX = 0, panScrollY = 0;
  let offsetX = 0, offsetY = 0; // Canvas translate offset for panning

  window.baSetTool = function(t) {
    currentTool = t;
    document.getElementById('ba-tool-hand').classList.toggle('active', t === 'hand');
    document.getElementById('ba-tool-draw').classList.toggle('active', t === 'draw');
    document.getElementById('ba-tool-select').classList.toggle('active', t === 'select');
    // Set cursor directly
    cvs.style.setProperty('cursor', t === 'hand' ? 'grab' : (t === 'draw' ? 'crosshair' : 'default'), 'important');
    console.log('Tool set to:', t);
  };
  let skipped = [];     // indices of skipped fields
  let sessionDone = 0, sessionFields = 0;

  function hitTest(px, py) {
    // Check if point is inside any annotation box (return index or -1)
    for (let i = annotations.length - 1; i >= 0; i--) {
      const a = annotations[i];
      if (!a) continue;
      if (px >= a.x && px <= a.x + a.w && py >= a.y && py <= a.y + a.h) return i;
    }
    return -1;
  }

  function hitResize(px, py) {
    // Check if near edge of any annotation (for resize)
    const margin = 8 / scale;
    for (let i = annotations.length - 1; i >= 0; i--) {
      const a = annotations[i];
      if (!a) continue;
      // Right edge
      if (Math.abs(px - (a.x + a.w)) < margin && py >= a.y && py <= a.y + a.h) return {idx: i, handle: 'right'};
      // Bottom edge
      if (Math.abs(py - (a.y + a.h)) < margin && px >= a.x && px <= a.x + a.w) return {idx: i, handle: 'bottom'};
      // Bottom-right corner
      if (Math.abs(px - (a.x + a.w)) < margin && Math.abs(py - (a.y + a.h)) < margin) return {idx: i, handle: 'br'};
    }
    return null;
  }

  const cvs = document.getElementById('ba-canvas');
  const ctx = cvs.getContext('2d');
  const wrap = document.getElementById('ba-wrap');

  // ── Auto-refresh spreadsheet dropdown on folder change ──
  let baFolderTimer = null;
  document.getElementById('ba-folder').addEventListener('change', baRefreshSpreadsheets);
  document.getElementById('ba-folder').addEventListener('blur', baRefreshSpreadsheets);
  document.getElementById('ba-folder').addEventListener('keyup', function() {
    clearTimeout(baFolderTimer);
    baFolderTimer = setTimeout(baRefreshSpreadsheets, 500);
  });

  function baRefreshSpreadsheets() {
    const folder = document.getElementById('ba-folder').value.trim();
    if (!folder) return;
    fetch('{{ route("admin.ai.htr.bulkAnnotateLoad") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({ folder: folder }),
    })
    .then(r => r.json())
    .then(data => {
      if (data.needsSelection && data.spreadsheets) {
        const sel = document.getElementById('ba-spreadsheet');
        sel.innerHTML = '<option value="">Select spreadsheet...</option>';
        data.spreadsheets.forEach(name => {
          const opt = document.createElement('option');
          opt.value = name;
          opt.textContent = name;
          sel.appendChild(opt);
        });
        if (data.spreadsheets.length === 1) sel.value = data.spreadsheets[0];
      }
    })
    .catch(() => {});
  }
  baRefreshSpreadsheets(); // load on page open

  // ── Load data ──
  window.loadBulkData = function() {
    const folder = document.getElementById('ba-folder').value.trim();
    if (!folder) { alert('Enter folder path'); return; }

    const btn = document.getElementById('ba-load-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading...';

    fetch('{{ route("admin.ai.htr.bulkAnnotateLoad") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({ folder: folder, spreadsheet: document.getElementById('ba-spreadsheet').value }),
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-upload me-1"></i>Load';
      if (!data.success) { alert(data.error || 'Load failed'); return; }

      // Handle spreadsheet selection (shared loader now requires it)
      if (data.needsSelection) {
        const ssSelect = document.getElementById('ba-spreadsheet');
        ssSelect.innerHTML = '<option value="">Select spreadsheet...</option>';
        (data.spreadsheets || []).forEach(name => {
          const opt = document.createElement('option');
          opt.value = name;
          opt.textContent = name;
          ssSelect.appendChild(opt);
        });
        if (data.spreadsheets.length === 1) {
          ssSelect.value = data.spreadsheets[0];
          loadBulkData();
        }
        return;
      }

      images = data.images;
      COLUMNS = (data.columns || []).filter(c => !HIDE_COLUMNS.includes(c));
      imgIdx = -1;
      document.getElementById('ba-workspace').style.display = '';
      document.getElementById('ba-remaining-count').textContent = images.length;

      // Show column list for confirmation
      if (COLUMNS.length === 0 && images.length > 0) {
        COLUMNS = Object.keys(images[0].fields).filter(c => !HIDE_COLUMNS.includes(c));
      }

      nextImage();
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-upload me-1"></i>Load';
      alert(err.message);
    });
  };

  function nextImage() {
    imgIdx++;
    if (imgIdx >= images.length) {
      alert('All images annotated!');
      return;
    }
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
      // Reset pan offset
      offsetX = 0; offsetY = 0; cvs.style.transform = '';
      // Start at full width
      scale = wrap.clientWidth / img.width;
      cvs.width = img.width * scale;
      cvs.height = img.height * scale;
      redraw();
    };
    img.src = '{{ route("admin.ai.htr.serveImage") }}?path=' + encodeURIComponent(entry.path);
  }

  function buildFieldList() {
    const entry = images[imgIdx];
    const container = document.getElementById('ba-fields');
    container.innerHTML = '';

    COLUMNS.forEach(function(col, i) {
      const val = entry.fields[col] || '';
      const div = document.createElement('div');
      div.className = 'ba-field' + (i === fieldIdx ? ' active' : '');
      div.dataset.idx = i;

      // Skip button
      const skipBtn = '<button class="btn btn-sm btn-outline-secondary ba-skip-btn" onclick="event.stopPropagation(); baSkipField(' + i + ')" title="Skip / unskip">' +
                       '<i class="fas fa-forward"></i></button>';

      const escapedVal = (val || '').replace(/"/g, '&quot;');
      div.innerHTML = skipBtn +
                       '<div class="ba-label">' + (i + 1) + '. ' + col + '</div>' +
                       '<input class="ba-edit-input" type="text" value="' + escapedVal + '" data-field-idx="' + i + '" placeholder="Type value..." onclick="event.stopPropagation()">' +
                       '<div class="ba-coords" id="ba-coords-' + i + '"></div>';

      // Update value when edited
      const input = div.querySelector('.ba-edit-input');
      input.addEventListener('change', function() {
        const idx = parseInt(this.dataset.fieldIdx);
        entry.fields[COLUMNS[idx]] = this.value;
        if (annotations[idx]) annotations[idx].value = this.value;
      });
      input.addEventListener('keydown', function(e) {
        e.stopPropagation(); // Don't trigger global shortcuts while typing
        if (e.key === 'Enter') {
          this.blur();
          advanceToNextField();
          highlightField();
        }
      });
      div.onclick = function() {
        if (!skipped.includes(i)) { fieldIdx = i; highlightField(); }
      };
      container.appendChild(div);
    });

    // Auto-skip fields with empty values
    COLUMNS.forEach(function(col, i) {
      const val = entry.fields[col] || '';
      if (!val) skipped.push(i);
    });

    highlightField();
    updateProgress();
  }

  window.baSkipField = function(idx) {
    if (!skipped.includes(idx)) {
      skipped.push(idx);
      // Remove annotation if one was drawn
      annotations[idx] = null;
    } else {
      // Unskip
      skipped = skipped.filter(function(i) { return i !== idx; });
    }
    // Advance to next non-skipped field
    if (skipped.includes(fieldIdx)) {
      advanceToNextField();
    }
    highlightField();
    updateProgress();
    redraw();
  };

  function advanceToNextField() {
    let next = fieldIdx + 1;
    while (next < COLUMNS.length && skipped.includes(next)) next++;
    if (next < COLUMNS.length) {
      fieldIdx = next;
    }
  }

  function highlightField() {
    document.querySelectorAll('.ba-field').forEach(function(el, i) {
      el.classList.toggle('active', i === fieldIdx && !skipped.includes(i));
      el.classList.toggle('done', annotations[i] && annotations[i] !== null);
      el.classList.toggle('skipped', skipped.includes(i));
    });
    // Scroll active into view
    const active = document.querySelector('.ba-field.active');
    if (active) active.scrollIntoView({ block: 'nearest' });
  }

  function updateProgress() {
    const active = COLUMNS.length - skipped.length;
    const done = annotations.filter(function(a) { return a !== null && a !== undefined; }).length;
    const pct = active > 0 ? (done / active * 100) : 100;
    document.getElementById('ba-progress').style.width = pct + '%';
    document.getElementById('ba-save-btn').disabled = done < 1; // need at least 1 annotated field
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

    // ── Hand tool: pan by translating canvas ──
    if (currentTool === 'hand') {
      panning = true;
      panStartX = e.clientX;
      panStartY = e.clientY;
      panScrollX = offsetX;
      panScrollY = offsetY;
      cvs.style.cursor = 'grabbing';
      e.preventDefault();
      return;
    }

    // ── Select tool: move/resize existing boxes ──
    if (currentTool === 'select') {
      const rh = hitResize(p.x, p.y);
      if (rh) {
        resizing = true;
        resizeIdx = rh.idx;
        resizeHandle = rh.handle;
        return;
      }
      const hit = hitTest(p.x, p.y);
      if (hit >= 0) {
        dragging = true;
        dragIdx = hit;
        dragOffX = p.x - annotations[hit].x;
        dragOffY = p.y - annotations[hit].y;
        fieldIdx = hit;
        highlightField();
        return;
      }
      return;
    }

    // ── Draw tool: new box ──
    if (currentTool === 'draw') {
      drawing = true;
      sx = p.x; sy = p.y;
    }
  });

  // Mousemove on document so pan works even when cursor leaves canvas
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
    if (panning) return; // handled by document listener

    const p = pos(e);

    // Resize
    if (resizing && annotations[resizeIdx]) {
      const a = annotations[resizeIdx];
      if (resizeHandle === 'right' || resizeHandle === 'br') a.w = Math.max(10, p.x - a.x);
      if (resizeHandle === 'bottom' || resizeHandle === 'br') a.h = Math.max(10, p.y - a.y);
      redraw();
      return;
    }

    // Drag
    if (dragging && annotations[dragIdx]) {
      annotations[dragIdx].x = Math.max(0, p.x - dragOffX);
      annotations[dragIdx].y = Math.max(0, p.y - dragOffY);
      redraw();
      return;
    }

    // Drawing new box
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

    // Cursor hint
    const rh = hitResize(p.x, p.y);
    if (rh) {
      cvs.style.cursor = rh.handle === 'right' ? 'ew-resize' : (rh.handle === 'bottom' ? 'ns-resize' : 'nwse-resize');
    } else if (hitTest(p.x, p.y) >= 0) {
      cvs.style.cursor = 'move';
    } else {
      cvs.style.cursor = 'crosshair';
    }
  });

  cvs.addEventListener('mouseup', function(e) {
    // End pan
    if (panning) {
      panning = false;
      wrap.classList.remove('panning');
      return;
    }

    // End resize
    if (resizing) {
      resizing = false;
      wrap.classList.remove('dragging');
      const a = annotations[resizeIdx];
      if (a) {
        const coordsEl = document.getElementById('ba-coords-' + resizeIdx);
        if (coordsEl) coordsEl.textContent = Math.round(a.x) + ',' + Math.round(a.y) + ' ' + Math.round(a.w) + '×' + Math.round(a.h);
      }
      redraw();
      return;
    }

    // End drag
    if (dragging) {
      dragging = false;
      wrap.classList.remove('dragging');
      const a = annotations[dragIdx];
      if (a) {
        a.x = Math.round(a.x);
        a.y = Math.round(a.y);
        const coordsEl = document.getElementById('ba-coords-' + dragIdx);
        if (coordsEl) coordsEl.textContent = Math.round(a.x) + ',' + Math.round(a.y) + ' ' + Math.round(a.w) + '×' + Math.round(a.h);
      }
      redraw();
      return;
    }

    // End drawing
    if (!drawing) return;
    drawing = false;
    const p = pos(e);
    let x = Math.min(sx, p.x), y = Math.min(sy, p.y);
    let w = Math.abs(p.x - sx), h = Math.abs(p.y - sy);
    if (w < 5 || h < 5) { redraw(); return; }

    const entry = images[imgIdx];
    const col = COLUMNS[fieldIdx];
    const val = entry.fields[col] || '';

    // Add or replace annotation for this field
    annotations[fieldIdx] = {
      label: col,
      value: val,
      x: Math.round(x), y: Math.round(y), w: Math.round(w), h: Math.round(h),
    };

    // Show coords
    const coordsEl = document.getElementById('ba-coords-' + fieldIdx);
    if (coordsEl) coordsEl.textContent = Math.round(x) + ',' + Math.round(y) + ' ' + Math.round(w) + '×' + Math.round(h);

    sessionFields++;
    advanceToNextField();
    highlightField();
    updateProgress();
    updateCounters();
    redraw();
  });

  function redraw() {
    if (!img) return;
    ctx.clearRect(0, 0, cvs.width, cvs.height);
    ctx.drawImage(img, 0, 0, img.width * scale, img.height * scale);

    // Draw existing annotations
    annotations.forEach(function(ann, i) {
      if (!ann) return;
      ctx.save();
      ctx.strokeStyle = COLORS[i % COLORS.length];
      ctx.lineWidth = 2;
      ctx.strokeRect(ann.x * scale, ann.y * scale, ann.w * scale, ann.h * scale);
      // Label
      ctx.fillStyle = COLORS[i % COLORS.length];
      ctx.globalAlpha = 0.8;
      ctx.fillRect(ann.x * scale, ann.y * scale - 14, ctx.measureText((i+1) + '. ' + ann.label).width + 8, 14);
      ctx.globalAlpha = 1;
      ctx.fillStyle = '#fff';
      ctx.font = '11px sans-serif';
      ctx.fillText((i+1) + '. ' + ann.label, ann.x * scale + 3, ann.y * scale - 3);
      // Value preview
      if (ann.value) {
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        ctx.fillRect(ann.x * scale, (ann.y + ann.h) * scale, ctx.measureText(ann.value).width + 8, 14);
        ctx.fillStyle = '#333';
        ctx.fillText(ann.value, ann.x * scale + 3, (ann.y + ann.h) * scale + 11);
      }
      ctx.restore();
    });
  }

  // ── Keyboard shortcuts ──
  document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

    if (e.key === 'Enter') { // Confirm current field, move to next non-skipped
      advanceToNextField();
      highlightField();
      e.preventDefault();
    }
    if (e.key === 'Backspace') { // Undo last annotation
      if (annotations.length > 0) {
        annotations.pop();
        fieldIdx = Math.max(0, annotations.length);
        highlightField();
        updateProgress();
        redraw();
      }
      e.preventDefault();
    }
    if (e.key === 'ArrowRight') { // Skip current field and advance
      if (!skipped.includes(fieldIdx)) {
        skipped.push(fieldIdx);
        annotations[fieldIdx] = null;
      }
      advanceToNextField();
      highlightField();
      updateProgress();
      redraw();
      e.preventDefault();
    }
    if (e.key === 'ArrowLeft') { // Previous field
      fieldIdx = Math.max(0, fieldIdx - 1);
      highlightField();
      e.preventDefault();
    }
    if (e.ctrlKey && e.key === 's') { // Save & next
      e.preventDefault();
      baSaveAndNext();
    }
  });

  // ── Zoom ──
  window.baZoomIn = function() { scale *= 1.2; cvs.width = img.width * scale; cvs.height = img.height * scale; redraw(); };
  window.baZoomOut = function() { scale = Math.max(0.1, scale / 1.2); cvs.width = img.width * scale; cvs.height = img.height * scale; redraw(); };
  window.baZoomFit = function() { scale = wrap.clientWidth / img.width; cvs.width = img.width * scale; cvs.height = img.height * scale; offsetX = 0; offsetY = 0; cvs.style.transform = ''; redraw(); };

  // ── Mouse wheel zoom (zoom at cursor position) ──
  wrap.addEventListener('wheel', function(e) {
    if (!img) return;
    e.preventDefault();
    const rect = wrap.getBoundingClientRect();
    const mx = e.clientX - rect.left + wrap.scrollLeft;
    const my = e.clientY - rect.top + wrap.scrollTop;
    const oldScale = scale;

    if (e.deltaY < 0) {
      scale = Math.min(scale * 1.15, 10);
    } else {
      scale = Math.max(scale / 1.15, 0.1);
    }

    cvs.width = img.width * scale;
    cvs.height = img.height * scale;
    redraw();

    // Keep zoom centered on cursor
    const ratio = scale / oldScale;
    wrap.scrollLeft = mx * ratio - (e.clientX - rect.left);
    wrap.scrollTop = my * ratio - (e.clientY - rect.top);
  }, { passive: false });

  // ── Keyboard tool switching (matching annotate page) ──
  document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (e.key === 'h' || e.key === 'H') baSetTool('hand');
    if (e.key === 'r' || e.key === 'R') baSetTool('draw');
    if (e.key === 'v' || e.key === 'V') baSetTool('select');
  });

  // ── Navigation ──
  window.baPrev = function() {
    if (imgIdx > 0) { imgIdx -= 2; nextImage(); }
  };
  window.baSkip = function() { nextImage(); };

  // ── Save ──
  window.baSaveAndNext = function() {
    const entry = images[imgIdx];
    const btn = document.getElementById('ba-save-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

    fetch('{{ route("admin.ai.htr.bulkAnnotateSave") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({
        image_path: entry.path,
        fname: entry.fname,
        fields: entry.fields,
        annotations: annotations.filter(a => a), // remove nulls
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
