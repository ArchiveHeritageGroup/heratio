{{-- TIFF/PDF Merge Modal --}}
<div class="modal fade" id="tiffPdfMergeModal" tabindex="-1" aria-labelledby="tiffPdfMergeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="modal-title" id="tiffPdfMergeModalLabel"><i class="fas fa-file-pdf me-2"></i>TIFF/PDF Merge</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="{{ Route::has('preservation.tiffpdfmerge.store') ? route('preservation.tiffpdfmerge.store') : '#' }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Output Format <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="output_format" class="form-select">
              <option value="pdf">PDF</option>
              <option value="tiff">Multi-page TIFF</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Source Files <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="file" name="files[]" class="form-control" multiple accept=".tif,.tiff,.pdf,.jpg,.jpeg,.png">
            <div class="form-text">Select TIFF, PDF, or image files to merge.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Output Filename <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="output_filename" class="form-control" placeholder="merged-output">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn atom-btn-white"><i class="fas fa-cogs me-1"></i>Start Merge</button>
        </div>
      </form>
    </div>
  </div>
</div>