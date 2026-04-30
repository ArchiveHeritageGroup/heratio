@extends('theme::layouts.1col')

@section('title', $title)

@section('content')

  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
      <li class="breadcrumb-item">Import</li>
      <li class="breadcrumb-item active">FTP Upload</li>
    </ol>
  </nav>

  <h1 class="mb-4"><i class="fa fa-upload me-2"></i>FTP Upload</h1>

  @if(!$configured)
    <div class="alert alert-warning">
      <h5><i class="fa fa-exclamation-triangle me-2"></i>FTP/SFTP Not Configured</h5>
      <p class="mb-2">Please configure the FTP/SFTP connection settings before using this page.</p>
      <a href="{{ url('/admin/settings/ftp') }}" class="btn atom-btn-white">
        <i class="fa fa-cog me-1"></i>{{ __('Configure FTP Settings') }}
      </a>
    </div>
  @else

    <!-- CSV Path Info -->
    <div class="alert alert-info border-start border-4 border-primary">
      <h5><i class="fa fa-info-circle me-2"></i>Important: CSV Digital Object Path</h5>
      <p class="mb-2">Files uploaded here are stored at the remote path shown below. In your CSV import file, set the <code>digitalObjectPath</code> column to:</p>
      <div class="bg-white border rounded p-3 mb-2">
        <code id="path-prefix" class="fs-5 text-primary user-select-all">{{ $diskPath }}/</code><code class="fs-5 text-muted">your-filename.ext</code>
        <button type="button" class="btn btn-sm atom-btn-white ms-3" id="copy-path-btn" title="{{ __('Copy path prefix') }}">
          <i class="fa fa-copy"></i>
        </button>
      </div>
      <small class="text-muted">Replace <em>your-filename.ext</em> with the actual filename you upload.</small>
    </div>

    <!-- Upload Zone -->
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fa fa-cloud-upload-alt me-2"></i>Upload Files
          <small class="ms-2 opacity-75">(supports files up to 2 GB — chunked upload with resume)</small>
        </h5>
      </div>
      <div class="card-body">
        <div id="drop-zone" class="border border-2 border-dashed rounded p-5 text-center mb-3" style="cursor:pointer; border-color:#0d6efd!important;">
          <i class="fa fa-cloud-upload-alt fa-3x text-muted mb-3 d-block"></i>
          <p class="lead mb-1">Drag and drop files here</p>
          <p class="text-muted">or click to browse</p>
          <input type="file" id="file-input" multiple class="d-none">
        </div>
        <div class="row">
          <div class="col-md-4">
            <small class="text-muted"><i class="fa fa-file me-1"></i>Accepted: CSV, XML, ZIP, images, documents</small>
          </div>
          <div class="col-md-4 text-center">
            <small class="text-muted"><i class="fa fa-hdd me-1"></i>Max file size: 2 GB per file</small>
          </div>
          <div class="col-md-4 text-end">
            <small class="text-muted"><i class="fa fa-server me-1"></i>Protocol: {{ strtoupper($protocol) }}</small>
          </div>
        </div>
        <div id="upload-progress" class="d-none mt-3">
          <!-- Per-file progress bars inserted by JS -->
        </div>
      </div>
    </div>

    <!-- File Listing -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fa fa-folder-open me-2"></i>Remote Files</h5>
        <button type="button" class="btn btn-sm atom-btn-white" id="refresh-btn">
          <i class="fa fa-sync-alt me-1"></i>{{ __('Refresh') }}
        </button>
      </div>
      <div class="card-body">
        @if($listError)
          <div class="alert alert-danger">
            <i class="fa fa-exclamation-circle me-2"></i>{{ $listError }}
          </div>
        @endif
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover mb-0" id="files-table">
            <thead>
              <tr>
                <th>{{ __('Filename') }}</th>
                <th class="text-end" style="width:120px">{{ __('Size') }}</th>
                <th style="width:180px">{{ __('Modified') }}</th>
                <th class="text-center" style="width:80px">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody id="files-tbody">
              @if(empty($files))
                <tr id="empty-row"><td colspan="4" class="text-center text-muted py-4">No files found</td></tr>
              @else
                @foreach($files as $file)
                  <tr>
                    <td><i class="fa fa-file me-2 text-muted"></i>{{ $file['name'] }}</td>
                    <td class="text-end">{{ \AhgFtpUpload\Services\FtpService::formatBytes($file['size']) }}</td>
                    <td>{{ $file['modified'] }}</td>
                    <td class="text-center">
                      <button type="button" class="btn btn-sm atom-btn-outline-danger delete-file-btn" data-filename="{{ $file['name'] }}" title="{{ __('Delete') }}">
                        <i class="fa fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                @endforeach
              @endif
            </tbody>
          </table>
        </div>
      </div>
    </div>

  @endif

  <script>
  (function() {
      'use strict';

      var CHUNK_SIZE = {{ (int) $chunkSize }};
      var MAX_RETRIES = 3;
      var RETRY_DELAY_MS = 2000;
      var UPLOAD_URL = '{{ route("ftpUpload.uploadChunk") }}';
      var LIST_URL = '{{ route("ftpUpload.listFiles") }}';
      var DELETE_URL = '{{ route("ftpUpload.deleteFile") }}';
      var CSRF_TOKEN = '{{ csrf_token() }}';

      var uploads = {};

      // Copy path prefix
      var copyBtn = document.getElementById('copy-path-btn');
      if (copyBtn) {
          copyBtn.addEventListener('click', function() {
              var pathEl = document.getElementById('path-prefix');
              if (pathEl && navigator.clipboard) {
                  navigator.clipboard.writeText(pathEl.textContent).then(function() {
                      copyBtn.innerHTML = '<i class="fa fa-check text-success"></i>';
                      setTimeout(function() { copyBtn.innerHTML = '<i class="fa fa-copy"></i>'; }, 2000);
                  });
              }
          });
      }

      var dropZone = document.getElementById('drop-zone');
      var fileInput = document.getElementById('file-input');
      var progressContainer = document.getElementById('upload-progress');

      if (!dropZone || !fileInput) return;

      dropZone.addEventListener('click', function() { fileInput.click(); });
      fileInput.addEventListener('change', function() {
          if (this.files.length > 0) startUploads(this.files);
          this.value = '';
      });

      dropZone.addEventListener('dragover', function(e) { e.preventDefault(); e.stopPropagation(); this.classList.add('bg-light'); });
      dropZone.addEventListener('dragleave', function(e) { e.preventDefault(); e.stopPropagation(); this.classList.remove('bg-light'); });
      dropZone.addEventListener('drop', function(e) {
          e.preventDefault(); e.stopPropagation();
          this.classList.remove('bg-light');
          if (e.dataTransfer.files.length > 0) startUploads(e.dataTransfer.files);
      });

      function startUploads(files) {
          progressContainer.classList.remove('d-none');
          for (var i = 0; i < files.length; i++) {
              chunkedUpload(files[i]);
          }
      }

      function generateId() {
          return 'up_' + Date.now() + '_' + Math.random().toString(36).substr(2, 8);
      }

      function chunkedUpload(file) {
          var uploadId = generateId();
          var totalChunks = Math.ceil(file.size / CHUNK_SIZE) || 1;
          var domId = 'progress-' + uploadId;

          var html = '<div id="' + domId + '" class="mb-3 border rounded p-3">' +
              '<div class="d-flex justify-content-between align-items-center mb-1">' +
                  '<span class="text-truncate fw-semibold" style="max-width:50%"><i class="fa fa-file me-1"></i>' + escapeHtml(file.name) + '</span>' +
                  '<span class="text-muted small">' + formatBytes(file.size) + ' &middot; ' + totalChunks + ' chunk' + (totalChunks > 1 ? 's' : '') + '</span>' +
                  '<span class="upload-status badge bg-primary">0%</span>' +
              '</div>' +
              '<div class="progress mb-2" style="height:8px">' +
                  '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%"></div>' +
              '</div>' +
              '<div class="upload-detail text-muted small"></div>' +
              '<div class="upload-actions mt-1">' +
                  '<button type="button" class="btn btn-sm atom-btn-white pause-btn d-none"><i class="fa fa-pause me-1"></i>Pause</button> ' +
                  '<button type="button" class="btn btn-sm atom-btn-outline-success resume-btn d-none"><i class="fa fa-play me-1"></i>Resume</button> ' +
                  '<button type="button" class="btn btn-sm atom-btn-outline-danger cancel-btn"><i class="fa fa-times me-1"></i>Cancel</button>' +
              '</div>' +
          '</div>';
          progressContainer.insertAdjacentHTML('beforeend', html);

          var el = document.getElementById(domId);
          var bar = el.querySelector('.progress-bar');
          var status = el.querySelector('.upload-status');
          var detail = el.querySelector('.upload-detail');
          var pauseBtn = el.querySelector('.pause-btn');
          var resumeBtn = el.querySelector('.resume-btn');
          var cancelBtn = el.querySelector('.cancel-btn');

          var state = {
              uploadId: uploadId,
              file: file,
              totalChunks: totalChunks,
              currentChunk: 0,
              paused: false,
              cancelled: false,
              retries: 0,
              startTime: Date.now()
          };
          uploads[uploadId] = state;

          pauseBtn.classList.remove('d-none');
          pauseBtn.addEventListener('click', function() {
              state.paused = true;
              pauseBtn.classList.add('d-none');
              resumeBtn.classList.remove('d-none');
              status.textContent = 'Paused';
              status.className = 'upload-status badge bg-warning text-dark';
              bar.classList.remove('progress-bar-animated');
              detail.textContent = 'Chunk ' + state.currentChunk + '/' + totalChunks + ' — paused';
          });

          resumeBtn.addEventListener('click', function() {
              state.paused = false;
              resumeBtn.classList.add('d-none');
              pauseBtn.classList.remove('d-none');
              status.className = 'upload-status badge bg-primary';
              bar.classList.add('progress-bar-animated');
              sendChunk();
          });

          cancelBtn.addEventListener('click', function() {
              state.cancelled = true;
              bar.classList.remove('progress-bar-animated', 'progress-bar-striped');
              bar.classList.add('bg-secondary');
              status.textContent = 'Cancelled';
              status.className = 'upload-status badge bg-secondary';
              detail.textContent = '';
              el.querySelector('.upload-actions').innerHTML = '';
              delete uploads[uploadId];
          });

          function sendChunk() {
              if (state.cancelled || state.paused) return;
              if (state.currentChunk >= totalChunks) return;

              var start = state.currentChunk * CHUNK_SIZE;
              var end = Math.min(start + CHUNK_SIZE, file.size);
              var blob = file.slice(start, end);

              var formData = new FormData();
              formData.append('file', blob);
              formData.append('uploadId', uploadId);
              formData.append('chunkIndex', state.currentChunk);
              formData.append('totalChunks', totalChunks);
              formData.append('fileName', file.name);
              formData.append('fileSize', file.size);
              formData.append('_token', CSRF_TOKEN);

              var xhr = new XMLHttpRequest();
              xhr.open('POST', UPLOAD_URL, true);

              xhr.upload.addEventListener('progress', function(e) {
                  if (e.lengthComputable) {
                      var chunkPct = e.loaded / e.total;
                      var overallPct = ((state.currentChunk + chunkPct) / totalChunks) * 100;
                      bar.style.width = overallPct.toFixed(1) + '%';
                      status.textContent = overallPct.toFixed(0) + '%';

                      var elapsed = (Date.now() - state.startTime) / 1000;
                      var bytesUploaded = state.currentChunk * CHUNK_SIZE + e.loaded;
                      if (elapsed > 1) {
                          var speed = bytesUploaded / elapsed;
                          var remaining = (file.size - bytesUploaded) / speed;
                          detail.textContent = 'Chunk ' + (state.currentChunk + 1) + '/' + totalChunks +
                              ' — ' + formatBytes(Math.round(speed)) + '/s' +
                              ' — ' + formatTime(remaining) + ' remaining';
                      }
                  }
              });

              xhr.addEventListener('load', function() {
                  if (xhr.status >= 200 && xhr.status < 300) {
                      try {
                          var data = JSON.parse(xhr.responseText);
                          if (data.success) {
                              state.retries = 0;
                              state.currentChunk++;

                              if (data.complete === false) {
                                  sendChunk();
                              } else {
                                  bar.classList.remove('progress-bar-animated', 'progress-bar-striped');
                                  bar.classList.add('bg-success');
                                  bar.style.width = '100%';
                                  status.textContent = 'Uploaded';
                                  status.className = 'upload-status badge bg-success';
                                  var elapsed = (Date.now() - state.startTime) / 1000;
                                  detail.textContent = formatBytes(file.size) + ' in ' + formatTime(elapsed);
                                  el.querySelector('.upload-actions').innerHTML = '<i class="fa fa-check-circle text-success"></i>';
                                  delete uploads[uploadId];
                                  refreshFileList();
                              }
                          } else {
                              handleChunkError(data.message || 'Server error');
                          }
                      } catch (e) {
                          handleChunkError('Invalid response');
                      }
                  } else {
                      handleChunkError('HTTP ' + xhr.status);
                  }
              });

              xhr.addEventListener('error', function() {
                  handleChunkError('Network error');
              });

              xhr.addEventListener('timeout', function() {
                  handleChunkError('Timeout');
              });

              xhr.timeout = 120000;
              xhr.send(formData);
          }

          function handleChunkError(msg) {
              if (state.cancelled) return;

              state.retries++;
              if (state.retries <= MAX_RETRIES) {
                  var delay = RETRY_DELAY_MS * state.retries;
                  detail.innerHTML = '<i class="fa fa-exclamation-triangle text-warning me-1"></i>' +
                      'Retry ' + state.retries + '/' + MAX_RETRIES +
                      ' in ' + (delay / 1000) + 's — ' + escapeHtml(msg);
                  status.textContent = 'Retrying...';
                  status.className = 'upload-status badge bg-warning text-dark';
                  setTimeout(function() {
                      if (!state.cancelled && !state.paused) {
                          status.className = 'upload-status badge bg-primary';
                          sendChunk();
                      }
                  }, delay);
              } else {
                  bar.classList.remove('progress-bar-animated', 'progress-bar-striped');
                  bar.classList.add('bg-danger');
                  status.textContent = 'Failed';
                  status.className = 'upload-status badge bg-danger';
                  detail.innerHTML = '<i class="fa fa-times-circle text-danger me-1"></i>' + escapeHtml(msg) +
                      ' — chunk ' + (state.currentChunk + 1) + '/' + totalChunks;
                  pauseBtn.classList.add('d-none');
                  resumeBtn.classList.remove('d-none');
                  resumeBtn.innerHTML = '<i class="fa fa-redo me-1"></i>Retry';
                  resumeBtn.addEventListener('click', function handler() {
                      state.retries = 0;
                      state.paused = false;
                      resumeBtn.classList.add('d-none');
                      pauseBtn.classList.remove('d-none');
                      bar.classList.remove('bg-danger');
                      bar.classList.add('progress-bar-striped', 'progress-bar-animated');
                      status.className = 'upload-status badge bg-primary';
                      resumeBtn.removeEventListener('click', handler);
                      sendChunk();
                  });
              }
          }

          sendChunk();
      }

      // === File list refresh ===
      var refreshBtn = document.getElementById('refresh-btn');
      if (refreshBtn) refreshBtn.addEventListener('click', refreshFileList);

      function refreshFileList() {
          fetch(LIST_URL)
              .then(function(r) { return r.json(); })
              .then(function(data) {
                  var tbody = document.getElementById('files-tbody');
                  if (!tbody) return;
                  tbody.innerHTML = '';

                  if (!data.success || !data.files || data.files.length === 0) {
                      tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No files found</td></tr>';
                      return;
                  }

                  data.files.forEach(function(f) {
                      var row = '<tr>' +
                          '<td><i class="fa fa-file me-2 text-muted"></i>' + escapeHtml(f.name) + '</td>' +
                          '<td class="text-end">' + formatBytes(f.size) + '</td>' +
                          '<td>' + escapeHtml(f.modified) + '</td>' +
                          '<td class="text-center"><button type="button" class="btn btn-sm atom-btn-outline-danger delete-file-btn" data-filename="' + escapeHtml(f.name) + '" title="Delete"><i class="fa fa-trash"></i></button></td>' +
                          '</tr>';
                      tbody.insertAdjacentHTML('beforeend', row);
                  });

                  bindDeleteButtons();
              })
              .catch(function() {});
      }

      // === Delete file ===
      function bindDeleteButtons() {
          document.querySelectorAll('.delete-file-btn').forEach(function(btn) {
              btn.addEventListener('click', function() {
                  var filename = this.getAttribute('data-filename');
                  if (!confirm('Delete "' + filename + '"?')) return;

                  var row = this.closest('tr');
                  this.disabled = true;
                  this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                  fetch(DELETE_URL, {
                      method: 'POST',
                      headers: {
                          'Content-Type': 'application/json',
                          'X-CSRF-TOKEN': CSRF_TOKEN
                      },
                      body: JSON.stringify({filename: filename})
                  })
                  .then(function(r) { return r.json(); })
                  .then(function(data) {
                      if (data.success) {
                          row.remove();
                          var tbody = document.getElementById('files-tbody');
                          if (tbody && tbody.children.length === 0) {
                              tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No files found</td></tr>';
                          }
                      } else {
                          alert(data.message || 'Delete failed');
                          refreshFileList();
                      }
                  })
                  .catch(function() { alert('Network error'); refreshFileList(); });
              });
          });
      }
      bindDeleteButtons();

      // === Helpers ===
      function escapeHtml(str) { var d = document.createElement('div'); d.textContent = str; return d.innerHTML; }

      function formatBytes(bytes) {
          if (bytes === 0) return '0 B';
          var k = 1024, s = ['B', 'KB', 'MB', 'GB', 'TB'];
          var i = Math.floor(Math.log(bytes) / Math.log(k));
          return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + s[i];
      }

      function formatTime(seconds) {
          if (seconds < 60) return Math.round(seconds) + 's';
          if (seconds < 3600) return Math.floor(seconds / 60) + 'm ' + Math.round(seconds % 60) + 's';
          return Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm';
      }
  })();
  </script>

@endsection
