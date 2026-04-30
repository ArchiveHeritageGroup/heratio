{{-- Museum Reports Dashboard — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Museum Reports Dashboard')
@section('body-class', 'museum-reports index')
@section('sidebar')
<div class="sidebar-content">
  <h4>{{ __('Museum Reports') }}</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('museum.report-objects') }}"><i class="fas fa-cube me-2"></i>{{ __('Objects') }}</a></li>
    <li><a href="{{ route('museum.report-creators') }}"><i class="fas fa-user-edit me-2"></i>{{ __('Creators') }}</a></li>
    <li><a href="{{ route('museum.report-condition') }}"><i class="fas fa-heartbeat me-2"></i>{{ __('Condition') }}</a></li>
    <li><a href="{{ route('museum.report-provenance') }}"><i class="fas fa-history me-2"></i>{{ __('Provenance') }}</a></li>
    <li><a href="{{ route('museum.report-style-period') }}"><i class="fas fa-theater-masks me-2"></i>{{ __('Style & Period') }}</a></li>
    <li><a href="{{ route('museum.report-materials') }}"><i class="fas fa-layer-group me-2"></i>{{ __('Materials') }}</a></li>
  </ul>
  <hr>
  <a href="{{ url('/glam/browse') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back to Browse') }}</a>
</div>
@endsection
@section('title-block')<h1><i class="fas fa-landmark me-2"></i>Museum Reports Dashboard</h1>@endsection
@section('content')
<div class="museum-reports-dashboard">
  <div class="row mb-4">
    <div class="col-md-4"><div class="card text-center bg-primary text-white"><div class="card-body"><h2>{{ number_format($stats['totalObjects'] ?? 0) }}</h2><p class="mb-0">Total Objects</p></div></div></div>
    <div class="col-md-4"><div class="card text-center bg-success text-white"><div class="card-body"><h2>{{ number_format($stats['withProvenance'] ?? 0) }}</h2><p class="mb-0">With Provenance</p></div></div></div>
    <div class="col-md-4"><div class="card text-center bg-info text-white"><div class="card-body"><h2>{{ count($stats['byCondition'] ?? []) }}</h2><p class="mb-0">Condition Assessed</p></div></div></div>
  </div>
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fas fa-shapes me-2"></i>By Work Type</h5></div>
        <ul class="list-group list-group-flush">
          @forelse($stats['byWorkType'] ?? [] as $type)
          <li class="list-group-item d-flex justify-content-between">{{ e($type->work_type ?? '') }} <span class="badge bg-primary">{{ $type->count ?? 0 }}</span></li>
          @empty
          <li class="list-group-item text-muted">No work types recorded</li>
          @endforelse
        </ul>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fas fa-heartbeat me-2"></i>By Condition</h5></div>
        <ul class="list-group list-group-flush">
          @forelse($stats['byCondition'] ?? [] as $cond)
          <li class="list-group-item d-flex justify-content-between">{{ ucfirst($cond->condition_term ?? '') }} <span class="badge bg-{{ in_array($cond->condition_term ?? '', ['poor','critical']) ? 'danger' : 'success' }}">{{ $cond->count ?? 0 }}</span></li>
          @empty
          <li class="list-group-item text-muted">No conditions recorded</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection
