@extends('theme::layouts.1col')

@section('title', 'Merge Files to PDF')

@section('content')

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">Merge Files to PDF</h5>
    <a href="{{ route('pdf-tools.index') }}" class="btn atom-btn-white btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
  <div class="card-body">

    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if(!$imageMagickAvailable)
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>ImageMagick Not Available</strong>
        <p class="mb-0 mt-2">ImageMagick is required for file merging. Install with: <code>sudo apt install imagemagick</code></p>
      </div>
    @else

    <form action="{{ route('pdf-tools.merge') }}" method="POST" enctype="multipart/form-data" id="mergeForm">
      @csrf

      {{-- File Upload --}}
      <div class="mb-4">
        <label class="form-label fw-bold">Files to Merge <span class="badge bg-danger ms-1">Required</span></label>
        <input type="file" class="form-control" name="files[]" multiple required
               accept=".{{ implode(',.', $formats) }},.pdf"
               id="fileInput">
        <div class="form-text">
          Supported formats: {{ strtoupper(implode(', ', $formats)) }}, PDF. Maximum 100 MB per file.
          Select files in the order you want them merged.
        </div>

        @error('files')
          <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
        @error('files.*')
          <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
      </div>

      {{-- Selected Files Preview --}}
      <div class="mb-4 d-none" id="fileList">
        <label class="form-label fw-bold">Selected Files <span class="badge bg-secondary ms-1">Optional</span></label>
        <ul class="list-group list-group-flush" id="fileListItems"></ul>
      </div>

      <hr>

      {{-- Options --}}
      <div class="row g-4">
        {{-- Quality --}}
        <div class="col-md-6">
          <label class="form-label fw-bold" for="quality">Quality <span class="badge bg-secondary ms-1">Optional</span></label>
          <div class="d-flex align-items-center gap-3">
            <input type="range" class="form-range flex-grow-1" id="quality" name="quality"
                   min="0" max="100" value="90" step="5">
            <span class="badge bg-secondary" id="qualityValue">90</span>
          </div>
          <div class="form-text">JPEG compression quality (0 = lowest, 100 = highest)</div>
        </div>

        {{-- DPI --}}
        <div class="col-md-6">
          <label class="form-label fw-bold" for="dpi">DPI (Resolution) <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="number" class="form-control" id="dpi" name="dpi"
                 min="72" max="600" value="150" step="1">
          <div class="form-text">Output resolution in dots per inch (72-600)</div>

          @error('dpi')
            <div class="text-danger small mt-1">{{ $message }}</div>
          @enderror
        </div>

        {{-- Page Size --}}
        <div class="col-md-6">
          <label class="form-label fw-bold" for="page_size">Page Size <span class="badge bg-secondary ms-1">Optional</span></label>
          <select class="form-select" id="page_size" name="page_size">
            @foreach($pageSizes as $size)
              <option value="{{ $size }}" {{ $size === 'a4' ? 'selected' : '' }}>
                {{ strtoupper($size) }}
              </option>
            @endforeach
          </select>
        </div>

        {{-- Orientation --}}
        <div class="col-md-6">
          <label class="form-label fw-bold">Orientation <span class="badge bg-secondary ms-1">Optional</span></label>
          <div class="d-flex gap-3 mt-1">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="orientation" id="orientPortrait" value="portrait" checked>
              <label class="form-check-label" for="orientPortrait">
                <i class="bi bi-phone me-1"></i>Portrait <span class="badge bg-secondary ms-1">Optional</span>
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="orientation" id="orientLandscape" value="landscape">
              <label class="form-check-label" for="orientLandscape">
                <i class="bi bi-phone-landscape me-1"></i>Landscape <span class="badge bg-secondary ms-1">Optional</span>
              </label>
            </div>
          </div>
        </div>

        {{-- PDF/A --}}
        <div class="col-md-6">
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="pdfa" name="pdfa" value="1"
                   {{ !$ghostscriptAvailable ? 'disabled' : '' }}>
            <label class="form-check-label fw-bold" for="pdfa">
              Generate PDF/A <span class="badge bg-secondary ms-1">Optional</span>
            </label>
          </div>
          @if(!$ghostscriptAvailable)
            <div class="form-text text-warning">
              <i class="bi bi-exclamation-triangle me-1"></i>Ghostscript required for PDF/A. Install with: <code>sudo apt install ghostscript</code>
            </div>
          @endif
        </div>

        {{-- PDF/A Version --}}
        <div class="col-md-6" id="pdfaVersionGroup" style="display: none;">
          <label class="form-label fw-bold" for="pdfa_version">PDF/A Version <span class="badge bg-secondary ms-1">Optional</span></label>
          <select class="form-select" id="pdfa_version" name="pdfa_version">
            @foreach($pdfaVersions as $ver)
              <option value="{{ $ver }}" {{ $ver === '2b' ? 'selected' : '' }}>
                PDF/A-{{ $ver }}
              </option>
            @endforeach
          </select>
        </div>
      </div>

      <hr>

      {{-- Submit --}}
      <div class="d-flex gap-2">
        <button type="submit" class="btn atom-btn-outline-success" id="mergeBtn">
          <i class="bi bi-files me-1"></i>Merge to PDF
        </button>
        <a href="{{ route('pdf-tools.index') }}" class="btn atom-btn-white">Cancel</a>
      </div>
    </form>

    @endif

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Quality slider display
  var quality = document.getElementById('quality');
  var qualityValue = document.getElementById('qualityValue');
  if (quality && qualityValue) {
    quality.addEventListener('input', function() {
      qualityValue.textContent = this.value;
    });
  }

  // PDF/A toggle
  var pdfa = document.getElementById('pdfa');
  var pdfaVersionGroup = document.getElementById('pdfaVersionGroup');
  if (pdfa && pdfaVersionGroup) {
    pdfa.addEventListener('change', function() {
      pdfaVersionGroup.style.display = this.checked ? 'block' : 'none';
    });
  }

  // File list preview
  var fileInput = document.getElementById('fileInput');
  var fileList = document.getElementById('fileList');
  var fileListItems = document.getElementById('fileListItems');

  if (fileInput && fileList && fileListItems) {
    fileInput.addEventListener('change', function() {
      fileListItems.innerHTML = '';

      if (this.files.length > 0) {
        fileList.classList.remove('d-none');

        for (var i = 0; i < this.files.length; i++) {
          var li = document.createElement('li');
          li.className = 'list-group-item d-flex justify-content-between align-items-center';

          var name = document.createElement('span');
          name.textContent = (i + 1) + '. ' + this.files[i].name;

          var size = document.createElement('span');
          size.className = 'badge bg-secondary';
          var sizeKb = (this.files[i].size / 1024).toFixed(1);
          size.textContent = sizeKb > 1024 ? (sizeKb / 1024).toFixed(1) + ' MB' : sizeKb + ' KB';

          li.appendChild(name);
          li.appendChild(size);
          fileListItems.appendChild(li);
        }
      } else {
        fileList.classList.add('d-none');
      }
    });
  }

  // Form submit loading state
  var mergeForm = document.getElementById('mergeForm');
  var mergeBtn = document.getElementById('mergeBtn');
  if (mergeForm && mergeBtn) {
    mergeForm.addEventListener('submit', function() {
      mergeBtn.disabled = true;
      mergeBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Merging...';
    });
  }
});
</script>

@endsection
