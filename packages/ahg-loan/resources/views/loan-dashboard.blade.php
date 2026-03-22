@extends('theme::layouts.1col')
@section('title', 'Loan Dashboard')
@section('body-class', 'dashboard')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-tachometer-alt me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">Loan Dashboard</h1></div></div>
  <div class="row"><div class="col-md-3 mb-3"><div class="card text-center h-100"><div class="card-body"><i class="fas fa-exchange-alt fa-2x text-primary mb-2"></i><h5>{{ $activeCount ?? 0 }}</h5><p class="text-muted small mb-0">Active</p></div></div></div><div class="col-md-3 mb-3"><div class="card text-center h-100"><div class="card-body"><i class="fas fa-clock fa-2x text-danger mb-2"></i><h5>{{ $overdueCount ?? 0 }}</h5><p class="text-muted small mb-0">Overdue</p></div></div></div></div>
@endsection
