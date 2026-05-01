{{--
  Add / Link a digital object — dedicated 1-col page.
  Cloned from atom-ahg-plugins/ahgThemeB5Plugin/modules/object/templates/addDigitalObjectSuccess.php

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ __('Link digital object') }}
    </h1>
    <span class="small" id="heading-label">
      {{ $resourceDescription ?? '' }}
    </span>
  </div>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @php
    $maxBytes   = \AhgCore\Services\DigitalObjectService::getMaxUploadSize();
    $maxDisplay = \AhgCore\Services\DigitalObjectService::formatFileSize($maxBytes);
    $showMerge  = \AhgCore\Services\MenuService::isPluginEnabled('ahgPreservationPlugin');
    $showFtp    = \AhgCore\Services\MenuService::isPluginEnabled('ahgFtpPlugin')
                  || \Illuminate\Support\Facades\Schema::hasTable('ahg_settings');
  @endphp

  <form method="POST" action="{{ route('io.digitalobject.upload', $resource->slug) }}"
        enctype="multipart/form-data" id="uploadForm">
    @csrf

    <div class="accordion mb-3" id="addDoAccordion">

      {{-- 1. Upload local file --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="upload-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse"
                  data-bs-target="#upload-collapse" aria-expanded="true" aria-controls="upload-collapse">
            <i class="fas fa-upload me-2"></i> {{ __('Upload a digital object') }}
          </button>
        </h2>
        <div id="upload-collapse" class="accordion-collapse collapse show"
             aria-labelledby="upload-heading" data-bs-parent="#addDoAccordion">
          <div class="accordion-body">
            <label for="digital_object" class="form-label">{{ __('File') }}</label>
            <input type="file" id="digital_object" name="digital_object"
                   class="form-control @error('digital_object') is-invalid @enderror"
                   accept="image/*,application/pdf,audio/*,video/*,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.txt,.rtf,.csv,.xml,.json">
            @error('digital_object')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
              {{ __('The maximum size of file uploads is :size.', ['size' => $maxDisplay]) }}
            </div>
          </div>
        </div>
      </div>

      {{-- 2. Link to an external URL --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="external-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#external-collapse" aria-expanded="false" aria-controls="external-collapse">
            <i class="fas fa-link me-2"></i> {{ __('Link to an external digital object') }}
          </button>
        </h2>
        <div id="external-collapse" class="accordion-collapse collapse"
             aria-labelledby="external-heading" data-bs-parent="#addDoAccordion">
          <div class="accordion-body">
            <label for="external_url" class="form-label">{{ __('URL') }}</label>
            <input type="url" id="external_url" name="external_url"
                   class="form-control @error('external_url') is-invalid @enderror"
                   value="{{ old('external_url') }}"
                   placeholder="https://example.com/file.pdf, ftp://host/path/file.tif, https://sketchfab.com/3d-models/...">
            @error('external_url')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">
              {{ __('HTTP(S) and FTP URLs accepted. Sketchfab, YouTube and Vimeo links are recognised and rendered with a media icon.') }}
            </div>

            <label for="external_name" class="form-label mt-3">
              {{ __('Display name') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span>
            </label>
            <input type="text" id="external_name" name="external_name" class="form-control"
                   value="{{ old('external_name') }}"
                   placeholder="{{ __('Defaults to filename in URL') }}">
          </div>
        </div>
      </div>

      {{-- 3. Select from FTP/SFTP server (only if ahgFtpPlugin is enabled / configured) --}}
      @if($showFtp)
      <div class="accordion-item">
        <h2 class="accordion-header" id="ftp-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#ftp-collapse" aria-expanded="false" aria-controls="ftp-collapse">
            <i class="fas fa-server me-2"></i> {{ __('Select from FTP/SFTP server') }}
          </button>
        </h2>
        <div id="ftp-collapse" class="accordion-collapse collapse"
             aria-labelledby="ftp-heading" data-bs-parent="#addDoAccordion">
          <div class="accordion-body">
            <div id="ftp-status" class="alert alert-info" style="display:none;"></div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <small class="text-muted">{{ __('Files in the configured FTP/SFTP upload directory:') }}</small>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="ftp-refresh">
                <i class="fas fa-sync me-1"></i>{{ __('Refresh') }}
              </button>
            </div>
            <div id="ftp-file-list" class="border rounded bg-white" style="min-height: 80px; max-height: 320px; overflow-y: auto;">
              <div class="text-muted text-center py-3"><i class="fas fa-spinner fa-spin me-1"></i>{{ __('Loading…') }}</div>
            </div>
            <input type="hidden" id="ftp_filename" name="ftp_filename" value="">
            <div class="form-text mt-2">
              {{ __('Pick one file. On submit, an FTP/SFTP URL is recorded and the file remains on the remote server.') }}
            </div>
          </div>
        </div>
      </div>
      @endif

      {{-- 4. Merge images to PDF (only if ahgPreservationPlugin is enabled) --}}
      @if($showMerge)
      <div class="accordion-item">
        <h2 class="accordion-header" id="merge-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#merge-collapse" aria-expanded="false" aria-controls="merge-collapse">
            <i class="fas fa-layer-group me-2"></i> {{ __('Merge images to PDF') }}
          </button>
        </h2>
        <div id="merge-collapse" class="accordion-collapse collapse"
             aria-labelledby="merge-heading" data-bs-parent="#addDoAccordion">
          <div class="accordion-body">
            <p class="text-muted small mb-2">
              {{ __('Merge multiple TIFF/JPEG/PNG images into a single PDF/A document and link it to this record.') }}
            </p>
            <a href="{{ url('/preservation/tiff-pdf-merge?io=' . $resource->id) }}"
               class="btn btn-primary">
              <i class="fas fa-file-pdf me-1"></i>{{ __('Open Merge Tool') }}
            </a>
          </div>
        </div>
      </div>
      @endif

    </div>{{-- /accordion --}}

    <ul class="actions mb-3 nav gap-2">
      <li>
        <a href="{{ route('informationobject.show', $resource->slug) }}"
           class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a>
      </li>
      <li>
        <input class="btn atom-btn-outline-success" type="submit" value="{{ __('Create') }}">
      </li>
    </ul>

  </form>

  @if($showFtp)
  @push('js')
  <script>
  (function () {
    'use strict';
    var listUrl = @json(route('ftpUpload.listFiles'));
    var listEl = document.getElementById('ftp-file-list');
    var pickInput = document.getElementById('ftp_filename');
    var statusEl = document.getElementById('ftp-status');
    var refreshBtn = document.getElementById('ftp-refresh');
    var collapse = document.getElementById('ftp-collapse');
    var loaded = false;

    function setStatus(msg, level) {
      if (!msg) { statusEl.style.display = 'none'; return; }
      statusEl.className = 'alert alert-' + (level || 'info');
      statusEl.textContent = msg;
      statusEl.style.display = 'block';
    }

    function fetchFiles() {
      listEl.innerHTML = '<div class="text-muted text-center py-3"><i class="fas fa-spinner fa-spin me-1"></i>Loading…</div>';
      fetch(listUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || data.success === false) {
            setStatus((data && data.message) || 'FTP/SFTP not configured. Configure under Settings → File Upload.', 'warning');
            listEl.innerHTML = '';
            return;
          }
          setStatus(null);
          var files = data.files || [];
          if (!files.length) {
            listEl.innerHTML = '<div class="text-muted text-center py-3"><i class="fas fa-folder-open me-1"></i>No files found</div>';
            return;
          }
          listEl.innerHTML = files.map(function (f) {
            var name = (typeof f === 'string') ? f : (f.name || f.filename || '');
            var size = (typeof f === 'object' && f.size) ? (' · ' + f.size) : '';
            return '<label class="d-flex align-items-center p-2 border-bottom" style="cursor:pointer;">' +
                   '  <input type="radio" name="__ftp_pick" value="' + name.replace(/"/g, '&quot;') + '" class="form-check-input me-2">' +
                   '  <i class="fas fa-file me-2 text-secondary"></i>' +
                   '  <span class="flex-grow-1"><small class="fw-semibold">' + name + '</small>' +
                   (size ? '<br><small class="text-muted">' + size + '</small>' : '') +
                   '  </span>' +
                   '</label>';
          }).join('');
          listEl.querySelectorAll('input[name=__ftp_pick]').forEach(function (r) {
            r.addEventListener('change', function () { pickInput.value = r.value; });
          });
        })
        .catch(function (err) { setStatus('Error loading FTP files: ' + err.message, 'danger'); listEl.innerHTML = ''; });
    }

    if (collapse) {
      collapse.addEventListener('shown.bs.collapse', function () {
        if (!loaded) { loaded = true; fetchFiles(); }
      });
    }
    if (refreshBtn) refreshBtn.addEventListener('click', fetchFiles);
  })();
  </script>
  @endpush
  @endif
@endsection
