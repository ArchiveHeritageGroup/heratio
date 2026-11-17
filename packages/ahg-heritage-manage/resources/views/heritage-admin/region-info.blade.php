@extends('theme::layouts.1col')
@section('title', $region->region_name)
@section('body-class', 'admin heritage')

@section('content')
@php
  $rulesByCategory = collect($rules)->groupBy(fn ($r) => $r->category ?: 'other');
  $sevClass = fn ($s) => match ($s) { 'error' => 'danger', 'warning' => 'warning', default => 'info' };
  $activate = fn ($class, $label, $icon = null) => [
    'url' => route('heritage.hadmin.region-activate', $region->region_code), 'class' => $class, 'icon' => $icon, 'label' => $label,
    'confirm' => __('Set :name as the active region?', ['name' => $region->region_name]),
  ];
  $install = fn ($class, $label, $icon = null) => [
    'url' => route('heritage.hadmin.region-install', $region->region_code), 'class' => $class, 'icon' => $icon, 'label' => $label,
    'confirm' => __('Install :name? This will add the accounting standard and compliance rules.', ['name' => $region->region_name]),
  ];
@endphp
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1 class="h3 mb-1"><i class="fas fa-globe me-2"></i>{{ $region->region_name }}</h1>
    <p class="mb-4"><a href="{{ route('heritage.hadmin.regions') }}"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Regions') }}</a></p>

    @if($isActive)
      <div class="alert alert-primary"><i class="fas fa-check-circle me-2"></i><strong>{{ __('This is the currently active region for compliance checking.') }}</strong></div>
    @elseif($region->is_installed)
      <div class="alert alert-success d-flex align-items-center gap-2">
        <span><i class="fas fa-check-circle me-2"></i>{{ __('This region is installed and ready to use.') }}</span>
        @include('ahg-heritage-manage::heritage-admin._post-action', $activate('btn btn-link alert-link p-0 align-baseline', __('Set as active')))
      </div>
    @else
      <div class="alert alert-secondary d-flex align-items-center gap-2">
        <span><i class="fas fa-info-circle me-2"></i>{{ __('This region is not installed.') }}</span>
        @include('ahg-heritage-manage::heritage-admin._post-action', $install('btn btn-link alert-link p-0 align-baseline', __('Install now')))
      </div>
    @endif

    <div class="row">
      <div class="col-lg-6">
        <div class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-info-circle me-2"></i>{{ __('Region Details') }}</div>
          <div class="card-body p-0">
            <table class="table table-bordered mb-0">
              <tr><th style="width:40%">{{ __('Code') }}</th><td><code>{{ $region->region_code }}</code></td></tr>
              <tr><th>{{ __('Name') }}</th><td>{{ $region->region_name }}</td></tr>
              <tr><th>{{ __('Default Currency') }}</th><td><span class="badge bg-primary">{{ $region->default_currency }}</span></td></tr>
              <tr><th>{{ __('Financial Year Start') }}</th><td>{{ $region->financial_year_start }}</td></tr>
              <tr><th>{{ __('Regulatory Body') }}</th><td>{{ $region->regulatory_body }}</td></tr>
              <tr><th>{{ __('Countries') }}</th><td>@foreach($region->countries as $country)<span class="badge bg-light text-dark me-1 mb-1">{{ $country }}</span>@endforeach</td></tr>
              @if($region->is_installed && $region->installed_at)
                <tr><th>{{ __('Installed') }}</th><td>{{ $region->installed_at }}</td></tr>
              @endif
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-balance-scale me-2"></i>{{ __('Accounting Standard') }}</div>
          <div class="card-body p-0">
            @if($standard)
              <table class="table table-bordered mb-0">
                <tr><th style="width:40%">{{ __('Code') }}</th><td><span class="badge bg-success">{{ $standard->code }}</span></td></tr>
                <tr><th>{{ __('Name') }}</th><td>{{ $standard->name }}</td></tr>
                <tr><th>{{ __('Description') }}</th><td class="small">{{ $standard->description }}</td></tr>
                <tr><th>{{ __('Capitalisation') }}</th><td>
                  @if($standard->capitalisation_required)<span class="badge bg-warning text-dark">{{ __('Required') }}</span>@else<span class="badge bg-secondary">{{ __('Optional') }}</span>@endif
                </td></tr>
                @php $methods = json_decode((string) $standard->valuation_methods, true) ?: []; @endphp
                @if($methods)
                  <tr><th>{{ __('Valuation Methods') }}</th><td>@foreach($methods as $m)<span class="badge bg-light text-dark me-1 mb-1">{{ $m }}</span>@endforeach</td></tr>
                @endif
              </table>
            @else
              <p class="text-muted p-3 mb-0"><i class="fas fa-exclamation-circle me-2"></i>{{ __('Standard not installed. Install the region to add the accounting standard.') }}</p>
            @endif
          </div>
        </div>
      </div>
    </div>

    @if($region->is_installed && count($rules))
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
          <span><i class="fas fa-check-square me-2"></i>{{ __('Compliance Rules') }} <span class="badge bg-secondary ms-2">{{ count($rules) }}</span></span>
          <a href="{{ route('heritage.hadmin.rule-list', ['standard_id' => $standard->id]) }}" class="btn btn-sm btn-light"><i class="fas fa-pencil-alt me-1"></i>{{ __('Manage Rules') }}</a>
        </div>
        <div class="card-body p-0">
          <div class="accordion accordion-flush" id="rulesAccordion">
            @foreach($rulesByCategory as $category => $categoryRules)
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ \Illuminate\Support\Str::slug($category) }}">
                    <span class="text-capitalize">{{ __(ucfirst($category)) }}</span>
                    <span class="badge bg-secondary ms-2">{{ count($categoryRules) }}</span>
                  </button>
                </h2>
                <div id="collapse-{{ \Illuminate\Support\Str::slug($category) }}" class="accordion-collapse collapse" data-bs-parent="#rulesAccordion">
                  <div class="accordion-body p-0">
                    <table class="table table-sm table-bordered table-hover mb-0">
                      <thead class="table-light"><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Status') }}</th></tr></thead>
                      <tbody>
                        @foreach($categoryRules as $rule)
                          <tr>
                            <td><code>{{ $rule->code }}</code></td>
                            <td>{{ $rule->name }}</td>
                            <td><span class="badge bg-{{ $sevClass($rule->severity) }}">{{ __(ucfirst((string) $rule->severity)) }}</span></td>
                            <td class="small text-muted">{{ $rule->reference ?: '-' }}</td>
                            <td>@if($rule->is_active)<span class="badge bg-success">{{ __('Active') }}</span>@else<span class="badge bg-secondary">{{ __('Inactive') }}</span>@endif</td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    <div class="mt-4 d-flex gap-2">
      @if($region->is_installed && !$isActive)
        @include('ahg-heritage-manage::heritage-admin._post-action', $activate('btn btn-primary', __('Set as Active Region'), 'fas fa-check-circle me-1'))
        @include('ahg-heritage-manage::heritage-admin._post-action', [
          'url' => route('heritage.hadmin.region-uninstall', $region->region_code), 'class' => 'btn btn-outline-danger',
          'icon' => 'fas fa-trash me-1', 'label' => __('Uninstall Region'),
          'confirm' => __('Uninstall :name? This will remove the standard and compliance rules.', ['name' => $region->region_name]),
        ])
      @elseif(!$region->is_installed)
        @include('ahg-heritage-manage::heritage-admin._post-action', $install('btn btn-success', __('Install Region'), 'fas fa-download me-1'))
      @endif
    </div>
  </div>
</div>
@endsection
