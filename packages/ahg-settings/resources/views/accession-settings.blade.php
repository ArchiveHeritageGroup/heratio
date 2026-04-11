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
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.accession') }}">
    @csrf

    {{-- ── Card 1: Intake Queue ── --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-inbox me-2"></i>Intake Queue</h5>
      </div>
      <div class="card-body">
        {{-- Row 1: Numbering Mask + Default Priority --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="accession_numbering_mask" class="form-label fw-bold">Numbering Mask</label>
            <input type="text" class="form-control" id="accession_numbering_mask"
                   name="accession_numbering_mask"
                   value="{{ $settings['accession_numbering_mask'] ?? '' }}"
                   placeholder="ACC-{YYYY}-{####}">
            <div class="form-text">Pattern for auto-generated accession numbers. Use <code>{YYYY}</code> for year, <code>{####}</code> for sequence.</div>
          </div>
          <div class="col-md-6">
            <label for="accession_default_priority" class="form-label fw-bold">Default Priority</label>
            <select class="form-select" id="accession_default_priority" name="accession_default_priority">
              @php $currentPriority = $settings['accession_default_priority'] ?? 'normal'; @endphp
              @foreach(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $val => $label)
                <option value="{{ $val }}" {{ $currentPriority === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
            <div class="form-text">Default priority assigned to new accession records.</div>
          </div>
        </div>

        {{-- Row 2: Three toggles --}}
        <div class="row">
          <div class="col-md-4">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="accession_auto_assign_enabled"
                     name="accession_auto_assign_enabled" value="1"
                     {{ ($settings['accession_auto_assign_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="accession_auto_assign_enabled">Auto-Assign to Archivist</label>
            </div>
            <div class="form-text">Automatically assign new accessions to the default archivist.</div>
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="accession_require_donor_agreement"
                     name="accession_require_donor_agreement" value="1"
                     {{ ($settings['accession_require_donor_agreement'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="accession_require_donor_agreement">Require Donor Agreement</label>
            </div>
            <div class="form-text">Require a signed donor agreement before finalising an accession.</div>
          </div>
          <div class="col-md-4">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="accession_require_appraisal"
                     name="accession_require_appraisal" value="1"
                     {{ ($settings['accession_require_appraisal'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="accession_require_appraisal">Require Appraisal</label>
            </div>
            <div class="form-text">Require an appraisal step before an accession can be completed.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- ── Card 2: Containers & Rights ── --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-box me-2"></i>Containers &amp; Rights</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="accession_allow_container_barcodes"
                     name="accession_allow_container_barcodes" value="1"
                     {{ ($settings['accession_allow_container_barcodes'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="accession_allow_container_barcodes">Allow Container Barcodes</label>
            </div>
            <div class="form-text">Enable barcode scanning for physical containers during accessioning.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="accession_rights_inheritance_enabled"
                     name="accession_rights_inheritance_enabled" value="1"
                     {{ ($settings['accession_rights_inheritance_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
              <label class="form-check-label fw-bold" for="accession_rights_inheritance_enabled">Rights Inheritance</label>
            </div>
            <div class="form-text">Automatically inherit rights statements from the parent fonds or collection.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Save --}}
    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Settings
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>Save
      </button>
    </div>
  </form>
@endsection
