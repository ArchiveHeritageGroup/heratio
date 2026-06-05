{{-- heratio#1138 — Digital Twin: virtual collection builder (Phase 1). --}}
@extends('theme::layouts.1col')

@section('title', __('Digital Twin Builder') . ' — ' . $space->name)
@section('body-class', 'exhibition-space builder')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1">
      <i class="fas fa-cubes me-2"></i>{{ __('Digital Twin Builder') }}
      <small class="text-muted">{{ $space->name }}</small>
    </h1>
    <a href="{{ route('exhibition-space.walkthrough', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-primary">
      <i class="fas fa-vr-cardboard me-1"></i>{{ __('Walkthrough') }}
    </a>
    <a href="{{ route('exhibition-space.show', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to space') }}
    </a>
  </div>
  <p class="text-muted small mb-3">
    {{ __('The start of digital twins: arrange this collection visually. Search an object, drop it on the floorplan, then drag, rotate and scale it into place. Changes save automatically.') }}
  </p>

  @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif

  <div class="row g-3">
    {{-- Left: tools --}}
    <div class="col-lg-3">
      <div class="card mb-3">
        <div class="card-header py-2"><strong><i class="fas fa-plus me-1"></i>{{ __('Add object') }}</strong></div>
        <div class="card-body">
          <select id="objectSearch" class="form-control form-control-sm">
            <option value="">{{ __('Type to search...') }}</option>
          </select>
          <label for="initialSize" class="form-label small mt-2 mb-1">{{ __('Initial size (units)') }}</label>
          <input type="number" id="initialSize" class="form-control form-control-sm" min="0" step="0.01" value="1">
          <small class="text-muted d-block mt-1">{{ __('Selecting an object drops it on the canvas at this size.') }}</small>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header py-2"><strong><i class="fas fa-list me-1"></i>{{ __('Objects in this space') }}</strong> <span class="badge bg-secondary" id="objCount">0</span></div>
        <div class="card-body p-2" style="max-height:220px;overflow:auto;">
          <div id="objList" class="small text-muted">{{ __('None yet.') }}</div>
        </div>
      </div>

      @auth
      <div class="card mb-3">
        <div class="card-header py-2"><strong><i class="fas fa-map me-1"></i>{{ __('Floorplan') }}</strong></div>
        <div class="card-body">
          <form method="POST" action="{{ route('exhibition-space.builder.floorplan', ['slug' => $space->slug]) }}" enctype="multipart/form-data">
            @csrf
            <input type="file" name="floorplan" accept="image/*" class="form-control form-control-sm mb-2" required>
            <div class="row g-1 mb-2">
              <div class="col-6"><input type="number" step="0.01" min="0" name="floorplan_width_m" class="form-control form-control-sm" placeholder="{{ __('Width m') }}" value="{{ $space->floorplan_width_m }}"></div>
              <div class="col-6"><input type="number" step="0.01" min="0" name="floorplan_height_m" class="form-control form-control-sm" placeholder="{{ __('Height m') }}" value="{{ $space->floorplan_height_m }}"></div>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-upload me-1"></i>{{ __('Upload floorplan') }}</button>
          </form>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header py-2"><strong><i class="fas fa-image me-1"></i>{{ __('Ceiling') }}</strong></div>
        <div class="card-body">
          <form method="POST" action="{{ route('exhibition-space.builder.ceiling', ['slug' => $space->slug]) }}" enctype="multipart/form-data" class="mb-2">
            @csrf
            <input type="file" name="ceiling" accept="image/*" class="form-control form-control-sm mb-2" required>
            <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-upload me-1"></i>{{ __('Upload painted ceiling') }}</button>
          </form>
          @if(!empty($space->ceiling_image_path))
          <form method="POST" action="{{ route('exhibition-space.builder.ceiling-clear', ['slug' => $space->slug]) }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-times me-1"></i>{{ __('Clear ceiling') }}</button>
          </form>
          @endif
        </div>
      </div>
      @endauth

      @auth
      <div class="card mb-3">
        <div class="card-header py-2"><strong><i class="fas fa-grip-lines-vertical me-1"></i>{{ __('Interior walls') }}</strong></div>
        <div class="card-body">
          <button type="button" id="wallAdd" class="btn btn-sm btn-outline-primary w-100 mb-2"><i class="fas fa-plus me-1"></i>{{ __('Add wall') }}</button>
          <div id="wallList" class="small"></div>
          <small id="wallHint" class="text-muted d-block mt-1">{{ __('Add a divider wall to hang objects in the middle of the room.') }}</small>
        </div>
      </div>
      @endauth

      <div class="card">
        <div class="card-header py-2"><strong><i class="fas fa-sliders-h me-1"></i>{{ __('Selected object') }}</strong></div>
        <div class="card-body">
          <div id="selPanel" class="text-muted small">{{ __('Click an object on the canvas to select it.') }}</div>
          <div id="selControls" class="d-none">
            <div class="fw-bold small mb-2" id="selTitle"></div>
            <div class="btn-group btn-group-sm w-100 mb-2" role="group">
              <button type="button" class="btn btn-outline-secondary" data-act="rotL" title="{{ __('Rotate left') }}"><i class="fas fa-undo"></i></button>
              <button type="button" class="btn btn-outline-secondary" data-act="rotR" title="{{ __('Rotate right') }}"><i class="fas fa-redo"></i></button>
              <button type="button" class="btn btn-outline-secondary" data-act="smaller" title="{{ __('Smaller') }}"><i class="fas fa-search-minus"></i></button>
              <button type="button" class="btn btn-outline-secondary" data-act="bigger" title="{{ __('Bigger') }}"><i class="fas fa-search-plus"></i></button>
            </div>
            <label for="selSize" class="form-label small mb-1">{{ __('Size (units)') }}</label>
            <input type="number" id="selSize" class="form-control form-control-sm mb-2" min="0" step="0.01">
            <div id="tiltControls" class="d-none border-top pt-2 mb-2">
              <label class="form-label small mb-1">{{ __('3D orientation (degrees)') }}</label>
              <div class="row g-1 mb-1">
                <div class="col-6"><input type="number" id="tiltX" class="form-control form-control-sm" step="90" placeholder="{{ __('Tilt X') }}"></div>
                <div class="col-6"><input type="number" id="tiltZ" class="form-control form-control-sm" step="90" placeholder="{{ __('Tilt Z') }}"></div>
              </div>
              <button type="button" id="tiltAuto" class="btn btn-outline-secondary btn-sm w-100">{{ __('Auto (reset)') }}</button>
              <small class="text-muted d-block mt-1">{{ __('Empty = auto. Use 90 / -90 to stand a model upright.') }}</small>
            </div>
            <label for="selWall" class="form-label small mb-1">{{ __('Hang on wall') }}</label>
            <select id="selWall" class="form-select form-select-sm mb-2"></select>
            <button type="button" id="btnRemove" class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-trash me-1"></i>{{ __('Remove from twin') }}</button>
          </div>
        </div>
      </div>
    </div>

    {{-- Right: canvas --}}
    <div class="col-lg-9">
      <div class="card">
        <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div class="btn-group btn-group-sm" role="group">
            <button type="button" id="modeFloor" class="btn btn-primary">{{ __('Floor view') }}</button>
            <button type="button" id="modeWall" class="btn btn-outline-primary">{{ __('Wall view') }}</button>
          </div>
          <select id="wvWall" class="form-select form-select-sm d-none" style="max-width:160px;">
            <option value="north">{{ __('Back wall') }}</option>
            <option value="south">{{ __('Front wall') }}</option>
            <option value="west">{{ __('Left wall') }}</option>
            <option value="east">{{ __('Right wall') }}</option>
          </select>
          <span class="small text-muted"><span id="saveState">{{ __('All changes saved') }}</span> <i id="saveIcon" class="fas fa-check text-success ms-1"></i></span>
        </div>
        <div class="card-body p-0">
          <div id="stageWrap" style="width:100%;background:#f4f4f4;border-radius:0 0 .375rem .375rem;overflow:hidden;"></div>
        </div>
      </div>
    </div>
  </div>

  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/konva@9.3.6/konva.min.js"></script>
  <script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    var CSRF = '{{ csrf_token() }}';
    var URLS = {
      autocomplete: '{{ url('informationobject/autocomplete') }}',
      place: '{{ route('exhibition-space.builder.place', ['slug' => $space->slug]) }}',
      layout: '{{ route('exhibition-space.builder.layout', ['slug' => $space->slug]) }}',
      remove: '{{ route('exhibition-space.builder.remove', ['slug' => $space->slug]) }}',
      size: '{{ route('exhibition-space.builder.size', ['slug' => $space->slug]) }}',
      tilt: '{{ route('exhibition-space.builder.tilt', ['slug' => $space->slug]) }}',
      walls: '{{ route('exhibition-space.builder.walls', ['slug' => $space->slug]) }}',
      wall: '{{ route('exhibition-space.builder.wall', ['slug' => $space->slug]) }}',
      wallPlace: '{{ route('exhibition-space.builder.wall-place', ['slug' => $space->slug]) }}',
      wallPos: '{{ route('exhibition-space.builder.wall-pos', ['slug' => $space->slug]) }}',
      placements: '{{ route('exhibition-space.builder.placements', ['slug' => $space->slug]) }}'
    };
    var FLOORPLAN = @json($space->floorplan_image_path);
    var PLACEMENTS = @json($placements);
    var WALLS = @json($walls ?? []);

    if (typeof Konva === 'undefined') {
      document.getElementById('stageWrap').innerHTML =
        '<div class="p-4 text-muted">{{ __('Canvas library failed to load.') }}</div>';
      return;
    }

    var wrap = document.getElementById('stageWrap');
    var W = Math.max(320, wrap.clientWidth || 800);
    var H = Math.round(W * 0.6);
    var NODE = 90; // base object box in px

    var stage = new Konva.Stage({ container: 'stageWrap', width: W, height: H });
    var bgLayer = new Konva.Layer();
    var wallLayer = new Konva.Layer();
    var layer = new Konva.Layer();
    var wvLayer = new Konva.Layer({ visible: false });
    stage.add(bgLayer); stage.add(wallLayer); stage.add(layer); stage.add(wvLayer);

    // Background: floorplan image or a grid.
    if (FLOORPLAN) {
      var bg = new Image();
      bg.onload = function () {
        var k = new Konva.Image({ image: bg, x: 0, y: 0, width: W, height: H, listening: false });
        bgLayer.add(k); bgLayer.draw();
      };
      bg.src = FLOORPLAN;
    } else {
      var grid = 40;
      for (var gx = 0; gx <= W; gx += grid) bgLayer.add(new Konva.Line({ points: [gx, 0, gx, H], stroke: '#e3e3e3', strokeWidth: 1, listening: false }));
      for (var gy = 0; gy <= H; gy += grid) bgLayer.add(new Konva.Line({ points: [0, gy, W, gy], stroke: '#e3e3e3', strokeWidth: 1, listening: false }));
      bgLayer.draw();
    }

    var tr = new Konva.Transformer({ rotateEnabled: true, enabledAnchors: ['top-left','top-right','bottom-left','bottom-right'], keepRatio: true });
    layer.add(tr);
    var selected = null;

    // ---- save (debounced) ----
    var dirty = false, saveTimer = null;
    function setState(t, ok) {
      document.getElementById('saveState').textContent = t;
      var ic = document.getElementById('saveIcon');
      ic.className = ok ? 'fas fa-check text-success ms-1' : 'fas fa-circle-notch fa-spin text-warning ms-1';
    }
    function scheduleSave() {
      dirty = true; setState('{{ __('Saving...') }}', false);
      if (saveTimer) clearTimeout(saveTimer);
      saveTimer = setTimeout(saveLayout, 700);
    }
    function saveLayout() {
      var positions = layer.find('.placement').map(function (g) {
        return {
          id: g.getAttr('placementId'),
          pos_x: g.x() / W, pos_y: g.y() / H,
          rotation_deg: g.rotation(), scale: g.scaleX(),
          z_order: g.zIndex()
        };
      });
      fetch(URLS.layout, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ positions: positions })
      }).then(function (r) { return r.json(); })
        .then(function () { dirty = false; setState('{{ __('All changes saved') }}', true); })
        .catch(function () { setState('{{ __('Save failed - retrying') }}', false); setTimeout(saveLayout, 2000); });
    }

    function selectNode(g) {
      selected = g; tr.nodes([g]);
      document.getElementById('selPanel').classList.add('d-none');
      document.getElementById('selControls').classList.remove('d-none');
      document.getElementById('selTitle').textContent = g.getAttr('titleText') || '';
      document.getElementById('selSize').value = g.getAttr('sizeUnits') != null ? g.getAttr('sizeUnits') : 0;
      var is3d = g.getAttr('objKind') === '3d';
      document.getElementById('tiltControls').classList.toggle('d-none', !is3d);
      if (is3d) {
        var tx = g.getAttr('tiltX'); var tz = g.getAttr('tiltZ');
        document.getElementById('tiltX').value = (tx === null || tx === undefined) ? '' : tx;
        document.getElementById('tiltZ').value = (tz === null || tz === undefined) ? '' : tz;
      }
      refreshWallOptions();
      document.getElementById('selWall').value = g.getAttr('wallKey') || '';
      layer.draw();
    }
    function clearSelect() {
      selected = null; tr.nodes([]);
      document.getElementById('selPanel').classList.remove('d-none');
      document.getElementById('selControls').classList.add('d-none');
      layer.draw();
    }

    // ---- build a placement node ----
    function addNode(p) {
      var px = (p.pos_x !== null && p.pos_x !== undefined) ? p.pos_x * W : W / 2;
      var py = (p.pos_y !== null && p.pos_y !== undefined) ? p.pos_y * H : H / 2;
      var g = new Konva.Group({
        x: px, y: py, draggable: true, name: 'placement',
        rotation: p.rotation_deg || 0, scaleX: p.scale || 1, scaleY: p.scale || 1
      });
      g.setAttr('placementId', p.id);
      g.setAttr('titleText', p.title);
      g.setAttr('sizeUnits', p.size_units_used != null ? p.size_units_used : 0);
      g.setAttr('objKind', p.kind || null);
      g.setAttr('wallKey', p.wall_or_zone || '');
      g.setAttr('tiltX', (p.tilt_x === null || p.tilt_x === undefined) ? null : p.tilt_x);
      g.setAttr('tiltZ', (p.tilt_z === null || p.tilt_z === undefined) ? null : p.tilt_z);

      var rect = new Konva.Rect({
        x: -NODE / 2, y: -NODE / 2, width: NODE, height: NODE,
        fill: '#ffffff', stroke: '#6c757d', strokeWidth: 1, cornerRadius: 4,
        shadowColor: '#000', shadowBlur: 6, shadowOpacity: 0.15, shadowOffset: { x: 0, y: 2 }
      });
      g.add(rect);

      if (p.thumb_url) {
        var img = new Image();
        img.onload = function () {
          var ki = new Konva.Image({ image: img, x: -NODE / 2 + 3, y: -NODE / 2 + 3, width: NODE - 6, height: NODE - 6, cornerRadius: 3 });
          g.add(ki); ki.moveToBottom(); rect.moveToBottom(); layer.draw();
        };
        img.onerror = function () {
          g.add(new Konva.Text({ text: '#' + p.information_object_id, x: -NODE / 2, y: -8, width: NODE, align: 'center', fontSize: 11, fill: '#999' }));
          layer.draw();
        };
        img.src = p.thumb_url;
      } else {
        g.add(new Konva.Text({ text: '#' + p.information_object_id, x: -NODE / 2, y: -8, width: NODE, align: 'center', fontSize: 11, fill: '#999' }));
      }

      var label = new Konva.Label({ x: 0, y: NODE / 2 + 4 });
      label.add(new Konva.Tag({ fill: 'rgba(33,37,41,0.85)', cornerRadius: 3, pointerDirection: 'up', pointerWidth: 6, pointerHeight: 4 }));
      label.add(new Konva.Text({ text: (p.title || '').substring(0, 28), fontSize: 11, padding: 3, fill: '#fff' }));
      label.offsetX(label.width() / 2);
      g.add(label);

      g.on('click tap', function (e) { e.cancelBubble = true; selectNode(g); });
      g.on('dragend', scheduleSave);
      g.on('transformend', scheduleSave);
      layer.add(g);
      return g;
    }

    PLACEMENTS.forEach(addNode);
    layer.draw();
    renderObjList();

    // ---- interior walls ----
    var wallAdding = false, wallStart = null;
    var wallBtn = document.getElementById('wallAdd');
    var wallHintEl = document.getElementById('wallHint');
    var HINT_IDLE = '{{ __('Add a divider wall to hang objects in the middle of the room.') }}';
    function setWallMode(on) {
      wallAdding = on; wallStart = null;
      wallBtn.classList.toggle('btn-primary', on); wallBtn.classList.toggle('btn-outline-primary', !on);
      wallHintEl.textContent = on ? '{{ __('Click the start point, then the end point.') }}' : HINT_IDLE;
      stage.container().style.cursor = on ? 'crosshair' : 'default';
    }
    function redrawWalls() {
      wallLayer.destroyChildren();
      WALLS.forEach(function (w) {
        wallLayer.add(new Konva.Line({ points: [w.x1 * W, w.z1 * H, w.x2 * W, w.z2 * H], stroke: '#495057', strokeWidth: 7, lineCap: 'round', listening: false }));
      });
      wallLayer.draw();
      renderWallList();
    }
    function renderWallList() {
      var el = document.getElementById('wallList'); el.innerHTML = '';
      WALLS.forEach(function (w, i) {
        var row = document.createElement('div');
        row.className = 'd-flex justify-content-between align-items-center mb-1';
        row.innerHTML = '<span>{{ __('Wall') }} ' + (i + 1) + '</span>';
        var del = document.createElement('button');
        del.type = 'button'; del.className = 'btn btn-sm btn-outline-danger py-0'; del.innerHTML = '<i class="fas fa-times"></i>';
        del.addEventListener('click', function () { WALLS.splice(i, 1); saveWalls(); });
        row.appendChild(del); el.appendChild(row);
      });
    }
    function saveWalls() {
      fetch(URLS.walls, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: JSON.stringify({ walls: WALLS }) })
        .then(function (r) { return r.json(); }).then(function (d) { if (d.ok && d.walls) { WALLS = d.walls; } redrawWalls(); refreshWallOptions(); });
    }
    function refreshWallOptions() {
      var sel = document.getElementById('selWall'); var cur = sel.value;
      var opts = '<option value="">{{ __('Auto (nearest)') }}</option>' +
        '<option value="north">{{ __('Back wall') }}</option><option value="south">{{ __('Front wall') }}</option>' +
        '<option value="west">{{ __('Left wall') }}</option><option value="east">{{ __('Right wall') }}</option>';
      WALLS.forEach(function (w, i) { opts += '<option value="' + w.id + '">{{ __('Wall') }} ' + (i + 1) + '</option>'; });
      sel.innerHTML = opts; sel.value = cur;
    }
    wallBtn.addEventListener('click', function () { setWallMode(!wallAdding); });
    document.getElementById('selWall').addEventListener('change', function () {
      if (!selected) return;
      selected.setAttr('wallKey', this.value);
      fetch(URLS.wall, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: JSON.stringify({ placement_id: selected.getAttr('placementId'), wall: this.value }) });
    });
    redrawWalls(); refreshWallOptions();

    stage.on('click tap', function (e) {
      if (wallAdding) {
        var p = stage.getPointerPosition(); if (!p) return;
        if (!wallStart) { wallStart = { x: p.x / W, z: p.y / H }; }
        else {
          WALLS.push({ id: 'wall-' + Date.now(), x1: wallStart.x, z1: wallStart.z, x2: p.x / W, z2: p.y / H });
          setWallMode(false); saveWalls();
        }
        return;
      }
      if (e.target === stage) clearSelect();
    });

    // ---- selected-object controls ----
    document.querySelectorAll('#selControls [data-act]').forEach(function (b) {
      b.addEventListener('click', function () {
        if (!selected) return;
        var a = b.getAttribute('data-act');
        if (a === 'rotL') selected.rotation(selected.rotation() - 15);
        if (a === 'rotR') selected.rotation(selected.rotation() + 15);
        if (a === 'smaller') { var s = Math.max(0.3, selected.scaleX() - 0.1); selected.scale({ x: s, y: s }); }
        if (a === 'bigger') { var s2 = Math.min(4, selected.scaleX() + 0.1); selected.scale({ x: s2, y: s2 }); }
        layer.draw(); scheduleSave();
      });
    });
    // Remove a placement (used by the selected-object button and the object list).
    function removePlacement(g) {
      if (!g) return;
      fetch(URLS.remove, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ placement_id: g.getAttr('placementId') })
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (d.ok) { if (selected === g) clearSelect(); g.destroy(); layer.draw(); renderObjList(); }
      });
    }
    document.getElementById('btnRemove').addEventListener('click', function () { removePlacement(selected); });

    // Full list of placed objects (works even for objects off-canvas / unreachable).
    function renderObjList() {
      var nodes = layer.find('.placement');
      var el = document.getElementById('objList');
      document.getElementById('objCount').textContent = nodes.length;
      if (!nodes.length) { el.innerHTML = '{{ __('None yet.') }}'; return; }
      el.innerHTML = '';
      nodes.forEach(function (g) {
        var row = document.createElement('div');
        row.className = 'd-flex justify-content-between align-items-center mb-1';
        var name = document.createElement('a');
        name.href = '#'; name.className = 'text-truncate me-2 text-decoration-none';
        name.style.maxWidth = '180px';
        name.textContent = g.getAttr('titleText') || ('#' + g.getAttr('placementId'));
        name.addEventListener('click', function (e) { e.preventDefault(); selectNode(g); });
        var del = document.createElement('button');
        del.type = 'button'; del.className = 'btn btn-sm btn-outline-danger py-0';
        del.innerHTML = '<i class="fas fa-times"></i>';
        del.addEventListener('click', function () { removePlacement(g); });
        row.appendChild(name); row.appendChild(del); el.appendChild(row);
      });
    }

    // ---- edit size of the selected object ----
    document.getElementById('selSize').addEventListener('change', function () {
      if (!selected) return;
      var v = Math.max(0, parseFloat(this.value) || 0);
      selected.setAttr('sizeUnits', v);
      fetch(URLS.size, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ placement_id: selected.getAttr('placementId'), size_units_used: v })
      });
    });

    // ---- edit 3D orientation (tilt) of the selected object ----
    function saveTilt() {
      if (!selected) return;
      var xs = document.getElementById('tiltX').value;
      var zs = document.getElementById('tiltZ').value;
      var tx = xs === '' ? null : (parseFloat(xs) || 0);
      var tz = zs === '' ? null : (parseFloat(zs) || 0);
      selected.setAttr('tiltX', tx); selected.setAttr('tiltZ', tz);
      fetch(URLS.tilt, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ placement_id: selected.getAttr('placementId'), tilt_x: tx, tilt_z: tz })
      });
    }
    document.getElementById('tiltX').addEventListener('change', saveTilt);
    document.getElementById('tiltZ').addEventListener('change', saveTilt);
    document.getElementById('tiltAuto').addEventListener('click', function () {
      document.getElementById('tiltX').value = ''; document.getElementById('tiltZ').value = '';
      saveTilt();
    });

    // ---- add object via search ----
    if (typeof TomSelect !== 'undefined') {
      new TomSelect('#objectSearch', {
        valueField: 'id', labelField: 'name', searchField: ['name'],
        placeholder: '{{ __('Type to search information objects...') }}',
        maxItems: 1, maxOptions: 15,
        load: function (q, cb) {
          if (q.length < 2) return cb();
          fetch(URLS.autocomplete + '?query=' + encodeURIComponent(q) + '&limit=15')
            .then(function (r) { return r.json(); }).then(cb).catch(function () { cb(); });
        },
        render: { option: function (d, e) { return '<div>' + e(d.name) + ' <small class="text-muted">#' + e(d.id) + '</small></div>'; } },
        onChange: function (val) {
          if (!val) return;
          var self = this;
          var done = function () { self.clear(true); self.clearOptions(); };
          if (mode === 'wall') {
            // Hang the object on the currently-viewed wall (centre by default).
            fetch(URLS.wallPlace, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
              body: JSON.stringify({ information_object_id: val, wall: wvWall, u: 0.5, v: 0.55 })
            }).then(function (r) { return r.json(); }).then(function (d) {
              if (d.ok) wvAddNode(d.placement);
              done();
            });
            return;
          }
          fetch(URLS.place, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ information_object_id: val, pos_x: 0.5, pos_y: 0.5, size_units_used: parseFloat(document.getElementById('initialSize').value) || 0 })
          }).then(function (r) { return r.json(); }).then(function (d) {
            if (d.ok) { var g = addNode(d.placement); layer.draw(); selectNode(g); renderObjList(); }
            done();
          });
        }
      });
    }

    // ---- Wall view (elevation editor): hang objects flat on a wall, no floor clutter ----
    var mode = 'floor', wvWall = 'north', WV_NODE = 70;
    (function () {   // add interior walls to the wall picker
      var sel = document.getElementById('wvWall');
      WALLS.forEach(function (w, i) { var o = document.createElement('option'); o.value = w.id; o.textContent = '{{ __('Wall') }} ' + (i + 1); sel.appendChild(o); });
    })();
    function setMode(m) {
      mode = m;
      var fb = document.getElementById('modeFloor'), wb = document.getElementById('modeWall');
      fb.classList.toggle('btn-primary', m === 'floor'); fb.classList.toggle('btn-outline-primary', m !== 'floor');
      wb.classList.toggle('btn-primary', m === 'wall'); wb.classList.toggle('btn-outline-primary', m !== 'wall');
      document.getElementById('wvWall').classList.toggle('d-none', m !== 'wall');
      var floorOn = (m === 'floor');
      bgLayer.visible(floorOn); wallLayer.visible(floorOn); layer.visible(floorOn); wvLayer.visible(!floorOn);
      tr.nodes([]); clearSelect();
      if (floorOn) { stage.draw(); } else { buildWallView(); }
    }
    document.getElementById('modeFloor').addEventListener('click', function () { setMode('floor'); });
    document.getElementById('modeWall').addEventListener('click', function () { setMode('wall'); });
    document.getElementById('wvWall').addEventListener('change', function () { wvWall = this.value; buildWallView(); });

    function buildWallView() {
      wvLayer.destroyChildren();
      wvLayer.add(new Konva.Rect({ x: 0, y: 0, width: W, height: H, fill: '#e9ecef', listening: false }));
      wvLayer.add(new Konva.Line({ points: [0, H - 3, W, H - 3], stroke: '#adb5bd', strokeWidth: 5, listening: false }));
      var lbl = document.getElementById('wvWall').selectedOptions[0];
      wvLayer.add(new Konva.Text({ x: 8, y: 8, text: (lbl ? lbl.text : '') + ' — {{ __('drag to position; search to add') }}', fontSize: 12, fill: '#495057', listening: false }));
      wvLayer.draw();
      fetch(URLS.placements).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.ok) return;
        d.placements.forEach(function (p) {
          if (p.wall_or_zone === wvWall && p.wall_u !== null && p.wall_u !== undefined) wvAddNode(p);
        });
        wvLayer.draw();
      });
    }
    function wvAddNode(p) {
      var u = (p.wall_u != null) ? p.wall_u : 0.5, v = (p.wall_v != null) ? p.wall_v : 0.55;
      var g = new Konva.Group({ x: u * W, y: (1 - v) * H, draggable: true, name: 'wvitem' });
      g.setAttr('placementId', p.id);
      var r = new Konva.Rect({ x: -WV_NODE / 2, y: -WV_NODE / 2, width: WV_NODE, height: WV_NODE, fill: '#fff', stroke: '#6c757d', strokeWidth: 1, cornerRadius: 3, shadowColor: '#000', shadowBlur: 5, shadowOpacity: 0.15 });
      g.add(r);
      if (p.thumb_url) {
        var im = new Image();
        im.onload = function () { var ki = new Konva.Image({ image: im, x: -WV_NODE / 2 + 2, y: -WV_NODE / 2 + 2, width: WV_NODE - 4, height: WV_NODE - 4 }); g.add(ki); r.moveToBottom(); wvLayer.draw(); };
        im.onerror = function () { g.add(new Konva.Text({ text: '#' + p.information_object_id, x: -WV_NODE / 2, y: -6, width: WV_NODE, align: 'center', fontSize: 10, fill: '#999' })); wvLayer.draw(); };
        im.src = p.thumb_url;
      } else { g.add(new Konva.Text({ text: '#' + p.information_object_id, x: -WV_NODE / 2, y: -6, width: WV_NODE, align: 'center', fontSize: 10, fill: '#999' })); }
      g.on('dragend', function () {
        var nu = Math.max(0, Math.min(1, g.x() / W)), nv = Math.max(0, Math.min(1, 1 - g.y() / H));
        fetch(URLS.wallPos, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: JSON.stringify({ placement_id: g.getAttr('placementId'), u: nu, v: nv }) });
      });
      wvLayer.add(g); wvLayer.draw();
    }

    // keep canvas usable on resize (re-anchor by normalized coords)
    window.addEventListener('resize', function () {
      var nw = Math.max(320, wrap.clientWidth || W);
      if (Math.abs(nw - W) < 20) return;
      var ratios = layer.find('.placement').map(function (g) { return { g: g, rx: g.x() / W, ry: g.y() / H }; });
      W = nw; H = Math.round(W * 0.6);
      stage.width(W); stage.height(H);
      ratios.forEach(function (o) { o.g.x(o.rx * W); o.g.y(o.ry * H); });
      bgLayer.destroyChildren();
      if (FLOORPLAN) { var b2 = new Image(); b2.onload = function () { bgLayer.add(new Konva.Image({ image: b2, width: W, height: H, listening: false })); bgLayer.draw(); }; b2.src = FLOORPLAN; }
      layer.draw();
    });
  })();
  </script>
@endsection
