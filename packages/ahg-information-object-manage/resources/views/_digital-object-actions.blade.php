@php /**
 * Digital Object Actions - includes TIFF to PDF Merge
 * Include this in your information object view template
 */

// Only show for users with edit permissions
if (!$sf_user->hasCredential(['contributor', 'editor', 'administrator'], false)) {
    return;
}

$resourceId = $resource->id ?? null;
$resourceSlug = $resource->slug ?? null; @endphp

<div class="digital-object-actions mb-3">
    <div class="btn-group" role="group" aria-label="Digital object actions">
        <!-- Standard Upload -->
        <a href="@php echo url_for(['module' => 'digitalobject', 'action' => 'edit', 'informationObject' => $resourceSlug]); @endphp" 
           class="btn btn-outline-primary btn-sm">
            <i class="fas fa-upload me-1"></i>
            Upload file
        </a>
        
        <!-- TIFF to PDF Merge -->
        <button type="button" 
                class="btn btn-outline-secondary btn-sm" 
                data-bs-toggle="modal" 
                data-bs-target="#tiffPdfMergeModal"
                title="Upload multiple TIFF/image files and merge into a single PDF/A document">
            <i class="fas fa-layer-group me-1"></i>
            Merge to PDF
        </button>
    </div>
</div>

<!-- TIFF to PDF Merge Modal -->
<div class="modal fade" id="tiffPdfMergeModal" tabindex="-1" aria-labelledby="tiffPdfMergeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="tiffPdfMergeModalLabel">
                    <i class="fas fa-file-pdf me-2"></i>
                    Merge Images to PDF
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <!-- Alert area -->
                <div id="tpmAlert" class="alert" style="display: none;"></div>

                <!-- Hidden fields -->
                <input type="hidden" id="tpmInformationObjectId" value="@php echo $resourceId; @endphp">
                <input type="hidden" id="tpmJobId" value="">

                <!-- Settings Row -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label for="tpmPdfStandard" class="form-label">PDF Standard</label>
                        <select id="tpmPdfStandard" class="form-select form-select-sm">
                            <option value="pdfa-2b" selected>PDF/A-2b (Recommended)</option>
                            <option value="pdfa-1b">PDF/A-1b</option>
                            <option value="pdfa-3b">PDF/A-3b</option>
                            <option value="pdf">Standard PDF</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="tpmDpi" class="form-label">Resolution (DPI)</label>
                        <select id="tpmDpi" class="form-select form-select-sm">
                            <option value="150">150 DPI (Screen)</option>
                            <option value="300" selected>300 DPI (Print)</option>
                            <option value="400">400 DPI (High Quality)</option>
                            <option value="600">600 DPI (Archival)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="tpmQuality" class="form-label">Quality</label>
                        <select id="tpmQuality" class="form-select form-select-sm">
                            <option value="70">70% (Smaller file)</option>
                            <option value="85" selected>85% (Balanced)</option>
                            <option value="95">95% (High quality)</option>
                            <option value="100">100% (Maximum)</option>
                        </select>
                    </div>
                </div>

                <!-- Drop Zone -->
                <div id="tpmDropZone" class="border border-2 border-dashed rounded p-4 text-center mb-3 bg-light">
                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                    <p class="mb-1"><strong>Drag and drop images here</strong></p>
                    <p class="text-muted small mb-2">or click to browse</p>
                    <p class="text-muted small mb-0">Supported: TIFF, JPEG, PNG, BMP, GIF</p>
                    <input type="file" id="tpmFileInput" class="d-none" multiple 
                           accept=".tif,.tiff,.jpg,.jpeg,.png,.bmp,.gif">
                </div>

                <!-- Progress Bar -->
                <div id="tpmProgressContainer" class="mb-3" style="display: none;">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Uploading...</small>
                        <small id="tpmProgressText" class="text-muted"></small>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div id="tpmProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%"></div>
                    </div>
                </div>

                <!-- File List -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">
                            <i class="fas fa-list me-1"></i>
                            Files to Merge
                            <span id="tpmFileCount" class="badge bg-secondary ms-1">0</span>
                        </h6>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Drag to reorder pages
                        </small>
                    </div>
                    <div id="tpmFileList" class="border rounded" style="max-height: 300px; overflow-y: auto;">
                        <div class="text-muted text-center py-4">No files uploaded yet</div>
                    </div>
                </div>

                <!-- Options -->
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="tpmAttachToRecord" checked>
                    <label class="form-check-label" for="tpmAttachToRecord">
                        Attach PDF to this record as digital object
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="tpmCreateBtn" disabled>
                    <i class="fas fa-file-pdf me-1"></i> Create PDF
                </button>
            </div>
        </div>
    </div>
