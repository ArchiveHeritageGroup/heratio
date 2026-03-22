{{-- GLAM/DAM TIFF PDF Merge partial --}}
<div class="card mb-4">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff;">
    <h5 class="mb-0"><i class="fas fa-file-pdf me-2"></i>TIFF to PDF Merge</h5>
  </div>
  <div class="card-body">
    <p class="text-muted">Merge multiple TIFF images into a single PDF document for digital preservation.</p>
    <div class="row">
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">Output quality (DPI) <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="number" name="settings[tiff_pdf_merge_dpi]" class="form-control" value="{{ $settings['tiff_pdf_merge_dpi'] ?? '300' }}" min="72" max="600">
        </div>
      </div>
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">Max file size (MB) <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="number" name="settings[tiff_pdf_merge_max_size]" class="form-control" value="{{ $settings['tiff_pdf_merge_max_size'] ?? '100' }}" min="1">
        </div>
      </div>
    </div>
  </div>
</div>
