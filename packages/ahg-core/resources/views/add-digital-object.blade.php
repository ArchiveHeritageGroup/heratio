@php decorate_with('layout_1col.php'); @endphp

@php slot('title'); @endphp
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ __(
          'Link %1%',
          ['%1%' => mb_strtolower(sfConfig::get('app_ui_label_digitalobject'))]
      ) }}
    </h1>
    <span class="small" id="heading-label">
      @php echo $resourceDescription; @endphp
    </span>
  </div>
@php end_slot(); @endphp

@php slot('content'); @endphp

  @if(QubitDigitalObject::reachedAppUploadLimit())

    <div class="alert alert-warning" role="alert">
      {{ __('The maximum disk space of %1% GB available for uploading digital objects has been reached. Please contact your AtoM system administrator to increase the available disk space.', ['%1%' => sfConfig::get('app_upload_limit')]) }}
    </div>

    <section class="actions mb-3">
      @php echo link_to(__('Cancel'), [$resource, 'module' => $sf_request->module], ['class' => 'btn atom-btn-outline-light']); @endphp
    </section>

  @php } else { @endphp

    @php echo $form->renderGlobalErrors(); @endphp

    @php echo $form->renderFormTag(url_for([$resource, 'module' => 'object', 'action' => 'addDigitalObject']), ['id' => 'uploadForm']); @endphp

      @php echo $form->renderHiddenFields(); @endphp

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header" id="upload-heading">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#upload-collapse" aria-expanded="true" aria-controls="upload-collapse">
              {{ __('Upload a %1%', ['%1%' => mb_strtolower(sfConfig::get('app_ui_label_digitalobject'))]) }}
            </button>
          </h2>
          <div id="upload-collapse" class="accordion-collapse collapse show" aria-labelledby="upload-heading">
            <div class="accordion-body">
              @if(null == $repository || -1 == $repository->uploadLimit || floatval($repository->getDiskUsage() / pow(10, 9)) < floatval($repository->uploadLimit) || -1 == sfConfig::get('app_upload_limit'))

                @php echo render_field($form->file); @endphp

              @php } elseif (0 == $repository->uploadLimit) { @endphp

                <div class="alert alert-warning" role="alert">
                  {{ __('Uploads for <a class="alert-link" href="%1%">%2%</a> are disabled', [
                      '%1%' => url_for([$repository, 'module' => 'repository']),
                      '%2%' => $repository->__toString(),
                  ]) }}
                </div>

              @php } else { @endphp

                <div class="alert alert-warning" role="alert">
                  {{ __('The upload limit of %1% GB for <a class="alert-link" href="%2%">%3%</a> has been reached', [
                      '%1%' => $repository->uploadLimit,
                      '%2%' => url_for([$repository, 'module' => 'repository']),
                      '%3%' => $repository->__toString(), ]) }}
                </div>

              @endforeach
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header" id="external-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#external-collapse" aria-expanded="false" aria-controls="external-collapse">
              {{ __('Link to an external %1%', ['%1%' => mb_strtolower(sfConfig::get('app_ui_label_digitalobject'))]) }}
            </button>
          </h2>
          <div id="external-collapse" class="accordion-collapse collapse" aria-labelledby="external-heading">
            <div class="accordion-body">
              @php echo render_field($form->url); @endphp
            </div>
          </div>
        </div>

        @php // Show merge option if ahgPreservationPlugin is enabled and user has credentials
        $showMerge = false;
        $rawResource = sfOutputEscaper::unescape($resource);
        if ($sf_user->hasCredential(['contributor', 'editor', 'administrator'], false) && $rawResource instanceof QubitInformationObject) {
            try {
                $showMerge = \Illuminate\Database\Capsule\Manager::table('atom_plugin')
                    ->where('name', 'ahgPreservationPlugin')
                    ->where('is_enabled', 1)
                    ->exists();
            } catch (\Exception $e) {
                $showMerge = false;
            }
        } @endphp

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
                  <input type="text" class="form-control" id="tpmJobName" value="{{ $resource->identifier ? $resource->identifier . ' - Merged' : 'Merged Document ' . date('Y-m-d') }}">
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
        @endforeach
      </div>

      <ul class="actions mb-3 nav gap-2">
        <li>@php echo link_to(__('Cancel'), [$resource, 'module' => $sf_request->module], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Create') }}"></li>
      </ul>

    </form>

    @if($showMerge)
    @php $n = sfConfig::get('csp_nonce', ''); $nonceAttr = $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp
    <style @php echo $nonceAttr; @endphp>
    #tpmDropZone { transition: all 0.3s ease; }
    #tpmDropZone:hover, #tpmDropZone.drag-over { border-color: #0d6efd !important; background-color: #e8f4ff !important; }
    .tpm-file-item { transition: background-color 0.2s; cursor: grab; }
    .tpm-file-item:hover { background-color: #f8f9fa; }
    .sortable-ghost { opacity: 0.4; background-color: #cfe2ff !important; }
    </style>
    <script src="/plugins/ahgCorePlugin/web/js/vendor/Sortable.min.js" @php echo $nonceAttr; @endphp></script>
    <script @php echo $nonceAttr; @endphp>
    (function() {
        'use strict';
        var currentJob = null, uploadedFiles = [], sortable = null;
        var ioSlug = @php echo json_encode($resource->slug ?? ''); @endphp;
        var ioId = @php echo json_encode($resource->id); @endphp;
        var createUrl = @php echo json_encode(route('tiffpdfmerge.create')); @endphp;
        var uploadUrl = @php echo json_encode(route('tiffpdfmerge.upload')); @endphp;
        var reorderUrl = @php echo json_encode(route('tiffpdfmerge.reorder')); @endphp;
        var removeUrl = @php echo json_encode(route('tiffpdfmerge.removeFile')); @endphp;
        var processUrl = @php echo json_encode(route('tiffpdfmerge.process')); @endphp;
        var deleteUrl = @php echo json_encode(route('tiffpdfmerge.delete')); @endphp;
        var recordUrl = @php echo json_encode(url_for([$resource, 'module' => 'informationobject'])); @endphp;

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

            // Collapse other accordions when merge is expanded
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
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ job_id: currentJob, 'file_order[]': uploadedFiles.map(function(f) { return f.id; }) })
            }).catch(function() {});
            updateFileList();
        }

        window.tpmRemoveFile = function(id) {
            if (!confirm('Remove this file?')) return;
            fetch(removeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
                fetch(deleteUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ job_id: currentJob }) }).catch(function() {});
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
    @endforeach

  @endforeach

@php end_slot(); @endphp
