@extends('theme::layouts.1col')

@section('title', 'Import Exchange')
@section('body-class', 'researcher import-exchange')

@section('content')
  {{-- Breadcrumb --}}
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('researcher.dashboard') }}">Researcher</a></li>
      <li class="breadcrumb-item active" aria-current="page">Import Exchange</li>
    </ol>
  </nav>

  <div class="d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-file-import me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Import Exchange') }}</h1>
      <span class="small text-muted">{{ __('Import data from an exchange JSON file') }}</span>
    </div>
  </div>

  {{-- Import result --}}
  @if($result)
    <div class="card border-success mb-4">
      <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Import Complete</h5>
      </div>
      <div class="card-body">
        <p>Your exchange file has been imported as a <strong>draft submission</strong>. Review the items and submit for archivist approval.</p>

        <div class="row g-3 mb-3">
          @if(($result['notes'] ?? 0) > 0)
            <div class="col-4 col-md-2 text-center">
              <h4 class="mb-0">{{ number_format($result['notes']) }}</h4>
              <small class="text-muted">{{ __('Notes') }}</small>
            </div>
          @endif
          @if(($result['files'] ?? 0) > 0)
            <div class="col-4 col-md-2 text-center">
              <h4 class="mb-0">{{ number_format($result['files']) }}</h4>
              <small class="text-muted">{{ __('File Items') }}</small>
            </div>
          @endif
          @if(($result['items'] ?? 0) > 0)
            <div class="col-4 col-md-2 text-center">
              <h4 class="mb-0">{{ number_format($result['items']) }}</h4>
              <small class="text-muted">{{ __('New Items') }}</small>
            </div>
          @endif
          @if(($result['creators'] ?? 0) > 0)
            <div class="col-4 col-md-2 text-center">
              <h4 class="mb-0">{{ number_format($result['creators']) }}</h4>
              <small class="text-muted">{{ __('Creators') }}</small>
            </div>
          @endif
          @if(($result['repos'] ?? 0) > 0)
            <div class="col-4 col-md-2 text-center">
              <h4 class="mb-0">{{ number_format($result['repos']) }}</h4>
              <small class="text-muted">{{ __('Repositories') }}</small>
            </div>
          @endif
          @if(($result['collections'] ?? 0) > 0)
            <div class="col-4 col-md-2 text-center">
              <h4 class="mb-0">{{ number_format($result['collections']) }}</h4>
              <small class="text-muted">{{ __('Collections') }}</small>
            </div>
          @endif
        </div>

        <a href="{{ route('researcher.submission.view', $result['submission_id']) }}" class="btn btn-success">
          <i class="fas fa-eye me-1"></i>{{ __('View Submission') }}
        </a>
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
          <h5 class="mb-0">{{ __('Upload Exchange File') }}</h5>
        </div>
        <div class="card-body">
          <div class="alert alert-info mb-3">
            <i class="fas fa-info-circle me-2"></i>
            <strong>{{ __('What is this?') }}</strong> When you use the Portable Export viewer in edit mode (offline),
            you can add notes, import files, create new items, creators, and repositories.
            The viewer exports a <code>researcher-exchange.json</code> file that you upload here.
            It becomes a draft submission for archivist review.
          </div>

          <form method="POST" action="{{ route('researcher.import.store') }}" enctype="multipart/form-data" id="importForm">
            @csrf

            <div class="mb-3">
              <label for="exchange_file" class="form-label fw-bold">Exchange File <span class="text-danger">*</span></label>
              <input type="file"
                     class="form-control @error('exchange_file') is-invalid @enderror"
                     id="exchange_file"
                     name="exchange_file"
                     accept=".json"
                     required>
              <small class="text-muted">Select a <code>researcher-exchange.json</code> file exported from the Portable Viewer.</small>
              @error('exchange_file')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="repository_id" class="form-label fw-bold">{{ __('Target Repository (optional)') }}</label>
              <select class="form-select @error('repository_id') is-invalid @enderror"
                      id="repository_id"
                      name="repository_id">
                <option value="">-- Auto-detect or leave unset --</option>
                @foreach($repositories as $repo)
                  <option value="{{ $repo['id'] }}" {{ old('repository_id') == $repo['id'] ? 'selected' : '' }}>
                    {{ $repo['name'] ?: '[Unnamed repository #' . $repo['id'] . ']' }}
                  </option>
                @endforeach
              </select>
              <small class="text-muted">{{ __('Override the target repository for all imported items.') }}</small>
              @error('repository_id')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            {{-- JSON preview area --}}
            <div id="jsonPreview" class="mb-3" style="display: none;">
              <label class="form-label"><i class="fas fa-eye me-2"></i>File Preview</label>
              <div class="card bg-light">
                <div class="card-body py-2">
                  <dl class="row mb-0" id="jsonPreviewContent">
                  </dl>
                </div>
              </div>
            </div>

            <hr>

            <div class="d-flex justify-content-between">
              <a href="{{ route('researcher.dashboard') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>{{ __('Cancel') }}
              </a>
              <button type="submit" class="btn btn-primary" id="importBtn">
                <i class="fas fa-upload me-1"></i>{{ __('Import') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <h6 class="mb-0"><i class="fas fa-list-check me-2"></i>Supported Collection Types</h6>
        </div>
        <div class="card-body small">
          <dl class="row mb-0">
            <dt class="col-4"><span class="badge bg-info">notes</span></dt>
            <dd class="col-8">Research notes attached to existing records</dd>

            <dt class="col-4"><span class="badge bg-secondary">files</span></dt>
            <dd class="col-8">Imported files with captions and metadata</dd>

            <dt class="col-4"><span class="badge bg-success">new_items</span></dt>
            <dd class="col-8">New descriptive records with hierarchy, access points (subjects, places, genre, creators), extent and media</dd>

            <dt class="col-4"><span class="badge bg-primary">new_creators</span></dt>
            <dd class="col-8">New creator/actor records (persons, organizations, families)</dd>

            <dt class="col-4"><span class="badge bg-warning text-dark">new_repositories</span></dt>
            <dd class="col-8">New repository/institution records</dd>
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
                    if (data.export_options) {
                        html += '<dt class="col-sm-5">Images Included</dt>';
                        html += '<dd class="col-sm-7">' + (data.export_options.include_images ? 'Yes' : 'No (data only)') + '</dd>';
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
