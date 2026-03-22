@extends('theme::layouts.1col')
@section('title', 'Integrity - Export')
@section('body-class', 'admin integrity export')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Export</h1><span class="small text-muted">Digital object integrity management</span></div>
  </div>
@endsection
@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Export Integrity Data</h5></div>
  <div class="card-body">
    <form method="get" action="{{ route('integrity.export') }}">
      <div class="row">
        <div class="col-md-4 mb-3"><label class="form-label">Format</label><select name="format" class="form-select"><option value="csv">CSV</option><option value="json">JSON</option></select></div>
        <div class="col-md-4 mb-3"><label class="form-label">Date From</label><input type="date" name="from" class="form-control"></div>
        <div class="col-md-4 mb-3"><label class="form-label">Date To</label><input type="date" name="to" class="form-control"></div>
      </div>
      <button type="submit" class="btn atom-btn-white"><i class="fas fa-download me-1"></i>Export</button>
    </form>
  </div>
</div>
<div class="mt-3"><a href="{{ route('integrity.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a></div>
@endsection
