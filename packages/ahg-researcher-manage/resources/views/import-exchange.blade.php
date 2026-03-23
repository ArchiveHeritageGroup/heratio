@extends('theme::layouts.1col')

@section('title', 'Import Exchange')
@section('body-class', 'researcher import-exchange')

@section('content')
  {{-- Breadcrumb --}}
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('researcher.dashboard') }}">Researcher</a></li>
      <li class="breadcrumb-item active" aria-current="page">Import Exchange</li>
    </ol>
  </nav>

  <div class="d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-file-import me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Import Exchange</h1>
      <span class="small text-muted">Import data from an exchange JSON file</span>
    </div>
  </div>

  {{-- Import result --}}
  @if($result)
    <div class="card border-success mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-check-circle me-1"></i> Import Successful
      </div>
      <div class="card-body">
        <p class="mb-2">
          Submission <strong>"{{ $result['title'] }}"</strong> created as draft
          (ID: {{ $result['submission_id'] }}).
        </p>
        <div class="row g-3">
          <div class="col-6 col-md-4 col-lg-2">
            <div class="text-center">
              <div class="fs-4 fw-bold">{{ number_format($result['notes']) }}</div>
              <div class="small text-muted">Notes</div>
            </div>
          </div>
          <div class="col-6 col-md-4 col-lg-2">
            <div class="text-center">
              <div class="fs-4 fw-bold">{{ number_format($result['files']) }}</div>
              <div class="small text-muted">Files</div>
            </div>
          </div>
          <div class="col-6 col-md-4 col-lg-2">
            <div class="text-center">
              <div class="fs-4 fw-bold">{{ number_format($result['items']) }}</div>
              <div class="small text-muted">Items</div>
            </div>
          </div>
          <div class="col-6 col-md-4 col-lg-2">
            <div class="text-center">
              <div class="fs-4 fw-bold">{{ number_format($result['creators']) }}</div>
              <div class="small text-muted">Creators</div>
            </div>
          </div>
          <div class="col-6 col-md-4 col-lg-2">
            <div class="text-center">
              <div class="fs-4 fw-bold">{{ number_format($result['repos']) }}</div>
              <div class="small text-muted">Repositories</div>
            </div>
          </div>
          <div class="col-6 col-md-4 col-lg-2">
            <div class="text-center">
              <div class="fs-4 fw-bold">{{ number_format($result['collections']) }}</div>
              <div class="small text-muted">Collections</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger mb-4">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="row">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">Upload Exchange File</h5>
        </div>
        <div class="card-body">
          <div class="alert alert-info mb-3">
            <i class="fas fa-info-circle me-1"></i>
            Upload a JSON exchange file to create a new draft submission. The file will be parsed
            and its contents will be used to populate the submission. You can review and edit the
            submission before finalising it.
          </div>

          <form method="POST" action="{{ route('researcher.import.store') }}" enctype="multipart/form-data" id="importForm">
            @csrf

            <div class="mb-3">
              <label for="exchange_file" class="form-label">Exchange File (.json) <span class="badge bg-danger ms-1">Required</span></label>
              <input type="file"
                     class="form-control @error('exchange_file') is-invalid @enderror"
                     id="exchange_file"
                     name="exchange_file"
                     accept=".json"
                     required>
              @error('exchange_file')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="repository_id" class="form-label">Target Repository <span class="badge bg-secondary ms-1">Optional</span></label>
              <select class="form-select @error('repository_id') is-invalid @enderror"
                      id="repository_id"
                      name="repository_id">
                <option value="">-- Select a repository (optional) --</option>
                @foreach($repositories as $repo)
                  <option value="{{ $repo['id'] }}" {{ old('repository_id') == $repo['id'] ? 'selected' : '' }}>
                    {{ $repo['name'] ?: '[Unnamed repository #' . $repo['id'] . ']' }}
                  </option>
                @endforeach
              </select>
              @error('repository_id')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            {{-- JSON preview area --}}
            <div id="jsonPreview" class="mb-3" style="display: none;">
              <label class="form-label">File Preview <span class="badge bg-secondary ms-1">Optional</span></label>
              <div class="card bg-light">
                <div class="card-body py-2">
                  <dl class="row mb-0" id="jsonPreviewContent">
                  </dl>
                </div>
              </div>
            </div>

            <div class="d-flex gap-2">
              <a href="{{ route('researcher.dashboard') }}" class="btn atom-btn-white">Cancel</a>
              <button type="submit" class="btn atom-btn-outline-success" id="importBtn">
                <i class="fas fa-file-import me-1"></i> Import
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">Supported Types</h5>
        </div>
        <div class="card-body">
          <dl class="mb-0">
            <dt>
              <span class="badge bg-info me-1">notes</span>
              Research Notes
            </dt>
            <dd class="text-muted small mb-2">Transcriptions, annotations, and research notes attached to archival descriptions.</dd>

            <dt>
              <span class="badge bg-success me-1">files</span>
              Digital Files
            </dt>
            <dd class="text-muted small mb-2">File references and metadata for digital objects (images, documents, audio, video).</dd>

            <dt>
              <span class="badge bg-primary me-1">new_items</span>
              New Items
            </dt>
            <dd class="text-muted small mb-2">New information objects (archival descriptions) to be created in the repository.</dd>

            <dt>
              <span class="badge bg-warning text-dark me-1">new_creators</span>
              New Creators
            </dt>
            <dd class="text-muted small mb-2">New authority records (persons, families, corporate bodies) referenced in the data.</dd>

            <dt>
              <span class="badge bg-danger me-1">new_repositories</span>
              New Repositories
            </dt>
            <dd class="text-muted small mb-2">New archival institutions referenced in the exchange data.</dd>
          </dl>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var fileInput = document.getElementById('exchange_file');
    var preview = document.getElementById('jsonPreview');
    var previewContent = document.getElementById('jsonPreviewContent');

    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (!file) {
                preview.style.display = 'none';
                return;
            }

            var reader = new FileReader();
            reader.onload = function(ev) {
                try {
                    var data = JSON.parse(ev.target.result);
                    var html = '';

                    if (data.format_version) {
                        html += '<dt class="col-sm-5">Format Version</dt>';
                        html += '<dd class="col-sm-7">' + escapeHtml(String(data.format_version)) + '</dd>';
                    }
                    if (data.source) {
                        html += '<dt class="col-sm-5">Source</dt>';
                        html += '<dd class="col-sm-7">' + escapeHtml(String(data.source)) + '</dd>';
                    }
                    if (data.exported_at) {
                        html += '<dt class="col-sm-5">Exported At</dt>';
                        html += '<dd class="col-sm-7">' + escapeHtml(String(data.exported_at)) + '</dd>';
                    }
                    if (data.title) {
                        html += '<dt class="col-sm-5">Title</dt>';
                        html += '<dd class="col-sm-7">' + escapeHtml(String(data.title)) + '</dd>';
                    }

                    // Collections breakdown
                    if (data.collections && Array.isArray(data.collections)) {
                        html += '<dt class="col-sm-5">Collections</dt>';
                        html += '<dd class="col-sm-7">' + data.collections.length + ' collection(s)</dd>';
                        data.collections.forEach(function(coll, idx) {
                            var label = coll.title || coll.name || ('Collection ' + (idx + 1));
                            var itemCount = coll.items ? (Array.isArray(coll.items) ? coll.items.length : coll.items) : 0;
                            html += '<dt class="col-sm-5 text-muted ps-3">' + escapeHtml(label) + '</dt>';
                            html += '<dd class="col-sm-7 text-muted">' + itemCount + ' item(s)</dd>';
                        });
                    }

                    // Summary counts
                    var counts = ['notes', 'files', 'new_items', 'new_creators', 'new_repositories'];
                    counts.forEach(function(key) {
                        if (data[key] && Array.isArray(data[key]) && data[key].length > 0) {
                            html += '<dt class="col-sm-5">' + key.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); }) + '</dt>';
                            html += '<dd class="col-sm-7">' + data[key].length + '</dd>';
                        }
                    });

                    if (html) {
                        previewContent.innerHTML = html;
                        preview.style.display = 'block';
                    } else {
                        previewContent.innerHTML = '<dt class="col-12 text-muted">No recognizable exchange data found in this file.</dt>';
                        preview.style.display = 'block';
                    }
                } catch (err) {
                    previewContent.innerHTML = '<dt class="col-12 text-danger">Invalid JSON: ' + escapeHtml(err.message) + '</dt>';
                    preview.style.display = 'block';
                }
            };
            reader.readAsText(file);
        });
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
});
</script>
@endpush
