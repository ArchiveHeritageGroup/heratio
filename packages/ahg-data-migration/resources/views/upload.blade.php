@extends('theme::layouts.1col')

@section('title', 'Upload File - Data Migration')
@section('body-class', 'admin data-migration upload')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-upload me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Upload File</h1>
      <span class="small text-muted">Data Migration</span>
    </div>
  </div>

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('data-migration.index') }}">Data Migration</a></li>
      <li class="breadcrumb-item active">Upload</li>
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
      <h5 class="mb-0"><i class="fas fa-cloud-upload-alt"></i> Data Migration Tool</h5>
      <div>
        <a href="{{ route('data-migration.batch-export') }}" class="btn btn-sm btn-outline-light me-2">
          <i class="fas fa-download me-1"></i>Batch Export
        </a>
        <a href="{{ route('data-migration.jobs') }}" class="btn btn-sm btn-outline-light">
          <i class="fas fa-tasks me-1"></i>View Jobs
        </a>
      </div>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('data-migration.upload') }}" enctype="multipart/form-data" id="uploadForm">
        @csrf

        {{-- Step 1: File Upload with Drag & Drop --}}
        <div class="mb-4">
          <h6 class="text-primary"><span class="badge bg-primary me-2">1</span>Select File</h6>
          <div class="border rounded p-4 bg-light text-center" id="dropZone" style="cursor:pointer">
            <input type="file" name="file" id="importFile" class="d-none"
                   accept=".csv,.xls,.xlsx,.xml,.json,.opex,.pax,.zip,.txt">
            <div id="dropText">
              <p class="mb-2"><i class="fas fa-file-upload" style="font-size: 3rem;"></i></p>
              <p class="mb-2">Drag & drop file here or <a href="#" onclick="document.getElementById('importFile').click(); return false;">browse</a></p>
              <small class="text-muted">Supported: CSV, Excel (XLS/XLSX), XML, JSON, OPEX, PAX, ZIP</small>
            </div>
            <div id="fileInfo" class="d-none">
              <p class="mb-1"><strong id="fileName"></strong></p>
              <small class="text-muted" id="fileSize"></small>
              <br><a href="#" onclick="clearFile(); return false;" class="text-danger small">Remove</a>
            </div>
          </div>
        </div>

        {{-- Step 2: File Options (shown after file selected) --}}
        <div class="mb-4 d-none" id="fileOptions">
          <h6 class="text-primary"><span class="badge bg-primary me-2">2</span>File Options</h6>
          <div class="row g-3">
            {{-- Excel Sheet Selection (only for Excel files) --}}
            <div class="col-md-6 d-none" id="sheetSelectGroup">
              <label class="form-label">Excel Sheet</label>
              <select name="sheet_index" id="sheetSelect" class="form-select">
                <option value="0">Loading sheets...</option>
              </select>
              <small class="text-muted">Select which sheet to import</small>
            </div>

            {{-- Header Row Option --}}
            <div class="col-md-6">
              <label class="form-label">First Row Contains</label>
              <select name="first_row_header" id="firstRowHeader" class="form-select">
                <option value="1" selected>Column Headers (skip first row)</option>
                <option value="0">Data (no headers - use column letters)</option>
              </select>
            </div>

            {{-- CSV Delimiter (only for CSV files) --}}
            <div class="col-md-6 d-none" id="delimiterGroup">
              <label class="form-label">CSV Delimiter</label>
              <select name="delimiter" id="delimiter" class="form-select">
                <option value="auto">Auto-detect</option>
                <option value=",">Comma (,)</option>
                <option value=";">Semicolon (;)</option>
                <option value="\t">Tab</option>
                <option value="|">Pipe (|)</option>
              </select>
            </div>

            {{-- Encoding --}}
            <div class="col-md-6">
              <label class="form-label">File Encoding</label>
              <select name="encoding" id="encoding" class="form-select">
                <option value="auto">Auto-detect</option>
                <option value="UTF-8">UTF-8</option>
                <option value="ISO-8859-1">ISO-8859-1 (Latin-1)</option>
                <option value="Windows-1252">Windows-1252</option>
              </select>
            </div>
          </div>
        </div>

        {{-- Step 2b: Digital Objects Location --}}
        <div class="mb-4 d-none" id="digitalObjectsSection">
          <h6 class="text-primary"><span class="badge bg-primary me-2">2b</span>Digital Objects Location (Optional)</h6>
          <div class="alert alert-info small py-2 mb-3">
            <i class="fas fa-info-circle me-1"></i>
            <strong>Note:</strong> Digital objects must be pre-uploaded to the server via FTP/SFTP before import.
            The browser cannot access files on your local PC for security reasons.
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">File Location</label>
              <select name="digital_object_source" id="digitalObjectSource" class="form-select">
                <option value="none">No digital objects to import</option>
                <option value="server">Files already on server (FTP/SFTP uploaded)</option>
                <option value="same">Same folder as import file</option>
              </select>
            </div>
            <div class="col-md-6 d-none" id="serverPathDiv">
              <label class="form-label">Server Folder Path</label>
              <select name="digital_object_folder" id="digitalObjectFolder" class="form-select">
                <option value="{{ config('heratio.uploads_path', storage_path('app/uploads')) }}/migration/">uploads/migration/</option>
                <option value="{{ config('heratio.uploads_path', storage_path('app/uploads')) }}/imports/">uploads/imports/</option>
                <option value="{{ config('heratio.uploads_path', storage_path('app/uploads')) }}/digital_objects/">uploads/digital_objects/</option>
                <option value="custom">Custom path...</option>
              </select>
            </div>
          </div>
          <div class="row g-3 mt-2 d-none" id="customPathRow">
            <div class="col-md-12">
              <label class="form-label">Custom Server Path</label>
              <input type="text" name="custom_digital_path" id="customDigitalPath" class="form-control"
                     placeholder="{{ config('heratio.uploads_path', storage_path('app/uploads')) }}/myfiles/">
              <small class="text-muted">Full server path where digital objects are stored</small>
            </div>
          </div>
          <div class="mt-2">
            <small class="text-muted">
              <i class="fab fa-windows me-1"></i><strong>Windows users:</strong> Use WinSCP, FileZilla, or similar to upload files to the server first.
            </small>
          </div>
        </div>

        {{-- Step 2c: Source Format --}}
        <div class="mb-4 d-none" id="sourceFormatSection">
          <h6 class="text-primary"><span class="badge bg-primary me-2">2c</span>Source Format</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Import From</label>
              <select name="source_format" id="sourceFormat" class="form-select">
                <option value="auto">Auto-detect from file</option>
                <optgroup label="Industry Standards">
                  <option value="preservica_opex">Preservica OPEX (folder/ZIP)</option>
                  <option value="preservica_xip">Preservica XIP/PAX</option>
                  <option value="archivesspace">ArchivesSpace Export</option>
                  <option value="vernon">Vernon CMS</option>
                  <option value="emu">EMu (Museum)</option>
                  <option value="pastperfect">PastPerfect</option>
                  <option value="collectiveaccess">CollectiveAccess</option>
                </optgroup>
                <optgroup label="Standard Formats">
                  <option value="ead">EAD 2002 (Archives)</option>
                  <option value="ead3">EAD3 (Archives)</option>
                  <option value="dc">Dublin Core XML</option>
                  <option value="mods">MODS (Library)</option>
                  <option value="marc">MARC XML (Library)</option>
                  <option value="lido">LIDO (Museum)</option>
                  <option value="spectrum">Spectrum CSV</option>
                </optgroup>
                <optgroup label="Generic">
                  <option value="generic_csv">Generic CSV</option>
                  <option value="generic_xml">Generic XML</option>
                </optgroup>
              </select>
            </div>
            <div class="col-md-6 d-none" id="folderSourceGroup">
              <label class="form-label">Or Select Server Folder</label>
              <div class="input-group">
                <input type="text" name="source_folder" id="sourceFolder" class="form-control"
                       placeholder="{{ config('heratio.uploads_path', storage_path('app/uploads')) }}/preservica_export/">
                <button type="button" class="btn btn-outline-secondary" onclick="browseServerFolder()">
                  <i class="fas fa-folder-open"></i>
                </button>
              </div>
              <small class="text-muted">For Preservica: point to extracted export folder</small>
            </div>
          </div>
        </div>

        {{-- Step 3: Import Target & Mapping --}}
        <div class="mb-4">
          <h6 class="text-primary"><span class="badge bg-primary me-2">3</span>Import Target & Mapping</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="target_type" class="form-label">Target Record Type <span class="text-danger">*</span></label>
              <select class="form-select @error('target_type') is-invalid @enderror" id="target_type" name="target_type" required>
                <option value="">-- Select target type --</option>
                <option value="archives" {{ old('target_type') === 'archives' ? 'selected' : '' }}>Archives (ISAD-G)</option>
                <option value="library" {{ old('target_type') === 'library' ? 'selected' : '' }}>Library</option>
                <option value="museum" {{ old('target_type') === 'museum' ? 'selected' : '' }}>Museum (Spectrum)</option>
                <option value="gallery" {{ old('target_type') === 'gallery' ? 'selected' : '' }}>Gallery (CCO)</option>
                <option value="dam" {{ old('target_type') === 'dam' ? 'selected' : '' }}>Digital Assets (DAM)</option>
                <option value="accession" {{ old('target_type') === 'accession' ? 'selected' : '' }}>Accession Records</option>
                <option value="actor" {{ old('target_type') === 'actor' ? 'selected' : '' }}>Authority Records (ISAAR)</option>
                <option value="repository" {{ old('target_type') === 'repository' ? 'selected' : '' }}>Repositories (ISDIAH)</option>
              </select>
              @error('target_type')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="col-md-6">
              <label for="saved_mapping" class="form-label">Load Saved Mapping (Optional)</label>
              <select name="saved_mapping" id="savedMapping" class="form-select">
                <option value="">-- None (map manually) --</option>
                @foreach($savedMappings as $mapping)
                  <option value="{{ $mapping['id'] }}">{{ htmlspecialchars($mapping['name']) }} ({{ $mapping['field_count'] ?? count(json_decode($mapping['field_mappings'] ?? '{}', true)) }} fields)</option>
                @endforeach
              </select>
              <small class="text-muted">Pre-load field mappings from a saved template</small>
            </div>
          </div>
        </div>

        {{-- Import Type --}}
        <div class="mb-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="import_type" class="form-label">Import Type <span class="text-danger">*</span></label>
              <select class="form-select @error('import_type') is-invalid @enderror" id="import_type" name="import_type" required>
                <option value="create" {{ old('import_type', 'create') === 'create' ? 'selected' : '' }}>Create new records</option>
                <option value="update" {{ old('import_type') === 'update' ? 'selected' : '' }}>Match and update existing</option>
                <option value="replace" {{ old('import_type') === 'replace' ? 'selected' : '' }}>Delete and replace</option>
              </select>
              <div class="form-text">
                <strong>Create new:</strong> All rows create new records.<br>
                <strong>Match and update:</strong> Match by identifier/name and update existing records.<br>
                <strong>Delete and replace:</strong> Delete matched records and re-create from CSV.
              </div>
              @error('import_type')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
        </div>

        {{-- Step 4: File Preview --}}
        <div class="mb-4 d-none" id="previewSection">
          <h6 class="text-primary"><span class="badge bg-primary me-2">4</span>Preview</h6>
          <div class="table-responsive border rounded" style="max-height: 200px; overflow: auto;">
            <table class="table table-sm table-striped mb-0" id="previewTable">
              <thead class="table-light sticky-top" id="previewHead"></thead>
              <tbody id="previewBody"></tbody>
            </table>
          </div>
          <small class="text-muted">Showing first 5 rows</small>
        </div>

        {{-- Submit --}}
        <div class="d-flex justify-content-between">
          <a href="{{ route('data-migration.index') }}" class="btn btn btn-outline-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" disabled>
            <i class="fas fa-arrow-right me-1"></i>Continue to Field Mapping
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Recent Imports --}}
  @if(isset($recentImports) && count($recentImports) > 0)
  <div class="card mt-4">
    <div class="card-header">
      <h6 class="mb-0">Recent Imports</h6>
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
    document.getElementById('digitalObjectsSection').classList.remove('d-none');
    document.getElementById('sourceFormatSection').classList.remove('d-none');

    // Show/hide Excel sheet selector
    if (ext === 'xls' || ext === 'xlsx') {
      document.getElementById('sheetSelectGroup').classList.remove('d-none');
      document.getElementById('delimiterGroup').classList.add('d-none');
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

  // Re-generate preview when header option changes
  document.getElementById('firstRowHeader').addEventListener('change', function() {
    if (currentFile) {
      // Preview re-generation would need server-side endpoint
    }
  });

  window.clearFile = function() {
    fileInput.value = '';
    currentFile = null;
    document.getElementById('dropText').classList.remove('d-none');
    document.getElementById('fileInfo').classList.add('d-none');
    document.getElementById('fileOptions').classList.add('d-none');
    document.getElementById('digitalObjectsSection').classList.add('d-none');
    document.getElementById('sourceFormatSection').classList.add('d-none');
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

  // Digital object source handling
  document.getElementById('digitalObjectSource').addEventListener('change', function() {
    var serverPathDiv = document.getElementById('serverPathDiv');
    var customPathRow = document.getElementById('customPathRow');
    if (this.value === 'server') {
      serverPathDiv.classList.remove('d-none');
    } else {
      serverPathDiv.classList.add('d-none');
      customPathRow.classList.add('d-none');
    }
  });

  // Show/hide custom path field
  document.getElementById('digitalObjectFolder').addEventListener('change', function() {
    var customPathRow = document.getElementById('customPathRow');
    if (this.value === 'custom') {
      customPathRow.classList.remove('d-none');
    } else {
      customPathRow.classList.add('d-none');
    }
  });

  // Source format handling
  document.getElementById('sourceFormat').addEventListener('change', function() {
    var format = this.value;
    var folderGroup = document.getElementById('folderSourceGroup');
    if (format === 'preservica_opex') {
      folderGroup.classList.remove('d-none');
    } else {
      folderGroup.classList.add('d-none');
    }
  });
});

// Browse server folder (placeholder)
function browseServerFolder() {
  alert('Server folder browser not yet implemented.\nPlease type the full path manually.');
}
</script>
@endpush
