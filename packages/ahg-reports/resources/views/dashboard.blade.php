@extends('theme::layouts.1col')
@section('title', 'Reports')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <div class="d-flex align-items-center mb-3">
      <i class="fas fa-3x fa-chart-bar me-3"></i>
      <div><h1 class="mb-0">Reports Dashboard</h1><span class="small text-muted">Overview of all entities</span></div>
    </div>

    <div class="row g-3 mb-4">
      @foreach([
        ['descriptions', 'Descriptions', 'primary', 'fa-file-alt', 'reports.descriptions'],
        ['authorities', 'Authority records', 'success', 'fa-user', 'reports.authorities'],
        ['repositories', 'Repositories', 'info', 'fa-university', 'reports.repositories'],
        ['accessions', 'Accessions', 'warning', 'fa-inbox', 'reports.accessions'],
        ['donors', 'Donors', 'secondary', 'fa-hand-holding-heart', 'reports.donors'],
        ['digital_objects', 'Digital objects', 'danger', 'fa-photo-video', null],
        ['physical_storage', 'Physical storage', 'dark', 'fa-box', 'reports.storage'],
        ['users', 'Users', 'primary', 'fa-users', null],
      ] as [$key, $label, $color, $icon, $route])
        <div class="col-6 col-md-3">
          <div class="card text-center h-100">
            <div class="card-body py-3">
              <i class="fas {{ $icon }} fa-2x text-{{ $color }} mb-2"></i>
              <div class="fs-3 fw-bold text-{{ $color }}">{{ number_format($stats[$key] ?? 0) }}</div>
              <div class="small text-muted">{{ $label }}</div>
            </div>
            @if($route)
              <div class="card-footer bg-transparent border-0 pb-3">
                <a href="{{ route($route) }}" class="btn btn-sm btn-outline-{{ $color }}">View report</a>
              </div>
            @endif
          </div>
        </div>
      @endforeach
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card border-success">
          <div class="card-body text-center">
            <div class="fs-3 fw-bold text-success">{{ number_format($stats['published'] ?? 0) }}</div>
            <div class="small text-muted">Published</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-warning">
          <div class="card-body text-center">
            <div class="fs-3 fw-bold text-warning">{{ number_format($stats['draft'] ?? 0) }}</div>
            <div class="small text-muted">Draft</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-info">
          <div class="card-body text-center">
            <div class="fs-3 fw-bold text-info">{{ number_format($stats['recent_updates'] ?? 0) }}</div>
            <div class="small text-muted">Updated (7 days)</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