</div>

<style @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
#tpmDropZone { cursor: pointer; min-height: 150px; transition: all 0.2s; }
#tpmDropZone:hover, #tpmDropZone.drag-over { border-color: #0d6efd !important; background-color: #e7f1ff !important; }
.tpm-file-item { transition: background-color 0.2s; }
.tpm-file-item:hover { background-color: #f8f9fa; }
.tpm-drag-handle { cursor: grab; }
.tpm-sortable-ghost { opacity: 0.4; background-color: #e7f1ff; }
</style>

<script src="/plugins/ahgThemeB5Plugin/js/sortable.min.js"></script>
<script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
(function() {
    'use strict';
    
    const API_BASE = '/api/tiff-pdf-merge';
    let currentJob = null;
    let uploadedFiles = [];
    let sortable = null;

    // Initialize when modal opens
    document.getElementById('tiffPdfMergeModal')?.addEventListener('show.bs.modal', initModal);

    async function initModal() {
        resetState();
        
        try {
            const infoObjId = document.getElementById('tpmInformationObjectId')?.value;
            const response = await fetch(API_BASE + '/jobs', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    job_name: 'Merged PDF ' + new Date().toISOString().slice(0, 19).replace('T', ' '),
                    information_object_id: infoObjId || null,
                    pdf_standard: document.getElementById('tpmPdfStandard')?.value || 'pdfa-2b',
                    dpi: parseInt(document.getElementById('tpmDpi')?.value || '300'),
                    compression_quality: parseInt(document.getElementById('tpmQuality')?.value || '85'),
                    attach_to_record: document.getElementById('tpmAttachToRecord')?.checked ?? true
                })
            });
            
            const data = await response.json();
            if (data.success) {
                currentJob = data.job_id;
                document.getElementById('tpmJobId').value = currentJob;
            } else {
                showAlert('Failed to create job: ' + data.error, 'danger');
            }
        } catch (error) {
            showAlert('Failed to create job: ' + error.message, 'danger');
        }

        initSortable();
    }

    function resetState() {
        currentJob = null;
        uploadedFiles = [];
        updateFileList();
        hideAlert();
        document.getElementById('tpmCreateBtn')?.setAttribute('disabled', 'disabled');
        document.getElementById('tpmProgressContainer').style.display = 'none';
    }

    function initSortable() {
        const fileList = document.getElementById('tpmFileList');
        if (sortable) sortable.destroy();
        sortable = new Sortable(fileList, {
            animation: 150,
            ghostClass: 'tpm-sortable-ghost',
            handle: '.tpm-drag-handle',
            onEnd: handleReorder
        });
    }

    // File input
    document.getElementById('tpmFileInput')?.addEventListener('change', function(e) {
        processFiles(Array.from(e.target.files));
        e.target.value = '';
    });

    // Drop zone
    const dropZone = document.getElementById('tpmDropZone');
    dropZone?.addEventListener('click', () => document.getElementById('tpmFileInput')?.click());
    dropZone?.addEventListener('dragover', (e) => { e.preventDefault(); e.currentTarget.classList.add('drag-over'); });
    dropZone?.addEventListener('dragleave', (e) => { e.preventDefault(); e.currentTarget.classList.remove('drag-over'); });
    dropZone?.addEventListener('drop', (e) => {
        e.preventDefault();
        e.currentTarget.classList.remove('drag-over');
        processFiles(Array.from(e.dataTransfer.files));
    });

    async function processFiles(files) {
        if (!currentJob) {
            showAlert('No active job. Please close and reopen the dialog.', 'danger');
            return;
        }

        const validExts = ['tif', 'tiff', 'jpg', 'jpeg', 'png', 'bmp', 'gif'];
        const validFiles = files.filter(f => validExts.includes(f.name.split('.').pop().toLowerCase()));

        if (validFiles.length === 0) {
            showAlert('No valid image files. Supported: TIFF, JPEG, PNG, BMP, GIF', 'warning');
            return;
        }

        document.getElementById('tpmProgressContainer').style.display = 'block';
        let uploaded = 0;

        for (const file of validFiles) {
            updateProgress(uploaded, validFiles.length);
            
            try {
                const formData = new FormData();
                formData.append('file', file);

                const response = await fetch(`${API_BASE}/jobs/${currentJob}/upload`, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    uploadedFiles.push({
                        id: result.file_id,
                        name: file.name,
                        size: file.size,
                        image_info: result.image_info
                    });
                }
            } catch (error) {
                console.error('Upload error:', error);
            }
            uploaded++;
        }

        updateProgress(uploaded, validFiles.length);
        setTimeout(() => document.getElementById('tpmProgressContainer').style.display = 'none', 500);
        updateFileList();

        if (uploadedFiles.length > 0) {
            document.getElementById('tpmCreateBtn')?.removeAttribute('disabled');
        }
    }

    function updateFileList() {
        const container = document.getElementById('tpmFileList');
        document.getElementById('tpmFileCount').textContent = uploadedFiles.length;

        if (uploadedFiles.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-4">No files uploaded yet</div>';
            return;
        }

        container.innerHTML = uploadedFiles.map((file, i) => `
            <div class="tpm-file-item d-flex align-items-center p-2 border-bottom" data-file-id="${file.id}">
                <i class="fas fa-grip-vertical text-muted me-3 tpm-drag-handle"></i>
                <span class="badge bg-secondary me-2">${i + 1}</span>
                <i class="fas fa-file-image text-primary me-2"></i>
                <div class="flex-grow-1">
                    <div class="fw-medium">${escapeHtml(file.name)}</div>
                    <small class="text-muted">${formatSize(file.size)}${file.image_info?.width ? ` • ${file.image_info.width}×${file.image_info.height}px` : ''}</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.tpmRemoveFile(${file.id})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
    }

    async function handleReorder(evt) {
        const moved = uploadedFiles.splice(evt.oldIndex, 1)[0];
        uploadedFiles.splice(evt.newIndex, 0, moved);

        try {
            await fetch(`${API_BASE}/jobs/${currentJob}/reorder`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ file_order: uploadedFiles.map(f => f.id) })
            });
        } catch (e) { console.error('Reorder error:', e); }

        updateFileList();
    }

    window.tpmRemoveFile = async function(fileId) {
        if (!confirm('Remove this file?')) return;
        
        try {
            await fetch(`${API_BASE}/jobs/${currentJob}/files/${fileId}`, { method: 'DELETE' });
            uploadedFiles = uploadedFiles.filter(f => f.id !== fileId);
            updateFileList();
            if (uploadedFiles.length === 0) {
                document.getElementById('tpmCreateBtn')?.setAttribute('disabled', 'disabled');
            }
        } catch (e) {
            showAlert('Failed to remove file', 'danger');
        }
    };

    // Create PDF button
    document.getElementById('tpmCreateBtn')?.addEventListener('click', async function() {
        if (!currentJob || uploadedFiles.length === 0) return;

        const btn = this;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Creating PDF...';
        btn.setAttribute('disabled', 'disabled');

        try {
            const response = await fetch(`${API_BASE}/jobs/${currentJob}/process`, { method: 'POST' });
            const result = await response.json();

            if (result.success) {
                showAlert(`
                    <strong>PDF created successfully!</strong> ${result.pages} page(s)
                    <a href="${API_BASE}/jobs/${currentJob}/download" class="btn btn-sm btn-success ms-2" target="_blank">
                        <i class="fas fa-download me-1"></i> Download
                    </a>
                `, 'success');

                if (result.digital_object_id) {
                    setTimeout(() => {
                        if (confirm('PDF attached to record. Reload page to see it?')) {
                            location.reload();
                        }
                    }, 1500);
                }
            } else {
                showAlert('Failed: ' + result.error, 'danger');
                btn.innerHTML = originalHtml;
                btn.removeAttribute('disabled');
            }
        } catch (error) {
            showAlert('Error: ' + error.message, 'danger');
            btn.innerHTML = originalHtml;
            btn.removeAttribute('disabled');
        }
    });

    function updateProgress(current, total) {
        const pct = total > 0 ? Math.round((current / total) * 100) : 0;
        document.getElementById('tpmProgressBar').style.width = pct + '%';
        document.getElementById('tpmProgressText').textContent = `${current} of ${total}`;
    }

    function showAlert(msg, type) {
        const alert = document.getElementById('tpmAlert');
        alert.className = 'alert alert-' + type;
        alert.innerHTML = msg;
        alert.style.display = 'block';
    }

    function hideAlert() {
        document.getElementById('tpmAlert').style.display = 'none';
    }

    function formatSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024, sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
</script>
