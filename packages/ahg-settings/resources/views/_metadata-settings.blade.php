{{-- Metadata extraction settings partial --}}
<div class="card mb-4">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff;">
    <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Metadata Extraction</h5>
  </div>
  <div class="card-body">
    <div class="form-check form-switch mb-3">
      <input type="hidden" name="settings[meta_extract_on_upload]" value="0">
      <input class="form-check-input" type="checkbox" name="settings[meta_extract_on_upload]" id="meta_extract" value="1" {{ ($settings['meta_extract_on_upload'] ?? '0') == '1' ? 'checked' : '' }}>
      <label class="form-check-label" for="meta_extract">Extract metadata on upload</label>
    </div>
    <div class="form-check form-switch mb-3">
      <input type="hidden" name="settings[meta_auto_populate]" value="0">
      <input class="form-check-input" type="checkbox" name="settings[meta_auto_populate]" id="meta_auto" value="1" {{ ($settings['meta_auto_populate'] ?? '0') == '1' ? 'checked' : '' }}>
      <label class="form-check-label" for="meta_auto">Auto-populate fields from metadata</label>
    </div>
    <h6 class="mt-3">File types</h6>
    @foreach(['images', 'pdf', 'office', 'video', 'audio'] as $type)
      <div class="form-check form-check-inline">
        <input type="hidden" name="settings[meta_{{ $type }}]" value="0">
        <input class="form-check-input" type="checkbox" name="settings[meta_{{ $type }}]" id="meta_{{ $type }}" value="1" {{ ($settings['meta_' . $type] ?? '0') == '1' ? 'checked' : '' }}>
        <label class="form-check-label" for="meta_{{ $type }}">{{ ucfirst($type) }}</label>
      </div>
    @endforeach
  </div>
</div>
