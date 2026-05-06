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

      {{-- 1b. Bulk folder upload (each file becomes a child IO; folders --}}
      {{--      mirror as parent IOs when "Mirror folder structure" is on). --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="bulk-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#bulk-collapse" aria-expanded="false" aria-controls="bulk-collapse">
            <i class="fas fa-folder-tree me-2"></i> {{ __('Upload a folder of files') }}
          </button>
        </h2>
        <div id="bulk-collapse" class="accordion-collapse collapse"
             aria-labelledby="bulk-heading" data-bs-parent="#addDoAccordion">
          <div class="accordion-body">
            <p class="text-muted small mb-3">
              {{ __('Pick or drag a folder. Each file becomes a child description; subfolders become parent descriptions when "Mirror folder structure" is on.') }}
            </p>
            <div id="bulk-drop-zone" class="border border-2 border-dashed rounded p-4 text-center mb-3">
              <i class="fas fa-folder-open fa-2x text-muted mb-2 d-block"></i>
              <p class="mb-1">{{ __('Drag and drop a folder here') }}</p>
              <p class="text-muted small mb-2">{{ __('or use the buttons below') }}</p>
              <div class="d-flex justify-content-center gap-2 flex-wrap">
                <button type="button" id="bulk-pick-files-btn" class="btn atom-btn-white btn-sm">
                  <i class="fas fa-file me-1"></i>{{ __('Browse files') }}
                </button>
                <button type="button" id="bulk-pick-folder-btn" class="btn atom-btn-white btn-sm">
                  <i class="fas fa-folder-open me-1"></i>{{ __('Browse folder') }}
                </button>
              </div>
              <input type="file" id="bulk-files-input" multiple class="d-none">
              <input type="file" id="bulk-folder-input" webkitdirectory directory class="d-none">
            </div>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="bulk_mirror_tree" checked>
              <label class="form-check-label" for="bulk_mirror_tree">
                {{ __('Mirror folder structure as child descriptions') }}
              </label>
              <div class="form-text">{{ __('Off = every file is a flat sibling under this description (folder names ignored).') }}</div>
            </div>
            <div id="bulk-file-list" class="mb-2" style="max-height:240px;overflow-y:auto;"></div>
            <div id="bulk-progress" class="d-none">
              <div class="progress" style="height:1.25rem;"><div id="bulk-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%"></div></div>
              <small id="bulk-progress-status" class="text-muted d-block mt-1"></small>
            </div>
            <button type="button" id="bulk-submit-btn" class="btn btn-primary mt-2" disabled>
              <i class="fas fa-cloud-upload-alt me-1"></i>{{ __('Upload selected') }} <span id="bulk-count-badge" class="badge bg-light text-dark ms-1">0</span>
            </button>
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

  @push('js')
  <script>
  (function () {
    'use strict';
    // Folder-upload UI for the addDigitalObject page. Mirrors the FTP page's
    // webkitdirectory + drag-drop folder support, simpler (no chunking)
    // because IO uploads are typically smaller and a single request is
    // easier to reason about for first pass. Each file is sent as
    // digital_objects[i] + relative_paths[i] so the server can rebuild the
    // folder tree as a child-IO hierarchy.
    var BULK_URL  = @json(route('io.digitalobject.bulk-upload', $resource->slug));
    var CSRF      = @json(csrf_token());
    var dropZone  = document.getElementById('bulk-drop-zone');
    var filesIn   = document.getElementById('bulk-files-input');
    var folderIn  = document.getElementById('bulk-folder-input');
    var pickFiles = document.getElementById('bulk-pick-files-btn');
    var pickFolder= document.getElementById('bulk-pick-folder-btn');
    var fileList  = document.getElementById('bulk-file-list');
    var submitBtn = document.getElementById('bulk-submit-btn');
    var countBadge= document.getElementById('bulk-count-badge');
    var mirrorChk = document.getElementById('bulk_mirror_tree');
    var progress  = document.getElementById('bulk-progress');
    var progBar   = document.getElementById('bulk-progress-bar');
    var progStat  = document.getElementById('bulk-progress-status');
    if (!dropZone || !submitBtn) return;

    var staged = []; // [{file, relPath}]

    function relPathOf(f) { return f.webkitRelativePath || f._ahgRelPath || f.name; }
    function refreshUI() {
      countBadge.textContent = staged.length;
      submitBtn.disabled = staged.length === 0;
      if (staged.length === 0) { fileList.innerHTML = ''; return; }
      var html = '<ul class="list-unstyled small mb-0">';
      var preview = staged.slice(0, 50);
      preview.forEach(function (s) {
        html += '<li><i class="fas fa-file me-1 text-muted"></i><code>' + (s.relPath || '').replace(/[<>]/g, '') + '</code> <span class="text-muted">(' + (s.file.size > 1048576 ? (s.file.size/1048576).toFixed(1)+' MB' : (s.file.size/1024).toFixed(1)+' KB') + ')</span></li>';
      });
      if (staged.length > 50) html += '<li class="text-muted">… and ' + (staged.length - 50) + ' more</li>';
      html += '</ul>';
      fileList.innerHTML = html;
    }

    function addFiles(arr) {
      for (var i = 0; i < arr.length; i++) {
        var f = arr[i];
        staged.push({ file: f, relPath: relPathOf(f) });
      }
      refreshUI();
    }

    pickFiles.addEventListener('click', function (e) { e.preventDefault(); filesIn.click(); });
    pickFolder.addEventListener('click', function (e) { e.preventDefault(); folderIn.click(); });

    filesIn.addEventListener('change', function () {
      if (this.files.length > 0) addFiles(this.files);
      this.value = '';
    });
    folderIn.addEventListener('change', function () {
      if (this.files.length > 0) addFiles(this.files);
      this.value = '';
    });

    dropZone.addEventListener('dragover', function (e) { e.preventDefault(); e.stopPropagation(); this.classList.add('bg-light'); });
    dropZone.addEventListener('dragleave', function (e) { e.preventDefault(); e.stopPropagation(); this.classList.remove('bg-light'); });
    dropZone.addEventListener('drop', function (e) {
      e.preventDefault(); e.stopPropagation(); dropZone.classList.remove('bg-light');
      var dt = e.dataTransfer;
      var items = dt && dt.items ? dt.items : null;
      var hasEntryAPI = items && items.length && typeof items[0].webkitGetAsEntry === 'function';

      if (hasEntryAPI) {
        var collected = [];
        var pending = items.length;
        var done = function () { pending--; if (pending === 0 && collected.length > 0) addFiles(collected); };
        var walk = function (entry, prefix) {
          if (entry.isFile) {
            entry.file(function (file) { file._ahgRelPath = (prefix ? prefix + '/' : '') + file.name; collected.push(file); done(); }, done);
          } else if (entry.isDirectory) {
            var reader = entry.createReader();
            var readBatch = function () {
              reader.readEntries(function (entries) {
                if (!entries.length) { done(); return; }
                pending += entries.length;
                entries.forEach(function (sub) { walk(sub, (prefix ? prefix + '/' : '') + entry.name); });
                done();
                readBatch(); // keep draining (Chrome caps each batch at ~100)
              });
            };
            readBatch();
          } else { done(); }
        };
        for (var i = 0; i < items.length; i++) {
          var ent = items[i].webkitGetAsEntry();
          if (ent) walk(ent, ''); else done();
        }
      } else {
        // Fallback: flat-file drop
        if (dt.files && dt.files.length) addFiles(dt.files);
      }
    });

    submitBtn.addEventListener('click', function (e) {
      e.preventDefault();
      if (staged.length === 0) return;
      submitBtn.disabled = true;
      progress.classList.remove('d-none');
      progStat.textContent = 'Uploading 0 / ' + staged.length + ' …';

      var fd = new FormData();
      fd.append('_token', CSRF);
      fd.append('bulk_mirror_tree', mirrorChk.checked ? '1' : '0');
      staged.forEach(function (s) {
        fd.append('digital_objects[]', s.file, s.file.name);
        fd.append('relative_paths[]', s.relPath);
      });
      var xhr = new XMLHttpRequest();
      xhr.open('POST', BULK_URL);
      xhr.upload.addEventListener('progress', function (ev) {
        if (ev.lengthComputable) {
          var pct = (ev.loaded / ev.total) * 100;
          progBar.style.width = pct.toFixed(1) + '%';
          progStat.textContent = 'Uploading ' + Math.round(ev.loaded/1048576) + ' / ' + Math.round(ev.total/1048576) + ' MB …';
        }
      });
      xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 400) {
          progBar.style.width = '100%';
          progStat.textContent = 'Done. Redirecting …';
          // Server redirects to the IO show page on success — follow it.
          window.location = (xhr.responseURL || ('/' + @json($resource->slug)));
        } else {
          progStat.textContent = 'Upload failed: HTTP ' + xhr.status;
          submitBtn.disabled = false;
        }
      };
      xhr.onerror = function () {
        progStat.textContent = 'Network error during upload.';
        submitBtn.disabled = false;
      };
      xhr.send(fd);
    });
  })();
  </script>
  @endpush
@endsection
