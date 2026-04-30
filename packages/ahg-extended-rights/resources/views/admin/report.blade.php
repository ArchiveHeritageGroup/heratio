@extends('theme::layouts.2col')

@section('title', 'Rights Report')
@section('body-class', 'admin rights-admin report')

@section('sidebar')
  @include('ahg-extended-rights::admin._sidebar')
@endsection

@section('title-block')
  <div class="d-flex justify-content-between align-items-center">
    <h1 class="mb-0">
      <i class="fas fa-chart-bar me-2"></i>
      @switch($type ?? 'summary')
        @case('embargoes') Embargoes Report @break
        @case('orphan_works') Orphan Works Report @break
        @case('tk_labels') TK Labels Report @break
        @default Rights Summary Report
      @endswitch
    </h1>
    @if(($type ?? 'summary') !== 'summary')
    <a href="{{ route('ext-rights-admin.report', ['type' => $type, 'export' => 'csv']) }}" class="btn btn-outline-success btn-sm">
      <i class="fas fa-file-csv me-1"></i>Export CSV
    </a>
    @endif
  </div>
@endsection

@section('content')
  {{-- Report Type Tabs --}}
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item">
      <a class="nav-link {{ ($type ?? 'summary') === 'summary' ? 'active' : '' }}" href="{{ route('ext-rights-admin.report', ['type' => 'summary']) }}">
        Summary
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ ($type ?? '') === 'embargoes' ? 'active' : '' }}" href="{{ route('ext-rights-admin.report', ['type' => 'embargoes']) }}">
        Embargoes
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ ($type ?? '') === 'orphan_works' ? 'active' : '' }}" href="{{ route('ext-rights-admin.report', ['type' => 'orphan_works']) }}">
        Orphan Works
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ ($type ?? '') === 'tk_labels' ? 'active' : '' }}" href="{{ route('ext-rights-admin.report', ['type' => 'tk_labels']) }}">
        TK Labels
      </a>
    </li>
  </ul>

  @if(($type ?? 'summary') === 'summary')
    {{-- Summary Statistics --}}
    @if(is_array($data))
    <div class="row">
      @foreach($data as $key => $value)
        @if(!is_array($value))
        <div class="col-md-3 mb-3">
          <div class="card text-center h-100">
            <div class="card-body">
              <h3 class="mb-0">{{ is_numeric($value) ? number_format($value) : $value }}</h3>
              <small class="text-muted">{{ ucwords(str_replace('_', ' ', $key)) }}</small>
            </div>
          </div>
        </div>
        @endif
      @endforeach
    </div>

    {{-- Breakdown charts --}}
    @if(!empty($data['by_basis']))
    <div class="card mt-4">
      <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <h5 class="mb-0">{{ __('Rights by Basis') }}</h5>
      </div>
      <div class="card-body p-0">
        <table class="table table-striped mb-0">
          <thead><tr><th>{{ __('Basis') }}</th><th class="text-end">{{ __('Count') }}</th></tr></thead>
          <tbody>
            @foreach($data['by_basis'] as $basis => $count)
            <tr><td>{{ ucfirst($basis) }}</td><td class="text-end">{{ number_format($count) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

    @if(!empty($data['by_rights_statement']))
    <div class="card mt-4">
      <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <h5 class="mb-0">{{ __('By Rights Statement') }}</h5>
      </div>
      <div class="card-body p-0">
        <table class="table table-striped mb-0">
          <thead><tr><th>{{ __('Statement Code') }}</th><th class="text-end">{{ __('Count') }}</th></tr></thead>
          <tbody>
            @foreach($data['by_rights_statement'] as $code => $count)
            <tr><td><span class="badge bg-secondary">{{ $code }}</span></td><td class="text-end">{{ number_format($count) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif
    @endif

  @else
    {{-- Data Table --}}
    @if(empty($data) || (is_object($data) && method_exists($data, 'isEmpty') && $data->isEmpty()))
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-2"></i>No data found for this report.
    </div>
    @else
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead class="table-light">
          <tr>
            @php
              $firstRow = is_array($data) ? reset($data) : ($data->first() ?? null);
              $cols = $firstRow ? array_keys((array) $firstRow) : [];
            @endphp
            @foreach($cols as $col)
            <th>{{ ucwords(str_replace('_', ' ', $col)) }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($data as $row)
          <tr>
            @foreach((array) $row as $col => $value)
            <td>
              @if(in_array($col, ['created_at', 'updated_at', 'start_date', 'end_date', 'expiry_date', 'search_started_date', 'search_completed_date']))
                {{ $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : '-' }}
              @elseif($col === 'object_title')
                <a href="{{ isset($row->slug) ? url($row->slug) : '#' }}">{{ $value ?? 'Untitled' }}</a>
              @elseif(in_array($col, ['status', 'category', 'code']))
                <span class="badge bg-secondary">{{ $value ?? '' }}</span>
              @else
                {{ $value ?? '-' }}
              @endif
            </td>
            @endforeach
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  @endif
@endsection
