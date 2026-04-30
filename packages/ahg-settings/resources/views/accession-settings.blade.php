{{--
  Accession Management — intake workflow, numbering, appraisal, container and rights settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('accession')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Accession Management')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-inbox me-2"></i>Accession Management</h1>
<p class="text-muted">Intake workflow, numbering, appraisal, container and rights settings</p>
@endsection

@section('content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.accession') }}">
    @csrf

    {{-- Intake Queue --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-inbox me-2"></i>Intake Queue</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="accession_numbering_mask">{{ __('Numbering Mask') }}</label>
            <input type="text" class="form-control" id="accession_numbering_mask"
                   name="accession_numbering_mask"
                   value="{{ e($settings['accession_numbering_mask'] ?? 'ACC-{YYYY}-{####}') }}"
                   placeholder="ACC-{YYYY}-{####}">
            <div class="form-text">Pattern for auto-generated accession numbers. Use {YYYY} for year and {####} for sequence.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="accession_default_priority">{{ __('Default Priority') }}</label>
            <select class="form-select" id="accession_default_priority" name="accession_default_priority">
              <option value="low" {{ ($settings['accession_default_priority'] ?? 'normal') === 'low' ? 'selected' : '' }}>{{ __('Low') }}</option>
              <option value="normal" {{ ($settings['accession_default_priority'] ?? 'normal') === 'normal' ? 'selected' : '' }}>{{ __('Normal') }}</option>
              <option value="high" {{ ($settings['accession_default_priority'] ?? 'normal') === 'high' ? 'selected' : '' }}>{{ __('High') }}</option>
              <option value="urgent" {{ ($settings['accession_default_priority'] ?? 'normal') === 'urgent' ? 'selected' : '' }}>{{ __('Urgent') }}</option>
            </select>
            <div class="form-text">Default priority assigned to new accessions in the intake queue.</div>
          </div>
        </div>
        <div class="row g-3 mt-2">
          <div class="col-md-4">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="accession_auto_assign_enabled"
                     name="accession_auto_assign_enabled" value="true"
                     {{ ($settings['accession_auto_assign_enabled'] ?? 'false') === 'true' || ($settings['accession_auto_assign_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="accession_auto_assign_enabled">
                <strong>{{ __('Auto-Assign to Archivist') }}</strong>
              </label>
            </div>
            <div class="form-text">Automatically assign new accessions to the creating archivist.</div>
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="accession_require_donor_agreement"
                     name="accession_require_donor_agreement" value="true"
                     {{ ($settings['accession_require_donor_agreement'] ?? 'false') === 'true' || ($settings['accession_require_donor_agreement'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="accession_require_donor_agreement">
                <strong>{{ __('Require Donor Agreement') }}</strong>
              </label>
            </div>
            <div class="form-text">Donor agreement must be attached before an accession can be finalised.</div>
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="accession_require_appraisal"
                     name="accession_require_appraisal" value="true"
                     {{ ($settings['accession_require_appraisal'] ?? 'false') === 'true' || ($settings['accession_require_appraisal'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="accession_require_appraisal">
                <strong>{{ __('Require Appraisal') }}</strong>
              </label>
            </div>
            <div class="form-text">Appraisal must be completed before an accession can be finalised.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Containers & Rights --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-box me-2"></i>Containers &amp; Rights</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="accession_allow_container_barcodes"
                     name="accession_allow_container_barcodes" value="true"
                     {{ ($settings['accession_allow_container_barcodes'] ?? 'false') === 'true' || ($settings['accession_allow_container_barcodes'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="accession_allow_container_barcodes">
                <strong>{{ __('Allow Container Barcodes') }}</strong>
              </label>
            </div>
            <div class="form-text">Enable barcode scanning for linking containers to accessions.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="accession_rights_inheritance_enabled"
                     name="accession_rights_inheritance_enabled" value="true"
                     {{ ($settings['accession_rights_inheritance_enabled'] ?? 'false') === 'true' || ($settings['accession_rights_inheritance_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="accession_rights_inheritance_enabled">
                <strong>{{ __('Rights Inheritance') }}</strong>
              </label>
            </div>
            <div class="form-text">Automatically inherit rights from the donor agreement to created information objects.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Save --}}
    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>{{ __('Save') }}
      </button>
    </div>
  </form>
@endsection
