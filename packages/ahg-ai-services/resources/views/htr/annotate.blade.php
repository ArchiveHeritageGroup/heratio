@extends('theme::layouts.1col')
@section('title', 'ILM Annotate — HTR')
@section('body-class', 'admin ai-services htr')
@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.ai.htr.dashboard') }}">HTR</a></li>
    <li class="breadcrumb-item active">ILM Annotate</li>
  </ol>
</nav>
@include('ahg-ai-services::htr._nav')

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-pen-square me-2"></i>ILM Annotate</h1>
  <div class="d-flex gap-2">
    <a href="{{ route('admin.ai.htr.sources') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-database me-1"></i>Sources</a>
    <a href="{{ route('admin.ai.htr.bulkAnnotate') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-magic me-1"></i>Bulk Annotate</a>
    <a href="{{ route('admin.ai.htr.fsOverlay') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-layer-group me-1"></i>FS Overlay</a>
    <a href="{{ route('admin.ai.htr.training') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-graduation-cap me-1"></i>Training</a>
  </div>
</div>

{{-- Folder bar --}}
<div class="card mb-2">
  <div class="card-body py-2">
    <div class="row align-items-center g-2">
      <div class="col-auto"><label class="form-label mb-0 small fw-bold">Server Folder:</label></div>
      <div class="col-md-4">
        <div class="input-group input-group-sm">
          <select id="folder-preset" class="form-select form-select-sm">
            <option value="/usr/share/nginx/heratio/FamilySearch" selected>FamilySearch (all)</option>
            <option value="/usr/share/nginx/heratio/FamilySearch/1898">FamilySearch / 1898</option>
            <option value="/usr/share/nginx/heratio/FamilySearch/1904">FamilySearch / 1904</option>
            <option value="/usr/share/nginx/heratio/FamilySearch/1912">FamilySearch / 1912</option>
            <option value="/usr/share/nginx/heratio/FamilySearch/1920">FamilySearch / 1920</option>
            <option value="/usr/share/nginx/heratio/FamilySearch/1930">FamilySearch / 1930</option>
            <option value="/usr/share/nginx/heratio/FamilySearch/wynberg_death_1925-1929">Wynberg Deaths 1925-1929 (248)</option>
            <option value="/tmp">Temp (/tmp)</option>
            <option value="type_a">Training: Type A</option>
            <option value="type_b">Training: Type B</option>
            <option value="type_c">Training: Type C</option>
            <option value="">— Custom path —</option>
          </select>
          <input type="text" id="folder-custom" class="form-control form-control-sm" placeholder="or type path here">
          <button class="btn atom-btn-outline-success" id="btn-folder-load"><i class="fas fa-folder-open me-1"></i>Load</button>
        </div>
      </div>
      <div class="col-auto" id="folder-nav" style="display:none;">
        <div class="btn-group btn-group-sm">
          <button class="btn atom-btn-white" id="btn-prev" disabled><i class="fas fa-chevron-left"></i></button>
          <button class="btn atom-btn-white disabled" id="btn-counter" style="min-width:90px;pointer-events:none;">0/0</button>
          <button class="btn atom-btn-white" id="btn-next" disabled><i class="fas fa-chevron-right"></i></button>
        </div>
      </div>
      <div class="col-auto" id="folder-stats" style="display:none;">
        <span class="badge bg-success" id="badge-done">0</span> done
        <span class="badge bg-secondary ms-1" id="badge-todo">0</span> todo
      </div>
      <div class="col-auto" id="skip-wrap" style="display:none;">
        <div class="form-check form-check-inline mb-0">
          <input class="form-check-input" type="checkbox" id="skip-done" checked>
          <label class="form-check-label small" for="skip-done">Skip done</label>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Toolbar --}}
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="row align-items-center g-2">
      <div class="col-auto">
        <input type="file" id="image-upload" class="form-control form-control-sm" accept="image/*" style="max-width:180px;">
      </div>
      <div class="col-auto">
        <select id="doc-type" class="form-select form-select-sm" style="width:auto;">
          <option value="type_a">Type A — Death Cert</option>
          <option value="type_b">Type B — Register</option>
          <option value="type_c">Type C — Narrative</option>
        </select>
      </div>
      <div class="col-auto border-start ps-2">
        <div class="btn-group btn-group-sm">
          <button class="btn atom-btn-white" id="tool-rect" title="Draw (R)"><i class="fas fa-vector-square"></i></button>
          <button class="btn atom-btn-white active" id="tool-hand" title="Pan (H / Space)"><i class="fas fa-hand-paper"></i></button>
          <button class="btn atom-btn-white" id="tool-select" title="Select (V)"><i class="fas fa-mouse-pointer"></i></button>
        </div>
      </div>
      <div class="col-auto">
        <div class="btn-group btn-group-sm">
          <button class="btn atom-btn-white" id="btn-zin"><i class="fas fa-search-plus"></i></button>
          <button class="btn atom-btn-white" id="btn-zout"><i class="fas fa-search-minus"></i></button>
          <button class="btn atom-btn-white" id="btn-zfit"><i class="fas fa-expand"></i></button>
        </div>
      </div>
      <div class="col-auto">
        <button class="btn atom-btn-white btn-sm" id="btn-undo" disabled><i class="fas fa-undo"></i></button>
      </div>
      <div class="col-auto">
        <div class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" id="non-gen-toggle">
          <label class="form-check-label small" for="non-gen-toggle">Non-genealogical image</label>
        </div>
      </div>
      <div class="col-auto border-start ps-2" id="row-split-tools" style="display:none;">
        <div class="btn-group btn-group-sm">
          <button class="btn atom-btn-white" id="btn-auto-rows" title="Auto-detect rows"><i class="fas fa-grip-lines me-1"></i>Auto Rows</button>
          <input type="number" class="form-control form-control-sm" id="row-count" value="7" min="1" max="20" style="width:50px;" title="Number of rows">
          <button class="btn atom-btn-white" id="btn-split-go" title="Split into row images"><i class="fas fa-cut me-1"></i>Split &amp; Annotate</button>
        </div>
      </div>
      <div class="col-auto ms-auto">
        <button class="btn atom-btn-white btn-sm" id="btn-skip" disabled title="Skip — move to rework folder"><i class="fas fa-forward me-1"></i>Skip</button>
        <button class="btn atom-btn-outline-success btn-sm" id="btn-save" disabled><i class="fas fa-save me-1"></i>Save</button>
      </div>
    </div>
  </div>
</div>

{{-- Non-genealogical bar (hidden by default) --}}
<div class="alert alert-warning mb-3 py-2" id="ng-bar" style="display:none;">
  <div class="d-flex align-items-center gap-3">
    <strong>Non-genealogical type:</strong>
    <select id="ng-type" class="form-select form-select-sm" style="width:auto;">
      <option value="135026">Administrative Image</option>
      <option value="135165">No Extractable Data</option>
      <option value="135784">No Genealogical Data</option>
    </select>
    <span class="small text-muted">No field boxes needed — just save.</span>
  </div>
</div>

