@extends('theme::layouts.1col')

@section('title', 'Upload File - Data Migration')
@section('body-class', 'admin data-migration upload')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-upload me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Upload File') }}</h1>
      <span class="small text-muted">{{ __('Data Migration') }}</span>
    </div>
  </div>

  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('data-migration.index') }}">{{ __('Data Migration') }}</a></li>
      <li class="breadcrumb-item active">{{ __('Upload') }}</li>
    </ol>
  </nav>

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"
         >
      <h5 class="mb-0"><i class="fas fa-cloud-upload-alt"></i> {{ __('Data Migration Tool') }}</h5>
      <div>
        <a href="{{ route('data-migration.batch-export') }}" class="btn btn-sm btn-outline-light me-2">
          <i class="fas fa-download me-1"></i>{{ __('Batch Export') }}
        </a>
        <a href="{{ route('data-migration.jobs') }}" class="btn btn-sm btn-outline-light">
          <i class="fas fa-tasks me-1"></i>{{ __('View Jobs') }}
        </a>
      </div>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('data-migration.upload') }}" enctype="multipart/form-data" id="uploadForm">
        @csrf

        {{-- Step 1: File Upload with Drag & Drop --}}
        <div class="mb-4">
          <h6 class="text-primary"><span class="badge bg-primary me-2">1</span>{{ __('Select File') }}</h6>
          <div class="border rounded p-4 bg-light text-center" id="dropZone" style="cursor:pointer">
            <input type="file" name="file" id="importFile" class="d-none"
                   accept=".csv,.txt,.xls,.xlsx">
            <div id="dropText">
              <p class="mb-2"><i class="fas fa-file-upload" style="font-size: 3rem;"></i></p>
              <p class="mb-2">{{ __('Drag & drop file here or') }} <a href="#" onclick="document.getElementById('importFile').click(); return false;">browse</a></p>
              <small class="text-muted">{{ __('Supported: CSV or Excel (XLS/XLSX), with a header row') }}</small>
            </div>
            <div id="fileInfo" class="d-none">
              <p class="mb-1"><strong id="fileName"></strong></p>
              <small class="text-muted" id="fileSize"></small>
              <br><a href="#" onclick="clearFile(); return false;" class="text-danger small">{{ __('Remove') }}</a>
            </div>
          </div>
        </div>

        {{-- Step 2: File Options (shown after file selected) --}}
        <div class="mb-4 d-none" id="fileOptions">
          <h6 class="text-primary"><span class="badge bg-primary me-2">2</span>{{ __('File Options') }}</h6>
          <div class="row g-3">
            {{-- Excel Sheet Selection (only for Excel files) --}}
            <div class="col-md-6 d-none" id="sheetSelectGroup">
              <label class="form-label">{{ __('Excel Sheet') }}</label>
              <select name="sheet_index" id="sheetSelect" class="form-select">
                <option value="0">{{ __('First sheet') }}</option>
              </select>
              <small class="text-muted">{{ __('Select which sheet to import') }}</small>
            </div>

            {{-- CSV Delimiter (only for CSV files) --}}
            <div class="col-md-6 d-none" id="delimiterGroup">
              <label class="form-label">{{ __('CSV Delimiter') }}</label>
              <select name="delimiter" id="delimiter" class="form-select">
                <option value="auto">{{ __('Auto-detect') }}</option>
                <option value=",">{{ __('Comma (,)') }}</option>
                <option value=";">{{ __('Semicolon (;)') }}</option>
                <option value="\t">{{ __('Tab') }}</option>
                <option value="|">{{ __('Pipe (|)') }}</option>
              </select>
            </div>

            {{-- Encoding --}}
            <div class="col-md-6">
              <label class="form-label">{{ __('File Encoding') }}</label>
              <select name="encoding" id="encoding" class="form-select">
                <option value="auto">{{ __('Auto-detect') }}</option>
                <option value="UTF-8">{{ __('UTF-8') }}</option>
                <option value="ISO-8859-1">{{ __('ISO-8859-1 (Latin-1)') }}</option>
                <option value="Windows-1252">{{ __('Windows-1252') }}</option>
              </select>
            </div>
          </div>
        </div>

        <div class="alert alert-info small py-2 mb-4">
          <i class="fas fa-info-circle me-1"></i>{{ __('This wizard imports the rows of a CSV or Excel file with a header row, mapped column by column, as archival descriptions, accessions, authority records or repositories. To import library, museum, gallery or digital-asset records, or files with digital objects, use the') }}
          <a href="{{ route('ingest.index') }}">{{ __('Ingest wizard') }}</a>.
        </div>

        {{-- Step 3: Import Target & Mapping --}}
        <div class="mb-4">
          <h6 class="text-primary"><span class="badge bg-primary me-2">3</span>{{ __('Import Target & Mapping') }}</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="target_type" class="form-label">{{ __('Target Record Type') }} <span class="text-danger">*</span></label>
              <select class="form-select @error('target_type') is-invalid @enderror" id="target_type" name="target_type" required>
                <option value="">-- Select target type --</option>
                <option value="archives" {{ old('target_type') === 'archives' ? 'selected' : '' }}>{{ __('Archives (ISAD-G)') }}</option>
                <option value="accession" {{ old('target_type') === 'accession' ? 'selected' : '' }}>{{ __('Accession Records') }}</option>
                <option value="actor" {{ old('target_type') === 'actor' ? 'selected' : '' }}>{{ __('Authority Records (ISAAR)') }}</option>
                <option value="repository" {{ old('target_type') === 'repository' ? 'selected' : '' }}>{{ __('Repositories (ISDIAH)') }}</option>
              </select>
              @error('target_type')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-md-6">
              <label for="saved_mapping" class="form-label">{{ __('Load Saved Mapping (Optional)') }}</label>
              <select name="saved_mapping" id="savedMapping" class="form-select">
                <option value="">-- None (map manually) --</option>
                @foreach($savedMappings as $mapping)
                  <option value="{{ $mapping['id'] }}">{{ htmlspecialchars($mapping['name']) }} ({{ $mapping['field_count'] ?? count(json_decode($mapping['field_mappings'] ?? '{}', true)) }} fields)</option>
                @endforeach
              </select>
              <small class="text-muted">{{ __('Pre-load field mappings from a saved template') }}</small>
            </div>
          </div>
        </div>

        {{-- Import Type --}}
        <div class="mb-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="import_type" class="form-label">{{ __('Import Type') }} <span class="text-danger">*</span></label>
              <select class="form-select @error('import_type') is-invalid @enderror" id="import_type" name="import_type" required>
                <option value="create" {{ old('import_type', 'create') === 'create' ? 'selected' : '' }}>{{ __('Create new records') }}</option>
                <option value="update" {{ old('import_type') === 'update' ? 'selected' : '' }}>{{ __('Match and update existing') }}</option>
                <option value="replace" {{ old('import_type') === 'replace' ? 'selected' : '' }}>{{ __('Replace in place') }}</option>
              </select>
              <div class="form-text">
                <strong>{{ __('Create new:') }}</strong> {{ __('All rows create new records.') }}<br>
                <strong>{{ __('Match and update:') }}</strong> {{ __('Match by identifier (authorities: identifier, then name) and fill in the non-blank cells.') }}<br>
                <strong>{{ __('Replace in place:') }}</strong> {{ __('Match the same way and overwrite every mapped field, so blank cells clear values. The record keeps its id, links and children.') }}
              </div>
              @error('import_type')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
        </div>

        {{-- Step 4: File Preview --}}
        <div class="mb-4 d-none" id="previewSection">
          <h6 class="text-primary"><span class="badge bg-primary me-2">4</span>{{ __('Preview') }}</h6>
          <div class="table-responsive border rounded" style="max-height: 200px; overflow: auto;">
            <table class="table table-sm table-striped mb-0" id="previewTable">
              <thead class="table-light sticky-top" id="previewHead"></thead>
              <tbody id="previewBody"></tbody>
            </table>
          </div>
          <small class="text-muted">{{ __('Showing first 5 rows') }}</small>
        </div>

        {{-- Submit --}}
        <div class="d-flex justify-content-between">
          <a href="{{ route('data-migration.index') }}" class="btn btn btn-outline-secondary">{{ __('Cancel') }}</a>
          <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" disabled>
            <i class="fas fa-arrow-right me-1"></i>{{ __('Continue to Field Mapping') }}
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Recent Imports --}}
  @if(isset($recentImports) && count($recentImports) > 0)
  <div class="card mt-4">
    <div class="card-header">
      <h6 class="mb-0">{{ __('Recent Imports') }}</h6>
    </div>
    <div class="list-group list-group-flush">
      @foreach($recentImports as $import)
        <div class="list-group-item d-flex justify-content-between align-items-center">
          <div>
            <strong>{{ htmlspecialchars($import['filename'] ?? $import->filename ?? '') }}</strong>
            <br><small class="text-muted">{{ $import['row_count'] ?? $import->row_count ?? 0 }} rows &bull; {{ isset($import['created_at']) ? \Carbon\Carbon::parse($import['created_at'])->format('Y-m-d H:i') : '' }}</small>
          </div>
          <span class="badge bg-{{ ($import['status'] ?? $import->status ?? '') === 'completed' ? 'success' : 'warning' }}">
            {{ $import['status'] ?? $import->status ?? 'queued' }}
          </span>
        </div>
      @endforeach
    </div>
  </div>
  @endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  var fileInput = document.getElementById('importFile');
  var dropZone = document.getElementById('dropZone');
  var submitBtn = document.getElementById('submitBtn');
  var currentFile = null;

  // Drag and drop
  dropZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    dropZone.classList.add('border-primary', 'bg-white');
  });

  dropZone.addEventListener('dragleave', function(e) {
    e.preventDefault();
    dropZone.classList.remove('border-primary', 'bg-white');
  });

  dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    dropZone.classList.remove('border-primary', 'bg-white');
    if (e.dataTransfer.files.length) {
      fileInput.files = e.dataTransfer.files;
      handleFileSelect(e.dataTransfer.files[0]);
    }
  });

  // Click to browse
  dropZone.addEventListener('click', function(e) {
    if (e.target.tagName !== 'A' && e.target.tagName !== 'INPUT') {
      fileInput.click();
    }
  });

  // File input change
  fileInput.addEventListener('change', function() {
    if (this.files.length) {
      handleFileSelect(this.files[0]);
    }
  });

  function handleFileSelect(file) {
    currentFile = file;
    var ext = file.name.split('.').pop().toLowerCase();

    // Show file info
    document.getElementById('dropText').classList.add('d-none');
    document.getElementById('fileInfo').classList.remove('d-none');
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = formatBytes(file.size);

    // Show file options
    document.getElementById('fileOptions').classList.remove('d-none');

    // Show/hide Excel sheet selector
    if (ext === 'xls' || ext === 'xlsx') {
      document.getElementById('sheetSelectGroup').classList.remove('d-none');
      document.getElementById('delimiterGroup').classList.add('d-none');
      loadSheets(file);
    } else if (ext === 'csv' || ext === 'txt') {
      document.getElementById('sheetSelectGroup').classList.add('d-none');
      document.getElementById('delimiterGroup').classList.remove('d-none');
    } else {
      document.getElementById('sheetSelectGroup').classList.add('d-none');
      document.getElementById('delimiterGroup').classList.add('d-none');
    }

    // Enable submit
    submitBtn.disabled = false;
  }

  // List the workbook's sheets so the right one is imported.
  function loadSheets(file) {
    var select = document.getElementById('sheetSelect');
    var data = new FormData();
    data.append('file', file);
    fetch('{{ route('data-migration.detect-sheets') }}', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
      body: data
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (!res.success || !res.sheets) return;
      select.innerHTML = '';
      res.sheets.forEach(function (sh) {
        var opt = document.createElement('option');
        opt.value = sh.index;
        opt.textContent = sh.name + ' (' + sh.rows + ' rows)';
        select.appendChild(opt);
      });
    })
    .catch(function () {});
  }

  window.clearFile = function() {
    fileInput.value = '';
    currentFile = null;
    document.getElementById('dropText').classList.remove('d-none');
    document.getElementById('fileInfo').classList.add('d-none');
    document.getElementById('fileOptions').classList.add('d-none');
    document.getElementById('previewSection').classList.add('d-none');
    submitBtn.disabled = true;
  };

  function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

});

</script>
@endpush
