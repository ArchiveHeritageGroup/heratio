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

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-pen-square me-2"></i>ILM Annotate</h1>
  <div class="d-flex gap-2">
    <a href="{{ route('admin.ai.htr.sources') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-database me-1"></i>Sources</a>
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
            <option value="">— Select server folder —</option>
            <option value="/tmp">Downloaded images (/tmp)</option>
            <option value="type_a">Training: Type A (Death Certs)</option>
            <option value="type_b">Training: Type B (Registers)</option>
            <option value="type_c">Training: Type C (Narrative)</option>
          </select>
          <input type="text" id="folder-custom" class="form-control form-control-sm" placeholder="/path/to/images" style="display:none;">
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
      <div class="col-auto ms-auto">
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
        <ol class="mb-0 ps-3" style="line-height:1.8;">
          <li>Select <strong>server folder</strong> → click <strong>Load</strong></li>
          <li>Set <strong>Record Type</strong> (top-right)</li>
          <li>Press <strong>R</strong> → draw box around the <strong>event year</strong> → type the year</li>
          <li>Press <strong>R</strong> → draw box around the <strong>event place</strong> → type place or click Quick Place (", South Africa" auto-appended)</li>
          <li>Only <strong>2 boxes</strong> allowed — Record Type is set at document level</li>
          <li>Use <strong>V</strong> to drag/reposition boxes</li>
          <li>Click <strong>Save</strong> → auto-advances to next image</li>
        </ol>
        <hr class="my-2">
        <div class="text-muted" style="font-size:.75rem;">
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
</style>
@endpush

@push('js')
<script>
(function() {
  const COLORS = ['#e74c3c','#3498db','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#34495e','#e91e63','#00bcd4'];
  const ILM_LABELS = ['EVENT_YEAR_ORIG','EVENT_PLACE_ORIG'];
  const MAX_ANNS = 2; // Only 2 field boxes allowed: year + place
  const PLACES = ['Cape Province, South Africa','Transvaal, South Africa','Natal, South Africa','Orange Free State, South Africa','South Africa'];

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
    '<button class="btn btn-sm atom-btn-white qp" data-p="' + p + '">' + p.replace(', South Africa','') + '</button>'
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

  // ── Doc type → record type sync ──
  document.getElementById('doc-type').addEventListener('change', function() {
    const m = {type_a:'Death Records', type_b:'Church Records', type_c:'Other Records'};
    document.getElementById('record-type').value = m[this.value] || 'Other Records';
  });

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

  cvs.addEventListener('mousedown', function(e) {
    // Hand tool — pan
    if (tool==='hand') { panning=true; px=e.clientX; py=e.clientY; psx=wrap.scrollLeft; psy=wrap.scrollTop; e.preventDefault(); return; }

    const p = pos(e);

    // Select tool — click to select, drag to move
    if (tool==='select') {
      const hit = hitTest(p);
      if (hit) {
        setActive(hit.id);
        // Start dragging
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
    if (anns.length >= MAX_ANNS) { return; }
    drawing=true; sx=p.x; sy=p.y;
  });

  cvs.addEventListener('mousemove', function(e) {
    // Panning
    if (panning) { wrap.scrollLeft=psx-(e.clientX-px); wrap.scrollTop=psy-(e.clientY-py); return; }

    const p = pos(e);

    // Dragging annotation
    if (dragging && dragAnn) {
      dragAnn.x = Math.max(0, Math.min(img.width - dragAnn.w, Math.round(p.x - dragOffX)));
      dragAnn.y = Math.max(0, Math.min(img.height - dragAnn.h, Math.round(p.y - dragOffY)));
      redraw();
      return;
    }

    // Select tool hover cursor
    if (tool==='select' && !dragging) {
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

    anns.push({
      id: Date.now(), x:Math.round(x), y:Math.round(y), w:Math.round(w), h:Math.round(h),
      label: label, text: label === 'EVENT_PLACE_ORIG' ? ', South Africa' : '',
      color: COLORS[anns.length%COLORS.length],
    });
    setActive(anns[anns.length-1].id); redraw(); buildPanel();
    document.getElementById('btn-undo').disabled=false;

    // Auto-switch to hand after 2 boxes (done drawing)
    if (anns.length >= MAX_ANNS) setTool('hand');

    // Auto-focus the text input
    setTimeout(function() {
      const inp = document.querySelector('.fi.active input[data-role="txt"]');
      if (inp) inp.focus();
    }, 50);
  });

  document.addEventListener('mouseup', function() {
    if (panning) panning=false;
    if (dragging) { dragging=false; dragAnn=null; buildPanel(); }
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

      // Label tag
      let lbl = '#'+(i+1)+' '+a.label;
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
      return '<div class="fi'+(a.id===activeId?' active':'')+'" data-id="'+a.id+'">' +
        '<div class="d-flex align-items-center mb-1">' +
          '<span class="dot me-1" style="background:'+a.color+'"></span>' +
          '<strong class="small me-auto">'+(isYear ? 'Event Year' : 'Event Place')+'</strong>' +
          '<button class="btn btn-sm btn-link text-danger p-0" onclick="window._del('+a.id+')"><i class="fas fa-times"></i></button>' +
        '</div>' +
        '<input type="text" class="form-control form-control-sm" data-role="txt" ' +
          'placeholder="'+(isYear ? 'e.g. 1904' : 'e.g. Cape Province, South Africa')+'" ' +
          'value="'+(a.text||'').replace(/"/g,'&quot;')+'" ' +
          'onchange="window._sf('+a.id+',\'text\',this.value)" ' +
          'onfocus="window._act('+a.id+')">' +
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

  // Globals
  window._del=function(id){anns=anns.filter(a=>a.id!==id);if(activeId===id)activeId=null;redraw();buildPanel();document.getElementById('btn-undo').disabled=!anns.length;};
  window._sf=function(id,f,v){const a=anns.find(a=>a.id===id);if(a){a[f]=v;redraw();buildPanel();}};
  window._act=function(id){setActive(id);};
  pan.addEventListener('click',function(e){const it=e.target.closest('.fi');if(it&&!e.target.closest('input,select,button'))setActive(parseInt(it.dataset.id));});

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
    if (!isNg && anns.length === 0) { alert('Draw at least one field box, or mark as non-genealogical.'); return; }

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
          zone_id: i, label: a.label, text: a.text,
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
    ct.classList.toggle('btn-success',f&&f.annotated);
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
        if(fidx<files.length-1){
          fidx++;
          if(document.getElementById('skip-done').checked) while(fidx<files.length-1&&files[fidx].annotated)fidx++;
          loadFolderImg();
        }
      },800);
    }
  });
  obs.observe(saveBtn,{childList:true,subtree:true,characterData:true});

})();
</script>
@endpush
