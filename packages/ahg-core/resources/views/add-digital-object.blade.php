@extends('theme::layouts.1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ __(
          'Link %1%',
          ['%1%' => mb_strtolower(config('app.ui_label_digitalobject', __('digital object')))]
      ) }}
    </h1>
    <span class="small" id="heading-label">
      {{ $resourceDescription ?? '' }}
    </span>
  </div>
@endsection

@section('content')

  @if($uploadLimitReached ?? false)

    <div class="alert alert-warning" role="alert">
      {{ __('The maximum disk space of %1% GB available for uploading digital objects has been reached. Please contact your system administrator to increase the available disk space.', ['%1%' => config('app.upload_limit', 0)]) }}
    </div>

    <section class="actions mb-3">
      <a href="{{ $cancelUrl ?? url()->previous() }}" class="btn atom-btn-outline-light">{{ __('Cancel') }}</a>
    </section>

  @else

    @if($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ $formAction ?? '/object/addDigitalObject/' . ($resource->slug ?? '') }}" id="uploadForm">

      @csrf

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header" id="upload-heading">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#upload-collapse" aria-expanded="true" aria-controls="upload-collapse">
              {{ __('Upload a %1%', ['%1%' => mb_strtolower(config('app.ui_label_digitalobject', __('digital object')))]) }}
            </button>
          </h2>
          <div id="upload-collapse" class="accordion-collapse collapse show" aria-labelledby="upload-heading">
            <div class="accordion-body">
              @if(null == ($repository ?? null) || -1 == ($repository->uploadLimit ?? -1) || floatval(($repository->diskUsage ?? 0) / pow(10, 9)) < floatval($repository->uploadLimit ?? 0) || -1 == config('app.upload_limit', -1))

                <div class="mb-3">
                  <label class="form-label" for="file">{{ __('File') }}</label>
                  <input class="form-control" type="file" name="file" id="file">
                </div>

              @elseif(0 == ($repository->uploadLimit ?? 0))

                <div class="alert alert-warning" role="alert">
                  {!! __('Uploads for <a class="alert-link" href="%1%">%2%</a> are disabled', [
                      '%1%' => route('repository.show', $repository->slug ?? ''),
                      '%2%' => $repository->authorized_form_of_name ?? (string)$repository,
                  ]) !!}
                </div>

              @else

                <div class="alert alert-warning" role="alert">
                  {!! __('The upload limit of %1% GB for <a class="alert-link" href="%2%">%3%</a> has been reached', [
                      '%1%' => $repository->uploadLimit ?? 0,
                      '%2%' => route('repository.show', $repository->slug ?? ''),
                      '%3%' => $repository->authorized_form_of_name ?? (string)$repository,
                  ]) !!}
                </div>

              @endif
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header" id="external-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#external-collapse" aria-expanded="false" aria-controls="external-collapse">
              {{ __('Link to an external %1%', ['%1%' => mb_strtolower(config('app.ui_label_digitalobject', __('digital object')))]) }}
            </button>
          </h2>
          <div id="external-collapse" class="accordion-collapse collapse" aria-labelledby="external-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label" for="url">{{ __('URL') }}</label>
                <input class="form-control" type="url" name="url" id="url" value="">
              </div>
            </div>
          </div>
        </div>

        @php
        $showMerge = false;
        if (auth()->check() && auth()->user()->is_admin && isset($resource) && isset($resource->id)) {
            try {
                $showMerge = \Illuminate\Support\Facades\DB::table('atom_plugin')
                    ->where('name', 'ahgPreservationPlugin')
                    ->where('is_enabled', 1)
                    ->exists();
            } catch (\Exception $e) {
                $showMerge = false;
            }
        }
        @endphp

        @if($showMerge)
        <div class="accordion-item">
          <h2 class="accordion-header" id="merge-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#merge-collapse" aria-expanded="false" aria-controls="merge-collapse">
              <i class="fas fa-layer-group me-2"></i>{{ __('Merge images to PDF') }}
            </button>
          </h2>
          <div id="merge-collapse" class="accordion-collapse collapse" aria-labelledby="merge-heading">
            <div class="accordion-body">
              <div id="tpmAlert" class="alert" style="display: none;"></div>

              <input type="hidden" id="tpmJobId" value="">

              <div class="row g-3 mb-3">
                <div class="col-md-4">
                  <label for="tpmJobName" class="form-label">{{ __('Document Name') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" class="form-control" id="tpmJobName" value="{{ ($resource->identifier ?? '') ? ($resource->identifier . ' - Merged') : ('Merged Document ' . date('Y-m-d')) }}">
                </div>
                <div class="col-md-3">
                  <label for="tpmPdfStandard" class="form-label">{{ __('PDF Standard') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select id="tpmPdfStandard" class="form-select">
                    <option value="pdfa-2b" selected>PDF/A-2b</option>
                    <option value="pdfa-1b">PDF/A-1b</option>
                    <option value="pdfa-3b">PDF/A-3b</option>
                    <option value="pdf">Standard PDF</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <label for="tpmDpi" class="form-label">{{ __('DPI') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select id="tpmDpi" class="form-select">
                    <option value="150">150</option>
                    <option value="300" selected>300</option>
                    <option value="400">400</option>
                    <option value="600">600</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="tpmQuality" class="form-label">{{ __('Quality') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select id="tpmQuality" class="form-select">
                    <option value="70">70%</option>
                    <option value="85" selected>85%</option>
                    <option value="95">95%</option>
                    <option value="100">100%</option>
                  </select>
                </div>
              </div>

              <div id="tpmDropZone" class="border border-2 border-dashed rounded p-4 text-center bg-light mb-3" style="cursor:pointer;">
                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                <h6>{{ __('Drag and drop images here') }}</h6>
                <p class="text-muted small mb-0">{{ __('TIFF, JPEG, PNG, BMP, GIF') }}</p>
                <input type="file" id="tpmFileInput" class="d-none" multiple accept=".tif,.tiff,.jpg,.jpeg,.png,.bmp,.gif">
              </div>

              <div id="tpmProgressContainer" class="mb-3" style="display: none;">
                <div class="d-flex justify-content-between mb-1">
                  <small class="text-muted">{{ __('Uploading...') }}</small>
                  <small id="tpmProgressText" class="text-muted"></small>
                </div>
                <div class="progress" style="height: 6px;">
                  <div id="tpmProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                </div>
              </div>

              <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted"><strong>{{ __('Pages') }}</strong> <span id="tpmFileCount" class="badge bg-secondary">0</span></small>
                <small class="text-muted"><i class="fas fa-arrows-alt me-1"></i>{{ __('Drag to reorder') }}</small>
              </div>
              <div id="tpmFileList" class="border rounded bg-white mb-3" style="min-height: 60px; max-height: 300px; overflow-y: auto;">
                <div class="text-muted text-center py-3"><i class="fas fa-images me-1"></i>{{ __('No files uploaded yet') }}</div>
              </div>

              <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-danger btn-sm" id="tpmClearBtn" style="display: none;">
                  <i class="fas fa-trash me-1"></i>{{ __('Clear') }}
                </button>
                <button type="button" class="btn atom-btn-white" id="tpmCreateBtn" disabled>
                  <i class="fas fa-file-pdf me-1"></i>{{ __('Create & Link PDF') }}
                </button>
              </div>
            </div>
          </div>
        </div>
        @endif
      </div>

      <ul class="actions mb-3 nav gap-2">
        <li><a href="{{ $cancelUrl ?? url()->previous() }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Create') }}"></li>
      </ul>

    </form>

    @if($showMerge)
    <style>
    #tpmDropZone { transition: all 0.3s ease; }
    #tpmDropZone:hover, #tpmDropZone.drag-over { border-color: #0d6efd !important; background-color: #e8f4ff !important; }
    .tpm-file-item { transition: background-color 0.2s; cursor: grab; }
    .tpm-file-item:hover { background-color: #f8f9fa; }
    .sortable-ghost { opacity: 0.4; background-color: #cfe2ff !important; }
    </style>
    <script src="/plugins/ahgCorePlugin/web/js/vendor/Sortable.min.js"></script>
    <script>
    (function() {
        'use strict';
        var currentJob = null, uploadedFiles = [], sortable = null;
        var ioSlug = @json($resource->slug ?? '');
        var ioId = @json($resource->id ?? null);
        var createUrl = @json(route('tiffpdfmerge.create'));
        var uploadUrl = @json(route('tiffpdfmerge.upload'));
        var reorderUrl = @json(route('tiffpdfmerge.reorder'));
        var removeUrl = @json(route('tiffpdfmerge.removeFile'));
        var processUrl = @json(route('tiffpdfmerge.process'));
        var deleteUrl = @json(route('tiffpdfmerge.delete'));
        var recordUrl = @json(isset($resource->slug) ? route('informationobject.show', $resource->slug) : '/');

        document.addEventListener('DOMContentLoaded', function() {
            var fi = document.getElementById('tpmFileInput');
            if (fi) fi.addEventListener('change', function(e) { processFiles(Array.from(e.target.files)); e.target.value = ''; });
            var dz = document.getElementById('tpmDropZone');
            if (dz) {
                dz.addEventListener('click', function() { document.getElementById('tpmFileInput').click(); });
                dz.addEventListener('dragover', function(e) { e.preventDefault(); e.currentTarget.classList.add('drag-over'); });
                dz.addEventListener('dragleave', function(e) { e.preventDefault(); e.currentTarget.classList.remove('drag-over'); });
                dz.addEventListener('drop', function(e) { e.preventDefault(); e.currentTarget.classList.remove('drag-over'); processFiles(Array.from(e.dataTransfer.files)); });
            }
            var cb = document.getElementById('tpmCreateBtn');
            if (cb) cb.addEventListener('click', createPdf);
            var clb = document.getElementById('tpmClearBtn');
            if (clb) clb.addEventListener('click', clearAll);

            var mergeCollapse = document.getElementById('merge-collapse');
            if (mergeCollapse) {
                mergeCollapse.addEventListener('shown.bs.collapse', function() {
                    if (!currentJob) initMergeJob();
                });
            }
        });

        function initMergeJob() {
            fetch(createUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: new URLSearchParams({
                    job_name: document.getElementById('tpmJobName').value || 'Merged PDF',
                    information_object_id: ioId,
                    pdf_standard: document.getElementById('tpmPdfStandard').value,
                    dpi: document.getElementById('tpmDpi').value,
                    compression_quality: document.getElementById('tpmQuality').value,
                    attach_to_record: '1'
                })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) {
                    currentJob = data.job_id;
                    document.getElementById('tpmJobId').value = currentJob;
                    initSortable();
                } else {
                    showAlert('Failed to initialize: ' + data.error, 'danger');
                }
            }).catch(function(e) { showAlert('Failed to initialize: ' + e.message, 'danger'); });
        }

        function initSortable() {
            var fl = document.getElementById('tpmFileList');
            if (sortable) sortable.destroy();
            sortable = new Sortable(fl, { animation: 150, ghostClass: 'sortable-ghost', handle: '.drag-handle', onEnd: handleReorder });
        }

        function processFiles(files) {
            if (!currentJob) { showAlert('Open the merge section first.', 'warning'); return; }
            var validExts = ['tif', 'tiff', 'jpg', 'jpeg', 'png', 'bmp', 'gif'];
            var validFiles = files.filter(function(f) { return validExts.indexOf(f.name.split('.').pop().toLowerCase()) !== -1; });
            if (!validFiles.length) { showAlert('No valid image files.', 'warning'); return; }

            showProgress(true);
            var uploaded = 0;
            function uploadNext() {
                if (uploaded >= validFiles.length) {
                    updateProgress(validFiles.length, validFiles.length);
                    setTimeout(function() { showProgress(false); }, 500);
                    updateFileList();
                    updateButtons();
                    return;
                }
                updateProgress(uploaded, validFiles.length);
                var fd = new FormData();
                fd.append('file', validFiles[uploaded]);
                fd.append('job_id', currentJob);
                fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
                fetch(uploadUrl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(result) {
                        if (result.success && result.results) {
                            result.results.forEach(function(r) {
                                if (r.success) uploadedFiles.push({ id: r.file_id, name: r.filename, size: r.size || validFiles[uploaded].size, width: r.width, height: r.height });
                            });
                        }
                        uploaded++;
                        uploadNext();
                    })
                    .catch(function() { uploaded++; uploadNext(); });
            }
            uploadNext();
        }

        function updateFileList() {
            var c = document.getElementById('tpmFileList');
            document.getElementById('tpmFileCount').textContent = uploadedFiles.length;
            if (!uploadedFiles.length) {
                c.innerHTML = '<div class="text-muted text-center py-3"><i class="fas fa-images me-1"></i>No files uploaded yet</div>';
                return;
            }
            c.innerHTML = uploadedFiles.map(function(f, i) {
                return '<div class="tpm-file-item d-flex align-items-center p-2 border-bottom" data-file-id="' + f.id + '">' +
                    '<div class="drag-handle me-2 text-muted"><i class="fas fa-grip-vertical"></i></div>' +
                    '<span class="badge bg-primary rounded-pill me-2">' + (i + 1) + '</span>' +
                    '<i class="fas fa-file-image text-secondary me-2"></i>' +
                    '<div class="flex-grow-1"><small class="fw-semibold">' + escapeHtml(f.name) + '</small>' +
                    '<br><small class="text-muted">' + formatSize(f.size) + (f.width ? ' \u2022 ' + f.width + '\u00d7' + f.height + 'px' : '') + '</small></div>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" onclick="window.tpmRemoveFile(' + f.id + ')"><i class="fas fa-times"></i></button>' +
                    '</div>';
            }).join('');
        }

        function handleReorder(evt) {
            var m = uploadedFiles.splice(evt.oldIndex, 1)[0];
            uploadedFiles.splice(evt.newIndex, 0, m);
            fetch(reorderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: new URLSearchParams({ job_id: currentJob, 'file_order[]': uploadedFiles.map(function(f) { return f.id; }) })
            }).catch(function() {});
            updateFileList();
        }

        window.tpmRemoveFile = function(id) {
            if (!confirm('Remove this file?')) return;
            fetch(removeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: new URLSearchParams({ file_id: id })
            }).then(function() {
                uploadedFiles = uploadedFiles.filter(function(f) { return f.id !== id; });
                updateFileList();
                updateButtons();
            }).catch(function() { showAlert('Failed to remove file', 'danger'); });
        };

        function createPdf() {
            if (!currentJob || !uploadedFiles.length) { showAlert('Upload files first.', 'warning'); return; }
            var btn = document.getElementById('tpmCreateBtn'), orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
            btn.disabled = true;

            fetch(processUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: new URLSearchParams({ job_id: currentJob })
            }).then(function(r) { return r.json(); }).then(function(result) {
                if (result.success) {
                    showAlert('<i class="fas fa-check-circle me-2"></i><strong>PDF created and linked!</strong> Redirecting to record...', 'success');
                    setTimeout(function() { window.location.href = recordUrl; }, 2000);
                } else {
                    showAlert(result.error || 'Processing failed', 'danger');
                    btn.innerHTML = orig;
                    btn.disabled = false;
                }
            }).catch(function(e) {
                showAlert('Error: ' + e.message, 'danger');
                btn.innerHTML = orig;
                btn.disabled = false;
            });
        }

        function clearAll() {
            if (!confirm('Clear all files?')) return;
            if (currentJob) {
                fetch(deleteUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: new URLSearchParams({ job_id: currentJob }) }).catch(function() {});
            }
            uploadedFiles = [];
            currentJob = null;
            updateFileList();
            updateButtons();
            hideAlert();
            initMergeJob();
        }

        function updateButtons() {
            document.getElementById('tpmCreateBtn').disabled = !uploadedFiles.length;
            document.getElementById('tpmClearBtn').style.display = uploadedFiles.length ? 'inline-block' : 'none';
        }

        function showProgress(s) { document.getElementById('tpmProgressContainer').style.display = s ? 'block' : 'none'; }
        function updateProgress(c, t) { document.getElementById('tpmProgressBar').style.width = (t ? Math.round(c / t * 100) : 0) + '%'; document.getElementById('tpmProgressText').textContent = c + ' of ' + t; }
        function showAlert(m, t) { var a = document.getElementById('tpmAlert'); a.className = 'alert alert-' + t; a.innerHTML = m; a.style.display = 'block'; }
        function hideAlert() { document.getElementById('tpmAlert').style.display = 'none'; }
        function formatSize(b) { if (!b) return ''; var k = 1024, s = ['B', 'KB', 'MB', 'GB'], i = Math.floor(Math.log(b) / Math.log(k)); return (b / Math.pow(k, i)).toFixed(1) + ' ' + s[i]; }
        function escapeHtml(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    })();
    </script>
    @endif

  @endif

@endsection
