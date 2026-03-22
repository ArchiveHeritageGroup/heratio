@extends('theme::layouts.1col')
@section('title', 'Donor Agreements')
@section('body-class', 'dashboard')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-tachometer-alt me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">Donor Agreements</h1></div></div>
  <div class="row"><div class="col-md-4 mb-3"><div class="card text-center h-100"><div class="card-body"><i class="fas fa-file-signature fa-2x text-success mb-2"></i><h5>{{ $activeCount ?? 0 }}</h5><p class="text-muted small mb-0">Active</p></div></div></div><div class="col-md-4 mb-3"><div class="card text-center h-100"><div class="card-body"><i class="fas fa-clock fa-2x text-warning mb-2"></i><h5>{{ $expiringCount ?? 0 }}</h5><p class="text-muted small mb-0">Expiring Soon</p></div></div></div></div>
@endsection
