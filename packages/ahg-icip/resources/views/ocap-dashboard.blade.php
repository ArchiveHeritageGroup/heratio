{{--
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems / AGPL v3+
  OCAP overlay dashboard — traffic-light per record across the 4 principles.
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
    <h1 class="h3 mb-0"><i class="fas fa-shield-alt me-2"></i>OCAP® Compliance Dashboard</h1>
    <a href="{{ route('ahgicip.ocap-settings') }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-cog me-1"></i>Settings
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
    <div class="card-header bg-light"><strong>Per-principle breakdown</strong></div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr><th>Principle</th><th class="text-center">Green</th><th class="text-center">Amber</th><th class="text-center">Red</th><th class="text-center">N/A</th></tr>
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
    <div class="card-header bg-light"><strong>Records with ICIP signal</strong> ({{ count($rollup) }})</div>
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Record</th>
            <th class="text-center">Ownership</th>
            <th class="text-center">Control</th>
            <th class="text-center">Access</th>
            <th class="text-center">Possession</th>
            <th class="text-center">Overall</th>
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
                <a href="{{ route('ahgicip.object-icip', ['id' => $r['io_id']]) }}" class="btn btn-sm btn-outline-secondary" title="ICIP detail">
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
</div>
@endsection
