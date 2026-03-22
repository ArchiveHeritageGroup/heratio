@extends('theme::layouts.1col')
@section('title', 'Identifier Settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-fingerprint me-2"></i>Identifier Settings</h1>
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Configure global identifier and accession numbering. Clear the application cache and rebuild the search index if you change the reference code separator.</div>

    <form method="post" action="{{ route('settings.identifier') }}">
      @csrf
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-box me-2"></i>Accession Numbering</div>
        <div class="card-body">
          <div class="form-check mb-3">
            <input type="hidden" name="settings[accession_mask_enabled]" value="0">
            <input class="form-check-input" type="checkbox" name="settings[accession_mask_enabled]" value="1" id="accession_mask_enabled" {{ ($settings['accession_mask_enabled'] ?? '') == '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="accession_mask_enabled">Accession mask enabled <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
          <div class="mb-3">
            <label class="form-label">Accession mask <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="settings[accession_mask]" class="form-control" value="{{ e($settings['accession_mask'] ?? '') }}">
            <small class="text-muted">e.g. %Y-%m-%d/#</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Accession counter <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" name="settings[accession_counter]" class="form-control" value="{{ e($settings['accession_counter'] ?? '0') }}">
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-fingerprint me-2"></i>Identifier Numbering</div>
        <div class="card-body">
          <div class="form-check mb-3">
            <input type="hidden" name="settings[identifier_mask_enabled]" value="0">
            <input class="form-check-input" type="checkbox" name="settings[identifier_mask_enabled]" value="1" id="identifier_mask_enabled" {{ ($settings['identifier_mask_enabled'] ?? '') == '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="identifier_mask_enabled">Identifier mask enabled <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
          <div class="mb-3">
            <label class="form-label">Identifier mask <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="settings[identifier_mask]" class="form-control" value="{{ e($settings['identifier_mask'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Identifier counter <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" name="settings[identifier_counter]" class="form-control" value="{{ e($settings['identifier_counter'] ?? '0') }}">
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-cog me-2"></i>Reference Code Options</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Reference code separator <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="settings[separator_character]" class="form-control" value="{{ e($settings['separator_character'] ?? '-') }}" maxlength="5">
          </div>
          <div class="form-check mb-3">
            <input type="hidden" name="settings[inherit_code_informationobject]" value="0">
            <input class="form-check-input" type="checkbox" name="settings[inherit_code_informationobject]" value="1" id="inherit_code_io" {{ ($settings['inherit_code_informationobject'] ?? '') == '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="inherit_code_io">Inherit reference code (information object) <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
          <div class="form-check mb-3">
            <input type="hidden" name="settings[inherit_code_dc_xml]" value="0">
            <input class="form-check-input" type="checkbox" name="settings[inherit_code_dc_xml]" value="1" id="inherit_code_dc" {{ ($settings['inherit_code_dc_xml'] ?? '') == '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="inherit_code_dc">Inherit reference code (DC XML) <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
          <div class="form-check mb-3">
            <input type="hidden" name="settings[prevent_duplicate_actor_identifiers]" value="0">
            <input class="form-check-input" type="checkbox" name="settings[prevent_duplicate_actor_identifiers]" value="1" id="prevent_dup" {{ ($settings['prevent_duplicate_actor_identifiers'] ?? '') == '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="prevent_dup">Prevent duplicate authority record identifiers <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
        </div>
      </div>

      <div class="alert alert-secondary mb-4" role="alert">
        <i class="fas fa-layer-group me-2"></i>
        <strong>Sector-specific numbering?</strong>
        Configure different numbering schemes per GLAM/DAM sector in the
        <a href="{{ route('settings.ahg', 'accession') }}">AHG Settings (Accession section)</a>.
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
