{{-- heratio#1138 — Digital Twin: 2.5D pannable walkthrough (Phase 2). --}}
@extends('theme::layouts.1col')

@section('title', __('Virtual Walkthrough') . ' — ' . $space->name)
@section('body-class', 'exhibition-space walkthrough')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1">
      <i class="fas fa-walking me-2"></i>{{ __('Virtual Walkthrough') }}
      <small class="text-muted">{{ $space->name }}</small>
    </h1>
    <a href="{{ route('exhibition-space.show', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to space') }}
    </a>
    @auth
      <a href="{{ route('exhibition-space.builder', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-cubes me-1"></i>{{ __('Edit in Builder') }}
      </a>
    @endauth
  </div>

  @if(count($stops) === 0)
    <div class="alert alert-info">
      {{ __('Nothing has been placed in this space yet.') }}
      @auth <a href="{{ route('exhibition-space.builder', ['slug' => $space->slug]) }}">{{ __('Open the Digital Twin Builder') }}</a>.@endauth
    </div>
  @else
    <div class="card">
      <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="btn-group btn-group-sm" role="group">
          <button type="button" class="btn btn-outline-secondary" id="zoomOut" title="{{ __('Zoom out') }}"><i class="fas fa-search-minus"></i></button>
          <button type="button" class="btn btn-outline-secondary" id="zoomReset" title="{{ __('Reset view') }}"><i class="fas fa-expand"></i></button>
          <button type="button" class="btn btn-outline-secondary" id="zoomIn" title="{{ __('Zoom in') }}"><i class="fas fa-search-plus"></i></button>
        </div>
        <div class="btn-group btn-group-sm" role="group">
          <button type="button" class="btn btn-primary" id="tourStart"><i class="fas fa-play me-1"></i>{{ __('Guided tour') }}</button>
          <button type="button" class="btn btn-outline-primary d-none" id="tourPrev"><i class="fas fa-chevron-left"></i></button>
          <span class="btn btn-outline-secondary disabled d-none" id="tourCounter">0 / 0</span>
          <button type="button" class="btn btn-outline-primary d-none" id="tourNext"><i class="fas fa-chevron-right"></i></button>
        </div>
        <span class="small text-muted">{{ __('Drag to pan, scroll to zoom, click an object for details.') }}</span>
      </div>
      <div class="card-body p-0">
        <div id="stageWrap" style="width:100%;background:#eef0f2;border-radius:0 0 .375rem .375rem;overflow:hidden;cursor:grab;"></div>
      </div>
    </div>
  @endif

  {{-- Detail panel --}}
  <div class="offcanvas offcanvas-end" tabindex="-1" id="wtPanel" aria-labelledby="wtPanelTitle">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="wtPanelTitle">{{ __('Object') }}</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}"></button>
    </div>
    <div class="offcanvas-body">
      <div id="wtImgWrap" class="text-center mb-3"></div>
      <h6 id="wtTitle" class="fw-bold"></h6>
      <p id="wtDesc" class="small text-muted"></p>
      <a id="wtRecord" href="#" class="btn btn-sm btn-outline-primary d-none"><i class="fas fa-external-link-alt me-1"></i>{{ __('View full record') }}</a>
    </div>
  </div>

  @if(count($stops) > 0)
  <script src="https://cdn.jsdelivr.net/npm/konva@9.3.6/konva.min.js"></script>
  <script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    var STOPS = @json($stops);
    var FLOORPLAN = @json($space->floorplan_image_path);
    var wrap = document.getElementById('stageWrap');
    if (typeof Konva === 'undefined') { wrap.innerHTML = '<div class="p-4 text-muted">{{ __('Canvas library failed to load.') }}</div>'; return; }

    var W0 = 1200, H0 = 750;                       // world size
    var SW = Math.max(320, wrap.clientWidth || 900);
    var SH = Math.round(SW * 0.6);
    var stage = new Konva.Stage({ container: 'stageWrap', width: SW, height: SH, draggable: true });
    var bgLayer = new Konva.Layer(); var layer = new Konva.Layer();
    stage.add(bgLayer); stage.add(layer);

    if (FLOORPLAN) {
      var bg = new Image();
      bg.onload = function () { bgLayer.add(new Konva.Image({ image: bg, x: 0, y: 0, width: W0, height: H0, listening: false })); bgLayer.draw(); };
      bg.src = FLOORPLAN;
    } else {
      var grid = 50;
      for (var gx = 0; gx <= W0; gx += grid) bgLayer.add(new Konva.Line({ points: [gx, 0, gx, H0], stroke: '#e0e3e6', strokeWidth: 1, listening: false }));
      for (var gy = 0; gy <= H0; gy += grid) bgLayer.add(new Konva.Line({ points: [0, gy, W0, gy], stroke: '#e0e3e6', strokeWidth: 1, listening: false }));
      bgLayer.draw();
    }

    // Detail offcanvas
    var oc = new bootstrap.Offcanvas(document.getElementById('wtPanel'));
    function openPanel(s) {
      document.getElementById('wtTitle').textContent = s.title;
      document.getElementById('wtDesc').textContent = s.description || '{{ __('No description available.') }}';
      var iw = document.getElementById('wtImgWrap');
      iw.innerHTML = s.thumb_url ? '<img src="' + s.thumb_url + '" class="img-fluid rounded" style="max-height:260px" alt="">' : '<div class="text-muted py-4"><i class="fas fa-image fa-2x"></i></div>';
      var rec = document.getElementById('wtRecord');
      if (s.record_url) { rec.href = s.record_url; rec.classList.remove('d-none'); } else { rec.classList.add('d-none'); }
      oc.show();
    }

    // Hotspots
    STOPS.forEach(function (s, i) {
      var g = new Konva.Group({ x: s.pos_x * W0, y: s.pos_y * H0 });
      var r = 22;
      g.add(new Konva.Circle({ radius: r, fill: '#0d6efd', stroke: '#fff', strokeWidth: 3, shadowColor: '#000', shadowBlur: 6, shadowOpacity: 0.3 }));
      if (s.thumb_url) {
        var im = new Image();
        im.onload = function () {
          var ki = new Konva.Image({ image: im, x: -r, y: -r, width: r * 2, height: r * 2 });
          var clip = new Konva.Group({ clipFunc: function (ctx) { ctx.arc(0, 0, r - 2, 0, Math.PI * 2, false); } });
          clip.add(ki); g.add(clip);
          g.add(new Konva.Circle({ radius: r, stroke: '#fff', strokeWidth: 3 }));
          layer.draw();
        };
        im.src = s.thumb_url;
      }
      var badge = new Konva.Label({ x: r - 6, y: -r - 6 });
      badge.add(new Konva.Tag({ fill: '#dc3545', cornerRadius: 8 }));
      badge.add(new Konva.Text({ text: String(i + 1), fontSize: 12, fontStyle: 'bold', padding: 4, fill: '#fff' }));
      g.add(badge);
      g.on('mouseenter', function () { stage.container().style.cursor = 'pointer'; });
      g.on('mouseleave', function () { stage.container().style.cursor = 'grab'; });
      g.on('click tap', function (e) { e.cancelBubble = true; openPanel(s); });
      layer.add(g);
    });
    layer.draw();

    // Zoom helpers
    function clampScale(s) { return Math.max(0.4, Math.min(4, s)); }
    function zoomAround(factor, cx, cy) {
      var old = stage.scaleX();
      var ns = clampScale(old * factor);
      var pt = { x: (cx - stage.x()) / old, y: (cy - stage.y()) / old };
      stage.scale({ x: ns, y: ns });
      stage.position({ x: cx - pt.x * ns, y: cy - pt.y * ns });
      stage.batchDraw();
    }
    stage.on('wheel', function (e) {
      e.evt.preventDefault();
      var p = stage.getPointerPosition();
      zoomAround(e.evt.deltaY > 0 ? 0.9 : 1.1, p.x, p.y);
    });
    document.getElementById('zoomIn').onclick = function () { zoomAround(1.2, SW / 2, SH / 2); };
    document.getElementById('zoomOut').onclick = function () { zoomAround(0.8, SW / 2, SH / 2); };
    function fitView() {
      var s = Math.min(SW / W0, SH / H0);
      stage.scale({ x: s, y: s });
      stage.position({ x: (SW - W0 * s) / 2, y: (SH - H0 * s) / 2 });
      stage.batchDraw();
    }
    document.getElementById('zoomReset').onclick = fitView;
    fitView();
    stage.on('dragstart', function () { stage.container().style.cursor = 'grabbing'; });
    stage.on('dragend', function () { stage.container().style.cursor = 'grab'; });

    // Guided tour
    var tour = -1;
    function gotoStop(i) {
      if (i < 0 || i >= STOPS.length) return;
      tour = i;
      var s = STOPS[i];
      var sc = 1.4;
      var wx = s.pos_x * W0, wy = s.pos_y * H0;
      stage.to({ scaleX: sc, scaleY: sc, x: SW / 2 - wx * sc, y: SH / 2 - wy * sc, duration: 0.4 });
      openPanel(s);
      document.getElementById('tourCounter').textContent = (i + 1) + ' / ' + STOPS.length;
    }
    document.getElementById('tourStart').onclick = function () {
      ['tourPrev', 'tourNext', 'tourCounter'].forEach(function (id) { document.getElementById(id).classList.remove('d-none'); });
      gotoStop(0);
    };
    document.getElementById('tourNext').onclick = function () { gotoStop(Math.min(STOPS.length - 1, tour + 1)); };
    document.getElementById('tourPrev').onclick = function () { gotoStop(Math.max(0, tour - 1)); };

    window.addEventListener('resize', function () {
      var nw = Math.max(320, wrap.clientWidth || SW);
      if (Math.abs(nw - SW) < 20) return;
      SW = nw; SH = Math.round(SW * 0.6);
      stage.width(SW); stage.height(SH); fitView();
    });
  })();
  </script>
  @endif
@endsection
