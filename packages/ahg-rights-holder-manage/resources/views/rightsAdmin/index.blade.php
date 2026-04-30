@extends('theme::layouts.2col')

@section('title', 'Rights Administration')
@section('body-class', 'admin rights-admin')

@section('sidebar')
  @include('ahg-rights-holder-manage::rightsAdmin._sidebar')
@endsection

@section('title-block')
  <h1 class="mb-0">{{ __('Rights Administration') }}</h1>
@endsection

@section('content')
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card text-center border-primary">
        <div class="card-body py-3"><h3 class="text-primary mb-0">{{ number_format($stats['total_rights'] ?? 0) }}</h3><small class="text-muted">{{ __('Total Rights Records') }}</small></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center border-warning">
        <div class="card-body py-3"><h3 class="text-warning mb-0">{{ number_format($stats['active_embargoes'] ?? 0) }}</h3><small class="text-muted">{{ __('Active Embargoes') }}</small></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center border-info">
        <div class="card-body py-3"><h3 class="text-info mb-0">{{ number_format($stats['orphan_works'] ?? 0) }}</h3><small class="text-muted">{{ __('Orphan Works') }}</small></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0">{{ __('Quick Actions') }}</h5>
    </div>
    <div class="card-body">
      <a href="{{ route('rights-admin.embargoes') }}" class="btn atom-btn-white me-2"><i class="fas fa-lock me-1"></i>{{ __('Manage Embargoes') }}</a>
      <a href="{{ route('rights-admin.orphan-works') }}" class="btn atom-btn-white me-2"><i class="fas fa-question-circle me-1"></i>{{ __('Orphan Works') }}</a>
      <a href="{{ route('rights-admin.report') }}" class="btn atom-btn-white"><i class="fas fa-chart-bar me-1"></i>{{ __('Generate Report') }}</a>
    </div>
  </div>
@endsection
