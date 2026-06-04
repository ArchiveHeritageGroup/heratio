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
            <button type="button" id="btnRemove" class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-trash me-1"></i>{{ __('Remove from twin') }}</button>
          </div>
        </div>
      </div>
    </div>

    {{-- Right: canvas --}}
    <div class="col-lg-9">
      <div class="card">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
          <strong>{{ __('Floorplan') }}</strong>
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
      tilt: '{{ route('exhibition-space.builder.tilt', ['slug' => $space->slug]) }}'
    };
    var FLOORPLAN = @json($space->floorplan_image_path);
    var PLACEMENTS = @json($placements);

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
    var layer = new Konva.Layer();
    stage.add(bgLayer); stage.add(layer);

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

    stage.on('click tap', function (e) { if (e.target === stage) clearSelect(); });

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
    document.getElementById('btnRemove').addEventListener('click', function () {
      if (!selected) return;
      var g = selected, id = g.getAttr('placementId');
      fetch(URLS.remove, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ placement_id: id })
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (d.ok) { clearSelect(); g.destroy(); layer.draw(); }
      });
    });

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
          fetch(URLS.place, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ information_object_id: val, pos_x: 0.5, pos_y: 0.5, size_units_used: parseFloat(document.getElementById('initialSize').value) || 0 })
          }).then(function (r) { return r.json(); }).then(function (d) {
            if (d.ok) { var g = addNode(d.placement); layer.draw(); selectNode(g); }
            self.clear(true); self.clearOptions();
          });
        }
      });
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
