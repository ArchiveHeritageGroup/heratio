@extends('theme::layouts.1col')
@section('title', 'DAM Reports')
@section('body-class', 'dashboard')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-chart-pie me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">DAM Reports</h1></div>
  </div>
  <div class="row"><div class="col-md-3 mb-3"><div class="card text-center h-100"><div class="card-body"><i class="fas fa-photo-video fa-2x text-primary mb-2"></i><h5>{{ $totalAssets ?? 0 }}</h5><p class="text-muted small mb-0">Total Assets</p></div></div></div><div class="col-md-3 mb-3"><div class="card text-center h-100"><div class="card-body"><i class="fas fa-hdd fa-2x text-info mb-2"></i><h5>{{ $storageUsed ?? 0 }}</h5><p class="text-muted small mb-0">Storage Used</p></div></div></div></div>
@endsection
