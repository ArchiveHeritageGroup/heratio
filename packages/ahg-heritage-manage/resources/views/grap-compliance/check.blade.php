@extends('theme::layouts.1col')
@section('title', __('Compliance Check'))
@section('body-class', 'admin heritage')

@section('content')
@php
  $sum = $complianceResult['summary'];
  $scoreClass = $sum['status'] === 'compliant' ? 'text-success' : ($sum['status'] === 'partially_compliant' ? 'text-warning' : 'text-danger');
  $catColors = ['recognition' => 'primary', 'measurement' => 'info', 'disclosure' => 'secondary', 'documentation' => 'dark'];
@endphp
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h1 class="h3 mb-1"><i class="fas fa-clipboard-check me-2"></i>{{ __('Compliance Check') }}</h1>
        <p class="text-muted mb-0">
          {{ $asset->object_identifier ?: __('Asset #:id', ['id' => $asset->id]) }}
          @if($asset->object_title) - {{ $asset->object_title }}@endif
          @if($asset->standard_code)<span class="badge bg-light text-dark ms-2">{{ $asset->standard_code }}</span>@endif
        </p>
      </div>
      <a href="{{ route('heritage.accounting.view', $asset->id) }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Asset') }}</a>
    </div>

    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card text-center h-100"><div class="card-body">
          <div class="display-5 {{ $scoreClass }}">{{ $sum['score'] }}%</div>
          <p class="mb-0">{{ __('Compliance Score') }}</p>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card bg-success text-white text-center h-100"><div class="card-body"><h2>{{ $sum['passed'] }}</h2><p class="mb-0">{{ __('Passed') }}</p></div></div>
      </div>
      <div class="col-md-3">
        <div class="card bg-warning text-dark text-center h-100"><div class="card-body"><h2>{{ $sum['warnings'] }}</h2><p class="mb-0">{{ __('Warnings') }}</p></div></div>
      </div>
      <div class="col-md-3">
        <div class="card bg-danger text-white text-center h-100"><div class="card-body"><h2>{{ $sum['failed'] }}</h2><p class="mb-0">{{ __('Failed') }}</p></div></div>
      </div>
    </div>

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-list-check me-2"></i>{{ __('Compliance Checklist') }}</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:100px">{{ __('Code') }}</th>
                <th>{{ __('Check') }}</th>
                <th>{{ __('Reference') }}</th>
                <th>{{ __('Category') }}</th>
                <th class="text-center">{{ __('Status') }}</th>
                <th>{{ __('Message') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($complianceResult['checks'] as $check)
                <tr>
                  <td><code>{{ $check['code'] }}</code></td>
                  <td><strong>{{ __($check['title']) }}</strong></td>
                  <td><small class="text-muted">{{ $check['reference'] }}</small></td>
                  <td><span class="badge bg-{{ $catColors[$check['category']] ?? 'secondary' }}">{{ __(ucfirst($check['category'])) }}</span></td>
                  <td class="text-center">
                    @if($check['status'] === 'passed')
                      <span class="badge bg-success"><i class="fas fa-check"></i> {{ __('Passed') }}</span>
                    @elseif($check['status'] === 'warning')
                      <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle"></i> {{ __('Warning') }}</span>
                    @else
                      <span class="badge bg-danger"><i class="fas fa-times"></i> {{ __('Failed') }}</span>
                    @endif
                  </td>
                  <td><small>{{ __($check['message']) }}</small></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer"><small class="text-muted"><i class="fas fa-clock me-1"></i>{{ __('Checked at: :t', ['t' => $complianceResult['checked_at']]) }}</small></div>
    </div>

    <div class="mt-4">
      <a href="{{ route('heritage.accounting.edit', $asset->id) }}" class="btn btn-warning"><i class="fas fa-edit me-1"></i>{{ __('Edit Asset to Fix Issues') }}</a>
      <a href="{{ route('heritage.grap.dashboard') }}" class="btn btn-outline-secondary ms-2"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to GRAP Dashboard') }}</a>
    </div>
  </div>
</div>
@endsection
