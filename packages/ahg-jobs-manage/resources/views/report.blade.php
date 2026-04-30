@extends('theme::layouts.1col')
@section('title', 'Jobs Report')
@section('body-class', 'dashboard')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-chart-bar me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">{{ __('Jobs Report') }}</h1></div></div>
  <div class="row"><div class="col-md-3 mb-3"><div class="card text-center h-100"><div class="card-body"><i class="fas fa-tasks fa-2x text-primary mb-2"></i><h5>{{ $totalJobs ?? 0 }}</h5><p class="text-muted small mb-0">Total Jobs</p></div></div></div><div class="col-md-3 mb-3"><div class="card text-center h-100"><div class="card-body"><i class="fas fa-check-circle fa-2x text-success mb-2"></i><h5>{{ $completedJobs ?? 0 }}</h5><p class="text-muted small mb-0">Completed</p></div></div></div></div>
@endsection
