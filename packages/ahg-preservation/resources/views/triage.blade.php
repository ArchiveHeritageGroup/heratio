{{-- heratio#1200 Collections-wide preservation triage: risk-ranked at-risk list. --}}
@extends('theme::layouts.1col')

@section('title', 'Preservation Triage')
@section('body-class', 'admin preservation')

@section('content')
@php
  $bandMeta = [
    'critical' => ['bg-danger', __('Critical')],
    'high'     => ['bg-warning text-dark', __('High')],
    'medium'   => ['bg-info text-dark', __('Medium')],
    'low'      => ['bg-success', __('Low')],
  ];
@endphp
<div class="row">
  <div class="col-md-3">
    @include('ahg-preservation::_menu')
  </div>
  <div class="col-md-9">
    <h1 class="mb-0"><i class="fas fa-triangle-exclamation text-warning"></i> {{ __('Preservation Triage') }}</h1>
    <p class="text-muted mb-3">{{ __('A collection-wide priority list. Each assessed record is scored on its latest condition report - rating, priority, overdue checks and how stale the assessment is - worst first.') }}</p>

    <div class="row g-2 mb-4">
      @foreach($bandMeta as $key => $meta)
        <div class="col-6 col-lg-3">
          <div class="card text-center h-100"><div class="card-body py-2">
            <div class="h4 mb-0"><span class="badge {{ $meta[0] }}">{{ $summary['bands'][$key] ?? 0 }}</span></div>
            <div class="small text-muted">{{ $meta[1] }}</div>
          </div></div>
        </div>
      @endforeach
    </div>
    <div class="row g-2 mb-4">
      <div class="col-6 col-lg-3"><div class="card text-center"><div class="card-body py-2"><div class="h5 mb-0">{{ number_format($summary['assessed']) }}</div><div class="small text-muted">{{ __('Assessed') }}</div></div></div></div>
      <div class="col-6 col-lg-3"><div class="card text-center"><div class="card-body py-2"><div class="h5 mb-0 text-danger">{{ number_format($summary['overdue']) }}</div><div class="small text-muted">{{ __('Overdue check') }}</div></div></div></div>
      <div class="col-6 col-lg-3"><div class="card text-center"><div class="card-body py-2"><div class="h5 mb-0">{{ number_format($summary['total_objects']) }}</div><div class="small text-muted">{{ __('Total records') }}</div></div></div></div>
      <div class="col-6 col-lg-3"><div class="card text-center"><div class="card-body py-2"><div class="h5 mb-0 text-muted">{{ number_format($summary['unassessed']) }}</div><div class="small text-muted">{{ __('Never assessed') }}</div></div></div></div>
    </div>

    @if(empty($items))
      <div class="alert alert-info">{{ __('No condition assessments recorded yet. Add condition reports to records to populate the triage list.') }}</div>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light"><tr>
            <th>{{ __('Risk') }}</th><th>{{ __('Record') }}</th><th>{{ __('Rating') }}</th>
            <th>{{ __('Priority') }}</th><th>{{ __('Last assessed') }}</th><th>{{ __('Next check') }}</th>
            <th>{{ __('Recommendation') }}</th>
          </tr></thead>
          <tbody>
            @foreach($items as $it)
              @php [$cls, $lbl] = $bandMeta[$it['band']]; @endphp
              <tr>
                <td><span class="badge {{ $cls }}" title="{{ __('score') }} {{ $it['score'] }}/100">{{ $lbl }} · {{ $it['score'] }}</span></td>
                <td>
                  @if($it['slug'])<a href="/{{ $it['slug'] }}" target="_blank" rel="noopener">{{ $it['title'] }}</a>@else{{ $it['title'] }}@endif
                </td>
                <td class="small">{{ ucfirst($it['rating'] ?? '—') }}</td>
                <td class="small">{{ ucfirst($it['priority'] ?? '—') }}</td>
                <td class="small text-muted">{{ $it['assessed'] ?? '—' }}</td>
                <td class="small {{ $it['overdue'] ? 'text-danger fw-bold' : 'text-muted' }}">
                  {{ $it['next_check'] ?? '—' }}@if($it['overdue']) <i class="fas fa-clock" title="{{ __('Overdue') }}"></i>@endif
                </td>
                <td class="small text-muted">{{ $it['recommendation'] ?: '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <p class="small text-muted"><i class="fas fa-info-circle me-1"></i>{{ __('First slice: scored from condition reports. Digital format-obsolescence risk and budget-aware allocation are planned next.') }}</p>
    @endif
  </div>
</div>
@endsection
