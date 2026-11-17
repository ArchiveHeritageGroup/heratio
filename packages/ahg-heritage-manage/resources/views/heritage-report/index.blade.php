@extends('theme::layouts.1col')
@section('title', __('Heritage Asset Reports'))
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1 class="h3 mb-3"><i class="fas fa-chart-bar me-2"></i>{{ __('Heritage Asset Reports') }}</h1>

    <div class="row">
      @foreach([
        ['heritage.hreport.asset-register', 'fa-list', __('Asset Register'), __('Complete register of all heritage assets with carrying amounts and recognition status.')],
        ['heritage.hreport.valuation', 'fa-dollar-sign', __('Valuation Report'), __('Assets with valuation history, revaluation surplus, and impairment losses.')],
        ['heritage.hreport.movement', 'fa-exchange-alt', __('Movement Report'), __('Track loans, transfers, exhibitions and storage changes by date range.')],
      ] as [$route, $icon, $title, $text])
        <div class="col-md-4 mb-4">
          <div class="card h-100">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas {{ $icon }} me-2"></i>{{ $title }}</div>
            <div class="card-body"><p class="mb-0">{{ $text }}</p></div>
            <div class="card-footer"><a href="{{ route($route) }}" class="btn atom-btn-white btn-sm">{{ __('View Report') }}</a></div>
          </div>
        </div>
      @endforeach
    </div>

    <h4 class="mt-2 mb-3">{{ __('Standard-Specific Reports') }}</h4>
    <div class="row">
      @forelse($standards as $std)
        <div class="col-md-4 col-lg-3 mb-3">
          <div class="card h-100">
            <div class="card-body text-center">
              <h5>{{ $std->code }}</h5>
              <small class="text-muted">{{ $std->country }}</small>
              <div class="mt-3"><a href="{{ route('heritage.hreport.asset-register', ['standard_id' => $std->id]) }}" class="btn btn-sm btn-outline-primary">{{ __('View') }}</a></div>
            </div>
          </div>
        </div>
      @empty
        <div class="col-12 text-muted">{{ __('No accounting standards enabled.') }}</div>
      @endforelse
    </div>

    <div class="mt-3">
      <a href="{{ route('heritage.accounting.dashboard') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Dashboard') }}</a>
    </div>
  </div>
</div>
@endsection
