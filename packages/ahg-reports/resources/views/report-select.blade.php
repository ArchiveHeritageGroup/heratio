@extends('theme::layouts.1col')
@section('title', 'Select Report Type')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-clipboard-list me-2"></i>Select Report Type</h1>

    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">Report Type</div>
      <div class="card-body">
        <form method="get" action="{{ route('reports.select') }}">
          <div class="mb-3">
            <label class="form-label">Type</label>
            <select name="objectType" class="form-select">
              <option value="accession">Accession</option>
              <option value="informationObject">Archival Description</option>
              <option value="authorityRecord">Authority Record / Actor</option>
              <option value="donor">Donor</option>
              <option value="physical_storage">Physical Storage</option>
              <option value="repository">Repository / Archival Institution</option>
            </select>
          </div>
          <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-check me-1"></i>Select</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection