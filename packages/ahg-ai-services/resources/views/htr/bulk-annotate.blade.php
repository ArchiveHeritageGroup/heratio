@extends('theme::layouts.1col')

@section('title', 'Bulk Annotate — HTR Training')
@section('body-class', 'admin ai htr')

@push('css')
<style>
  .ba-wrap { position: relative; overflow: auto; background: #1a1a2e; border-radius: 4px; cursor: crosshair; max-height: 75vh; }
  .ba-wrap canvas { display: block; }
  .ba-sidebar { max-height: 75vh; overflow-y: auto; }
  .ba-field { padding: 6px 10px; border-left: 3px solid #ccc; margin-bottom: 4px; cursor: pointer; }
  .ba-field.active { border-left-color: #0d6efd; background: #e8f0fe; }
  .ba-field.done { border-left-color: #198754; background: #d1e7dd; }
  .ba-field .ba-label { font-weight: 600; font-size: 0.8rem; color: #666; }
  .ba-field .ba-value { font-size: 0.95rem; }
  .ba-field .ba-coords { font-size: 0.7rem; color: #999; }
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
      <kbd>R</kbd> draw box · <kbd>Enter</kbd> confirm & next field · <kbd>Backspace</kbd> undo last · <kbd>→</kbd> skip field · <kbd>Ctrl+S</kbd> save & next image
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
  const COLUMNS = ['Name', 'Sex', 'Age', 'Birth Year (Estimated)', 'Residence Place',
                    'Relationship to Head of Household', 'Event Type', 'Event Date', 'Event Place'];
  const COLORS = ['#ff6b6b','#4ecdc4','#45b7d1','#96ceb4','#ffeaa7','#dfe6e9','#fd79a8','#6c5ce7','#00b894'];

  let images = [];       // [{fname, fields: {Name: 'x', Sex: 'y', ...}}]
  let imgIdx = -1;
  let fieldIdx = 0;      // current field being annotated
  let annotations = [];  // [{label, value, x, y, w, h}]
  let img = null;
  let scale = 1;
  let drawing = false, sx = 0, sy = 0;
  let sessionDone = 0, sessionFields = 0;

  const cvs = document.getElementById('ba-canvas');
  const ctx = cvs.getContext('2d');
  const wrap = document.getElementById('ba-wrap');

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
      body: JSON.stringify({ folder: folder }),
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-upload me-1"></i>Load';
      if (!data.success) { alert(data.error || 'Load failed'); return; }

      images = data.images;
      imgIdx = -1;
      document.getElementById('ba-workspace').style.display = '';
      document.getElementById('ba-remaining-count').textContent = images.length;
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
      scale = Math.min(wrap.clientWidth / img.width, 1.5);
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
      div.innerHTML = '<div class="ba-label">' + (i + 1) + '. ' + col + '</div>' +
                       '<div class="ba-value">' + (val || '<em class="text-muted">empty</em>') + '</div>' +
                       '<div class="ba-coords" id="ba-coords-' + i + '"></div>';
      div.onclick = function() { fieldIdx = i; highlightField(); };
      container.appendChild(div);
    });

    highlightField();
    updateProgress();
  }

  function highlightField() {
    document.querySelectorAll('.ba-field').forEach(function(el, i) {
      el.classList.toggle('active', i === fieldIdx);
      el.classList.toggle('done', i < annotations.length);
    });
    // Scroll active into view
    const active = document.querySelector('.ba-field.active');
    if (active) active.scrollIntoView({ block: 'nearest' });
  }

  function updateProgress() {
    const pct = COLUMNS.length > 0 ? (annotations.length / COLUMNS.length * 100) : 0;
    document.getElementById('ba-progress').style.width = pct + '%';
    document.getElementById('ba-save-btn').disabled = annotations.length < 2; // need at least year + place
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
    drawing = true;
    sx = p.x; sy = p.y;
  });

  cvs.addEventListener('mousemove', function(e) {
    if (!drawing) return;
    const p = pos(e);
    redraw();
    ctx.save();
    ctx.strokeStyle = COLORS[fieldIdx % COLORS.length];
    ctx.lineWidth = 2 / scale;
    ctx.setLineDash([4 / scale, 4 / scale]);
    ctx.strokeRect(sx * scale, sy * scale, (p.x - sx) * scale, (p.y - sy) * scale);
    ctx.restore();
  });

  cvs.addEventListener('mouseup', function(e) {
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
    fieldIdx = Math.min(fieldIdx + 1, COLUMNS.length - 1);
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

    if (e.key === 'Enter') { // Confirm current field, move to next
      if (fieldIdx < COLUMNS.length - 1) fieldIdx++;
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
    if (e.key === 'ArrowRight') { // Skip field
      fieldIdx = Math.min(fieldIdx + 1, COLUMNS.length - 1);
      highlightField();
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
  window.baZoomOut = function() { scale /= 1.2; cvs.width = img.width * scale; cvs.height = img.height * scale; redraw(); };
  window.baZoomFit = function() { scale = Math.min(wrap.clientWidth / img.width, 1.5); cvs.width = img.width * scale; cvs.height = img.height * scale; redraw(); };

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
