{{-- heratio#1143 — Building plan editor: arrange rooms on a blueprint. --}}
@extends('theme::layouts.1col')

@section('title', __('Building Plan') . ' — ' . $space->name)
@section('body-class', 'exhibition-space plan')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1"><i class="fas fa-drafting-compass me-2"></i>{{ __('Building Plan') }} <small class="text-muted">{{ $space->name }}</small></h1>
    @auth<button type="button" id="addRoomBtn" class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>{{ __('Add room') }}</button>@endauth
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
          <button type="button" id="planAdjustBtn" class="btn btn-sm btn-outline-secondary w-100 mb-2"><i class="fas fa-arrows-alt me-1"></i>{{ __('Adjust blueprint') }}</button>
          <small class="text-muted d-block mb-2">{{ __('The blueprint is pinned to the floor plan in metres, so it stays aligned with the rooms when you zoom or add rooms. Click Adjust to drag/resize it onto the rooms.') }}</small>
          <form method="POST" action="{{ route('exhibition-space.plan.image-clear', ['slug' => $space->slug]) }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-times me-1"></i>{{ __('Clear blueprint') }}</button>
          </form>
          @endif
        </div>
      </div>
      @endauth
      <div class="card mb-3">
        <div class="card-header py-2"><strong><i class="fas fa-th-large me-1"></i>{{ __('Rooms') }}</strong></div>
        <div class="card-body p-2"><div id="planRoomList" class="small text-muted"></div></div>
      </div>
      @auth
      <div class="card mb-3" id="roomCard" style="display:none;">
        <div class="card-header py-2"><strong><i class="fas fa-sync-alt me-1"></i>{{ __('Selected room') }}</strong> <span class="small text-muted" id="roomCardName"></span></div>
        <div class="card-body p-2">
          <a href="#" target="_blank" rel="noopener" id="roomEditLink" class="btn btn-sm btn-outline-secondary w-100 mb-2"><i class="fas fa-edit me-1"></i>{{ __('Edit room details') }}</a>
          <label class="form-label small mb-1">{{ __('Rotation (degrees)') }}</label>
          <div class="input-group input-group-sm mb-2">
            <button type="button" class="btn btn-outline-secondary" id="rotMinus" title="{{ __('Rotate left 15°') }}"><i class="fas fa-undo"></i></button>
            <input type="number" id="rotInput" class="form-control text-center" step="1" value="0">
            <button type="button" class="btn btn-outline-secondary" id="rotPlus" title="{{ __('Rotate right 15°') }}"><i class="fas fa-redo"></i></button>
            <button type="button" class="btn btn-outline-secondary" id="rotZero" title="{{ __('Reset') }}">0</button>
          </div>
          <small class="text-muted d-block">{{ __('You can also drag the round handle on the room to rotate it.') }}</small>
          <hr class="my-2">
          <label class="form-label small mb-1">{{ __('Footprint shape') }}</label>
          <button type="button" class="btn btn-sm btn-outline-primary w-100 mb-1" id="shapeEdit"><i class="fas fa-draw-polygon me-1"></i>{{ __('Edit room shape') }}</button>
          <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="shapeReset">{{ __('Reset to rectangle') }}</button>
          <small class="text-muted d-block mt-1" id="shapeHint">{{ __('Make L-shapes or cut corners. Drag a corner; click a + to add one; double-click to remove.') }}</small>
        </div>
      </div>
      <div class="card mb-3" id="doorCard" style="display:none;">
        <div class="card-header py-2"><strong><i class="fas fa-door-open me-1"></i>{{ __('Doors') }}</strong> <span class="small text-muted" id="doorRoomName"></span></div>
        <div class="card-body p-2">
          <p class="small text-muted mb-2">{{ __('Add a door to a wall, then drag it along that wall to position it. Double-click a door to remove it.') }}</p>
          <div class="btn-group btn-group-sm w-100 mb-2" role="group" id="doorWallBtns">
            <button type="button" class="btn btn-outline-primary" data-door="north">{{ __('Top') }}</button>
            <button type="button" class="btn btn-outline-primary" data-door="south">{{ __('Bottom') }}</button>
            <button type="button" class="btn btn-outline-primary" data-door="west">{{ __('Left') }}</button>
            <button type="button" class="btn btn-outline-primary" data-door="east">{{ __('Right') }}</button>
          </div>
          <div id="edgeDoorBtns" class="d-flex flex-wrap gap-1 mb-2" style="display:none;"></div>
          <div id="doorList" class="small"></div>
        </div>
      </div>
      <div class="card" id="corridorCard">
        <div class="card-header py-2"><strong><i class="fas fa-shoe-prints me-1"></i>{{ __('Corridor objects') }}</strong></div>
        <div class="card-body p-2">
          <p class="small text-muted mb-2">{{ __('Place objects in the open space between rooms. Search to add, then drag the dot. Double-click to remove.') }}</p>
          <select id="corridorAdd" class="form-control form-control-sm mb-2"><option value="">{{ __('Search an object to add…') }}</option></select>
          <div id="corridorList" class="small text-muted"></div>
        </div>
      </div>
      @endauth
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

  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/konva@9.3.6/konva.min.js"></script>
  <script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    var CSRF = '{{ csrf_token() }}';
    var SAVE_URL = '{{ route('exhibition-space.plan.save', ['slug' => $space->slug]) }}';
    var DOORS_URL = '{{ route('exhibition-space.plan.doors', ['slug' => $space->slug]) }}';
    var SHAPE_URL = '{{ route('exhibition-space.plan.shape', ['slug' => $space->slug]) }}';
    var ADD_ROOM_URL = '{{ route('exhibition-space.plan.add-room', ['slug' => $space->slug]) }}';
    var IMG_RECT_URL = '{{ route('exhibition-space.plan.image-rect', ['slug' => $space->slug]) }}';
    var EDIT_BASE = '{{ url('exhibition-space') }}';
    var CORR_ADD = '{{ route('exhibition-space.plan.corridor-add', ['slug' => $space->slug]) }}';
    var CORR_MOVE = '{{ route('exhibition-space.plan.corridor-move', ['slug' => $space->slug]) }}';
    var CORR_REMOVE = '{{ route('exhibition-space.plan.corridor-remove', ['slug' => $space->slug]) }}';
    var AUTOCOMPLETE = '{{ url('informationobject/autocomplete') }}';
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
    if (PLAN.plan_rect) { ext.w = Math.max(ext.w, PLAN.plan_rect.x + PLAN.plan_rect.w + 1); ext.h = Math.max(ext.h, PLAN.plan_rect.y + PLAN.plan_rect.h + 1); }
    var scale = Math.min(W / ext.w, H / ext.h);

    var stage = new Konva.Stage({ container: 'planWrap', width: W, height: H });
    var bg = new Konva.Layer(), layer = new Konva.Layer();
    stage.add(bg); stage.add(layer);
    // Blueprint is world-anchored (metres) so it scales/pins with the rooms.
    var planImg = null, planRect = PLAN.plan_rect || { x: 0, y: 0, w: ext.w, h: ext.h };
    if (PLAN.plan_image) {
      var img = new Image();
      img.onload = function () {
        planImg = new Konva.Image({ image: img, x: planRect.x * scale, y: planRect.y * scale, width: planRect.w * scale, height: planRect.h * scale, opacity: 0.55, listening: false });
        bg.add(planImg); bg.draw();
      };
      img.src = PLAN.plan_image;
    } else {
      for (var gx = 0; gx <= ext.w; gx += 2) bg.add(new Konva.Line({ points: [gx * scale, 0, gx * scale, H], stroke: '#e3e3e3', listening: false }));
      for (var gy = 0; gy <= ext.h; gy += 2) bg.add(new Konva.Line({ points: [0, gy * scale, W, gy * scale], stroke: '#e3e3e3', listening: false }));
      bg.draw();
    }

    var tr = new Konva.Transformer({ rotateEnabled: true, keepRatio: false, enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'] });
    layer.add(tr);
    var saveTimer = null;
    function flagSaving() { document.getElementById('planSave').textContent = '{{ __('Saving...') }}'; }
    // Snap a dragged room's edges to nearby rooms so they sit flush (no void).
    function snapRoom(g) {
      var r = g.getAttr('room');
      if (g.rotation()) return;   // axis-aligned snapping only
      var x = g.x() / scale, y = g.y() / scale, w = r.w, d = r.d, TH = 0.7;
      var bestDX = null, bestDY = null;
      PLAN.rooms.forEach(function (o) {
        if (o === r || o.rot) return;
        [[x, o.bld_x], [x, o.bld_x + o.w], [x + w, o.bld_x], [x + w, o.bld_x + o.w]].forEach(function (p) {
          var dx = p[1] - p[0]; if (Math.abs(dx) < TH && (bestDX === null || Math.abs(dx) < Math.abs(bestDX))) bestDX = dx;
        });
        [[y, o.bld_y], [y, o.bld_y + o.d], [y + d, o.bld_y], [y + d, o.bld_y + o.d]].forEach(function (p) {
          var dy = p[1] - p[0]; if (Math.abs(dy) < TH && (bestDY === null || Math.abs(dy) < Math.abs(bestDY))) bestDY = dy;
        });
      });
      if (bestDX !== null) g.x((x + bestDX) * scale);
      if (bestDY !== null) g.y((y + bestDY) * scale);
    }
    // Push a room out of any room it overlaps, abutting the closest edge (no overlap).
    function resolveOverlap(g) {
      var r = g.getAttr('room');
      if (g.rotation()) return;   // axis-aligned only
      var x = g.x() / scale, y = g.y() / scale, w = r.w, d = r.d;
      for (var pass = 0; pass < 6; pass++) {
        var moved = false;
        PLAN.rooms.forEach(function (o) {
          if (o === r || o.rot || o.bld_x === null || o.bld_y === null) return;
          var penX = Math.min(x + w, o.bld_x + o.w) - Math.max(x, o.bld_x);
          var penY = Math.min(y + d, o.bld_y + o.d) - Math.max(y, o.bld_y);
          if (penX > 0.02 && penY > 0.02) {   // overlapping: separate along the shallower axis
            if (penX <= penY) { x = ((x + w / 2) < (o.bld_x + o.w / 2)) ? (o.bld_x - w) : (o.bld_x + o.w); }
            else { y = ((y + d / 2) < (o.bld_y + o.d / 2)) ? (o.bld_y - d) : (o.bld_y + o.d); }
            x = Math.max(0, x); y = Math.max(0, y);
            moved = true;
          }
        });
        if (!moved) break;
      }
      g.x(x * scale); g.y(y * scale);
    }
    function saveRoom(g) {
      var r = g.getAttr('room');
      var x = g.x() / scale, y = g.y() / scale;
      var w = (g.width() * g.scaleX()) / scale, d = (g.height() * g.scaleY()) / scale;
      r.bld_x = x; r.bld_y = y; r.rot = g.rotation();
      fetch(SAVE_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ room_id: r.id, x: x, y: y, w: w, d: d, rot: g.rotation() }) })
        .then(function (res) { return res.json(); }).then(function () { document.getElementById('planSave').textContent = '{{ __('All changes saved') }}'; });
    }
    // ---- Doors (manual openings placed on a room's walls) ----
    var DOOR_LBL = { north: '{{ __('Top') }}', south: '{{ __('Bottom') }}', west: '{{ __('Left') }}', east: '{{ __('Right') }}' };
    var selectedG = null, DT = 7;   // door marker thickness (px)
    function saveDoors(g) {
      var r = g.getAttr('room');
      flagSaving();
      fetch(DOORS_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ room_id: r.id, doors: (r.doors || []).map(function (d) { return { wall: d.wall, edge: d.edge, pos: d.pos, width: d.width }; }) }) })
        .then(function (res) { return res.json(); }).then(function () { document.getElementById('planSave').textContent = '{{ __('All changes saved') }}'; });
    }
    function drawDoors(g) {
      var r = g.getAttr('room');
      g.find('.door').forEach(function (n) { n.destroy(); });
      var ww = r.w * scale, hh = r.d * scale;
      (r.doors || []).forEach(function (d, idx) {
        // Polygon-edge door: a marker that slides along its edge.
        if (typeof d.edge === 'number' && r.shape && r.shape.length >= 3) {
          var sa = r.shape[d.edge % r.shape.length], sb = r.shape[(d.edge + 1) % r.shape.length];
          var Ax = sa.x * ww, Ay = sa.z * hh, Bx = sb.x * ww, By = sb.z * hh;
          var L = Math.hypot(Bx - Ax, By - Ay) || 1, ux = (Bx - Ax) / L, uy = (By - Ay) / L;
          var dwpx = Math.min((d.width || 1.6) * scale, L);
          var en = new Konva.Rect({ name: 'door', width: dwpx, height: DT, offsetX: dwpx / 2, offsetY: DT / 2, fill: '#fff', stroke: '#0d6efd', strokeWidth: 2, cornerRadius: 1, rotation: Math.atan2(uy, ux) * 180 / Math.PI, draggable: true });
          en.setAttr('doorIdx', idx);
          var t0 = (d.pos == null ? 0.5 : d.pos); en.x(Ax + ux * t0 * L); en.y(Ay + uy * t0 * L);
          en.on('dragmove', function () { var t = ((en.x() - Ax) * ux + (en.y() - Ay) * uy) / L; t = Math.max(0, Math.min(1, t)); en.x(Ax + ux * t * L); en.y(Ay + uy * t * L); d.pos = t; });
          en.on('dragend', function () { saveDoors(g); });
          en.on('dblclick dbltap', function (e) { e.cancelBubble = true; r.doors.splice(en.getAttr('doorIdx'), 1); drawDoors(g); saveDoors(g); if (selectedG === g) refreshDoorList(g); });
          g.add(en);
          return;
        }
        var dw = Math.min((d.width || 1.6) * scale, (d.wall === 'north' || d.wall === 'south') ? ww : hh);
        var node = new Konva.Rect({ name: 'door', fill: '#fff', stroke: '#0d6efd', strokeWidth: 2, draggable: true, cornerRadius: 1 });
        node.setAttr('doorIdx', idx);
        var horiz = (d.wall === 'north' || d.wall === 'south');
        // Clamp in LOCAL coords so dragging works even when the room is rotated.
        if (horiz) {
          node.width(dw); node.height(DT);
          node.x(d.pos * ww - dw / 2); node.y((d.wall === 'north' ? 0 : hh) - DT / 2);
          node.on('dragmove', function () {
            node.y((d.wall === 'north' ? 0 : hh) - DT / 2);
            var nx = Math.max(0, Math.min(ww - dw, node.x())); node.x(nx);
            d.pos = Math.max(0, Math.min(1, (nx + dw / 2) / ww));
          });
        } else {
          node.width(DT); node.height(dw);
          node.x((d.wall === 'west' ? 0 : ww) - DT / 2); node.y(d.pos * hh - dw / 2);
          node.on('dragmove', function () {
            node.x((d.wall === 'west' ? 0 : ww) - DT / 2);
            var ny = Math.max(0, Math.min(hh - dw, node.y())); node.y(ny);
            d.pos = Math.max(0, Math.min(1, (ny + dw / 2) / hh));
          });
        }
        node.on('dragend', function () { saveDoors(g); });
        node.on('dblclick dbltap', function (e) { e.cancelBubble = true; r.doors.splice(node.getAttr('doorIdx'), 1); drawDoors(g); saveDoors(g); if (selectedG === g) refreshDoorList(g); });
        g.add(node);
      });
      layer.draw();
    }
    function refreshDoorList(g) {
      var el = document.getElementById('doorList'); if (!el) return;
      var r = g.getAttr('room');
      if (!r.doors || !r.doors.length) { el.innerHTML = '<span class="text-muted">{{ __('No doors yet.') }}</span>'; return; }
      el.innerHTML = '';
      r.doors.forEach(function (d, idx) {
        var row = document.createElement('div');
        row.className = 'd-flex align-items-center gap-1 mb-1';
        var lbl = (typeof d.edge === 'number') ? ('{{ __('Wall') }} ' + (d.edge + 1)) : (DOOR_LBL[d.wall] || '?');
        row.innerHTML = '<span class="badge bg-secondary">' + lbl + '</span>' +
          '<input type="number" class="form-control form-control-sm" style="width:68px" min="0.5" max="6" step="0.1" value="' + d.width + '">' +
          '<span class="small text-muted">m</span>' +
          '<button class="btn btn-sm btn-outline-danger ms-auto" type="button" title="{{ __('Remove') }}">&times;</button>';
        row.querySelector('input').addEventListener('change', function (e) { d.width = Math.max(0.5, Math.min(6, parseFloat(e.target.value) || 1.6)); drawDoors(g); saveDoors(g); });
        row.querySelector('button').addEventListener('click', function () { r.doors.splice(idx, 1); drawDoors(g); saveDoors(g); refreshDoorList(g); });
        el.appendChild(row);
      });
    }
    function addDoor(wall) {
      if (!selectedG) return;
      var r = selectedG.getAttr('room');
      if (!r.doors) r.doors = [];
      r.doors.push({ wall: wall, pos: 0.5, width: 1.6 });
      drawDoors(selectedG); saveDoors(selectedG); refreshDoorList(selectedG);
    }
    // Toggle door controls: rectangle named walls vs one button per polygon edge.
    function updateDoorControls(g) {
      var r = g.getAttr('room'), hasShape = r.shape && r.shape.length >= 3;
      var wallBtns = document.getElementById('doorWallBtns'), edgeBox = document.getElementById('edgeDoorBtns');
      if (wallBtns) wallBtns.style.display = hasShape ? 'none' : '';
      if (!edgeBox) return;
      edgeBox.style.display = hasShape ? 'flex' : 'none';
      edgeBox.innerHTML = '';
      if (hasShape) {
        r.shape.forEach(function (p, i) {
          var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-outline-primary';
          b.textContent = '{{ __('Wall') }} ' + (i + 1);
          b.addEventListener('click', function () { if (!r.doors) r.doors = []; r.doors.push({ edge: i, pos: 0.5, width: 1.6 }); drawDoors(g); saveDoors(g); refreshDoorList(g); });
          edgeBox.appendChild(b);
        });
      }
    }
    function selectRoom(g) {
      if (shapeMode && shapeG && shapeG !== g) { var prev = shapeG; shapeMode = false; shapeG = null; drawShape(prev); }
      selectedG = g; tr.nodes((shapeMode && shapeG === g) ? [] : [g]); layer.draw();
      var nm = g.getAttr('room').name;
      var c = document.getElementById('doorCard');
      if (c) { c.style.display = 'block'; document.getElementById('doorRoomName').textContent = nm; refreshDoorList(g); updateDoorControls(g); }
      var rc = document.getElementById('roomCard');
      if (rc) {
        rc.style.display = 'block'; document.getElementById('roomCardName').textContent = nm; document.getElementById('rotInput').value = Math.round(g.rotation());
        var el = document.getElementById('roomEditLink'); if (el) el.href = EDIT_BASE + '/' + g.getAttr('room').slug + '/edit';
      }
      setShapeBtn(shapeMode && shapeG === g);
    }
    function deselect() {
      if (shapeMode && shapeG) { var prev = shapeG; shapeMode = false; shapeG = null; drawShape(prev); setShapeBtn(false); }
      selectedG = null; tr.nodes([]);
      var c = document.getElementById('doorCard'); if (c) c.style.display = 'none';
      var rc = document.getElementById('roomCard'); if (rc) rc.style.display = 'none';
      layer.draw();
    }
    document.querySelectorAll('#doorCard [data-door]').forEach(function (b) { b.addEventListener('click', function () { addDoor(b.getAttribute('data-door')); }); });
    // Rotation controls (rotate about the room's top-left, matching the walkthrough).
    function applyRot(deg) {
      if (!selectedG) return;
      selectedG.rotation(((deg % 360) + 360) % 360);
      document.getElementById('rotInput').value = Math.round(selectedG.rotation());
      layer.draw(); flagSaving(); saveRoom(selectedG);
    }
    document.getElementById('rotMinus').addEventListener('click', function () { if (selectedG) applyRot(selectedG.rotation() - 15); });
    document.getElementById('rotPlus').addEventListener('click', function () { if (selectedG) applyRot(selectedG.rotation() + 15); });
    document.getElementById('rotZero').addEventListener('click', function () { applyRot(0); });
    document.getElementById('rotInput').addEventListener('change', function (e) { applyRot(parseFloat(e.target.value) || 0); });

    // ---- Footprint shape (polygon): make L-shapes / cut corners ----
    var shapeMode = false, shapeG = null;
    function defaultShape() { return [{ x: 0, z: 0 }, { x: 1, z: 0 }, { x: 1, z: 1 }, { x: 0, z: 1 }]; }
    function saveShape(g) {
      var r = g.getAttr('room'); flagSaving();
      fetch(SHAPE_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ room_id: r.id, points: (r.shape && r.shape.length >= 3) ? r.shape : null }) })
        .then(function (res) { return res.json(); }).then(function () { document.getElementById('planSave').textContent = '{{ __('All changes saved') }}'; });
    }
    function updatePoly(g) {
      var r = g.getAttr('room'), ww = r.w * scale, hh = r.d * scale, poly = g.findOne('.shapepoly');
      if (poly && r.shape) { var pts = []; r.shape.forEach(function (p) { pts.push(p.x * ww, p.z * hh); }); poly.points(pts); layer.draw(); }
    }
    function drawShape(g) {
      g.find('.shapepoly').forEach(function (n) { n.destroy(); });
      g.find('.shapevert').forEach(function (n) { n.destroy(); });
      g.find('.shapeadd').forEach(function (n) { n.destroy(); });
      g.find('.shapenum').forEach(function (n) { n.destroy(); });
      var r = g.getAttr('room'), ww = r.w * scale, hh = r.d * scale;
      var sh = (r.shape && r.shape.length >= 3) ? r.shape : null;
      var rect = g.findOne('.roomrect');
      if (sh) {
        if (rect) rect.opacity(0.12);
        var pts = []; sh.forEach(function (p) { pts.push(p.x * ww, p.z * hh); });
        g.add(new Konva.Line({ name: 'shapepoly', points: pts, closed: true, fill: r.is_current ? 'rgba(13,110,253,.28)' : 'rgba(108,117,125,.26)', stroke: '#0d6efd', strokeWidth: 1.5, listening: false }));
        // Number each wall to match the door "Wall N" buttons.
        var cxp = 0, cyp = 0; sh.forEach(function (p) { cxp += p.x * ww; cyp += p.z * hh; }); cxp /= sh.length; cyp /= sh.length;
        sh.forEach(function (p, i) {
          var q = sh[(i + 1) % sh.length], mx = (p.x + q.x) / 2 * ww, my = (p.z + q.z) / 2 * hh;
          var dx = cxp - mx, dy = cyp - my, dl = Math.hypot(dx, dy) || 1; mx += dx / dl * 11; my += dy / dl * 11;
          g.add(new Konva.Circle({ name: 'shapenum', x: mx, y: my, radius: 8, fill: '#0d6efd', opacity: 0.85, listening: false }));
          g.add(new Konva.Text({ name: 'shapenum', x: mx - 8, y: my - 5, width: 16, align: 'center', text: '' + (i + 1), fontSize: 10, fill: '#fff', listening: false }));
        });
      } else if (rect) { rect.opacity(1); }
      if (shapeMode && shapeG === g && sh) {
        // Clickable edge lines: click anywhere on an edge to insert a bend point there.
        sh.forEach(function (p, idx) {
          var q = sh[(idx + 1) % sh.length];
          var ax = p.x * ww, ay = p.z * hh, bx = q.x * ww, by = q.z * hh;
          var hit = new Konva.Line({ name: 'shapeadd', points: [ax, ay, bx, by], stroke: 'rgba(13,110,253,0.001)', strokeWidth: 16, hitStrokeWidth: 16 });
          hit.on('click tap', function (e) {
            e.cancelBubble = true;
            var lp = g.getRelativePointerPosition();
            var ex = bx - ax, ey = by - ay, L2 = ex * ex + ey * ey || 1;
            var t = Math.max(0, Math.min(1, ((lp.x - ax) * ex + (lp.y - ay) * ey) / L2));
            r.shape.splice(idx + 1, 0, { x: (ax + t * ex) / ww, z: (ay + t * ey) / hh });
            drawShape(g); saveShape(g); if (selectedG === g) updateDoorControls(g);
          });
          g.add(hit);
        });
        // Quick "+" at each edge midpoint.
        sh.forEach(function (p, idx) {
          var q = sh[(idx + 1) % sh.length], mx = (p.x + q.x) / 2, mz = (p.z + q.z) / 2;
          var a = new Konva.Circle({ name: 'shapeadd', x: mx * ww, y: mz * hh, radius: 5, fill: '#198754', stroke: '#fff', strokeWidth: 1.5 });
          a.on('click tap', function (e) { e.cancelBubble = true; r.shape.splice(idx + 1, 0, { x: mx, z: mz }); drawShape(g); saveShape(g); if (selectedG === g) updateDoorControls(g); });
          g.add(a);
        });
        // Draggable corners (topmost so they win the hit test).
        sh.forEach(function (p, idx) {
          var v = new Konva.Circle({ name: 'shapevert', x: p.x * ww, y: p.z * hh, radius: 7, fill: '#fff', stroke: '#0d6efd', strokeWidth: 2, draggable: true });
          v.on('dragmove', function () { p.x = Math.max(0, Math.min(1, v.x() / ww)); p.z = Math.max(0, Math.min(1, v.y() / hh)); v.x(p.x * ww); v.y(p.z * hh); updatePoly(g); });
          v.on('dragend', function () { saveShape(g); });
          v.on('dblclick dbltap', function (e) { e.cancelBubble = true; if (r.shape.length > 3) { r.shape.splice(idx, 1); drawShape(g); saveShape(g); if (selectedG === g) updateDoorControls(g); } });
          g.add(v);
        });
      }
      layer.draw();
    }
    function setShapeBtn(on) { var se = document.getElementById('shapeEdit'); se.classList.toggle('btn-primary', on); se.classList.toggle('btn-outline-primary', !on); }
    document.getElementById('shapeEdit').addEventListener('click', function () {
      if (!selectedG) return; var r = selectedG.getAttr('room');
      if (shapeMode && shapeG === selectedG) { shapeMode = false; shapeG = null; setShapeBtn(false); tr.nodes([selectedG]); drawShape(selectedG); }
      else { if (!r.shape || r.shape.length < 3) r.shape = defaultShape(); shapeMode = true; shapeG = selectedG; setShapeBtn(true); tr.nodes([]); drawShape(selectedG); saveShape(selectedG); }
      updateDoorControls(selectedG);
    });
    document.getElementById('shapeReset').addEventListener('click', function () {
      if (!selectedG) return; selectedG.getAttr('room').shape = null; shapeMode = false; shapeG = null; setShapeBtn(false);
      tr.nodes([selectedG]); drawShape(selectedG); saveShape(selectedG); updateDoorControls(selectedG);
    });

    function addRoomNode(r) {
      var g = new Konva.Group({ x: r.bld_x * scale, y: r.bld_y * scale, rotation: r.rot || 0, draggable: true });
      g.setAttr('room', r);
      var rect = new Konva.Rect({ name: 'roomrect', width: r.w * scale, height: r.d * scale, fill: r.is_current ? 'rgba(13,110,253,.25)' : 'rgba(108,117,125,.2)', stroke: r.is_current ? '#0d6efd' : '#6c757d', strokeWidth: 2 });
      var label = new Konva.Text({ x: 3, y: 3, text: r.name, fontSize: 9, fill: '#212529', width: r.w * scale - 6 });
      g.add(rect); g.add(label);
      g.on('click tap', function (e) { e.cancelBubble = true; selectRoom(g); });
      g.on('dragmove', function () { snapRoom(g); resolveOverlap(g); });
      g.on('dragend', function () { resolveOverlap(g); flagSaving(); saveRoom(g); });
      g.on('transformend', function () {
        // bake scale into the rect size, reset scale, resize label, reflow doors
        var nw = g.width() * g.scaleX(), nh = g.height() * g.scaleY();
        rect.width(nw); rect.height(nh); label.width(nw - 8); g.scale({ x: 1, y: 1 }); g.width(nw); g.height(nh);
        var r2 = g.getAttr('room'); r2.w = nw / scale; r2.d = nh / scale;
        if (selectedG === g) document.getElementById('rotInput').value = Math.round(g.rotation());
        resolveOverlap(g); drawDoors(g); drawShape(g); flagSaving(); saveRoom(g); layer.draw();
      });
      g.width(r.w * scale); g.height(r.d * scale);
      layer.add(g);
      drawDoors(g); drawShape(g);
      return g;
    }
    PLAN.rooms.forEach(addRoomNode);
    layer.draw();
    stage.on('click tap', function (e) { if (e.target === stage) deselect(); });

    function renderRoomList() {
      var el = document.getElementById('planRoomList');
      el.innerHTML = PLAN.rooms.map(function (r) { return '<div>' + (r.is_current ? '<b>' : '') + r.name + (r.is_current ? '</b>' : '') + ' <span class="text-muted">' + Math.round(r.w) + '×' + Math.round(r.d) + 'm</span></div>'; }).join('');
    }
    renderRoomList();

    // ---- Corridor objects: placed in building space (fraction of the room bbox) ----
    var corrLayer = new Konva.Layer(); stage.add(corrLayer);
    var CORRIDOR = PLAN.corridor || [];
    function hdr() { return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }; }
    function saved() { document.getElementById('planSave').textContent = '{{ __('All changes saved') }}'; }
    function bbox() {
      var b = { minX: Infinity, maxX: -Infinity, minZ: Infinity, maxZ: -Infinity };
      PLAN.rooms.forEach(function (r) { b.minX = Math.min(b.minX, r.bld_x); b.maxX = Math.max(b.maxX, r.bld_x + r.w); b.minZ = Math.min(b.minZ, r.bld_y); b.maxZ = Math.max(b.maxZ, r.bld_y + r.d); });
      if (!isFinite(b.minX)) b = { minX: 0, maxX: ext.w, minZ: 0, maxZ: ext.h };
      return b;
    }
    function corrToPx(c) { var b = bbox(); return { x: (b.minX + c.pos_x * (b.maxX - b.minX)) * scale, y: (b.minZ + c.pos_y * (b.maxZ - b.minZ)) * scale }; }
    function pxToCorr(px, py) { var b = bbox(); return { x: Math.max(0, Math.min(1, (px / scale - b.minX) / ((b.maxX - b.minX) || 1))), y: Math.max(0, Math.min(1, (py / scale - b.minZ) / ((b.maxZ - b.minZ) || 1))) }; }
    function removeCorr(c) {
      fetch(CORR_REMOVE, { method: 'POST', headers: hdr(), body: JSON.stringify({ placement_id: c.id }) })
        .then(function (r) { return r.json(); }).then(function (d) { if (d.ok) { var i = CORRIDOR.indexOf(c); if (i >= 0) CORRIDOR.splice(i, 1); drawCorridor(); saved(); } });
    }
    function listCorr() {
      var el = document.getElementById('corridorList'); if (!el) return;
      if (!CORRIDOR.length) { el.innerHTML = '<span class="text-muted">{{ __('None yet.') }}</span>'; return; }
      el.innerHTML = '';
      CORRIDOR.forEach(function (c) {
        var row = document.createElement('div');
        row.className = 'd-flex align-items-center gap-1 mb-1';
        row.innerHTML = '<i class="fas fa-shoe-prints text-warning"></i> <span class="text-truncate" style="max-width:150px">' + (c.title || ('#' + c.information_object_id)) + '</span><button class="btn btn-sm btn-outline-danger ms-auto" type="button" title="{{ __('Remove') }}">&times;</button>';
        row.querySelector('button').addEventListener('click', function () { removeCorr(c); });
        el.appendChild(row);
      });
    }
    function drawCorridor() {
      corrLayer.destroyChildren();
      CORRIDOR.forEach(function (c) {
        var p = corrToPx(c);
        var g = new Konva.Group({ x: p.x, y: p.y, draggable: true, name: 'corr' });
        g.add(new Konva.Circle({ radius: 9, fill: '#fd7e14', stroke: '#fff', strokeWidth: 2 }));
        g.add(new Konva.Text({ text: (c.title || '').substring(0, 16), x: 12, y: -6, fontSize: 11, fill: '#212529' }));
        g.on('dragmove', function () { var f = pxToCorr(g.x(), g.y()); c.pos_x = f.x; c.pos_y = f.y; });
        g.on('dragend', function () { flagSaving(); fetch(CORR_MOVE, { method: 'POST', headers: hdr(), body: JSON.stringify({ placement_id: c.id, x: c.pos_x, y: c.pos_y }) }).then(saved); });
        g.on('dblclick dbltap', function (e) { e.cancelBubble = true; removeCorr(c); });
        corrLayer.add(g);
      });
      corrLayer.draw();
      listCorr();
    }
    drawCorridor();
    (function () {
      var el = document.getElementById('corridorAdd'); if (!el || typeof TomSelect === 'undefined') return;
      new TomSelect(el, {
        valueField: 'id', labelField: 'name', searchField: ['name'], maxItems: 1, maxOptions: 15,
        load: function (q, cb) { if (q.length < 2) return cb(); fetch(AUTOCOMPLETE + '?query=' + encodeURIComponent(q) + '&limit=15').then(function (r) { return r.json(); }).then(cb).catch(function () { cb(); }); },
        render: { option: function (d, e) { return '<div>' + e(d.name) + ' <small class="text-muted">#' + e(d.id) + '</small></div>'; } },
        onChange: function (val) {
          if (!val) return; var self = this; flagSaving();
          fetch(CORR_ADD, { method: 'POST', headers: hdr(), body: JSON.stringify({ information_object_id: val, x: 0.5, y: 0.5 }) })
            .then(function (r) { return r.json(); }).then(function (d) { if (d.ok && d.placement) { CORRIDOR.push(d.placement); drawCorridor(); saved(); } self.clear(true); self.clearOptions(); });
        }
      });
    })();

    // Add a new room WITHOUT rescaling — existing rooms + blueprint stay aligned.
    // The new room is clamped into the current canvas so it's visible to drag.
    (function () {
      var b = document.getElementById('addRoomBtn'); if (!b) return;
      b.addEventListener('click', function () {
        b.disabled = true; flagSaving();
        fetch(ADD_ROOM_URL, { method: 'POST', headers: hdr(), body: JSON.stringify({}) })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            b.disabled = false;
            if (!d.ok || !d.room) return;
            var r = d.room;
            r.bld_x = Math.max(0, Math.min(ext.w - r.w, r.bld_x));   // keep on-canvas; don't grow extent
            r.bld_y = Math.max(0, Math.min(ext.h - r.d, r.bld_y));
            PLAN.rooms.push(r);
            var g = addRoomNode(r);
            saveRoom(g);            // persist the clamped position
            selectRoom(g);
            renderRoomList();
          })
          .catch(function () { b.disabled = false; });
      });
    })();

    // Adjust blueprint: move/resize the world-anchored image onto the rooms.
    (function () {
      var b = document.getElementById('planAdjustBtn'); if (!b) return;
      var adjLayer = new Konva.Layer(); stage.add(adjLayer);
      var imgTr = null, adjusting = false;
      function saveRect() {
        var nw = planImg.width() * planImg.scaleX(), nh = planImg.height() * planImg.scaleY();
        planImg.width(nw); planImg.height(nh); planImg.scale({ x: 1, y: 1 });
        planRect = { x: planImg.x() / scale, y: planImg.y() / scale, w: nw / scale, h: nh / scale };
        flagSaving();
        fetch(IMG_RECT_URL, { method: 'POST', headers: hdr(), body: JSON.stringify(planRect) }).then(saved);
      }
      b.addEventListener('click', function () {
        if (!planImg) return;
        adjusting = !adjusting;
        b.classList.toggle('btn-secondary', adjusting); b.classList.toggle('btn-outline-secondary', !adjusting);
        b.innerHTML = adjusting ? '<i class="fas fa-check me-1"></i>{{ __('Done') }}' : '<i class="fas fa-arrows-alt me-1"></i>{{ __('Adjust blueprint') }}';
        if (adjusting) {
          deselect();
          planImg.moveTo(adjLayer); planImg.listening(true); planImg.draggable(true); planImg.opacity(0.75);
          imgTr = new Konva.Transformer({ rotateEnabled: false, keepRatio: false, enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'] });
          adjLayer.add(imgTr); imgTr.nodes([planImg]);
          planImg.on('dragend.adj transformend.adj', saveRect);
          adjLayer.draw();
        } else {
          if (imgTr) { imgTr.destroy(); imgTr = null; }
          planImg.off('.adj'); planImg.draggable(false); planImg.listening(false); planImg.opacity(0.55);
          planImg.moveTo(bg); bg.draw(); adjLayer.draw();
        }
      });
    })();
  })();
  </script>
@endsection
