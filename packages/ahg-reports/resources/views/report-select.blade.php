@extends('theme::layouts.1col')
@section('title', 'Select Report Type')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-clipboard-list me-2"></i>{{ __('Select Report Type') }}</h1>

    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">Report Type</div>
      <div class="card-body">
        <form method="get" action="{{ route('reports.select') }}">
          <div class="mb-3">
            <label class="form-label">Type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <select name="objectType" class="form-select">
              <option value="accession">{{ __('Accession') }}</option>
              <option value="informationObject">{{ __('Archival Description') }}</option>
              <option value="authorityRecord">{{ __('Authority Record / Actor') }}</option>
              <option value="donor">{{ __('Donor') }}</option>
              <option value="physical_storage">{{ __('Physical Storage') }}</option>
              <option value="repository">{{ __('Repository / Archival Institution') }}</option>
            </select>
          </div>
          <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-check me-1"></i>{{ __('Select') }}</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection