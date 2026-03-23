@extends('theme::layouts.1col')

@section('title', ($model->model_title ?: ($model->original_filename ?? '3D Model')) . ' - 3D Model')
@section('body-class', 'show model3d')

@push('styles')
<style>
.model-viewer-wrapper model-viewer { --poster-color: transparent; }
.hotspot {
  display: block; width: 24px; height: 24px; border-radius: 50%;
  border: 2px solid white; background-color: var(--hotspot-color, var(--ahg-primary, #1a73e8));
  box-shadow: 0 2px 4px rgba(0,0,0,0.3); cursor: pointer; transition: transform 0.2s; padding: 0;
}
.hotspot:hover { transform: scale(1.2); }
.hotspot-annotation {
  display: none; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);
  background: white; padding: 12px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  min-width: 180px; max-width: 280px; text-align: left; z-index: 100; margin-bottom: 8px;
}
.hotspot:hover .hotspot-annotation, .hotspot:focus .hotspot-annotation { display: block; }
.hotspot-annotation strong { display: block; margin-bottom: 4px; color: #333; font-size: 0.95em; }
.hotspot-annotation p { margin: 0; font-size: 0.85em; color: #666; }
.viewer-controls { position: absolute; bottom: 16px; right: 16px; display: flex; gap: 8px; z-index: 10; }
.viewer-btn {
  width: 44px; height: 44px; border: none; border-radius: 8px;
  background: rgba(0,0,0,0.6); color: white; cursor: pointer; transition: background 0.2s; font-size: 1.1em;
}
.viewer-btn:hover { background: rgba(0,0,0,0.8); }
.viewer-btn.active { background: var(--ahg-primary, #1a73e8); }
.ar-button {
  position: absolute; bottom: 16px; left: 16px; padding: 8px 16px; border: none; border-radius: 8px;
  background: var(--ahg-primary, #1a73e8); color: white; font-weight: 500; cursor: pointer; z-index: 10;
}
.ar-button:hover { filter: brightness(0.85); }
.progress-bar { display: block; position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: rgba(0,0,0,0.1); }
.progress-bar .update-bar { height: 100%; background: var(--ahg-primary, #1a73e8); transition: width 0.1s; }
</style>
@endpush

@section('content')
  {{-- Include model-viewer --}}
  <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>

  {{-- Breadcrumb --}}
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
      @if($object ?? null)
        <li class="breadcrumb-item"><a href="{{ url($object->slug ?? '') }}">{{ e($object->title ?? '') }}</a></li>
      @endif
      <li class="breadcrumb-item active">3D Model</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1><i class="fas fa-cube me-2"></i>{{ e($model->model_title ?: ($model->original_filename ?? '3D Model')) }}</h1>
      <p class="text-muted mb-0">
        {{ strtoupper($model->format ?? '') }} &bull; {{ number_format(($model->file_size ?? 0) / 1048576, 2) }} MB
        @if(!empty($model->ar_enabled))
          <span class="badge bg-success ms-2"><i class="fas fa-mobile-alt me-1"></i>AR Ready</span>
        @endif
      </p>
    </div>
    <div>
      @auth
        <a href="{{ route('admin.3d-models.edit', $model->id) }}" class="btn atom-btn-white">
          <i class="fas fa-edit me-1"></i>Edit Settings
        </a>
      @endauth
      <a href="{{ route('admin.3d-models.index') }}" class="btn atom-btn-white ms-1">
        <i class="fas fa-list me-1"></i>All Models
      </a>
    </div>
  </div>

  @if(!empty($model->description))
    <div class="card mb-4">
      <div class="card-body">
        <p class="mb-0">{!! nl2br(e($model->description)) !!}</p>
      </div>
    </div>
  @endif

  {{-- 3D Viewer --}}
  <div class="card mb-4">
    <div class="card-body p-0">
      <div class="model-viewer-wrapper" style="height: 600px; position: relative;">
        <model-viewer
          id="main-viewer"
          src="/uploads/{{ $model->file_path }}"
          @if(!empty($model->poster_image)) poster="/uploads/{{ $model->poster_image }}" @endif
          alt="{{ e($model->alt_text ?: ($model->model_title ?? '3D Model')) }}"
          camera-controls
          touch-action="pan-y"
          @if(!empty($model->ar_enabled)) ar ar-modes="webxr scene-viewer quick-look" @endif
          @if(!empty($model->auto_rotate)) auto-rotate @endif
          rotation-per-second="{{ $model->rotation_speed ?? 30 }}deg"
          camera-orbit="{{ $model->camera_orbit ?? '0deg 75deg 105%' }}"
          field-of-view="{{ $model->field_of_view ?? '30deg' }}"
          exposure="{{ $model->exposure ?? 1 }}"
          shadow-intensity="{{ $model->shadow_intensity ?? 1 }}"
          shadow-softness="{{ $model->shadow_softness ?? 1 }}"
          @if(!empty($model->environment_image)) environment-image="/uploads/{{ $model->environment_image }}" @endif
          @if(!empty($model->skybox_image)) skybox-image="/uploads/{{ $model->skybox_image }}" @endif
          style="width: 100%; height: 100%; background-color: {{ $model->background_color ?? '#f5f5f5' }};"
        >
          {{-- Hotspots --}}
          @foreach($hotspots as $hotspot)
            <button class="hotspot"
                    slot="hotspot-{{ $hotspot->id }}"
                    data-position="{{ $hotspot->position_x }}m {{ $hotspot->position_y }}m {{ $hotspot->position_z }}m"
                    data-normal="{{ $hotspot->normal_x }}m {{ $hotspot->normal_y }}m {{ $hotspot->normal_z }}m"
                    data-type="{{ $hotspot->hotspot_type }}"
                    style="--hotspot-color: {{ $hotspot->color }};">
              <div class="hotspot-annotation">
                @if(!empty($hotspot->hotspot_title))
                  <strong>{{ e($hotspot->hotspot_title) }}</strong>
                @endif
                @if(!empty($hotspot->hotspot_description))
                  <p>{{ e($hotspot->hotspot_description) }}</p>
                @endif
                @if(!empty($hotspot->link_url))
                  <a href="{{ $hotspot->link_url }}" target="{{ $hotspot->link_target ?? '_blank' }}" class="btn btn-sm atom-btn-secondary mt-1">
                    <i class="fas fa-external-link-alt"></i> Learn More
                  </a>
                @endif
              </div>
            </button>
          @endforeach

          {{-- AR Button --}}
          @if(!empty($model->ar_enabled))
            <button slot="ar-button" class="ar-button">
              <i class="fas fa-cube"></i> View in AR
            </button>
          @endif

          {{-- Progress bar --}}
          <div class="progress-bar" slot="progress-bar">
            <div class="update-bar"></div>
          </div>
        </model-viewer>

        {{-- Control buttons --}}
        <div class="viewer-controls">
          <button id="btn-fullscreen" class="viewer-btn" title="Fullscreen">
            <i class="fas fa-expand"></i>
          </button>
          <button id="btn-rotate" class="viewer-btn" title="Toggle Auto-Rotate">
            <i class="fas fa-sync-alt"></i>
          </button>
          <button id="btn-reset" class="viewer-btn" title="Reset Camera">
            <i class="fas fa-undo"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- Model Info --}}
  <div class="row">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
          <i class="fas fa-info-circle me-2"></i>Model Information
        </div>
        <div class="card-body">
          <table class="table table-sm mb-0">
            <tr><th width="40%">Format</th><td>{{ strtoupper($model->format ?? '') }}</td></tr>
            <tr><th>File Size</th><td>{{ number_format(($model->file_size ?? 0) / 1048576, 2) }} MB</td></tr>
            @if(!empty($model->vertex_count))
              <tr><th>Vertices</th><td>{{ number_format($model->vertex_count) }}</td></tr>
            @endif
            @if(!empty($model->face_count))
              <tr><th>Faces</th><td>{{ number_format($model->face_count) }}</td></tr>
            @endif
            @if(!empty($model->texture_count))
              <tr><th>Textures</th><td>{{ $model->texture_count }}</td></tr>
            @endif
            @if(!empty($model->animation_count))
              <tr><th>Animations</th><td>{{ $model->animation_count }}</td></tr>
            @endif
            <tr>
              <th>AR Enabled</th>
              <td>{!! !empty($model->ar_enabled) ? '<span class="text-success">Yes</span>' : '<span class="text-muted">No</span>' !!}</td>
            </tr>
            <tr><th>Uploaded</th><td>{{ !empty($model->created_at) ? \Carbon\Carbon::parse($model->created_at)->format('M j, Y') : '-' }}</td></tr>
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
          <i class="fas fa-map-marker-alt me-2"></i>Hotspots ({{ count($hotspots) }})
        </div>
        <div class="card-body">
          @if(count($hotspots) === 0)
            <p class="text-muted mb-0">No hotspots defined for this model.</p>
          @else
            <ul class="list-group list-group-flush">
              @foreach($hotspots as $hotspot)
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                  <div>
                    <span class="badge me-2" style="background-color: {{ $hotspot->color }}">
                      {{ ucfirst($hotspot->hotspot_type) }}
                    </span>
                    {{ e($hotspot->hotspot_title ?: 'Untitled') }}
                  </div>
                  <button class="btn btn-sm atom-btn-white focus-hotspot" data-id="{{ $hotspot->id }}">
                    <i class="fas fa-eye"></i>
                  </button>
                </li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- IIIF Info --}}
  <div class="card mt-4">
    <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
      <i class="fas fa-link me-2"></i>IIIF 3D Manifest
    </div>
    <div class="card-body">
      <p>Access the IIIF 3D manifest for this model:</p>
      <div class="input-group">
        <input type="text" class="form-control" id="manifest-url" readonly
               value="{{ url('/iiif/3d/' . $model->id . '/manifest.json') }}">
        <button class="btn atom-btn-white" type="button" onclick="copyManifestUrl()">
          <i class="fas fa-copy"></i> Copy
        </button>
        <a href="{{ url('/iiif/3d/' . $model->id . '/manifest.json') }}" target="_blank" class="btn atom-btn-white">
          <i class="fas fa-external-link-alt"></i> View
        </a>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var viewer = document.getElementById('main-viewer');

    // Fullscreen
    document.getElementById('btn-fullscreen').addEventListener('click', function() {
        var wrapper = document.querySelector('.model-viewer-wrapper');
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            wrapper.requestFullscreen();
        }
    });

    // Toggle auto-rotate
    document.getElementById('btn-rotate').addEventListener('click', function() {
        if (viewer.hasAttribute('auto-rotate')) {
            viewer.removeAttribute('auto-rotate');
            this.classList.remove('active');
        } else {
            viewer.setAttribute('auto-rotate', '');
            this.classList.add('active');
        }
    });

    // Reset camera
    document.getElementById('btn-reset').addEventListener('click', function() {
        viewer.cameraOrbit = '{{ $model->camera_orbit ?? "0deg 75deg 105%" }}';
        viewer.fieldOfView = '{{ $model->field_of_view ?? "30deg" }}';
    });

    // Focus hotspot
    document.querySelectorAll('.focus-hotspot').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var hotspotId = this.dataset.id;
            var hotspot = viewer.querySelector('[slot="hotspot-' + hotspotId + '"]');
            if (hotspot) {
                hotspot.focus();
            }
        });
    });
});

function copyManifestUrl() {
    var input = document.getElementById('manifest-url');
    input.select();
    document.execCommand('copy');
    var btn = input.nextElementSibling;
    var originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    setTimeout(function() { btn.innerHTML = originalHtml; }, 2000);
}
</script>
@endpush
