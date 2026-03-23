{{--
  3D Model Partial - Include in digital object templates to display 3D models
  Usage: @include('ahg-3d-model::_model3d-viewer', ['resource' => $resource, 'models' => $models])
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
        <i class="fas fa-plus me-1"></i>Add 3D Model
      </a>
    @endauth
  </div>

  @if($models->count() === 1)
    @php $model = $models->first(); @endphp
    @php
      $isSplat = in_array(strtolower($model->format ?? ''), ['splat', 'ply', 'splats']);
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
            src="/uploads/{{ $model->file_path }}"
            alt="{{ e($model->alt_text ?? $model->model_title ?? 'Model') }}"
            camera-controls
            touch-action="pan-y"
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
                <i class="fas fa-cube"></i> View in AR
              </button>
            @endif
          </model-viewer>
        </div>
      </div>
    @endif

    <div class="mt-2">
      <small class="text-muted">
        {{ strtoupper($model->format ?? 'GLB') }} &bull;
        {{ number_format(($model->file_size ?? 0) / 1048576, 2) }} MB
        @if(!empty($model->ar_enabled) && !$isSplat)
          &bull; <span class="badge bg-success"><i class="fas fa-mobile-alt me-1"></i>AR Ready</span>
        @endif
      </small>
      @auth
        <div class="mt-1">
          <a href="{{ route('admin.3d-models.edit', $model->id) }}" class="btn btn-sm atom-btn-white">
            <i class="fas fa-cog me-1"></i>Settings
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
              <span class="badge bg-primary ms-1">Primary</span>
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
                src="/uploads/{{ $model->file_path }}"
                alt="{{ e($model->alt_text ?? $model->model_title ?? 'Model') }}"
                camera-controls touch-action="pan-y"
                @if(!empty($model->auto_rotate)) auto-rotate @endif
                @if(!empty($model->ar_enabled)) ar ar-modes="webxr scene-viewer quick-look" @endif
                style="width:100%; height:100%; background-color: {{ $model->background_color ?? '#f5f5f5' }};"
              ></model-viewer>
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
@endif
