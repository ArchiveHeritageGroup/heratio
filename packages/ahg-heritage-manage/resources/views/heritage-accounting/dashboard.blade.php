@extends('theme::layouts.1col')
@section('title', __('Heritage Asset Accounting'))
@section('body-class', 'admin heritage')

@section('content')
@php
  $statusColors = ['recognised' => 'success', 'not_recognised' => 'secondary', 'pending' => 'warning', 'derecognised' => 'danger'];
  $cur = $currency ? ' (' . $currency . ')' : '';
@endphp
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h1 class="h3 mb-0"><i class="fas fa-landmark me-2"></i>{{ __('Heritage Asset Accounting') }}</h1>
      <a href="{{ route('heritage.accounting.add') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Asset') }}</a>
    </div>
    <p class="text-muted">{{ __('Multi-standard heritage asset financial accounting') }}</p>

    @if(count($standards) > 1)
    <form method="get" class="row g-2 align-items-center mb-3">
      <div class="col-auto"><label for="dashStd" class="form-label mb-0 small">{{ __('Accounting standard') }}</label></div>
      <div class="col-auto">
        <select id="dashStd" name="standard_id" class="form-select form-select-sm">
          <option value="">{{ __('All standards') }}</option>
          @foreach($standards as $s)
            <option value="{{ $s->id }}" {{ (int) $standardId === (int) $s->id ? 'selected' : '' }}>{{ $s->code }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-auto"><button type="submit" class="btn btn-sm atom-btn-white">{{ __('Apply') }}</button></div>
    </form>
    @endif

    {{-- Statistics --}}
    <div class="row mb-4">
      <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white h-100"><div class="card-body">
          <h6 class="text-white-50">{{ __('Total Assets') }}</h6>
          <h2 class="mb-0">{{ number_format($stats['total']) }}</h2>
        </div></div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card bg-success text-white h-100"><div class="card-body">
          <h6 class="text-white-50">{{ __('Recognised') }}</h6>
          <h2 class="mb-0">{{ number_format($stats['recognised']) }}</h2>
        </div></div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card bg-warning text-dark h-100"><div class="card-body">
          <h6>{{ __('Pending') }}</h6>
          <h2 class="mb-0">{{ number_format($stats['pending']) }}</h2>
        </div></div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="card bg-info text-white h-100"><div class="card-body">
          <h6 class="text-white-50">{{ __('Total Value') }}{{ $cur }}</h6>
          <h2 class="mb-0">{{ number_format($stats['total_value'], 2) }}</h2>
          <small class="text-white-50">{{ __('Carrying amount of recognised assets') }}</small>
        </div></div>
      </div>
    </div>

    <div class="row">
      {{-- Quick actions --}}
      <div class="col-lg-4 mb-4">
        <div class="card h-100">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-bolt me-2"></i>{{ __('Quick Actions') }}</div>
          <div class="card-body">
            <div class="d-grid gap-2">
              <a href="{{ route('heritage.accounting.browse') }}" class="btn btn-outline-primary"><i class="fas fa-list me-2"></i>{{ __('Browse Assets') }}</a>
              <a href="{{ route('heritage.accounting.add') }}" class="btn btn-outline-success"><i class="fas fa-plus me-2"></i>{{ __('Add Asset') }}</a>
              <a href="{{ route('heritage.hreport.index') }}" class="btn btn-outline-info"><i class="fas fa-chart-bar me-2"></i>{{ __('Reports') }}</a>
            </div>
          </div>
        </div>
      </div>

      {{-- By asset class --}}
      <div class="col-lg-4 mb-4">
        <div class="card h-100">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-th-large me-2"></i>{{ __('By Asset Class') }}</div>
          <div class="card-body">
            @if(count($stats['by_class']))
              <ul class="list-group list-group-flush">
                @foreach($stats['by_class'] as $class)
                  <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    {{ $class->class_name ?: __('Unclassified') }}
                    <span class="badge bg-primary rounded-pill">{{ $class->count }}</span>
                  </li>
                @endforeach
              </ul>
            @else
              <p class="text-muted text-center mb-0">{{ __('No assets yet') }}</p>
            @endif
          </div>
        </div>
      </div>

      {{-- Supported standards --}}
      <div class="col-lg-4 mb-4">
        <div class="card h-100">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-balance-scale me-2"></i>{{ __('Supported Standards') }}</div>
          <div class="card-body">
            <ul class="list-group list-group-flush">
              @forelse($standards as $standard)
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                  <div><strong>{{ $standard->code }}</strong><br><small class="text-muted">{{ $standard->country }}</small></div>
                  @if($standard->capitalisation_required)
                    <span class="badge bg-success">{{ __('Required') }}</span>
                  @else
                    <span class="badge bg-secondary">{{ __('Optional') }}</span>
                  @endif
                </li>
              @empty
                <li class="list-group-item px-0 text-muted">{{ __('No standards enabled. Enable them under Settings.') }}</li>
              @endforelse
            </ul>
            <small class="text-muted d-block mt-2"><i class="fas fa-info-circle me-1"></i>{{ __('Required = capitalisation mandatory; Optional = at discretion') }}</small>
          </div>
        </div>
      </div>
    </div>

    {{-- Recent assets --}}
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <span><i class="fas fa-clock me-2"></i>{{ __('Recent Assets') }}</span>
        <a href="{{ route('heritage.accounting.browse') }}" class="btn btn-sm btn-light">{{ __('View All') }}</a>
      </div>
      <div class="card-body p-0">
        @if(count($recentAssets))
          <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>{{ __('Identifier') }}</th>
                  <th>{{ __('Title') }}</th>
                  <th>{{ __('Class') }}</th>
                  <th>{{ __('Standard') }}</th>
                  <th>{{ __('Status') }}</th>
                  <th class="text-end">{{ __('Carrying Amount') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($recentAssets as $asset)
                  <tr>
                    <td><a href="{{ route('heritage.accounting.view', $asset->id) }}">{{ $asset->object_identifier ?: __('Asset #:id', ['id' => $asset->id]) }}</a></td>
                    <td>
                      @if($asset->object_slug)
                        <a href="{{ url('/' . $asset->object_slug) }}">{{ $asset->object_title ?: '-' }}</a>
                      @else
                        {{ $asset->object_title ?: '-' }}
                      @endif
                    </td>
                    <td>{{ $asset->class_name ?: '-' }}</td>
                    <td>{{ $asset->standard_code ?: '-' }}</td>
                    <td><span class="badge bg-{{ $statusColors[$asset->recognition_status] ?? 'secondary' }}">{{ $statusLabels[$asset->recognition_status] ?? ucfirst(str_replace('_', ' ', (string) $asset->recognition_status)) }}</span></td>
                    <td class="text-end">{{ number_format((float) $asset->current_carrying_amount, 2) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="text-center py-5">
            <i class="fas fa-landmark fa-3x text-muted mb-3"></i>
            <p class="text-muted">{{ __('No heritage assets recorded yet.') }}</p>
            <a href="{{ route('heritage.accounting.add') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i>{{ __('Add First Asset') }}</a>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
