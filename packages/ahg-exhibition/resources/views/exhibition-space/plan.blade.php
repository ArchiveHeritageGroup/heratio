{{-- heratio#1143 — Building plan editor: arrange rooms on a blueprint. --}}
@extends('theme::layouts.1col')

@section('title', __('Building Plan') . ' — ' . $space->name)
@section('body-class', 'exhibition-space plan')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1"><i class="fas fa-drafting-compass me-2"></i>{{ __('Building Plan') }} <small class="text-muted">{{ $space->name }}</small></h1>
    <a href="{{ route('exhibition-space.walkthrough', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-vr-cardboard me-1"></i>{{ __('Walkthrough') }}</a>
    <a href="{{ route('exhibition-space.builder', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-cubes me-1"></i>{{ __('Builder') }}</a>
  </div>
  <p class="text-muted small mb-3">{{ __('Drag rooms to position them; use the corner handles to resize. Upload a blueprint to trace over. Saved automatically; the 3D walkthrough follows this layout.') }}</p>

  @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif

  <div class="row g-3">
    <div class="col-lg-3">
      @auth
      <div class="card mb-3">
        <div class="card-header py-2"><strong><i class="fas fa-map me-1"></i>{{ __('Blueprint') }}</strong></div>
        <div class="card-body">
          <form method="POST" action="{{ route('exhibition-space.plan.image', ['slug' => $space->slug]) }}" enctype="multipart/form-data" class="mb-2">
            @csrf
            <input type="file" name="plan_image" accept="image/*" class="form-control form-control-sm mb-2" required>
            <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-upload me-1"></i>{{ __('Upload blueprint') }}</button>
          </form>
          @if(!empty($plan['plan_image']))
          <form method="POST" action="{{ route('exhibition-space.plan.image-clear', ['slug' => $space->slug]) }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-times me-1"></i>{{ __('Clear blueprint') }}</button>
          </form>
          @endif
        </div>
      </div>
      @endauth
      <div class="card">
        <div class="card-header py-2"><strong><i class="fas fa-th-large me-1"></i>{{ __('Rooms') }}</strong></div>
        <div class="card-body p-2"><div id="planRoomList" class="small text-muted"></div></div>
      </div>
    </div>
    <div class="col-lg-9">
      <div class="card">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
          <strong>{{ __('Plan') }}</strong>
          <span class="small text-muted"><span id="planSave">{{ __('All changes saved') }}</span></span>
        </div>
        <div class="card-body p-0"><div id="planWrap" style="width:100%;background:#f4f4f4;border-radius:0 0 .375rem .375rem;overflow:hidden;"></div></div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/konva@9.3.6/konva.min.js"></script>
  <script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    var CSRF = '{{ csrf_token() }}';
    var SAVE_URL = '{{ route('exhibition-space.plan.save', ['slug' => $space->slug]) }}';
    var PLAN = @json($plan);
    var wrap = document.getElementById('planWrap');
    if (typeof Konva === 'undefined') { wrap.innerHTML = '<div class="p-4 text-muted">{{ __('Canvas failed to load.') }}</div>'; return; }

    var W = Math.max(320, wrap.clientWidth || 800), H = Math.round(W * 0.62);
    // Assign default positions (a simple row) to rooms with no plan coords yet.
    var cursor = 1;
    PLAN.rooms.forEach(function (r) {
      if (r.bld_x === null || r.bld_y === null) { r.bld_x = cursor; r.bld_y = 1; cursor += r.w + 1; }
    });
    // Building extent (with margin) -> pixel scale.
    var ext = { w: 30, h: 22 };
    PLAN.rooms.forEach(function (r) { ext.w = Math.max(ext.w, r.bld_x + r.w + 2); ext.h = Math.max(ext.h, r.bld_y + r.d + 2); });
    var scale = Math.min(W / ext.w, H / ext.h);

    var stage = new Konva.Stage({ container: 'planWrap', width: W, height: H });
    var bg = new Konva.Layer(), layer = new Konva.Layer();
    stage.add(bg); stage.add(layer);
    if (PLAN.plan_image) {
      var img = new Image();
      img.onload = function () { bg.add(new Konva.Image({ image: img, x: 0, y: 0, width: W, height: H, opacity: 0.6, listening: false })); bg.draw(); };
      img.src = PLAN.plan_image;
    } else {
      for (var gx = 0; gx <= ext.w; gx += 2) bg.add(new Konva.Line({ points: [gx * scale, 0, gx * scale, H], stroke: '#e3e3e3', listening: false }));
      for (var gy = 0; gy <= ext.h; gy += 2) bg.add(new Konva.Line({ points: [0, gy * scale, W, gy * scale], stroke: '#e3e3e3', listening: false }));
      bg.draw();
    }

    var tr = new Konva.Transformer({ rotateEnabled: false, keepRatio: false, enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'] });
    layer.add(tr);
    var saveTimer = null;
    function flagSaving() { document.getElementById('planSave').textContent = '{{ __('Saving...') }}'; }
    function saveRoom(g) {
      var r = g.getAttr('room');
      var x = g.x() / scale, y = g.y() / scale;
      var w = (g.width() * g.scaleX()) / scale, d = (g.height() * g.scaleY()) / scale;
      fetch(SAVE_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ room_id: r.id, x: x, y: y, w: w, d: d }) })
        .then(function (res) { return res.json(); }).then(function () { document.getElementById('planSave').textContent = '{{ __('All changes saved') }}'; });
    }
    function selectRoom(g) { tr.nodes([g]); layer.draw(); }

    PLAN.rooms.forEach(function (r) {
      var g = new Konva.Group({ x: r.bld_x * scale, y: r.bld_y * scale, draggable: true });
      g.setAttr('room', r);
      var rect = new Konva.Rect({ width: r.w * scale, height: r.d * scale, fill: r.is_current ? 'rgba(13,110,253,.25)' : 'rgba(108,117,125,.2)', stroke: r.is_current ? '#0d6efd' : '#6c757d', strokeWidth: 2 });
      var label = new Konva.Text({ x: 4, y: 4, text: r.name, fontSize: 12, fill: '#212529', width: r.w * scale - 8 });
      g.add(rect); g.add(label);
      g.on('click tap', function (e) { e.cancelBubble = true; selectRoom(g); });
      g.on('dragend', function () { flagSaving(); saveRoom(g); });
      g.on('transformend', function () {
        // bake scale into the rect size, reset scale, resize label
        var nw = g.width() * g.scaleX(), nh = g.height() * g.scaleY();
        rect.width(nw); rect.height(nh); label.width(nw - 8); g.scale({ x: 1, y: 1 }); g.width(nw); g.height(nh);
        flagSaving(); saveRoom(g); layer.draw();
      });
      g.width(r.w * scale); g.height(r.d * scale);
      layer.add(g);
    });
    layer.draw();
    stage.on('click tap', function (e) { if (e.target === stage) { tr.nodes([]); layer.draw(); } });

    (function list() {
      var el = document.getElementById('planRoomList');
      el.innerHTML = PLAN.rooms.map(function (r) { return '<div>' + (r.is_current ? '<b>' : '') + r.name + (r.is_current ? '</b>' : '') + ' <span class="text-muted">' + Math.round(r.w) + '×' + Math.round(r.d) + 'm</span></div>'; }).join('');
    })();
  })();
  </script>
@endsection
