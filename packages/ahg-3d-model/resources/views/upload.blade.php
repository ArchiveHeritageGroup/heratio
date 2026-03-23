@extends('theme::layouts.1col')

@section('title', 'Upload 3D Model')
@section('body-class', 'upload model3d')

@push('styles')
<style>
.upload-zone {
  border: 2px dashed #dee2e6; border-radius: 8px; padding: 40px; text-align: center;
  cursor: pointer; transition: border-color 0.2s, background-color 0.2s; position: relative;
}
.upload-zone:hover, .upload-zone.dragover {
  border-color: var(--ahg-primary, #1a73e8); background-color: #f8f9ff;
}
.upload-zone input[type="file"] {
  position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;
}
.upload-zone.has-file { border-color: #28a745; background-color: #f8fff8; }
.upload-zone.has-file .upload-content { display: none; }
.upload-zone.has-file .upload-preview { display: block !important; }
</style>
@endpush

@section('content')
  {{-- Breadcrumb --}}
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
      <li class="breadcrumb-item"><a href="{{ url($object->slug ?? '') }}">{{ e($object->title ?? 'Object') }}</a></li>
      <li class="breadcrumb-item active">Upload 3D Model</li>
    </ol>
  </nav>

  <h1><i class="fas fa-upload me-2"></i>Upload 3D Model</h1>
  <p class="text-muted">Add a 3D model to: <strong>{{ e($object->title ?? 'Object') }}</strong></p>

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <div class="card">
    <div class="card-body">
      <form method="POST" action="{{ route('admin.3d-models.upload', $object->id) }}" enctype="multipart/form-data" id="upload-form">
        @csrf
        <div class="row">
          <div class="col-md-8">
            {{-- File Upload --}}
            <div class="mb-4">
              <label class="form-label"><strong>3D Model File</strong> <span class="badge bg-danger ms-1">Required</span></label>
              <div class="upload-zone" id="upload-zone">
                <input type="file" name="model_file" id="model_file" accept=".glb,.gltf,.obj,.stl,.ply,.usdz" required>
                <div class="upload-content">
                  <i class="fas fa-cube fa-3x text-muted mb-3"></i>
                  <p class="mb-1">Drag and drop your 3D model here</p>
                  <p class="text-muted small mb-2">or click to browse</p>
                  <p class="text-muted small">
                    Supported formats: {{ strtoupper(implode(', ', $allowedFormats ?? ['GLB','GLTF','USDZ','OBJ','STL','PLY'])) }}
                  </p>
                </div>
                <div class="upload-preview" id="upload-preview" style="display:none;">
                  <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                  <p class="mb-0" id="file-name"></p>
                  <p class="text-muted small" id="file-size"></p>
                </div>
              </div>
            </div>

            {{-- Title --}}
            <div class="mb-3">
              <label for="title" class="form-label">Title <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" id="title" name="title"
                     value="{{ old('title') }}" placeholder="e.g., Bronze Statue - Front View">
              <div class="form-text">A descriptive title for this 3D model</div>
            </div>

            {{-- Description --}}
            <div class="mb-3">
              <label for="description" class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea class="form-control" id="description" name="description" rows="3"
                        placeholder="Describe the 3D model, its origin, scanning method, etc.">{{ old('description') }}</textarea>
            </div>

            {{-- Alt Text --}}
            <div class="mb-3">
              <label for="alt_text" class="form-label">Alt Text (Accessibility) <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" id="alt_text" name="alt_text"
                     value="{{ old('alt_text') }}" placeholder="A brief description for screen readers">
            </div>
          </div>

          <div class="col-md-4">
            {{-- Options --}}
            <div class="card bg-light">
              <div class="card-body">
                <h5 class="card-title mb-3">Options</h5>

                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="is_primary" name="is_primary" value="1">
                  <label class="form-check-label" for="is_primary">
                    <strong>Primary Model</strong> <span class="badge bg-secondary ms-1">Optional</span>
                    <br><small class="text-muted">Show this model first on the object page</small>
                  </label>
                </div>

                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="is_public" name="is_public" value="1" checked>
                  <label class="form-check-label" for="is_public">
                    <strong>Public</strong> <span class="badge bg-secondary ms-1">Optional</span>
                    <br><small class="text-muted">Make this model visible to all users</small>
                  </label>
                </div>

                <hr>

                <h6 class="mb-2">Supported Formats</h6>
                <ul class="list-unstyled small">
                  <li><strong>.glb</strong> - glTF Binary (recommended)</li>
                  <li><strong>.gltf</strong> - glTF JSON</li>
                  <li><strong>.usdz</strong> - Apple AR format</li>
                  <li><strong>.obj</strong> - Wavefront OBJ</li>
                  <li><strong>.stl</strong> - Stereolithography</li>
                  <li><strong>.ply</strong> - Polygon File Format</li>
                </ul>

                <div class="alert alert-info small mb-0">
                  <i class="fas fa-info-circle me-1"></i>
                  For best results, use <strong>GLB</strong> format with embedded textures.
                </div>
              </div>
            </div>
          </div>
        </div>

        <hr>

        <div class="d-flex justify-content-between">
          <a href="{{ url($object->slug ?? '/') }}" class="btn atom-btn-white">
            <i class="fas fa-arrow-left me-1"></i>Cancel
          </a>
          <button type="submit" class="btn atom-btn-white" id="submit-btn">
            <i class="fas fa-upload me-1"></i>Upload Model
          </button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var uploadZone = document.getElementById('upload-zone');
    var fileInput = document.getElementById('model_file');
    var fileName = document.getElementById('file-name');
    var fileSize = document.getElementById('file-size');
    var submitBtn = document.getElementById('submit-btn');

    ['dragenter', 'dragover'].forEach(function(eventName) {
        uploadZone.addEventListener(eventName, function(e) {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(function(eventName) {
        uploadZone.addEventListener(eventName, function(e) {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
        });
    });

    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            var file = this.files[0];
            fileName.textContent = file.name;
            var mb = (file.size / 1024 / 1024).toFixed(2);
            fileSize.textContent = mb + ' MB';
            uploadZone.classList.add('has-file');
        } else {
            uploadZone.classList.remove('has-file');
        }
    });

    document.getElementById('upload-form').addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Uploading...';
    });
});
</script>
@endpush
