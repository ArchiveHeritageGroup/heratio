{{-- TIFF PDF Merge Settings partial --}}
<div class="card mb-4">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff;">
    <h5 class="mb-0"><i class="fas fa-file-pdf me-2"></i>TIFF to PDF Merge Settings</h5>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">Output quality (DPI) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <input type="number" name="settings[tiff_pdf_dpi]" class="form-control" value="{{ $mergeSettings['tiff_pdf_dpi'] ?? '300' }}" min="72" max="600">
        </div>
        <div class="mb-3">
          <label class="form-label">Page size <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <select name="settings[tiff_pdf_page_size]" class="form-select">
            @foreach(['A4' => 'A4', 'Letter' => 'US Letter', 'A3' => 'A3', 'Legal' => 'US Legal'] as $val => $label)
              <option value="{{ $val }}" {{ ($mergeSettings['tiff_pdf_page_size'] ?? 'A4') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">Max pages per PDF <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <input type="number" name="settings[tiff_pdf_max_pages]" class="form-control" value="{{ $mergeSettings['tiff_pdf_max_pages'] ?? '500' }}" min="1">
        </div>
        <div class="form-check form-switch mb-3">
          <input type="hidden" name="settings[tiff_pdf_ocr_enabled]" value="0">
          <input class="form-check-input" type="checkbox" name="settings[tiff_pdf_ocr_enabled]" id="tiff_ocr" value="1" {{ ($mergeSettings['tiff_pdf_ocr_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="tiff_ocr">Enable OCR on merged PDFs <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
        </div>
        <div class="form-check form-switch mb-3">
          <input type="hidden" name="settings[tiff_pdf_compress]" value="0">
          <input class="form-check-input" type="checkbox" name="settings[tiff_pdf_compress]" id="tiff_compress" value="1" {{ ($mergeSettings['tiff_pdf_compress'] ?? '1') == '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="tiff_compress">Compress output PDF <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
        </div>
      </div>
    </div>
  </div>
</div>
