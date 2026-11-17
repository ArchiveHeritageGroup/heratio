@extends('theme::layouts.1col')
@section('title', __('Heritage Accounting Administration'))
@section('body-class', 'admin heritage')

@section('content')
@php
  $activeRegion = $activeConfig->region_code ?? null;
  $activeRegionData = $activeRegion ? $regions->firstWhere('region_code', $activeRegion) : null;
  $installedCount = $regions->where('is_installed', 1)->count();
@endphp
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1 class="h3 mb-3"><i class="fas fa-landmark me-2"></i>{{ __('Heritage Accounting Administration') }}</h1>

    @if($activeRegionData)
      <div class="alert alert-primary d-flex align-items-center mb-4">
        <i class="fas fa-globe me-3 fs-4"></i>
        <div>
          <strong>{{ __('Active Region:') }}</strong> {{ $activeRegionData->region_name }}
          <span class="badge bg-white text-primary ms-2">{{ $activeConfig->currency ?? $activeRegionData->default_currency }}</span>
        </div>
        <a href="{{ route('heritage.hadmin.regions') }}" class="btn btn-sm btn-outline-primary ms-auto"><i class="fas fa-cog me-1"></i>{{ __('Change') }}</a>
      </div>
    @else
      <div class="alert alert-warning mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>{{ __('No active region set.') }}
        <a href="{{ route('heritage.hadmin.regions') }}" class="alert-link">{{ __('Configure regions') }}</a>
      </div>
    @endif

    <div class="row mb-4">
      <div class="col-md-3"><div class="card text-center h-100"><div class="card-body"><h3 class="text-primary">{{ number_format($stats['total_assets']) }}</h3><small class="text-muted">{{ __('Total Assets') }}</small></div></div></div>
      <div class="col-md-3"><div class="card text-center h-100"><div class="card-body"><h3 class="text-success">{{ $installedCount }}</h3><small class="text-muted">{{ __('Regions Installed') }}</small></div></div></div>
      <div class="col-md-3"><div class="card text-center h-100"><div class="card-body"><h3 class="text-info">{{ count($standards) }}</h3><small class="text-muted">{{ __('Standards') }}</small></div></div></div>
      <div class="col-md-3"><div class="card text-center h-100"><div class="card-body"><h3 class="text-secondary">{{ count($regions) }}</h3><small class="text-muted">{{ __('Available Regions') }}</small></div></div></div>
    </div>

    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Assets by Standard') }}</div>
      <div class="card-body p-0">
        @if(count($stats['by_standard']))
          <table class="table table-bordered table-striped mb-0">
            <thead class="table-light"><tr><th>{{ __('Standard') }}</th><th>{{ __('Code') }}</th><th class="text-end">{{ __('Assets') }}</th></tr></thead>
            <tbody>
              @foreach($stats['by_standard'] as $row)
                <tr><td>{{ $row->name }}</td><td><code>{{ $row->code }}</code></td><td class="text-end">{{ number_format($row->count) }}</td></tr>
              @endforeach
            </tbody>
          </table>
        @else
          <p class="text-muted p-3 mb-0">{{ __('No assets recorded yet.') }}</p>
        @endif
      </div>
    </div>

    <div class="row">
      @foreach([
        ['heritage.hadmin.regions', 'fa-globe', __('Regions'), __('Install and configure regional accounting standards (IPSAS, GRAP, FRS, etc.)'), __('Manage Regions')],
        ['heritage.hadmin.standard-list', 'fa-balance-scale', __('Standards'), __('View and edit installed accounting standards.'), __('Manage Standards')],
        ['heritage.hadmin.rule-list', 'fa-check-square', __('Compliance Rules'), __('Configure compliance validation rules for each standard.'), __('Manage Rules')],
        ['heritage.hreport.index', 'fa-chart-bar', __('Reports'), __('Generate heritage asset accounting reports.'), __('View Reports')],
      ] as [$route, $icon, $title, $text, $btn])
        <div class="col-md-6 col-lg-3 mb-4">
          <div class="card h-100">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas {{ $icon }} me-2"></i>{{ $title }}</div>
            <div class="card-body d-flex flex-column">
              <p class="small flex-grow-1">{{ $text }}</p>
              <a href="{{ route($route) }}" class="btn atom-btn-white btn-sm">{{ $btn }}</a>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>
@endsection
