@extends('theme::layouts.1col')

@section('title', 'Edit 3D Model Settings')
@section('body-class', 'edit model3d')

@section('content')
  <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>

  {{-- Breadcrumb --}}
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
      <li class="breadcrumb-item"><a href="{{ route('admin.3d-models.index') }}">3D Models</a></li>
      <li class="breadcrumb-item"><a href="{{ route('admin.3d-models.view', $model->id) }}">{{ e($model->model_title ?: ($model->original_filename ?? '3D Model')) }}</a></li>
      <li class="breadcrumb-item active">Edit</li>
    </ol>
  </nav>

  <h1><i class="fas fa-edit me-2"></i>Edit 3D Model Settings</h1>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.3d-models.edit', $model->id) }}">
    @csrf
    <div class="row">
      <div class="col-md-8">
        {{-- Preview --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-eye me-2"></i>Preview
          </div>
          <div class="card-body p-0">
            <model-viewer
              id="preview-viewer"
              src="/uploads/{{ $model->file_path }}"
              alt="Preview"
              camera-controls
              touch-action="pan-y"
              auto-rotate
              rotation-per-second="{{ $model->rotation_speed ?? 30 }}deg"
              camera-orbit="{{ $model->camera_orbit ?? '0deg 75deg 105%' }}"
              field-of-view="{{ $model->field_of_view ?? '30deg' }}"
              exposure="{{ $model->exposure ?? 1 }}"
              shadow-intensity="{{ $model->shadow_intensity ?? 1 }}"
              style="width:100%; height:400px; background-color: {{ $model->background_color ?? '#f5f5f5' }};"
            ></model-viewer>
          </div>
        </div>

        {{-- Basic Info --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-info-circle me-2"></i>Basic Information
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="title" class="form-label">Title <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" id="title" name="title"
                     value="{{ old('title', $model->model_title ?? '') }}">
            </div>
            <div class="mb-3">
              <label for="description" class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $model->description ?? '') }}</textarea>
            </div>
            <div class="mb-3">
              <label for="alt_text" class="form-label">Alt Text (Accessibility) <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" id="alt_text" name="alt_text"
                     value="{{ old('alt_text', $model->alt_text ?? '') }}">
            </div>
          </div>
        </div>

        {{-- Viewer Settings --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-sliders-h me-2"></i>Viewer Settings
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="camera_orbit" class="form-label">Camera Orbit <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" class="form-control" id="camera_orbit" name="camera_orbit"
                         value="{{ old('camera_orbit', $model->camera_orbit ?? '0deg 75deg 105%') }}">
                  <div class="form-text">Format: "0deg 75deg 105%" (theta phi radius)</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="field_of_view" class="form-label">Field of View <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" class="form-control" id="field_of_view" name="field_of_view"
                         value="{{ old('field_of_view', $model->field_of_view ?? '30deg') }}">
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="exposure" class="form-label">Exposure <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="range" class="form-range" id="exposure" name="exposure"
                         min="0" max="2" step="0.1" value="{{ old('exposure', $model->exposure ?? 1) }}"
                         oninput="document.getElementById('exposure-val').textContent=this.value; updatePreview();">
                  <span id="exposure-val">{{ $model->exposure ?? 1 }}</span>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="shadow_intensity" class="form-label">Shadow Intensity <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="range" class="form-range" id="shadow_intensity" name="shadow_intensity"
                         min="0" max="2" step="0.1" value="{{ old('shadow_intensity', $model->shadow_intensity ?? 1) }}"
                         oninput="document.getElementById('shadow-val').textContent=this.value; updatePreview();">
                  <span id="shadow-val">{{ $model->shadow_intensity ?? 1 }}</span>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="rotation_speed" class="form-label">Rotation Speed (deg/sec) <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" class="form-control" id="rotation_speed" name="rotation_speed"
                         value="{{ old('rotation_speed', $model->rotation_speed ?? 30) }}" min="0" max="360" step="1">
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label for="background_color" class="form-label">Background Color <span class="badge bg-secondary ms-1">Optional</span></label>
              <div class="input-group" style="max-width:200px;">
                <input type="color" class="form-control form-control-color" id="bg_color_picker"
                       value="{{ old('background_color', $model->background_color ?? '#f5f5f5') }}"
                       onchange="document.getElementById('background_color').value=this.value; updatePreview();">
                <input type="text" class="form-control" id="background_color" name="background_color"
                       value="{{ old('background_color', $model->background_color ?? '#f5f5f5') }}">
              </div>
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="auto_rotate" name="auto_rotate" value="1"
                     {{ old('auto_rotate', $model->auto_rotate ?? false) ? 'checked' : '' }}>
              <label class="form-check-label" for="auto_rotate">Enable Auto-Rotate <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
          </div>
        </div>

        {{-- AR Settings --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-mobile-alt me-2"></i>Augmented Reality (AR)
          </div>
          <div class="card-body">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="ar_enabled" name="ar_enabled" value="1"
                     {{ old('ar_enabled', $model->ar_enabled ?? false) ? 'checked' : '' }}>
              <label class="form-check-label" for="ar_enabled">
                <strong>Enable AR Viewing</strong> <span class="badge bg-secondary ms-1">Optional</span>
                <br><small class="text-muted">Allow users to view this model in augmented reality on supported devices</small>
              </label>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="ar_scale" class="form-label">AR Scale <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select class="form-select" id="ar_scale" name="ar_scale">
                    <option value="auto" {{ ($model->ar_scale ?? 'auto') == 'auto' ? 'selected' : '' }}>Auto</option>
                    <option value="fixed" {{ ($model->ar_scale ?? '') == 'fixed' ? 'selected' : '' }}>Fixed</option>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="ar_placement" class="form-label">AR Placement <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select class="form-select" id="ar_placement" name="ar_placement">
                    <option value="floor" {{ ($model->ar_placement ?? 'floor') == 'floor' ? 'selected' : '' }}>Floor</option>
                    <option value="wall" {{ ($model->ar_placement ?? '') == 'wall' ? 'selected' : '' }}>Wall</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        {{-- Status --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-toggle-on me-2"></i>Status
          </div>
          <div class="card-body">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="is_primary" name="is_primary" value="1"
                     {{ old('is_primary', $model->is_primary ?? false) ? 'checked' : '' }}>
              <label class="form-check-label" for="is_primary"><strong>Primary Model</strong> <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="is_public" name="is_public" value="1"
                     {{ old('is_public', $model->is_public ?? true) ? 'checked' : '' }}>
              <label class="form-check-label" for="is_public"><strong>Public</strong> <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
          </div>
        </div>

        {{-- File Info --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
            <i class="fas fa-file me-2"></i>File Info
          </div>
          <div class="card-body">
            <table class="table table-sm mb-0">
              <tr><th>Filename</th><td>{{ e($model->original_filename ?? '') }}</td></tr>
              <tr><th>Format</th><td>{{ strtoupper($model->format ?? '') }}</td></tr>
              <tr><th>Size</th><td>{{ number_format(($model->file_size ?? 0) / 1048576, 2) }} MB</td></tr>
              <tr><th>Uploaded</th><td>{{ !empty($model->created_at) ? \Carbon\Carbon::parse($model->created_at)->format('M j, Y') : '-' }}</td></tr>
            </table>
          </div>
        </div>

        {{-- Hotspots --}}
        <div class="card mb-4">
          <div class="card-header fw-semibold d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff;">
            <span><i class="fas fa-map-marker-alt me-2"></i>Hotspots</span>
            <button type="button" class="btn btn-sm atom-btn-white" data-bs-toggle="modal" data-bs-target="#addHotspotModal">
              <i class="fas fa-plus"></i>
            </button>
          </div>
          <div class="card-body">
            @if(count($hotspots ?? []) === 0)
              <p class="text-muted mb-0 small">No hotspots defined.</p>
            @else
              <ul class="list-group list-group-flush">
                @foreach($hotspots as $hotspot)
                  <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                    <div>
                      <span class="badge" style="background-color:{{ $hotspot->color }};">{{ ucfirst($hotspot->hotspot_type) }}</span>
                      <small class="ms-1">{{ e($hotspot->hotspot_title ?: 'Untitled') }}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger delete-hotspot" data-id="{{ $hotspot->id }}">
                      <i class="fas fa-trash"></i>
                    </button>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>

        {{-- Danger Zone --}}
        <div class="card border-danger">
          <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
          </div>
          <div class="card-body">
            <p class="small text-muted">Permanently delete this 3D model and all associated data.</p>
            <form action="{{ route('admin.3d-models.delete', $model->id) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Are you sure you want to delete this 3D model? This cannot be undone.');">
              @csrf
              <button type="submit" class="btn atom-btn-outline-danger btn-sm">
                <i class="fas fa-trash me-1"></i>Delete Model
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <hr>

    <div class="d-flex justify-content-between">
      <a href="{{ route('admin.3d-models.view', $model->id) }}" class="btn atom-btn-white">
        <i class="fas fa-arrow-left me-1"></i>Cancel
      </a>
      <button type="submit" class="btn atom-btn-white">
        <i class="fas fa-save me-1"></i>Save Changes
      </button>
    </div>
  </form>

  {{-- Add Hotspot Modal --}}
  <div class="modal fade" id="addHotspotModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Hotspot</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted">Click on the 3D model to set the hotspot position, then fill in the details below.</p>
          <div class="mb-3">
            <label class="form-label">Type <span class="badge bg-danger ms-1">Required</span></label>
            <select class="form-select" id="hotspot_type">
              <option value="annotation">Annotation</option>
              <option value="info">Information</option>
              <option value="damage">Damage</option>
              <option value="detail">Detail</option>
              <option value="link">Link</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Title <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" class="form-control" id="hotspot_title">
          </div>
          <div class="mb-3">
            <label class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
            <textarea class="form-control" id="hotspot_description" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Position (X, Y, Z) <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="row g-2">
              <div class="col"><input type="number" class="form-control form-control-sm" id="hotspot_x" step="0.001" placeholder="X"></div>
              <div class="col"><input type="number" class="form-control form-control-sm" id="hotspot_y" step="0.001" placeholder="Y"></div>
              <div class="col"><input type="number" class="form-control form-control-sm" id="hotspot_z" step="0.001" placeholder="Z"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn atom-btn-white" id="saveHotspot">Add Hotspot</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
function updatePreview() {
    var viewer = document.getElementById('preview-viewer');
    viewer.exposure = document.getElementById('exposure').value;
    viewer.shadowIntensity = document.getElementById('shadow_intensity').value;
    viewer.style.backgroundColor = document.getElementById('background_color').value;
}

document.addEventListener('DOMContentLoaded', function() {
    var viewer = document.getElementById('preview-viewer');
    var modelId = {{ $model->id }};

    // Get position on click for hotspots
    viewer.addEventListener('click', function(event) {
        var rect = viewer.getBoundingClientRect();
        var x = event.clientX - rect.left;
        var y = event.clientY - rect.top;
        var hit = viewer.surfaceFromPoint(x, y);
        if (hit) {
            document.getElementById('hotspot_x').value = hit.x.toFixed(4);
            document.getElementById('hotspot_y').value = hit.y.toFixed(4);
            document.getElementById('hotspot_z').value = hit.z.toFixed(4);
        }
    });

    // Save hotspot
    document.getElementById('saveHotspot').addEventListener('click', function() {
        var data = {
            hotspot_type: document.getElementById('hotspot_type').value,
            title: document.getElementById('hotspot_title').value,
            description: document.getElementById('hotspot_description').value,
            position_x: parseFloat(document.getElementById('hotspot_x').value) || 0,
            position_y: parseFloat(document.getElementById('hotspot_y').value) || 0,
            position_z: parseFloat(document.getElementById('hotspot_z').value) || 0
        };
        fetch('{{ route("admin.3d-models.add-hotspot", $model->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(data)
        })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            if (result.success) { location.reload(); }
            else { alert('Error: ' + (result.error || 'Unknown error')); }
        });
    });

    // Delete hotspot
    document.querySelectorAll('.delete-hotspot').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (confirm('Delete this hotspot?')) {
                var hotspotId = this.dataset.id;
                fetch('/admin/3d-models/hotspot/' + hotspotId + '/delete', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                })
                .then(function(r) { return r.json(); })
                .then(function(result) {
                    if (result.success) { location.reload(); }
                });
            }
        });
    });
});
</script>
@endpush
