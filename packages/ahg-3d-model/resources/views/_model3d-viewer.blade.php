{{--
  3D Model Partial - Include in digital object templates to display 3D models
  Usage: @include('ahg-3d-model::_model3d-viewer', ['resource' => $resource, 'models' => $models])

  Phase 1 (#666): animation toolbar - Play/Pause + animation-name dropdown
                  + scrub slider + time display. Visible only when the model
                  reports >= 1 animation at runtime.
  Phase 2 (#666): camera bookmarks - Save current view / Views dropdown /
                  Edit & Delete. Bookmarks come from
                  GET /3d/{model_id}/bookmarks, writes go to POST/PUT/DELETE
                  /3d/{model_id}/bookmarks[/id]. CSRF token via meta tag.
  Phase 3 (#666): share-view URL - encode current camera/anim state in the
                  hash so /the/url#3d-<id>=<base64> restores the exact pose.
  Phase 4 (#666): measurement tool (bi-rulers), cross-section overlay
                  (bi-scissors), and prefers-reduced-motion respect (suppress
                  autoplay + auto-rotate + skip smooth tween on hash restore).
                  Implementation lives in resources/js/heratio-3d-measure.js
                  and resources/js/heratio-3d-cross-section.js; inlined below.
--}}
@props(['models' => collect(), 'resource' => null])

@if($models->count())
<div class="model-3d-section mb-4">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="mb-0">
      <i class="fas fa-cube me-2"></i>3D Model{{ $models->count() > 1 ? 's' : '' }}
      <span class="badge bg-secondary">{{ $models->count() }}</span>
    </h4>
    @auth
      <a href="{{ $resource ? route('admin.3d-models.upload', $resource->id ?? 0) : route('admin.3d-models.browse') }}" class="btn btn-sm atom-btn-white">
        <i class="fas fa-plus me-1"></i>{{ __('Add 3D Model') }}
      </a>
    @endauth
  </div>

  @if($models->count() === 1)
    @php $model = $models->first(); @endphp
    @php
      $isSplat = in_array(strtolower($model->format ?? ''), ['splat', 'ply', 'splats']);
      $hasAnimations = ((int) ($model->animation_count ?? 0)) > 0;
    @endphp

    @if($isSplat)
      @include('ahg-3d-model::_splat-viewer', [
        'splatUrl' => '/uploads/' . ($model->file_path ?? ''),
        'height' => '500px',
        'title' => $model->model_title ?? $model->original_filename ?? 'Gaussian Splat',
      ])
    @else
      <div class="card mb-3">
        <div class="card-body p-0" style="height:500px;">
          <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>
          <model-viewer
            id="ahg-3d-viewer-{{ $model->id }}"
            data-model-id="{{ $model->id }}"
            src="/uploads/{{ $model->file_path }}"
            alt="{{ e($model->alt_text ?? $model->model_title ?? 'Model') }}"
            camera-controls
            touch-action="pan-y"
            data-auto-rotate-pref="{{ !empty($model->auto_rotate) ? '1' : '0' }}"
            {{-- Phase 4 (#666): auto-rotate + autoplay are dropped at runtime
                 when prefers-reduced-motion is set. The data-* attribute is the
                 source of truth; the viewer JS toggles auto-rotate after load. --}}
            @if(!empty($model->auto_rotate)) auto-rotate @endif
            @if(!empty($model->ar_enabled)) ar ar-modes="webxr scene-viewer quick-look" @endif
            rotation-per-second="{{ $model->rotation_speed ?? 30 }}deg"
            camera-orbit="{{ $model->camera_orbit ?? '0deg 75deg 105%' }}"
            field-of-view="{{ $model->field_of_view ?? '30deg' }}"
            exposure="{{ $model->exposure ?? 1 }}"
            shadow-intensity="{{ $model->shadow_intensity ?? 1 }}"
            style="width:100%; height:100%; background-color: {{ $model->background_color ?? '#f5f5f5' }};"
          >
            @if(!empty($model->ar_enabled))
              <button slot="ar-button" style="position:absolute;bottom:16px;left:16px;padding:8px 16px;border:none;border-radius:8px;background:var(--ahg-primary,#1a73e8);color:white;font-weight:500;cursor:pointer;">
                <i class="fas fa-cube"></i> {{ __('View in AR') }}
              </button>
            @endif
          </model-viewer>
        </div>
      </div>

      {{-- Phase 1 - animation playback toolbar.
           Hidden by default; shown by JS once we confirm availableAnimations.length >= 1. --}}
      <div class="ahg-3d-anim-toolbar d-none align-items-center gap-2 mb-2 p-2 bg-light rounded"
           data-target="ahg-3d-viewer-{{ $model->id }}"
           role="toolbar" aria-label="{{ __('3D animation controls') }}">
        <button type="button" class="btn btn-sm btn-primary ahg-3d-anim-play"
                title="{{ __('Play / Pause animation') }}"
                aria-label="{{ __('Play / Pause animation') }}">
          <i class="fas fa-play"></i>
        </button>
        <select class="form-select form-select-sm ahg-3d-anim-name d-none"
                style="max-width:200px" aria-label="{{ __('Animation') }}"></select>
        <input type="range" class="form-range ahg-3d-anim-scrub flex-grow-1"
               min="0" max="1000" step="1" value="0" aria-label="{{ __('Scrub timeline') }}">
        <small class="text-muted ahg-3d-anim-time" style="min-width:80px;text-align:right;">00:00 / 00:00</small>
      </div>

      {{-- Phase 2 - camera bookmark toolbar --}}
      <div class="ahg-3d-bm-toolbar d-flex flex-wrap align-items-center gap-2 mb-2"
           data-target="ahg-3d-viewer-{{ $model->id }}"
           data-model-id="{{ $model->id }}"
           data-list-url="{{ url('/3d/' . $model->id . '/bookmarks') }}"
           data-store-url="{{ url('/3d/' . $model->id . '/bookmarks') }}">
        <div class="dropdown">
          <button type="button" class="btn btn-sm atom-btn-white dropdown-toggle ahg-3d-bm-views"
                  data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-camera me-1"></i>{{ __('Views') }}
          </button>
          <ul class="dropdown-menu ahg-3d-bm-list">
            <li><span class="dropdown-item-text text-muted small">{{ __('Loading…') }}</span></li>
          </ul>
        </div>
        @auth
          <button type="button" class="btn btn-sm atom-btn-white ahg-3d-bm-save"
                  title="{{ __('Save the current view as a bookmark') }}">
            <i class="fas fa-bookmark me-1"></i>{{ __('Save current view') }}
          </button>
        @endauth
        {{-- Phase 3 - share-view URL: copies a URL with the current camera
             state (orbit + target + fov + animation) encoded in the hash. --}}
        <button type="button" class="btn btn-sm atom-btn-white ahg-3d-share-view"
                title="{{ __('Copy a link to this exact view') }}">
          <i class="fas fa-share-alt me-1"></i>{{ __('Share view') }}
        </button>
      </div>

      {{-- Phase 4 - measurement tool.
           Click Measure, then click two points on the model; a distance label
           appears between them. Esc cancels. Distance is in model units (metres
           for glTF/glb by default). --}}
      <div class="ahg-3d-measure-toolbar d-flex flex-wrap align-items-center gap-2 mb-2"
           data-target="ahg-3d-viewer-{{ $model->id }}"
           data-measure-units="m">
        <button type="button" class="btn btn-sm atom-btn-white ahg-3d-measure-toggle"
                aria-pressed="false"
                title="{{ __('Measure distance between two points (Esc to cancel)') }}">
          <i class="bi bi-rulers me-1"></i>{{ __('Measure') }}
        </button>
        <button type="button" class="btn btn-sm atom-btn-white ahg-3d-measure-clear"
                title="{{ __('Clear measurement') }}">
          <i class="bi bi-eraser me-1"></i>{{ __('Clear') }}
        </button>
        <small class="text-muted ahg-3d-measure-status" aria-live="polite"></small>
      </div>

      {{-- Phase 4 - cross-section view.
           Activates a slice overlay along the chosen axis; slider moves the
           slice along that axis. Esc deactivates and restores camera target. --}}
      <div class="ahg-3d-cross-toolbar d-flex flex-wrap align-items-center gap-2 mb-2"
           data-target="ahg-3d-viewer-{{ $model->id }}">
        <button type="button" class="btn btn-sm atom-btn-white ahg-3d-cross-toggle"
                aria-pressed="false"
                title="{{ __('Cross-section view (Esc to cancel)') }}">
          <i class="bi bi-scissors me-1"></i>{{ __('Cross-section') }}
        </button>
        <div class="ahg-3d-cross-controls d-none align-items-center gap-2 flex-grow-1">
          <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Slice axis') }}">
            <input type="radio" class="btn-check" name="ahg-3d-cross-axis-{{ $model->id }}" id="ahg-3d-cross-axis-{{ $model->id }}-x" value="x" checked>
            <label class="btn btn-outline-secondary" for="ahg-3d-cross-axis-{{ $model->id }}-x">X</label>
            <input type="radio" class="btn-check" name="ahg-3d-cross-axis-{{ $model->id }}" id="ahg-3d-cross-axis-{{ $model->id }}-y" value="y">
            <label class="btn btn-outline-secondary" for="ahg-3d-cross-axis-{{ $model->id }}-y">Y</label>
            <input type="radio" class="btn-check" name="ahg-3d-cross-axis-{{ $model->id }}" id="ahg-3d-cross-axis-{{ $model->id }}-z" value="z">
            <label class="btn btn-outline-secondary" for="ahg-3d-cross-axis-{{ $model->id }}-z">Z</label>
          </div>
          <input type="range" class="form-range ahg-3d-cross-slider flex-grow-1"
                 min="0" max="100" step="1" value="50"
                 aria-label="{{ __('Slice position') }}" style="max-width:240px;">
          <small class="text-muted ahg-3d-cross-value" aria-live="polite" style="min-width:120px;">X: +0.000 m</small>
        </div>
      </div>
    @endif

    <div class="mt-2">
      <small class="text-muted">
        {{ strtoupper($model->format ?? 'GLB') }} &bull;
        {{ number_format(($model->file_size ?? 0) / 1048576, 2) }} MB
        @if(!empty($model->ar_enabled) && !$isSplat)
          &bull; <span class="badge bg-success"><i class="fas fa-mobile-alt me-1"></i>{{ __('AR Ready') }}</span>
        @endif
        @if(!$isSplat && $hasAnimations)
          &bull; <span class="badge bg-info"><i class="fas fa-film me-1"></i>{{ trans_choice('{1} :count animation|[2,*] :count animations', $model->animation_count ?? 0, ['count' => (int) ($model->animation_count ?? 0)]) }}</span>
        @endif
      </small>
      @auth
        <div class="mt-1">
          <a href="{{ route('admin.3d-models.edit', $model->id) }}" class="btn btn-sm atom-btn-white">
            <i class="fas fa-cog me-1"></i>{{ __('Settings') }}
          </a>
        </div>
      @endauth
    </div>

  @else
    {{-- Multiple models: tab gallery --}}
    <ul class="nav nav-tabs" role="tablist">
      @foreach($models as $index => $model)
        <li class="nav-item" role="presentation">
          <button class="nav-link {{ $index === 0 ? 'active' : '' }}" data-bs-toggle="tab"
                  data-bs-target="#model3d-tab-{{ $model->id }}" type="button" role="tab">
            {{ $model->model_title ?: ($model->original_filename ?? 'Model ' . ($index + 1)) }}
            @if(!empty($model->is_primary))
              <span class="badge bg-primary ms-1">{{ __('Primary') }}</span>
            @endif
          </button>
        </li>
      @endforeach
    </ul>
    <div class="tab-content mt-2">
      @foreach($models as $index => $model)
        <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="model3d-tab-{{ $model->id }}" role="tabpanel">
          <div class="card">
            <div class="card-body p-0" style="height:500px;">
              <model-viewer
                id="ahg-3d-viewer-{{ $model->id }}"
                data-model-id="{{ $model->id }}"
                src="/uploads/{{ $model->file_path }}"
                alt="{{ e($model->alt_text ?? $model->model_title ?? 'Model') }}"
                camera-controls touch-action="pan-y"
                data-auto-rotate-pref="{{ !empty($model->auto_rotate) ? '1' : '0' }}"
                @if(!empty($model->auto_rotate)) auto-rotate @endif
                @if(!empty($model->ar_enabled)) ar ar-modes="webxr scene-viewer quick-look" @endif
                style="width:100%; height:100%; background-color: {{ $model->background_color ?? '#f5f5f5' }};"
              ></model-viewer>
            </div>
          </div>

          <div class="ahg-3d-anim-toolbar d-none align-items-center gap-2 mt-2 mb-2 p-2 bg-light rounded"
               data-target="ahg-3d-viewer-{{ $model->id }}"
               role="toolbar" aria-label="{{ __('3D animation controls') }}">
            <button type="button" class="btn btn-sm btn-primary ahg-3d-anim-play"
                    title="{{ __('Play / Pause animation') }}"
                    aria-label="{{ __('Play / Pause animation') }}">
              <i class="fas fa-play"></i>
            </button>
            <select class="form-select form-select-sm ahg-3d-anim-name d-none"
                    style="max-width:200px" aria-label="{{ __('Animation') }}"></select>
            <input type="range" class="form-range ahg-3d-anim-scrub flex-grow-1"
                   min="0" max="1000" step="1" value="0" aria-label="{{ __('Scrub timeline') }}">
            <small class="text-muted ahg-3d-anim-time" style="min-width:80px;text-align:right;">00:00 / 00:00</small>
          </div>

          <div class="ahg-3d-bm-toolbar d-flex flex-wrap align-items-center gap-2 mb-2"
               data-target="ahg-3d-viewer-{{ $model->id }}"
               data-model-id="{{ $model->id }}"
               data-list-url="{{ url('/3d/' . $model->id . '/bookmarks') }}"
               data-store-url="{{ url('/3d/' . $model->id . '/bookmarks') }}">
            <div class="dropdown">
              <button type="button" class="btn btn-sm atom-btn-white dropdown-toggle ahg-3d-bm-views"
                      data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-camera me-1"></i>{{ __('Views') }}
              </button>
              <ul class="dropdown-menu ahg-3d-bm-list">
                <li><span class="dropdown-item-text text-muted small">{{ __('Loading…') }}</span></li>
              </ul>
            </div>
            @auth
              <button type="button" class="btn btn-sm atom-btn-white ahg-3d-bm-save">
                <i class="fas fa-bookmark me-1"></i>{{ __('Save current view') }}
              </button>
            @endauth
            {{-- Phase 3 - share-view URL --}}
            <button type="button" class="btn btn-sm atom-btn-white ahg-3d-share-view"
                    title="{{ __('Copy a link to this exact view') }}">
              <i class="fas fa-share-alt me-1"></i>{{ __('Share view') }}
            </button>
          </div>

          {{-- Phase 4 - measurement + cross-section toolbars (tabbed branch) --}}
          <div class="ahg-3d-measure-toolbar d-flex flex-wrap align-items-center gap-2 mb-2"
               data-target="ahg-3d-viewer-{{ $model->id }}"
               data-measure-units="m">
            <button type="button" class="btn btn-sm atom-btn-white ahg-3d-measure-toggle"
                    aria-pressed="false"
                    title="{{ __('Measure distance between two points (Esc to cancel)') }}">
              <i class="bi bi-rulers me-1"></i>{{ __('Measure') }}
            </button>
            <button type="button" class="btn btn-sm atom-btn-white ahg-3d-measure-clear"
                    title="{{ __('Clear measurement') }}">
              <i class="bi bi-eraser me-1"></i>{{ __('Clear') }}
            </button>
            <small class="text-muted ahg-3d-measure-status" aria-live="polite"></small>
          </div>
          <div class="ahg-3d-cross-toolbar d-flex flex-wrap align-items-center gap-2 mb-2"
               data-target="ahg-3d-viewer-{{ $model->id }}">
            <button type="button" class="btn btn-sm atom-btn-white ahg-3d-cross-toggle"
                    aria-pressed="false"
                    title="{{ __('Cross-section view (Esc to cancel)') }}">
              <i class="bi bi-scissors me-1"></i>{{ __('Cross-section') }}
            </button>
            <div class="ahg-3d-cross-controls d-none align-items-center gap-2 flex-grow-1">
              <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Slice axis') }}">
                <input type="radio" class="btn-check" name="ahg-3d-cross-axis-{{ $model->id }}" id="ahg-3d-cross-axis-{{ $model->id }}-x" value="x" checked>
                <label class="btn btn-outline-secondary" for="ahg-3d-cross-axis-{{ $model->id }}-x">X</label>
                <input type="radio" class="btn-check" name="ahg-3d-cross-axis-{{ $model->id }}" id="ahg-3d-cross-axis-{{ $model->id }}-y" value="y">
                <label class="btn btn-outline-secondary" for="ahg-3d-cross-axis-{{ $model->id }}-y">Y</label>
                <input type="radio" class="btn-check" name="ahg-3d-cross-axis-{{ $model->id }}" id="ahg-3d-cross-axis-{{ $model->id }}-z" value="z">
                <label class="btn btn-outline-secondary" for="ahg-3d-cross-axis-{{ $model->id }}-z">Z</label>
              </div>
              <input type="range" class="form-range ahg-3d-cross-slider flex-grow-1"
                     min="0" max="100" step="1" value="50"
                     aria-label="{{ __('Slice position') }}" style="max-width:240px;">
              <small class="text-muted ahg-3d-cross-value" aria-live="polite" style="min-width:120px;">X: +0.000 m</small>
            </div>
          </div>

          <small class="text-muted mt-1 d-block">
            {{ strtoupper($model->format ?? 'GLB') }} &bull;
            {{ number_format(($model->file_size ?? 0) / 1048576, 2) }} MB
          </small>
        </div>
      @endforeach
    </div>
  @endif
</div>

{{-- Load model-viewer if not already loaded --}}
<script>
if (!customElements.get('model-viewer')) {
    var s = document.createElement('script');
    s.type = 'module';
    s.src = 'https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js';
    document.head.appendChild(s);
}
</script>

{{-- Phase 1 + Phase 2 controller. Idempotent - guarded by a single global flag. --}}
<script>
(function () {
  if (window.__ahg3dViewerInit) { return; }
  window.__ahg3dViewerInit = true;

  // Phase 4 (#666): honour prefers-reduced-motion. We disable autoplay,
  // auto-rotate, and snap-restore share-view instead of tweening.
  var REDUCED_MOTION = (function () {
    try {
      return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e) { return false; }
  })();
  window.__ahg3dReducedMotion = REDUCED_MOTION;

  function applyReducedMotion(viewer) {
    if (!REDUCED_MOTION || !viewer) return;
    try { viewer.removeAttribute('auto-rotate'); } catch (e) {}
    try { viewer.removeAttribute('autoplay'); } catch (e) {}
    try { viewer.autoRotate = false; } catch (e) {}
    try { viewer.autoplay = false; } catch (e) {}
    try { viewer.pause(); } catch (e) {}
  }

  function csrfToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  }

  function fmt(t) {
    if (!isFinite(t) || t < 0) t = 0;
    var m = Math.floor(t / 60), s = Math.floor(t % 60);
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // ----- Phase 1: animation toolbar -----
  function wireAnimToolbar(toolbar) {
    var viewerId = toolbar.getAttribute('data-target');
    var viewer = document.getElementById(viewerId);
    if (!viewer) return;
    var playBtn = toolbar.querySelector('.ahg-3d-anim-play');
    var nameSel = toolbar.querySelector('.ahg-3d-anim-name');
    var scrub = toolbar.querySelector('.ahg-3d-anim-scrub');
    var timeEl = toolbar.querySelector('.ahg-3d-anim-time');
    var userScrubbing = false;

    function show() { toolbar.classList.remove('d-none'); toolbar.classList.add('d-flex'); }
    function setPlayIcon(paused) {
      playBtn.innerHTML = paused
        ? '<i class="fas fa-play"></i>'
        : '<i class="fas fa-pause"></i>';
    }

    function init() {
      var anims = viewer.availableAnimations || [];
      if (!anims.length) { return; }
      show();

      if (anims.length >= 2) {
        nameSel.classList.remove('d-none');
        nameSel.innerHTML = anims.map(function (n) {
          return '<option value="' + escapeHtml(n) + '">' + escapeHtml(n) + '</option>';
        }).join('');
        nameSel.value = viewer.animationName || anims[0];
      }

      // Start paused - user-driven playback.
      try { viewer.pause(); } catch (e) {}
      setPlayIcon(true);
      timeEl.textContent = '00:00 / ' + fmt(viewer.duration || 0);
    }

    playBtn.addEventListener('click', function () {
      if (viewer.paused) {
        viewer.play();
        setPlayIcon(false);
      } else {
        viewer.pause();
        setPlayIcon(true);
      }
    });

    nameSel.addEventListener('change', function () {
      viewer.animationName = nameSel.value;
      // model-viewer rewinds on name change; reflect immediately.
      scrub.value = 0;
      timeEl.textContent = '00:00 / ' + fmt(viewer.duration || 0);
    });

    scrub.addEventListener('input', function () { userScrubbing = true; });
    scrub.addEventListener('change', function () {
      var dur = viewer.duration || 0;
      var pct = parseFloat(scrub.value) / 1000;
      try { viewer.currentTime = pct * dur; } catch (e) {}
      userScrubbing = false;
    });

    viewer.addEventListener('load', init);
    if (viewer.loaded) { init(); }

    viewer.addEventListener('play', function () { setPlayIcon(false); });
    viewer.addEventListener('pause', function () { setPlayIcon(true); });

    // model-viewer emits a continuous tick we can sample.
    function tick() {
      if (viewer && viewer.availableAnimations && viewer.availableAnimations.length) {
        var dur = viewer.duration || 0;
        var cur = viewer.currentTime || 0;
        if (!userScrubbing && dur > 0) {
          scrub.value = String(Math.min(1000, (cur / dur) * 1000));
        }
        timeEl.textContent = fmt(cur) + ' / ' + fmt(dur);
      }
      window.requestAnimationFrame(tick);
    }
    window.requestAnimationFrame(tick);
  }

  // ----- Phase 2: camera bookmark toolbar -----
  function wireBookmarkToolbar(toolbar) {
    var viewerId = toolbar.getAttribute('data-target');
    var viewer = document.getElementById(viewerId);
    if (!viewer) return;
    var listUrl = toolbar.getAttribute('data-list-url');
    var storeUrl = toolbar.getAttribute('data-store-url');
    var modelId = toolbar.getAttribute('data-model-id');
    var listEl = toolbar.querySelector('.ahg-3d-bm-list');
    var saveBtn = toolbar.querySelector('.ahg-3d-bm-save');
    var currentUserId = null;

    function applyBookmark(bm) {
      if (bm.camera_orbit) viewer.cameraOrbit = bm.camera_orbit;
      if (bm.camera_target) viewer.cameraTarget = bm.camera_target;
      if (bm.field_of_view) viewer.fieldOfView = bm.field_of_view;
    }

    function render(bookmarks) {
      if (!bookmarks.length) {
        listEl.innerHTML = '<li><span class="dropdown-item-text text-muted small">{{ __('No saved views yet') }}</span></li>';
        return;
      }
      var html = bookmarks.map(function (bm) {
        var label = escapeHtml(bm.name) + (bm.is_default ? ' <i class="fas fa-star text-warning ms-1"></i>' : '');
        var canEdit = (currentUserId !== null) &&
          ((bm.user_id !== null && bm.user_id === currentUserId) ||
           (bm.user_id === null /* shared - admin will be allowed by server */));
        var del = canEdit
          ? ' <button type="button" class="btn btn-link btn-sm p-0 ms-2 text-danger ahg-3d-bm-del" data-id="' + bm.id + '" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>'
          : '';
        return '<li><a href="#" class="dropdown-item ahg-3d-bm-apply" data-id="' + bm.id + '">' + label + del + '</a></li>';
      }).join('');
      listEl.innerHTML = html;
      // attach handlers
      Array.prototype.forEach.call(listEl.querySelectorAll('.ahg-3d-bm-apply'), function (a) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          var id = a.getAttribute('data-id');
          var hit = bookmarks.find(function (b) { return String(b.id) === String(id); });
          if (hit) applyBookmark(hit);
        });
      });
      Array.prototype.forEach.call(listEl.querySelectorAll('.ahg-3d-bm-del'), function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          var id = btn.getAttribute('data-id');
          if (!window.confirm('{{ __('Delete this saved view?') }}')) return;
          fetch(storeUrl + '/' + id, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
          }).then(function (r) { if (r.ok) load(); });
        });
      });
    }

    function load() {
      fetch(listUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : { bookmarks: [], current_user_id: null }; })
        .then(function (data) {
          currentUserId = data.current_user_id == null ? null : Number(data.current_user_id);
          render(data.bookmarks || []);
        })
        .catch(function () {
          listEl.innerHTML = '<li><span class="dropdown-item-text text-muted small">{{ __('Failed to load') }}</span></li>';
        });
    }

    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        var name = window.prompt('{{ __('Name this view') }}', 'View ' + new Date().toLocaleTimeString());
        if (!name) return;
        var payload = {
          name: name,
          camera_orbit: viewer.getCameraOrbit ? viewer.getCameraOrbit().toString() : (viewer.cameraOrbit || ''),
          camera_target: viewer.getCameraTarget ? viewer.getCameraTarget().toString() : (viewer.cameraTarget || ''),
          field_of_view: viewer.getFieldOfView ? viewer.getFieldOfView().toString() + 'deg' : (viewer.fieldOfView || '')
        };
        fetch(storeUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
          },
          body: JSON.stringify(payload)
        }).then(function (r) {
          if (r.status === 401) { alert('{{ __('Please sign in to save views.') }}'); return; }
          if (r.ok) load();
        });
      });
    }

    load();
  }

  // ----- Phase 3: share-view URL state -----
  // Encode current camera-orbit + camera-target + field-of-view + active
  // animation in URL hash; decode on load to restore the exact view a
  // shared link points at. Hash format: #3d-<modelId>=<base64-json>.
  function readState(viewer) {
    var state = {
      orbit:  viewer.getCameraOrbit ? viewer.getCameraOrbit().toString() : (viewer.cameraOrbit || ''),
      target: viewer.getCameraTarget ? viewer.getCameraTarget().toString() : (viewer.cameraTarget || ''),
      fov:    viewer.getFieldOfView ? (viewer.getFieldOfView().toString() + 'deg') : (viewer.fieldOfView || ''),
    };
    if (viewer.animationName) state.anim = viewer.animationName;
    return state;
  }
  function applyState(viewer, state) {
    if (!state) return;
    // Phase 4 (#666): under prefers-reduced-motion, snap to the pose instead
    // of running model-viewer's smooth camera tween. We temporarily zero
    // interpolationDecay (the default is ~50ms), apply, then restore so
    // subsequent user-driven moves still tween normally.
    var savedDecay = null;
    if (REDUCED_MOTION) {
      try { savedDecay = viewer.interpolationDecay; viewer.interpolationDecay = 0; } catch (e) {}
    }
    if (state.orbit)  viewer.cameraOrbit  = state.orbit;
    if (state.target) viewer.cameraTarget = state.target;
    if (state.fov)    viewer.fieldOfView  = state.fov;
    if (state.anim)   viewer.animationName = state.anim;
    if (REDUCED_MOTION && savedDecay !== null) {
      // Restore on next frame so the snap is committed first.
      window.requestAnimationFrame(function () {
        try { viewer.interpolationDecay = savedDecay; } catch (e) {}
      });
    }
  }
  function encodeHash(modelId, state) {
    try {
      // Strip empty keys so URL stays short
      var clean = {};
      Object.keys(state).forEach(function (k) { if (state[k]) clean[k] = state[k]; });
      var b64 = btoa(unescape(encodeURIComponent(JSON.stringify(clean))));
      return '#3d-' + modelId + '=' + b64;
    } catch (e) { return ''; }
  }
  function decodeHashForModel(modelId) {
    var h = (window.location.hash || '').replace(/^#/, '');
    if (!h) return null;
    var parts = h.split('&');
    for (var i = 0; i < parts.length; i++) {
      var kv = parts[i].split('=');
      if (kv[0] === '3d-' + modelId && kv[1]) {
        try { return JSON.parse(decodeURIComponent(escape(atob(kv[1])))); }
        catch (e) { return null; }
      }
    }
    return null;
  }
  function copyToClipboard(text, btn) {
    var done = function () {
      var orig = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-check me-1"></i>{{ __('Link copied') }}';
      btn.classList.add('btn-success');
      setTimeout(function () { btn.innerHTML = orig; btn.classList.remove('btn-success'); }, 1800);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () { window.prompt('{{ __('Copy this URL:') }}', text); });
    } else {
      window.prompt('{{ __('Copy this URL:') }}', text);
    }
  }
  function wireShareView(toolbar) {
    var viewerId = toolbar.getAttribute('data-target');
    var viewer = document.getElementById(viewerId);
    var modelId = toolbar.getAttribute('data-model-id');
    var btn = toolbar.querySelector('.ahg-3d-share-view');
    if (!viewer || !btn) return;
    // Restore-on-load if the URL already encodes state for this model
    var pending = decodeHashForModel(modelId);
    if (pending) {
      if (viewer.modelIsVisible) applyState(viewer, pending);
      else viewer.addEventListener('load', function () { applyState(viewer, pending); }, { once: true });
    }
    // Click handler: encode + copy
    btn.addEventListener('click', function () {
      var hash = encodeHash(modelId, readState(viewer));
      var url = window.location.origin + window.location.pathname + window.location.search + hash;
      // Reflect in the address bar so the user can see the encoded state
      try { history.replaceState(null, '', hash); } catch (e) {}
      copyToClipboard(url, btn);
    });
  }

  function init() {
    // Phase 4 (#666): suppress autoplay + auto-rotate on every viewer when
    // the user has prefers-reduced-motion. We run this before any toolbar
    // wiring so the animation toolbar sees the paused state and renders the
    // play icon (it pauses on init anyway, but this also strips the
    // auto-rotate attribute on the element itself for the share-view case).
    Array.prototype.forEach.call(
      document.querySelectorAll('model-viewer[data-model-id]'),
      function (viewer) {
        applyReducedMotion(viewer);
        if (viewer.loaded) { applyReducedMotion(viewer); }
        else { viewer.addEventListener('load', function () { applyReducedMotion(viewer); }, { once: true }); }
      }
    );
    Array.prototype.forEach.call(
      document.querySelectorAll('.ahg-3d-anim-toolbar'),
      wireAnimToolbar
    );
    Array.prototype.forEach.call(
      document.querySelectorAll('.ahg-3d-bm-toolbar'),
      wireBookmarkToolbar
    );
    Array.prototype.forEach.call(
      document.querySelectorAll('.ahg-3d-bm-toolbar'),
      wireShareView
    );
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

{{-- Phase 4 (#666): inline the measurement + cross-section JS so the package
     works without `php artisan vendor:publish`. The canonical source-of-truth
     stays in packages/ahg-3d-model/resources/js/heratio-3d-*.js - this just
     embeds the bytes at render time. Both scripts are idempotent IIFEs guarded
     by window.__ahg3dMeasureInit / window.__ahg3dCrossSectionInit, so embedding
     them inline multiple times on a page (one per model) is harmless. --}}
@php
    $ahg3dJsBase = dirname(__DIR__) . '/js';
    $ahg3dMeasureJs = @file_get_contents($ahg3dJsBase . '/heratio-3d-measure.js') ?: '';
    $ahg3dCrossJs   = @file_get_contents($ahg3dJsBase . '/heratio-3d-cross-section.js') ?: '';
@endphp
@if($ahg3dMeasureJs !== '')
<script id="ahg-3d-measure-inline">
{!! $ahg3dMeasureJs !!}
</script>
@endif
@if($ahg3dCrossJs !== '')
<script id="ahg-3d-cross-section-inline">
{!! $ahg3dCrossJs !!}
</script>
@endif
@endif
