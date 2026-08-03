{{--
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems / AGPL v3+
  OCAP overlay dashboard - traffic-light per record across the 4 principles.
--}}
@extends('theme::layouts.1col')

@section('title', 'OCAP® Dashboard')

@section('content')
@php
  $badge = function (string $s): string {
      return match ($s) {
          'green' => '<span class="badge bg-success">green</span>',
          'amber' => '<span class="badge bg-warning text-dark">amber</span>',
          'red'   => '<span class="badge bg-danger">red</span>',
          default => '<span class="badge bg-secondary">n/a</span>',
      };
  };
@endphp

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="fas fa-shield-alt me-2"></i>{{ __('OCAP® Compliance Dashboard') }}</h1>
    <a href="{{ route('ahgicip.ocap-settings') }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-cog me-1"></i>{{ __('Settings') }}
    </a>
  </div>

  {{-- Tiles --}}
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-success"><div class="card-body text-center">
        <div class="display-6 text-success">{{ $agg['green'] }}</div>
        <div class="text-muted small">All four principles satisfied</div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card border-warning"><div class="card-body text-center">
        <div class="display-6 text-warning">{{ $agg['amber'] }}</div>
        <div class="text-muted small">Partial compliance</div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card border-danger"><div class="card-body text-center">
        <div class="display-6 text-danger">{{ $agg['red'] }}</div>
        <div class="text-muted small">Action required</div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card"><div class="card-body text-center">
        <div class="display-6 text-muted">{{ $agg['total'] }}</div>
        <div class="text-muted small">Records assessed</div>
      </div></div>
    </div>
  </div>

  {{-- Per-principle breakdown --}}
  <div class="card mb-4">
    <div class="card-header bg-light"><strong>{{ __('Per-principle breakdown') }}</strong></div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr><th>{{ __('Principle') }}</th><th class="text-center">{{ __('Green') }}</th><th class="text-center">{{ __('Amber') }}</th><th class="text-center">{{ __('Red') }}</th><th class="text-center">{{ __('N/A') }}</th></tr>
          </thead>
          <tbody>
            @foreach(\AhgIcip\Services\OcapService::PRINCIPLES as $p)
              @php $row = $agg['by_principle'][$p] ?? []; @endphp
              <tr>
                <td class="text-capitalize">{{ $p }}</td>
                <td class="text-center text-success">{{ $row['green'] ?? 0 }}</td>
                <td class="text-center text-warning">{{ $row['amber'] ?? 0 }}</td>
                <td class="text-center text-danger">{{ $row['red'] ?? 0 }}</td>
                <td class="text-center text-muted">{{ $row['n/a'] ?? 0 }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Records --}}
  <div class="card">
    <div class="card-header bg-light"><strong>{{ __('Records with ICIP signal') }}</strong> ({{ count($rollup) }})</div>
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('Record') }}</th>
            <th class="text-center">{{ __('Ownership') }}</th>
            <th class="text-center">{{ __('Control') }}</th>
            <th class="text-center">{{ __('Access') }}</th>
            <th class="text-center">{{ __('Possession') }}</th>
            <th class="text-center">{{ __('Overall') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($rollup as $r)
            <tr>
              <td>
                @if($r['slug'])
                  <a href="{{ url('/' . $r['slug']) }}">{{ $r['title'] }}</a>
                @else
                  {{ $r['title'] }}
                @endif
                <small class="text-muted ms-1">#{{ $r['io_id'] }}</small>
              </td>
              <td class="text-center">{!! $badge($r['ownership']) !!}</td>
              <td class="text-center">{!! $badge($r['control']) !!}</td>
              <td class="text-center">{!! $badge($r['access']) !!}</td>
              <td class="text-center">{!! $badge($r['possession']) !!}</td>
              <td class="text-center">{!! $badge($r['overall']) !!}</td>
              <td class="text-end">
                <a href="{{ route('ahgicip.object-icip', ['id' => $r['io_id']]) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('ICIP detail') }}">
                  <i class="fas fa-eye"></i>
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No records with ICIP signal yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- #1409: OCAP governance event log - append-only trail of protocol changes --}}
  <div class="card mt-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
      <strong><i class="fas fa-scroll me-1"></i>{{ __('Governance events') }}</strong>
      <span class="text-muted small">{{ __('Cultural-protocol changes (set / clear), newest first') }}</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead><tr>
          <th>{{ __('When') }}</th><th>{{ __('Event') }}</th><th>{{ __('Entity') }}</th>
          <th>{{ __('Label') }}</th><th>{{ __('Condition') }}</th><th>{{ __('By') }}</th>
        </tr></thead>
        <tbody>
          @forelse(($events ?? collect()) as $e)
            <tr>
              <td class="text-nowrap"><small class="text-muted">{{ $e->created_at ? \Illuminate\Support\Carbon::parse($e->created_at)->format('Y-m-d H:i') : '' }}</small></td>
              <td><span class="badge bg-{{ str_contains($e->event_type, 'clear') ? 'secondary' : 'success' }}">{{ str_replace('_', ' ', $e->event_type) }}</span></td>
              <td><small>{{ $e->entity_type }} #{{ $e->entity_id }}</small></td>
              <td>{{ trim(($e->label_family ? strtoupper($e->label_family) . ' ' : '') . ($e->label_code ?? '')) ?: '-' }}</td>
              <td>{{ $e->access_condition ? ucwords(str_replace('_', ' ', $e->access_condition)) : '-' }}</td>
              <td><small class="text-muted">{{ $e->actor_user_id ? ('user #' . $e->actor_user_id) : __('system') }}</small></td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No governance events recorded yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
