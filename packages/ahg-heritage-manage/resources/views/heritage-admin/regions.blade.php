@extends('theme::layouts.1col')
@section('title', __('Heritage Accounting Regions'))
@section('body-class', 'admin heritage')

@section('content')
@php
  $activeRegion = $activeConfig->region_code ?? null;
  $activeRegionData = $activeRegion ? $regions->firstWhere('region_code', $activeRegion) : null;
@endphp
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1 class="h3 mb-1"><i class="fas fa-globe me-2"></i>{{ __('Heritage Accounting Regions') }}</h1>
    <p class="mb-4"><a href="{{ route('heritage.hadmin.index') }}"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Heritage Admin') }}</a></p>

    @if($activeRegionData)
      <div class="alert alert-primary d-flex align-items-center mb-4">
        <i class="fas fa-check-circle me-3 fs-4"></i>
        <div>
          <strong>{{ __('Active Region:') }}</strong> {{ $activeRegionData->region_name }}
          <span class="badge bg-white text-primary ms-2">{{ $activeConfig->currency ?? $activeRegionData->default_currency }}</span>
        </div>
      </div>
    @else
      <div class="alert alert-warning mb-4"><i class="fas fa-exclamation-triangle me-2"></i>{{ __('No active region set. Install and activate a region to enable compliance checking.') }}</div>
    @endif

    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>{{ __('About Regional Standards') }}</h5>
        <p class="card-text text-muted mb-0">{{ __('Each region implements specific accounting standards for heritage assets. Install only the regions you need - this keeps your database lean and compliance rules relevant to your jurisdiction.') }}</p>
      </div>
    </div>

    <div class="row g-4">
      @forelse($regions as $region)
        @php
          $isInstalled = (bool) $region->is_installed;
          $isActive = $region->region_code === $activeRegion;
          $rulesCount = $rulesByRegion[$region->region_code] ?? 0;
          $countries = $region->countries;
        @endphp
        <div class="col-md-6 col-xl-4">
          <div class="card h-100 {{ $isActive ? 'border-primary border-2' : ($isInstalled ? 'border-success' : '') }}">
            <div class="card-header {{ $isActive ? 'bg-primary text-white' : ($isInstalled ? 'bg-success bg-opacity-10' : 'bg-light') }}">
              <div class="d-flex justify-content-between align-items-center">
                <strong>{{ $region->region_name }}</strong>
                @if($isActive)
                  <span class="badge bg-white text-primary">{{ __('ACTIVE') }}</span>
                @elseif($isInstalled)
                  <span class="badge bg-success">{{ __('Installed') }}</span>
                @else
                  <span class="badge bg-secondary">{{ __('Not Installed') }}</span>
                @endif
              </div>
            </div>
            <div class="card-body">
              <p class="card-text small text-muted mb-2">
                <i class="fas fa-map-marker-alt me-1"></i>{{ implode(', ', array_slice($countries, 0, 3)) }}
                @if(count($countries) > 3)<span class="text-muted">{{ __('+:n more', ['n' => count($countries) - 3]) }}</span>@endif
              </p>
              <div class="mb-3">
                <span class="badge bg-light text-dark me-1"><i class="fas fa-money-bill me-1"></i>{{ $region->default_currency }}</span>
                @if($isInstalled)
                  <span class="badge bg-info text-white"><i class="fas fa-check-square me-1"></i>{{ __(':n rules', ['n' => $rulesCount]) }}</span>
                @endif
              </div>
              <p class="card-text small"><strong>{{ __('Regulatory Body:') }}</strong><br>{{ $region->regulatory_body }}</p>
              @if($isInstalled && $region->installed_at)
                <p class="card-text small text-muted"><i class="fas fa-calendar-check me-1"></i>{{ __('Installed:') }} {{ \Illuminate\Support\Carbon::parse($region->installed_at)->format('Y-m-d') }}</p>
              @endif
            </div>
            <div class="card-footer bg-transparent d-flex flex-wrap gap-1">
              @if(!$isInstalled)
                @include('ahg-heritage-manage::heritage-admin._post-action', [
                  'url' => route('heritage.hadmin.region-install', $region->region_code),
                  'class' => 'btn btn-success btn-sm', 'icon' => 'fas fa-download me-1', 'label' => __('Install'),
                  'confirm' => __('Install :name? This will add the accounting standard and compliance rules.', ['name' => $region->region_name]),
                ])
              @else
                <a href="{{ route('heritage.hadmin.region-info', $region->region_code) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-info-circle me-1"></i>{{ __('Details') }}</a>
                @if(!$isActive)
                  @include('ahg-heritage-manage::heritage-admin._post-action', [
                    'url' => route('heritage.hadmin.region-activate', $region->region_code),
                    'class' => 'btn btn-primary btn-sm', 'icon' => 'fas fa-check-circle me-1', 'label' => __('Activate'),
                    'confirm' => __('Set :name as the active region?', ['name' => $region->region_name]),
                  ])
                  @include('ahg-heritage-manage::heritage-admin._post-action', [
                    'url' => route('heritage.hadmin.region-uninstall', $region->region_code),
                    'class' => 'btn btn-outline-danger btn-sm', 'icon' => 'fas fa-trash', 'title' => __('Uninstall'),
                    'confirm' => __('Uninstall :name? This will remove the standard and compliance rules.', ['name' => $region->region_name]),
                  ])
                @endif
              @endif
            </div>
          </div>
        </div>
      @empty
        <div class="col-12 text-muted">{{ __('No regions are configured.') }}</div>
      @endforelse
    </div>
  </div>
</div>
@endsection