<div class="row">
  {{-- Canvas --}}
  <div class="col-md-8">
    <div class="card">
      <div class="card-body p-0 position-relative" style="overflow:auto; max-height:78vh;" id="wrap">
        <div id="placeholder" class="text-center text-muted py-5" style="min-height:500px; display:flex; align-items:center; justify-content:center;">
          <div>
            <i class="fas fa-image fa-3x mb-3 d-block"></i>
            <p class="mb-1">Load a server folder or upload an image</p>
            <p class="small mb-0">Box 1 = <strong>Event Year</strong> | Box 2 = <strong>Event Place</strong> | Record Type = document level</p>
          </div>
        </div>
        <canvas id="cvs" style="display:none; cursor:grab;"></canvas>
      </div>
    </div>
    <small class="text-muted" id="info">No image</small>
  </div>

  {{-- Right panel --}}
  <div class="col-md-4">
    {{-- Document-level: Record Type --}}
    <div class="card mb-3" id="rectype-card">
      <div class="card-header py-2" style="background: var(--ahg-primary); color: white;">
        <i class="fas fa-file-alt me-1"></i>Record Type (document level)
      </div>
      <div class="card-body py-2">
        <select id="record-type" class="form-select form-select-sm">
          <option value="Death Records">Death Records</option>
          <option value="Birth Records">Birth Records</option>
          <option value="Marriage Records">Marriage Records</option>
          <option value="Christenings">Christenings</option>
          <option value="Burials">Burials</option>
          <option value="Church Records">Church Records</option>
          <option value="Civil Registration">Civil Registration</option>
          <option value="Census">Census</option>
          <option value="Other Records">Other Records</option>
        </select>
      </div>
    </div>

    {{-- Field annotations --}}
    <div class="card mb-3">
      <div class="card-header py-2" style="background: var(--ahg-primary); color: white;">
        <i class="fas fa-list me-1"></i>Field Annotations <span class="badge bg-light text-dark ms-1" id="ann-badge">0</span>
      </div>
      <div class="card-body p-0" id="ann-panel" style="max-height:40vh; overflow-y:auto;">
        <div class="text-center text-muted py-3" id="no-ann">
          <i class="fas fa-draw-polygon d-block mb-2"></i>
          Draw a box around the event year, then event place
        </div>
      </div>
    </div>

    {{-- Quick place --}}
    <div class="card mb-3">
      <div class="card-header py-2" style="background: var(--ahg-primary); color: white;">
        <i class="fas fa-map-marker-alt me-1"></i>Quick Place
      </div>
      <div class="card-body p-2" id="quick-place"></div>
    </div>

    {{-- Session --}}
    <div class="card mb-3">
      <div class="card-header py-2" style="background: var(--ahg-primary); color: white;">
        <i class="fas fa-chart-bar me-1"></i>Session
      </div>
      <div class="card-body py-2">
        <div class="d-flex gap-3 small">
          <span><strong>A:</strong> <span id="cnt-a">0</span></span>
          <span><strong>B:</strong> <span id="cnt-b">0</span></span>
          <span><strong>C:</strong> <span id="cnt-c">0</span></span>
        </div>
      </div>
    </div>

    {{-- How to annotate --}}
    <div class="card">
      <div class="card-header py-2" style="background: var(--ahg-primary); color: white;">
        <i class="fas fa-question-circle me-1"></i>How to Annotate
      </div>
      <div class="card-body py-2 small">
        <div class="fw-bold mb-1">Type A — Single Form (Death Cert):</div>
        <ol class="mb-2 ps-3" style="line-height:1.7;">
          <li>Select <strong>server folder</strong> → click <strong>Load</strong></li>
          <li>Set <strong>Record Type</strong> (top-right)</li>
          <li>Press <strong>R</strong> → draw box around <strong>event year</strong> → type year</li>
          <li>Press <strong>R</strong> → draw box around <strong>event place</strong> → type place</li>
          <li>Press <strong>Enter</strong> to save → auto-advances</li>
        </ol>
        <div class="fw-bold mb-1">Type B — Register (Multiple Records):</div>
        <ol class="mb-2 ps-3" style="line-height:1.7;">
          <li>Select <strong>Type B — Register</strong> from Doc Type</li>
          <li>Load image → click <strong>Auto Rows</strong> (set row count first)</li>
          <li>Use <strong>V</strong> to drag/resize row boxes to fit entries</li>
          <li><strong>Delete</strong> empty rows, <strong>R</strong> to add missed ones</li>
          <li>Click <strong>Split &amp; Annotate</strong> → crops each row</li>
          <li>Switches to Type A → annotate each row (year + place)</li>
        </ol>
        <hr class="my-1">
        <div class="text-muted" style="font-size:.7rem;">
          <strong>Keys:</strong> H=pan, R=draw, V=select/move, Space=hold-to-pan, +/-=zoom, Tab=cycle, Del=delete, Ctrl+Z=undo
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('css')
<style>
  #cvs { image-rendering: auto; }
  #wrap.panning, #wrap.panning canvas { cursor: grab !important; }
  #wrap.panning:active, #wrap.panning:active canvas { cursor: grabbing !important; }
  .fi { border-bottom: 1px solid #eee; padding: 6px 10px; font-size: .85rem; cursor: pointer; transition: background .15s; }
  .fi:hover { background: #f0f4f8; }
  .fi.active { background: #dbeafe; border-left: 4px solid var(--ahg-primary); }
  .fi .dot { width: 12px; height: 12px; border-radius: 2px; display: inline-block; border: 1px solid rgba(0,0,0,.2); }
  .fi input, .fi select { font-size: 0.78rem; }
  .qp { font-size: 0.72rem; margin: 1px; padding: 2px 6px; }
  .ac-dropdown { position:absolute; z-index:99; background:#fff; border:1px solid #ddd; border-top:0; max-height:180px; overflow-y:auto; width:100%; box-shadow:0 4px 8px rgba(0,0,0,.15); }
  .ac-item { padding:4px 8px; font-size:.78rem; cursor:pointer; }
  .ac-item:hover, .ac-item.ac-active { background:var(--ahg-primary); color:#fff; }
  .ac-item .ac-prov { opacity:.7; font-size:.7rem; }
  .spell-errors { font-size:.72rem; margin-top:3px; line-height:1.6; }
  .spell-bad { color:#dc3545; font-weight:bold; text-decoration:line-through; cursor:pointer; margin-right:2px; padding:1px 3px; border:1px dashed #dc3545; border-radius:3px; }
  .spell-bad:hover { background:#dc3545; color:#fff; text-decoration:none; }
  .spell-bad:hover::after { content:' +add'; font-size:.6rem; font-weight:normal; }
  .spell-sug { color:#0d6efd; cursor:pointer; padding:1px 4px; border:1px solid #0d6efd; border-radius:3px; margin:0 2px; font-size:.68rem; }
  .spell-sug:hover { background:#0d6efd; color:#fff; }
  .spell-ok { color:#198754; }
</style>
@endpush

@push('js')
<script>
(function() {
  const COLORS = ['#e74c3c','#3498db','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#34495e','#e91e63','#00bcd4'];
  const ILM_LABELS = ['EVENT_YEAR_ORIG','EVENT_PLACE_ORIG'];
  const MAX_ANNS = 2; // Only 2 field boxes allowed: year + place
  const PLACES = ['Cape Province','Transvaal','Natal','Orange Free State'];

  // Printed form labels — what's physically printed on the document next to the field
  // Grouped by ILM field they map to
  const FORM_LABELS = {
    EVENT_YEAR_ORIG: [
      {en: 'Date of Death', af: 'Datum van Oorlyde', nl: 'Datum van Overlijden'},
      {en: 'Date of Birth', af: 'Geboortedatum', nl: 'Datum van Geboorte'},
      {en: 'Date of Marriage', af: 'Huweliksdatum', nl: 'Datum van Huwelijk'},
      {en: 'Date of Baptism', af: 'Doopdatum', nl: 'Datum van Doop'},
      {en: 'Date of Burial', af: 'Begrafnisdatum', nl: 'Datum van Begrafnis'},
      {en: 'Date Registered', af: 'Datum Geregistreer', nl: 'Datum Ingeschreven'},
    ],
    EVENT_PLACE_ORIG: [
      {en: 'Place of Death', af: 'Plek waar Oorlede', nl: 'Plaats van Overlijden'},
      {en: 'Place of Birth', af: 'Geboorteplek', nl: 'Plaats van Geboorte'},
      {en: 'Usual Residence', af: 'Gewone Woonplek', nl: 'Verblijfplaats'},
      {en: 'Place of Burial', af: 'Begraafplek', nl: 'Plaats van Begrafenis'},
      {en: 'District', af: 'Distrik', nl: 'District'},
      {en: 'Parish', af: 'Gemeente', nl: 'Parochie'},
    ],
  };

  // State
  let img = null, scale = 1, anns = [], activeId = null;
  let drawing = false, sx = 0, sy = 0;
  let tool = 'hand', panning = false, px = 0, py = 0, psx = 0, psy = 0;
  let dragging = false, dragAnn = null, dragOffX = 0, dragOffY = 0;
  let sess = {type_a:0, type_b:0, type_c:0};
  let files = [], fidx = -1;

  const cvs = document.getElementById('cvs');
  const ctx = cvs.getContext('2d');
  const wrap = document.getElementById('wrap');
  const ph = document.getElementById('placeholder');
  const pan = document.getElementById('ann-panel');
  const noA = document.getElementById('no-ann');

  // ── Quick place buttons ──
  document.getElementById('quick-place').innerHTML = PLACES.map(p =>
    '<button class="btn btn-sm atom-btn-white qp" data-p="' + p + '">' + p + '</button>'
  ).join('');
  document.getElementById('quick-place').addEventListener('click', function(e) {
    const b = e.target.closest('.qp');
    if (!b || !activeId) return;
    const a = anns.find(a => a.id === activeId);
    if (a) { a.text = b.dataset.p; redraw(); buildPanel(); }
  });

  // ── Non-genealogical toggle ──
  document.getElementById('non-gen-toggle').addEventListener('change', function() {
    document.getElementById('ng-bar').style.display = this.checked ? '' : 'none';
    document.getElementById('rectype-card').style.display = this.checked ? 'none' : '';
    document.getElementById('btn-save').disabled = !img;
  });

  // ── Doc type → record type sync + show/hide row split tools ──
  document.getElementById('doc-type').addEventListener('change', function() {
    const m = {type_a:'Death Records', type_b:'Church Records', type_c:'Other Records'};
    document.getElementById('record-type').value = m[this.value] || 'Other Records';
    // Show row split tools for Type B (registers with multiple records per page)
    document.getElementById('row-split-tools').style.display = this.value === 'type_b' ? '' : 'none';
    // Type B has no max annotations limit (multiple rows)
    if (this.value === 'type_b') { rowSplitMode = true; } else { rowSplitMode = false; }
  });
  let rowSplitMode = false;
  let rowBoxes = []; // [{id, x, y, w, h, color}] — row regions before splitting
  let preSplitState = null; // Saved folder state before split, so we can restore after last row

  // ── Image loading ──
  function loadImg(src) {
    img = new Image();
    img.onload = function() {
      anns = []; activeId = null;
      scale = Math.max(wrap.clientWidth / img.width, 1.0);
      if (img.height * scale > (wrap.clientHeight||600) * 3) scale = ((wrap.clientHeight||600)*2)/img.height;
      cvs.width = img.width*scale; cvs.height = img.height*scale;
      cvs.style.display = 'block'; ph.style.display = 'none';
      document.getElementById('non-gen-toggle').checked = false;
      document.getElementById('ng-bar').style.display = 'none';
      document.getElementById('rectype-card').style.display = '';
      redraw(); updInfo(); buildPanel();
      document.getElementById('btn-save').disabled = false;
      document.getElementById('btn-skip').disabled = false;
    };
    img.onerror = function() { alert('Failed to load image.'); };
    img.src = src;
  }

  document.getElementById('image-upload').addEventListener('change', function(e) {
    const f = e.target.files[0]; if (!f) return;
    cvs.dataset.serverPath = '';
    const r = new FileReader();
    r.onload = ev => loadImg(ev.target.result);
    r.readAsDataURL(f);
  });

  // ── Mouse ──
  function pos(e) { const r=cvs.getBoundingClientRect(); return {x:(e.clientX-r.left)/scale, y:(e.clientY-r.top)/scale}; }

  // Hit-test: find annotation under cursor (top-most first)
  function hitTest(p) {
    for (let i=anns.length-1; i>=0; i--) {
      const a=anns[i];
      if (p.x>=a.x && p.x<=a.x+a.w && p.y>=a.y && p.y<=a.y+a.h) return a;
    }
    return null;
  }

  // Resize handle hit-test — returns which edge/corner to resize
  // Returns: 'nw','n','ne','e','se','s','sw','w' or null
  const HANDLE_SIZE = 8; // pixels in image coords
  function handleHitTest(p, a) {
    if (!a) return null;
    const hs = HANDLE_SIZE / scale;
    const nearL = Math.abs(p.x - a.x) < hs;
    const nearR = Math.abs(p.x - (a.x + a.w)) < hs;
    const nearT = Math.abs(p.y - a.y) < hs;
    const nearB = Math.abs(p.y - (a.y + a.h)) < hs;
    const inX = p.x >= a.x - hs && p.x <= a.x + a.w + hs;
    const inY = p.y >= a.y - hs && p.y <= a.y + a.h + hs;

    if (nearT && nearL && inX && inY) return 'nw';
    if (nearT && nearR && inX && inY) return 'ne';
    if (nearB && nearL && inX && inY) return 'sw';
    if (nearB && nearR && inX && inY) return 'se';
    if (nearT && inX) return 'n';
    if (nearB && inX) return 's';
    if (nearL && inY) return 'w';
    if (nearR && inY) return 'e';
    return null;
  }

  const CURSORS = {nw:'nw-resize',n:'n-resize',ne:'ne-resize',e:'e-resize',se:'se-resize',s:'s-resize',sw:'sw-resize',w:'w-resize'};
  let resizing = false, resizeHandle = null, resizeAnn = null, resizeStart = {};

  cvs.addEventListener('mousedown', function(e) {
    // Hand tool — pan
    if (tool==='hand') { panning=true; px=e.clientX; py=e.clientY; psx=wrap.scrollLeft; psy=wrap.scrollTop; e.preventDefault(); return; }

    const p = pos(e);

    // Select tool — check resize handle first, then drag, then select
    if (tool==='select') {
      // Check resize on active annotation
      if (activeId) {
        const active = anns.find(a => a.id === activeId);
        const handle = handleHitTest(p, active);
        if (handle) {
          resizing = true;
          resizeHandle = handle;
          resizeAnn = active;
          resizeStart = { x: active.x, y: active.y, w: active.w, h: active.h, mx: p.x, my: p.y };
          cvs.style.cursor = CURSORS[handle];
          return;
        }
      }

      // Check drag (click inside any annotation)
      const hit = hitTest(p);
      if (hit) {
        setActive(hit.id);
        dragging = true;
        dragAnn = hit;
        dragOffX = p.x - hit.x;
        dragOffY = p.y - hit.y;
        cvs.style.cursor = 'move';
      } else {
        setActive(null);
      }
      return;
    }

    // Rect tool — draw new box (max 2)
    if (!rowSplitMode && anns.length >= MAX_ANNS) { return; }
    drawing=true; sx=p.x; sy=p.y;
  });

  cvs.addEventListener('mousemove', function(e) {
    // Panning
    if (panning) { wrap.scrollLeft=psx-(e.clientX-px); wrap.scrollTop=psy-(e.clientY-py); return; }

    const p = pos(e);

    // Resizing annotation
    if (resizing && resizeAnn) {
      const dx = p.x - resizeStart.mx;
      const dy = p.y - resizeStart.my;
      const s = resizeStart;
      const h = resizeHandle;

      let nx=s.x, ny=s.y, nw=s.w, nh=s.h;
      if (h.includes('w')) { nx = s.x + dx; nw = s.w - dx; }
      if (h.includes('e')) { nw = s.w + dx; }
      if (h.includes('n')) { ny = s.y + dy; nh = s.h - dy; }
      if (h.includes('s')) { nh = s.h + dy; }

      // Enforce minimum size
      if (nw < 10) { nw = 10; if (h.includes('w')) nx = s.x + s.w - 10; }
      if (nh < 10) { nh = 10; if (h.includes('n')) ny = s.y + s.h - 10; }

      resizeAnn.x = Math.round(Math.max(0, nx));
      resizeAnn.y = Math.round(Math.max(0, ny));
      resizeAnn.w = Math.round(Math.min(nw, img.width - resizeAnn.x));
      resizeAnn.h = Math.round(Math.min(nh, img.height - resizeAnn.y));
      redraw();
      return;
    }

    // Dragging annotation
    if (dragging && dragAnn) {
      dragAnn.x = Math.max(0, Math.min(img.width - dragAnn.w, Math.round(p.x - dragOffX)));
      dragAnn.y = Math.max(0, Math.min(img.height - dragAnn.h, Math.round(p.y - dragOffY)));
      redraw();
      return;
    }

    // Select tool hover cursor — show resize cursor near edges of active
    if (tool==='select' && !dragging && !resizing) {
      if (activeId) {
        const active = anns.find(a => a.id === activeId);
        const handle = handleHitTest(p, active);
        if (handle) { cvs.style.cursor = CURSORS[handle]; return; }
      }
      const hit = hitTest(p);
      cvs.style.cursor = hit ? 'move' : 'pointer';
    }

    // Drawing new rect
    if (!drawing) return;
    redraw();
    ctx.save(); ctx.scale(scale,scale);
    ctx.strokeStyle=COLORS[anns.length%COLORS.length]; ctx.lineWidth=2/scale; ctx.setLineDash([5/scale,3/scale]);
    ctx.strokeRect(sx,sy,p.x-sx,p.y-sy); ctx.restore();
  });

  cvs.addEventListener('mouseup', function(e) {
    if (panning) { panning=false; return; }

    // End resize
    if (resizing) {
      resizing=false; resizeAnn=null; resizeHandle=null;
      cvs.style.cursor = 'pointer';
      buildPanel();
      return;
    }

    // End drag
    if (dragging) {
      dragging=false; dragAnn=null;
      cvs.style.cursor = 'pointer';
      buildPanel();
      return;
    }

    if (!drawing) return; drawing=false;
    const p=pos(e);
    let x=Math.min(sx,p.x),y=Math.min(sy,p.y),w=Math.abs(p.x-sx),h=Math.abs(p.y-sy);
    if (w<5||h<5){redraw();return;}

    // First box = EVENT_YEAR_ORIG, second = EVENT_PLACE_ORIG — fixed, no choice
    const label = ILM_LABELS[anns.length];

    // Default form label based on doc type
    const docType = document.getElementById('doc-type').value;
    const defaultFormLabel = label === 'EVENT_YEAR_ORIG'
      ? (docType === 'type_a' ? 'Date of Death' : 'Date of Birth')
      : (docType === 'type_a' ? 'Place of Death' : 'Place of Birth');

    const newAnn = {
      id: Date.now(), x:Math.round(x), y:Math.round(y), w:Math.round(w), h:Math.round(h),
      label: label, text: '',
      form_label: defaultFormLabel,
      color: COLORS[anns.length%COLORS.length],
    };
    anns.push(newAnn);
    setActive(newAnn.id); redraw(); buildPanel();
    document.getElementById('btn-undo').disabled=false;

    // Auto-switch to hand after 2 boxes (done drawing)
    if (!rowSplitMode && anns.length >= MAX_ANNS) setTool('hand');

    // Auto-focus the text input AND trigger hybrid OCR
    const serverPath = cvs.dataset.serverPath || '';
    setTimeout(function() {
      const inp = document.querySelector('.fi.active input[data-role="txt"]');
      if (inp) {
        inp.focus();
        // ── Hybrid OCR: auto-recognize text in the drawn box ──
        if (serverPath) {
          inp.placeholder = 'Recognizing...';
          inp.classList.add('border-warning');
        }
      }
    }, 50);

    if (serverPath) {
      const annId = newAnn.id;

      fetch('{{ url("/admin/ai/htr/crop-ocr") }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        body: JSON.stringify({
          image_path: serverPath,
          bbox: {x: newAnn.x, y: newAnn.y, w: newAnn.w, h: newAnn.h},
        }),
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.text) {
          // Pre-fill the recognized text
          const ann = anns.find(function(a) { return a.id === annId; });
          if (ann && !ann.text) {
            ann.text = data.text;
            // Update the input field
            const inp = document.querySelector('.fi[data-id="' + annId + '"] input[data-role="txt"]');
            if (inp) {
              inp.value = data.text;
              inp.placeholder = '';
              inp.classList.add('border-success');
              setTimeout(function() { inp.classList.remove('border-success'); }, 2000);
            }
            buildPanel();
          }
        } else {
          // OCR failed or empty — just clear placeholder
          const inp = document.querySelector('.fi.active input[data-role="txt"]');
          if (inp) inp.placeholder = 'Type text...';
        }
      })
      .catch(function() {
        const inp = document.querySelector('.fi.active input[data-role="txt"]');
        if (inp) inp.placeholder = 'Type text...';
      });
    }
  });

  document.addEventListener('mouseup', function() {
    if (panning) panning=false;
    if (dragging) { dragging=false; dragAnn=null; buildPanel(); }
    if (resizing) { resizing=false; resizeAnn=null; resizeHandle=null; buildPanel(); }
  });

  // Wheel zoom
  wrap.addEventListener('wheel', function(e) {
    if(!img)return; e.preventDefault();
    const os=scale; scale=e.deltaY<0?Math.min(scale*1.15,6):Math.max(scale/1.15,0.3);
    const r=wrap.getBoundingClientRect();
    const mx=e.clientX-r.left+wrap.scrollLeft, my=e.clientY-r.top+wrap.scrollTop;
    const rat=scale/os;
    cvs.width=img.width*scale; cvs.height=img.height*scale; redraw(); updInfo();
    wrap.scrollLeft=mx*rat-(e.clientX-r.left); wrap.scrollTop=my*rat-(e.clientY-r.top);
  },{passive:false});

  // ── Render ──
  function redraw() {
    if(!img)return;
    ctx.clearRect(0,0,cvs.width,cvs.height);
    ctx.save(); ctx.scale(scale,scale); ctx.drawImage(img,0,0);

    // Draw inactive annotations first, active last (on top)
    const sorted = [...anns].sort((a,b) => (a.id===activeId?1:0) - (b.id===activeId?1:0));

    sorted.forEach(function(a){
      const i = anns.indexOf(a);
      const act = a.id===activeId;

      // Fill — active is brighter
      ctx.fillStyle = a.color + (act ? '44' : '15');
      ctx.fillRect(a.x, a.y, a.w, a.h);

      // Border — active is much thicker with solid line
      ctx.strokeStyle = act ? a.color : a.color + 'AA';
      ctx.lineWidth = (act ? 4 : 1.5) / scale;
      ctx.setLineDash(act ? [] : []);
      ctx.strokeRect(a.x, a.y, a.w, a.h);

      // Corner handles on active (drag affordance)
      if (act) {
        const hs = 6/scale; // handle size
        ctx.fillStyle = a.color;
        // 4 corners
        ctx.fillRect(a.x-hs/2, a.y-hs/2, hs, hs);
        ctx.fillRect(a.x+a.w-hs/2, a.y-hs/2, hs, hs);
        ctx.fillRect(a.x-hs/2, a.y+a.h-hs/2, hs, hs);
        ctx.fillRect(a.x+a.w-hs/2, a.y+a.h-hs/2, hs, hs);
      }

      // Label tag — show form label (e.g. "Date of Death") not ILM field name
      let lbl = '#'+(i+1)+' '+(a.form_label || a.label);
      if (a.text) lbl += ': '+a.text;
      ctx.font = ((act?13:11)/scale)+'px sans-serif';
      const tw = ctx.measureText(lbl).width;
      const lh = (act?18:15)/scale;
      ctx.fillStyle = a.color;
      ctx.fillRect(a.x, a.y-lh, tw+8/scale, lh);
      ctx.fillStyle = '#fff';
      ctx.fillText(lbl, a.x+4/scale, a.y-4/scale);

      // Number badge in center of box for easy identification
      ctx.font = 'bold '+(24/scale)+'px sans-serif';
      ctx.fillStyle = a.color + (act ? '88' : '44');
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText('#'+(i+1), a.x+a.w/2, a.y+a.h/2);
      ctx.textAlign = 'start'; ctx.textBaseline = 'alphabetic';
    });
    ctx.restore();
  }

  // ── Panel ──
  function buildPanel() {
    if(!anns.length){noA.style.display=''; document.getElementById('ann-badge').textContent='0'; return;}
    noA.style.display='none';
    document.getElementById('ann-badge').textContent=anns.length;

    pan.innerHTML = anns.map(function(a,i){
      const isYear = a.label === 'EVENT_YEAR_ORIG';
      const missing = !a.text;
      const formLabels = FORM_LABELS[a.label] || [];
      const flOptions = formLabels.map(fl =>
        '<option value="'+fl.en+'"'+(a.form_label===fl.en?' selected':'')+'>'+fl.en+' / '+fl.af+'</option>'
      ).join('');

      return '<div class="fi'+(a.id===activeId?' active':'')+'" data-id="'+a.id+'">' +
        '<div class="d-flex align-items-center mb-1">' +
          '<span class="dot me-1" style="background:'+a.color+'"></span>' +
          '<strong class="small me-auto">'+(isYear ? 'Event Year' : 'Event Place')+
            ' <span class="badge bg-danger ms-1">Required</span></strong>' +
          '<button class="btn btn-sm btn-link text-danger p-0" onclick="window._del('+a.id+')"><i class="fas fa-times"></i></button>' +
        '</div>' +
        '<select class="form-select form-select-sm mb-1" style="font-size:.72rem;" onchange="window._sf('+a.id+',\'form_label\',this.value)">' +
          flOptions +
        '</select>' +
        '<div class="position-relative">' +
          '<input type="text" class="form-control form-control-sm'+(missing?' border-danger':'')+'" data-role="txt" ' +
            'data-field="'+(isYear?'year':'place')+'" ' +
            'placeholder="'+(isYear ? 'e.g. 1904 — Ctrl+Space to lookup' : 'e.g. Cape Province — Ctrl+Space to lookup')+'" ' +
            'value="'+(a.text||'').replace(/"/g,'&quot;')+'" ' +
            'oninput="window._input('+a.id+',this)" ' +
            'onchange="window._sf('+a.id+',\'text\',this.value)" ' +
            'onfocus="window._act('+a.id+')" ' +
            'autocomplete="off">' +
          '<div class="ac-dropdown" id="ac-'+a.id+'" style="display:none;"></div>' +
        '</div>' +
      '</div>';
    }).join('');
  }

  function setActive(id) {
    activeId=id; redraw();
    document.querySelectorAll('.fi').forEach(function(el) {
      const isActive = parseInt(el.dataset.id)===id;
      el.classList.toggle('active', isActive);
      if (isActive) el.scrollIntoView({block:'nearest', behavior:'smooth'});
    });
  }

  // ── SA Towns for autocomplete ──
  let saTowns = [];
  fetch('{{ route("admin.ai.htr.folderList") }}?path=towns')
    .catch(() => {}); // preload attempt
  // Load towns from inline data (generated server-side)
  @php
    $townsFile = '/opt/ahg-ai/htr/places_cache.json';
    $towns = [];
    if (file_exists($townsFile)) {
        $data = json_decode(file_get_contents($townsFile), true);
        $towns = array_map(fn($t) => ['n' => $t['name'], 'p' => $t['historical_province'] ?? $t['province'] ?? ''], $data['sa_towns'] ?? []);
    }
  @endphp
  saTowns = @json($towns);

  // Globals
  window._del=function(id){anns=anns.filter(a=>a.id!==id);if(activeId===id)activeId=null;redraw();buildPanel();document.getElementById('btn-undo').disabled=!anns.length;};
  window._sf=function(id,f,v){const a=anns.find(a=>a.id===id);if(a){a[f]=v;redraw();}};
  window._act=function(id){setActive(id);};

  // Autocomplete for both fields
  let acActiveIdx = -1;

  // Common Dutch/Afrikaans month names for year field lookup
  const MONTH_MAP = {
    'januarie':'January','januarij':'January','januari':'January','februarie':'February',
    'februarij':'February','februari':'February','maart':'March','april':'April',
    'mei':'May','junie':'June','juni':'June','julie':'July','juli':'July',
    'augustus':'August','september':'September','oktober':'October','okt':'October',
    'november':'November','desember':'December','december':'December',
    'mrt':'March','aug':'August','sept':'September','nov':'November',
  };

  function showDropdown(dd, id, items) {
    if (!items.length) { dd.style.display='none'; acActiveIdx=-1; return; }
    acActiveIdx = -1;
    dd.innerHTML = items.map(it =>
      '<div class="ac-item" data-val="'+it.val+'" data-id="'+id+'">'+it.html+'</div>'
    ).join('');
    dd.style.display = '';
  }

  function lookupYear(text) {
    // Extract last word and look up Dutch/Afrikaans month or partial date
    const words = text.trim().split(/\s+/);
    const last = (words[words.length-1] || '').toLowerCase();
    const results = [];

    // Month name lookup
    for (const [af, en] of Object.entries(MONTH_MAP)) {
      if (af.startsWith(last) && last.length >= 2) {
        // Build suggestion: replace last word with English
        const prefix = words.slice(0, -1).join(' ');
        const suggestion = (prefix ? prefix + ' ' : '') + en;
        results.push({val: suggestion, html: suggestion + ' <span class="ac-prov">(' + af + ')</span>'});
      }
    }

    // If text has a number, suggest common date patterns
    const yearMatch = text.match(/\b(\d{2,4})\b/);
    if (yearMatch) {
      const num = yearMatch[1];
      if (num.length === 2) {
        // Suggest 18xx and 19xx
        results.push({val: '18'+num, html: '18'+num});
        results.push({val: '19'+num, html: '19'+num});
      }
    }

    return results;
  }

  function lookupPlace(text) {
    const q = text.toLowerCase().trim();
    if (q.length < 1) return [];

    // Get last word for Ctrl+Space context
    const words = q.split(/\s+/);
    const last = words[words.length-1];

    return saTowns
      .filter(t => t.n.toLowerCase().includes(last))
      .slice(0, 15)
      .map(m => ({
        val: m.n,
        html: m.n + (m.p ? ' <span class="ac-prov">('+m.p+')</span>' : ''),
      }));
  }

  window._input=function(id, inp) {
    const a = anns.find(a => a.id === id);
    if (a) a.text = inp.value;
    redraw();

    // Auto-show place dropdown on typing (2+ chars)
    if (inp.dataset.field === 'place') {
      const dd = document.getElementById('ac-'+id);
      if (!dd) return;
      const q = inp.value.trim();
      if (q.length < 2) { dd.style.display='none'; acActiveIdx=-1; return; }
      showDropdown(dd, id, lookupPlace(q));
    }
  };

  // Ctrl+Space — force open lookup dropdown for current field
  function ctrlSpaceLookup(inp) {
    const id = getAnnIdFromInput(inp);
    if (id === null) return;
    const dd = document.getElementById('ac-'+id);
    if (!dd) return;

    const text = inp.value.trim();
    let items = [];

    if (inp.dataset.field === 'year') {
      items = lookupYear(text);
      // If no specific matches, show month list
      if (!items.length) {
        items = Object.entries(MONTH_MAP)
          .filter(([af,en], i, arr) => arr.findIndex(([a,e]) => e===en) === i) // unique English
          .slice(0, 12)
          .map(([af, en]) => ({val: (text ? text+' ':'') + en, html: en + ' <span class="ac-prov">('+af+')</span>'}));
      }
    } else {
      items = lookupPlace(text || 'a'); // show all if empty
      if (!text) {
        // Show provinces first
        items = [
          {val:'Cape Province', html:'Cape Province <span class="ac-prov">(Kaap)</span>'},
          {val:'Transvaal', html:'Transvaal <span class="ac-prov">(TVL)</span>'},
          {val:'Natal', html:'Natal'},
          {val:'Orange Free State', html:'Orange Free State <span class="ac-prov">(OVS)</span>'},
        ].concat(items.slice(0, 10));
      }
    }

    showDropdown(dd, id, items);
  }

  function getAnnIdFromInput(inp) {
    const fi = inp.closest('.fi');
    return fi ? parseInt(fi.dataset.id) : null;
  }

  // Click autocomplete item
  document.addEventListener('click', function(e) {
    const item = e.target.closest('.ac-item');
    if (item) {
      const id = parseInt(item.dataset.id);
      const val = item.dataset.val;
      const a = anns.find(a => a.id === id);
      if (a) a.text = val;

      // Close dropdown
      const dd = item.parentElement;
      if (dd) dd.style.display = 'none';

      // Update the input directly (don't rebuild panel — keeps focus)
      const fi = document.querySelector('.fi[data-id="'+id+'"]');
      if (fi) {
        const inp = fi.querySelector('input[data-role="txt"]');
        if (inp) inp.value = val;
      }

      redraw();
      return;
    }
    // Close all dropdowns on outside click
    document.querySelectorAll('.ac-dropdown').forEach(d => d.style.display='none');
  });

  // Keyboard navigation in autocomplete
  pan.addEventListener('keydown', function(e) {
    const inp = e.target.closest('input[data-role="txt"]');
    if (!inp) return;

    // Ctrl+Space = force open lookup
    if (e.key === ' ' && e.ctrlKey) {
      e.preventDefault();
      ctrlSpaceLookup(inp);
      return;
    }

    // Enter = spellcheck current field first, then save if clean
    if (e.key === 'Enter') {
      e.preventDefault();
      // Close any open autocomplete first
      const openAc = inp.parentElement.querySelector('.ac-dropdown');
      if (openAc && openAc.style.display !== 'none') {
        const active = openAc.querySelector('.ac-active');
        if (active) { active.click(); return; }
        openAc.style.display = 'none';
      }
      // Sync current input value
      const a = anns.find(a => a.id === activeId);
      if (a) a.text = inp.value;

      // Run spellcheck on current field, then try save
      const fi = inp.closest('.fi');
      if (fi && inp.value.trim()) {
        fetch('{{ route("admin.ai.htr.spellcheck") }}', {
          method: 'POST',
          headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}'},
          body: JSON.stringify({text: inp.value}),
        })
        .then(r => r.json())
        .then(data => {
          if (data.errors && data.errors.length) {
            // Errors found — show them, keep focus, don't save
            showSpellErrors(fi, inp, data.errors);
            inp.focus();
          } else {
            // Clean — proceed to save
            showSpellErrors(fi, inp, []);
            document.getElementById('btn-save').click();
          }
        })
        .catch(() => {
          // Network error — save anyway
          document.getElementById('btn-save').click();
        });
      } else {
        document.getElementById('btn-save').click();
      }
      return;
    }

    // Arrow keys for autocomplete
    const dd = inp.parentElement.querySelector('.ac-dropdown');
    if (!dd || dd.style.display === 'none') return;
    const items = dd.querySelectorAll('.ac-item');
    if (!items.length) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      acActiveIdx = Math.min(acActiveIdx + 1, items.length - 1);
      items.forEach((it,i) => it.classList.toggle('ac-active', i === acActiveIdx));
      items[acActiveIdx].scrollIntoView({block:'nearest'});
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      acActiveIdx = Math.max(acActiveIdx - 1, 0);
      items.forEach((it,i) => it.classList.toggle('ac-active', i === acActiveIdx));
    } else if (e.key === 'Tab') {
      // Tab = accept highlighted suggestion
      if (acActiveIdx >= 0 && items[acActiveIdx]) {
        e.preventDefault();
        items[acActiveIdx].click();
      }
    } else if (e.key === 'Escape') {
      dd.style.display = 'none';
      acActiveIdx = -1;
    }
  });

  pan.addEventListener('click',function(e){const it=e.target.closest('.fi');if(it&&!e.target.closest('input,select,button,.ac-dropdown'))setActive(parseInt(it.dataset.id));});

  // ── Spellcheck — runs on blur AND can be called directly ──
  let spellTimers = {};

  function renderSpellErrors(errors) {
    return errors.map(function(err) {
      const ew = err.word.replace(/'/g, "\\'");
      const sugs = (err.suggestions || []).map(s =>
        '<span class="spell-sug" onclick="window._spellFix(this,\'' + ew + '\',\'' + s.replace(/'/g, "\\'") + '\')" title="Replace with ' + s + '">' + s + '</span>'
      ).join(' ');
      return '<span class="spell-bad" onclick="window._spellAdd(\'' + ew + '\',this)" title="Click to add to dictionary">' + err.word + '</span> → ' +
        (sugs || '<em>no suggestions</em>');
    }).join('<br>');
  }

  function showSpellErrors(fi, inp, errors) {
    const old = fi.querySelector('.spell-errors');
    if (old) old.remove();

    if (!errors || !errors.length) {
      inp.classList.remove('border-danger');
      inp.classList.add('border-success');
      setTimeout(() => inp.classList.remove('border-success'), 2000);
      return;
    }

    inp.classList.add('border-danger');
    inp.classList.remove('border-success');

    const div = document.createElement('div');
    div.className = 'spell-errors';
    div.innerHTML = renderSpellErrors(errors);
    fi.appendChild(div);
  }

  function runSpellcheck(fi, inp) {
    if (!inp || !inp.value.trim()) return;

    const old = fi.querySelector('.spell-errors');
    if (old) old.remove();

    fetch('{{ route("admin.ai.htr.spellcheck") }}', {
      method: 'POST',
      headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}'},
      body: JSON.stringify({text: inp.value}),
    })
    .then(r => r.json())
    .then(data => showSpellErrors(fi, inp, data.errors || []))
    .catch(() => {});
  }

  // Spellcheck on blur
  pan.addEventListener('focusout', function(e) {
    const inp = e.target.closest('input[data-role="txt"]');
    if (!inp) return;
    const fi = inp.closest('.fi');
    if (fi) runSpellcheck(fi, inp);
  });

  // Debounced spellcheck while typing (after 800ms pause)
  pan.addEventListener('input', function(e) {
    const inp = e.target.closest('input[data-role="txt"]');
    if (!inp) return;
    const fi = inp.closest('.fi');
    if (!fi) return;
    const id = fi.dataset.id;
    clearTimeout(spellTimers[id]);
    spellTimers[id] = setTimeout(() => runSpellcheck(fi, inp), 800);
  });

  // Replace misspelled word with clicked suggestion — auto-updates text input
  window._spellFix = function(el, badWord, replacement) {
    const fi = el.closest('.fi');
    if (!fi) return;
    const inp = fi.querySelector('input[data-role="txt"]');
    if (!inp) return;
    const id = parseInt(fi.dataset.id);
    const a = anns.find(a => a.id === id);

    if (badWord && a) {
      // Replace the misspelled word in the text
      const regex = new RegExp(badWord.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
      a.text = a.text.replace(regex, replacement);
      inp.value = a.text;
      inp.classList.remove('border-danger');
      redraw();
    }

    // Remove error display and re-run spellcheck
    const errDiv = fi.querySelector('.spell-errors');
    if (errDiv) errDiv.remove();
    runSpellcheck(fi, inp);
  };

  // Add word to custom dictionary — no popup, just add and clear the error
  window._spellAdd = function(word, el) {
    fetch('{{ route("admin.ai.htr.addWord") }}', {
      method: 'POST',
      headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}'},
      body: JSON.stringify({word: word}),
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        // Re-run spellcheck to clear the error
        const fi = el.closest('.fi');
        if (fi) {
          const errDiv = fi.querySelector('.spell-errors');
          if (errDiv) errDiv.remove();
          const inp = fi.querySelector('input[data-role="txt"]');
          if (inp) {
            inp.classList.remove('border-danger');
            runSpellcheck(fi, inp);
          }
        }
      }
    })
    .catch(() => {});
  };

  // ── Tools ──
  function setTool(t){
    tool=t;
    document.getElementById('tool-rect').classList.toggle('active',t==='rect');
    document.getElementById('tool-hand').classList.toggle('active',t==='hand');
    document.getElementById('tool-select').classList.toggle('active',t==='select');
    wrap.classList.toggle('panning',t==='hand');
    cvs.style.cursor=t==='rect'?'crosshair':t==='hand'?'grab':'pointer';
  }
  document.getElementById('tool-rect').addEventListener('click',()=>setTool('rect'));
  document.getElementById('tool-hand').addEventListener('click',()=>setTool('hand'));
  document.getElementById('tool-select').addEventListener('click',()=>setTool('select'));

  // Zoom
  function zoom(ns){if(!img)return;const cx=wrap.scrollLeft+wrap.clientWidth/2,cy=wrap.scrollTop+wrap.clientHeight/2,r=ns/scale;scale=ns;cvs.width=img.width*scale;cvs.height=img.height*scale;redraw();wrap.scrollLeft=cx*r-wrap.clientWidth/2;wrap.scrollTop=cy*r-wrap.clientHeight/2;updInfo();}
  document.getElementById('btn-zin').addEventListener('click',()=>zoom(Math.min(scale*1.3,6)));
  document.getElementById('btn-zout').addEventListener('click',()=>zoom(Math.max(scale/1.3,0.3)));
  document.getElementById('btn-zfit').addEventListener('click',function(){if(!img)return;scale=Math.max(wrap.clientWidth/img.width,1.0);cvs.width=img.width*scale;cvs.height=img.height*scale;redraw();wrap.scrollLeft=0;wrap.scrollTop=0;updInfo();});
  document.getElementById('btn-undo').addEventListener('click',function(){if(!anns.length)return;const rm=anns.pop();if(activeId===rm.id)activeId=null;redraw();buildPanel();this.disabled=!anns.length;});
  function updInfo(){if(!img)return;const n=files.length?files[fidx]?.name:'uploaded';document.getElementById('info').textContent=(n||'image')+' | '+img.width+'×'+img.height+'px | '+Math.round(scale*100)+'%';}

  // ── Save ──
  document.getElementById('btn-save').addEventListener('click', async function() {
    const imageInput = document.getElementById('image-upload');
    const sp = cvs.dataset.serverPath || '';
    if (!imageInput.files.length && !sp) { alert('No image loaded.'); return; }

    const isNg = document.getElementById('non-gen-toggle').checked;
    if (!isNg) {
      if (anns.length < 2) { alert('Both fields required: draw box 1 (year) and box 2 (place).'); return; }
      const yearAnn = anns.find(a => a.label === 'EVENT_YEAR_ORIG');
      const placeAnn = anns.find(a => a.label === 'EVENT_PLACE_ORIG');
      if (!yearAnn || !yearAnn.text.trim()) { alert('Event Year is required. Type the year in box #1.'); return; }
      if (!placeAnn || !placeAnn.text.trim()) { alert('Event Place is required. Type the place in box #2.'); return; }

      // Check for unresolved spell errors — if any red errors visible, block save and focus the field
      const errDivs = document.querySelectorAll('.spell-errors .spell-error');
      if (errDivs.length > 0) {
        const firstErr = errDivs[0].closest('.fi');
        if (firstErr) {
          const inp = firstErr.querySelector('input[data-role="txt"]');
          if (inp) { inp.focus(); inp.classList.add('border-danger'); }
          setActive(parseInt(firstErr.dataset.id));
        }
        return; // Block save — fix spelling first
      }
    }

    const btn = this; btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
    const fd = new FormData();
    if (imageInput.files.length) fd.append('image', imageInput.files[0]);
    else fd.append('server_path', sp);
    fd.append('type', document.getElementById('doc-type').value);

    // Build ILM annotation payload
    let payload;
    if (isNg) {
      payload = [{
        non_genealogical: true,
        non_genealogical_type_id: parseInt(document.getElementById('ng-type').value),
        FS_RECORD_TYPE: '',
        FS_RECORD_TYPE_ID: '',
        EVENT_YEAR_ORIG: '',
        EVENT_PLACE_ORIG: '',
      }];
    } else {
      const rt = document.getElementById('record-type').value;
      // Record type IDs
      const RT_IDS = {'Death Records':'1000015','Birth Records':'1000001','Marriage Records':'1000006','Christenings':'1000003','Burials':'1000002','Church Records':'1000004','Civil Registration':'1000005','Census':'1000023','Other Records':'1000000'};

      // Collect field values from annotations
      let year = '', place = '';
      anns.forEach(a => {
        if (a.label === 'EVENT_YEAR_ORIG' && a.text) year = a.text;
        if (a.label === 'EVENT_PLACE_ORIG' && a.text) place = a.text;
      });

      payload = [{
        non_genealogical: false,
        non_genealogical_type_id: null,
        FS_RECORD_TYPE: rt,
        FS_RECORD_TYPE_ID: RT_IDS[rt] || '',
        EVENT_YEAR_ORIG: year,
        EVENT_PLACE_ORIG: place,
        LOCALITY_ID: '',
        fields: anns.map((a,i) => ({
          zone_id: i, label: a.label, form_label: a.form_label || '', text: a.text,
          bbox: {x:a.x, y:a.y, w:a.w, h:a.h},
        })),
      }];
    }
    fd.append('annotations', JSON.stringify(payload));

    try {
      const resp = await fetch('{{ route("admin.ai.htr.saveAnnotation") }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}, body:fd});
      const data = await resp.json();
      if (data.success) {
        const dt = document.getElementById('doc-type').value;
        sess[dt]=(sess[dt]||0)+1;
        document.getElementById('cnt-a').textContent=sess.type_a;
        document.getElementById('cnt-b').textContent=sess.type_b;
        document.getElementById('cnt-c').textContent=sess.type_c;
        anns=[]; activeId=null; redraw(); buildPanel();
        btn.innerHTML='<i class="fas fa-check me-1"></i>Saved!';
        // Auto-advance handled by observer below
        setTimeout(()=>{btn.innerHTML='<i class="fas fa-save me-1"></i>Save';btn.disabled=false;},1200);
      } else {
        alert('Error: '+(data.error||'Unknown'));
        btn.innerHTML='<i class="fas fa-save me-1"></i>Save'; btn.disabled=false;
      }
    } catch(err) {
      alert('Error: '+err.message);
      btn.innerHTML='<i class="fas fa-save me-1"></i>Save'; btn.disabled=false;
    }
  });

  // ── Keyboard ──
  let prev='rect';
  document.addEventListener('keydown', function(e) {
    if('INPUT SELECT TEXTAREA'.includes(e.target.tagName)) return;
    if(e.key==='r')setTool('rect'); if(e.key==='h')setTool('hand'); if(e.key==='v')setTool('select');
    if(e.key===' '&&tool!=='hand'){e.preventDefault();prev=tool;setTool('hand');}
    if(e.key==='Delete'&&activeId)window._del(activeId);
    if(e.key==='z'&&(e.ctrlKey||e.metaKey)){e.preventDefault();document.getElementById('btn-undo').click();}
    if(e.key==='+'||e.key==='=')document.getElementById('btn-zin').click();
    if(e.key==='-')document.getElementById('btn-zout').click();
    if(e.key==='Tab'&&anns.length){e.preventDefault();const i=anns.findIndex(a=>a.id===activeId);setActive(anns[(i+1)%anns.length].id);buildPanel();}
  });
  document.addEventListener('keyup', function(e) {
    if('INPUT SELECT TEXTAREA'.includes(e.target.tagName)) return;
    if(e.key===' '&&tool==='hand')setTool(prev);
  });

  // ══════════════════════════════════════════════════════════════════
  // Skip — move image to rework/ folder
  // ══════════════════════════════════════════════════════════════════
  document.getElementById('btn-skip').addEventListener('click', function() {
    const sp = cvs.dataset.serverPath || '';
    if (!sp) { alert('No server image loaded to skip.'); return; }

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Skipping...';

    fetch('{{ route("admin.ai.htr.skipImage") }}', {
      method: 'POST',
      headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}'},
      body: JSON.stringify({path: sp}),
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        // Remove from file list and advance
        if (files.length && fidx >= 0) {
          files.splice(fidx, 1);
          updBadges(null);
          if (fidx >= files.length) fidx = files.length - 1;
          if (files.length > 0) {
            loadFolderImg();
          } else {
            // No more images
            anns = []; activeId = null; img = null;
            cvs.dataset.serverPath = '';
            ctx.clearRect(0, 0, cvs.width, cvs.height);
            cvs.style.display = 'none'; ph.style.display = '';
            ph.innerHTML = '<div><i class="fas fa-check-circle fa-3x mb-3 d-block text-success"></i>' +
              '<p class="mb-1 fw-bold">All images processed!</p></div>';
            buildPanel();
            document.getElementById('btn-save').disabled = true;
            document.getElementById('btn-skip').disabled = true;
            document.getElementById('image-upload').value = '';
          }
        }
        btn.innerHTML = '<i class="fas fa-forward me-1"></i>Skip';
        btn.disabled = false;
      } else {
        alert('Error: ' + (data.error || 'Failed to skip.'));
        btn.innerHTML = '<i class="fas fa-forward me-1"></i>Skip';
        btn.disabled = false;
      }
    })
    .catch(err => {
      alert('Error: ' + err.message);
      btn.innerHTML = '<i class="fas fa-forward me-1"></i>Skip';
      btn.disabled = false;
    });
  });

  // ══════════════════════════════════════════════════════════════════
  // Folder browsing
  // ══════════════════════════════════════════════════════════════════
  document.getElementById('btn-folder-load').addEventListener('click', loadFolder);
  document.getElementById('folder-preset').addEventListener('change', function(){
    const c=document.getElementById('folder-custom');
    c.style.display=this.value?'none':''; if(!this.value)c.focus();
  });

  function loadFolder() {
    let path = document.getElementById('folder-preset').value;
    const c = document.getElementById('folder-custom');
    if (c.style.display!=='none' && c.value) path=c.value;
    if (!path) { alert('Select a folder.'); return; }

    fetch('{{ route("admin.ai.htr.folderList") }}?path='+encodeURIComponent(path))
      .then(r=>r.json())
      .then(data=>{
        if(!data.success){alert(data.error);return;}
        files=data.files; fidx=-1;
        document.getElementById('folder-nav').style.display='';
        document.getElementById('folder-stats').style.display='';
        document.getElementById('skip-wrap').style.display='';
        updBadges(data);
        if(files.length){
          fidx=files.findIndex(f=>!f.annotated);
          if(fidx<0)fidx=0;
          loadFolderImg();
        } else alert('No images found.');
      }).catch(err=>alert(err.message));
  }

  function updBadges(d) {
    const done=d?d.annotated:files.filter(f=>f.annotated).length;
    const tot=d?d.total:files.length;
    document.getElementById('badge-done').textContent=done;
    document.getElementById('badge-todo').textContent=tot-done;
  }
  function updNav() {
    document.getElementById('btn-counter').textContent=(fidx+1)+'/'+files.length;
    document.getElementById('btn-prev').disabled=fidx<=0;
    document.getElementById('btn-next').disabled=fidx>=files.length-1;
    const f=files[fidx];
    const ct=document.getElementById('btn-counter');
    ct.classList.toggle('atom-btn-outline-success',f&&f.annotated);
    ct.classList.toggle('atom-btn-white',!(f&&f.annotated));
  }
  function loadFolderImg() {
    if(fidx<0||fidx>=files.length)return;
    const f=files[fidx]; updNav();
    cvs.dataset.serverPath=f.path;
    loadImg('{{ route("admin.ai.htr.serveImage") }}?path='+encodeURIComponent(f.path));
  }

  document.getElementById('btn-prev').addEventListener('click',function(){if(fidx>0){fidx--;loadFolderImg();}});
  document.getElementById('btn-next').addEventListener('click', function() {
    if(fidx<files.length-1){
      fidx++;
      if(document.getElementById('skip-done').checked) while(fidx<files.length-1&&files[fidx].annotated)fidx++;
      loadFolderImg();
    }
  });

  // Auto-advance after save
  const saveBtn=document.getElementById('btn-save');
  const obs=new MutationObserver(function(){
    if(saveBtn.textContent.includes('Saved')&&files.length){
      if(files[fidx])files[fidx].annotated=true;
      updBadges(null);
      setTimeout(function(){
        // Check if there are more unannotated images
        let nextIdx = fidx + 1;
        if(document.getElementById('skip-done').checked) {
          while(nextIdx < files.length && files[nextIdx].annotated) nextIdx++;
        }
        if(nextIdx < files.length){
          fidx = nextIdx;
          loadFolderImg();
        } else if (preSplitState) {
          // Last split row done — restore original folder and advance to next Type B image
          files = preSplitState.files;
          // Mark the image we just split as annotated
          if (preSplitState.fidx >= 0 && preSplitState.fidx < files.length) {
            files[preSplitState.fidx].annotated = true;
          }
          fidx = preSplitState.fidx;
          preSplitState = null;

          // Switch back to Type B mode
          document.getElementById('doc-type').value = 'type_b';
          document.getElementById('doc-type').dispatchEvent(new Event('change'));

          // Advance to next unannotated image in original folder
          let nextOrigIdx = fidx + 1;
          if (document.getElementById('skip-done').checked) {
            while (nextOrigIdx < files.length && files[nextOrigIdx].annotated) nextOrigIdx++;
          }
          if (nextOrigIdx < files.length) {
            fidx = nextOrigIdx;
            updBadges(null);
            loadFolderImg();
          } else {
            // Truly all done in original folder
            anns = []; activeId = null; img = null;
            cvs.dataset.serverPath = '';
            ctx.clearRect(0, 0, cvs.width, cvs.height);
            cvs.style.display = 'none';
            ph.style.display = '';
            ph.innerHTML = '<div><i class="fas fa-check-circle fa-3x mb-3 d-block text-success"></i>' +
              '<p class="mb-1 fw-bold">All images in this folder are annotated!</p>' +
              '<p class="small text-muted">Load another folder or upload an image to continue.</p></div>';
            buildPanel();
            document.getElementById('btn-save').disabled = true;
            document.getElementById('btn-skip').disabled = true;
            document.getElementById('image-upload').value = '';
            updBadges(null);
            updNav();
          }
        } else {
          // Last image — clear everything (no split state to restore)
          anns = []; activeId = null; img = null;
          cvs.dataset.serverPath = '';
          ctx.clearRect(0, 0, cvs.width, cvs.height);
          cvs.style.display = 'none';
          ph.style.display = '';
          ph.innerHTML = '<div><i class="fas fa-check-circle fa-3x mb-3 d-block text-success"></i>' +
            '<p class="mb-1 fw-bold">All images in this folder are annotated!</p>' +
            '<p class="small text-muted">Load another folder or upload an image to continue.</p></div>';
          buildPanel();
          document.getElementById('btn-save').disabled = true;
          document.getElementById('btn-skip').disabled = true;
          document.getElementById('image-upload').value = '';
          updNav();
        }
      },800);
    }
  });
  obs.observe(saveBtn,{childList:true,subtree:true,characterData:true});

  // ══════════════════════════════════════════════════════════════════
  // Row Split Mode — for Type B register pages with multiple records
  // ══════════════════════════════════════════════════════════════════

  // Auto Rows — divide image into N equal horizontal rows
  document.getElementById('btn-auto-rows').addEventListener('click', function() {
    if (!img) return;
    const n = parseInt(document.getElementById('row-count').value) || 7;

    // Clear existing annotations and switch to row-drawing mode
    anns = []; activeId = null;

    // Skip top ~10% (header) and bottom ~5% (footer)
    const headerPct = 0.08;
    const footerPct = 0.03;
    const startY = Math.round(img.height * headerPct);
    const endY = Math.round(img.height * (1 - footerPct));
    const rowH = Math.round((endY - startY) / n);
    const pad = 5;

    for (let i = 0; i < n; i++) {
      anns.push({
        id: Date.now() + i,
        x: pad,
        y: startY + i * rowH,
        w: img.width - pad * 2,
        h: rowH - 2,
        label: 'ROW_' + (i + 1),
        form_label: 'Row ' + (i + 1),
        text: '',
        color: COLORS[i % COLORS.length],
      });
    }

    redraw(); buildPanel();
    document.getElementById('btn-undo').disabled = false;
    setTool('select'); // So user can adjust rows
  });

  // Split & Annotate — crop each row box and load them as separate images
  document.getElementById('btn-split-go').addEventListener('click', function() {
    const sp = cvs.dataset.serverPath || '';
    if (!sp) { alert('Load a server image first.'); return; }
    if (anns.length === 0) { alert('Draw or auto-detect rows first.'); return; }

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Splitting...';

    const rows = anns.map(a => ({x: a.x, y: a.y, w: a.w, h: a.h}));

    fetch('{{ route("admin.ai.htr.splitRows") }}', {
      method: 'POST',
      headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}'},
      body: JSON.stringify({path: sp, rows: rows}),
    })
    .then(r => r.json())
    .then(data => {
      btn.innerHTML = '<i class="fas fa-cut me-1"></i>Split & Annotate';
      btn.disabled = false;

      if (!data.success || !data.rows || !data.rows.length) {
        alert('Split failed: ' + (data.error || 'No rows created'));
        return;
      }

      // Save current folder state so we can restore after all rows are annotated
      preSplitState = {
        files: JSON.parse(JSON.stringify(files)),
        fidx: fidx,
        folderPath: document.getElementById('folder-preset').value || document.getElementById('folder-custom').value || '',
      };

      // Load the split directory as a new folder — each row becomes a separate image to annotate
      const splitDir = data.split_dir;
      files = data.rows.map(r => ({
        name: r.name,
        path: r.path,
        size: 0,
        annotated: false,
      }));
      fidx = 0;

      // Switch to Type A mode for individual row annotation (2 fields per row)
      document.getElementById('doc-type').value = 'type_a';
      document.getElementById('record-type').value = 'Death Records';
      document.getElementById('row-split-tools').style.display = 'none';
      rowSplitMode = false;

      // Show folder nav
      document.getElementById('folder-nav').style.display = '';
      document.getElementById('folder-stats').style.display = '';
      document.getElementById('skip-wrap').style.display = '';
      updBadges({total: files.length, annotated: 0});

      // Load first row
      loadFolderImg();

      alert('Split into ' + data.rows.length + ' rows. Now annotate each row (year + place).');
    })
    .catch(err => {
      alert('Error: ' + err.message);
      btn.innerHTML = '<i class="fas fa-cut me-1"></i>Split & Annotate';
      btn.disabled = false;
    });
  });

  // Override MAX_ANNS check when in row split mode — allow unlimited row boxes
  // (The mousedown handler already checks MAX_ANNS for rect tool)

})();
</script>
@endpush
