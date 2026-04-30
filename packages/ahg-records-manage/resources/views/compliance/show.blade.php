{{--
  Records Management — Compliance assessment detail (P2.8)
  @copyright Johan Pieterse / Plain Sailing Information Systems
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Compliance ' . $assessment->assessment_ref)
@section('body-class', 'admin records compliance show')

@section('content')
@php
  $pct = ($assessment->score_max && (float) $assessment->score_max > 0)
       ? round(((float) $assessment->score_total / (float) $assessment->score_max) * 100)
       : null;
  $bandClass = $pct === null ? 'secondary' : ($pct >= 80 ? 'success' : ($pct >= 50 ? 'warning text-dark' : 'danger'));
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0">
    <i class="fas fa-clipboard-check me-2"></i> {{ $assessment->assessment_ref }}
    <span class="badge bg-{{ $assessment->status === 'finalised' ? 'success' : 'warning text-dark' }} ms-2">{{ $assessment->status }}</span>
  </h1>
  <a href="{{ route('records.compliance.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-list me-1"></i>{{ __('All assessments') }}</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row mb-3">
  <div class="col-md-8">
    <table class="table table-sm">
      <tr><th class="text-muted" style="width:25%">{{ __('Framework') }}</th><td>{{ $assessment->framework }}</td></tr>
      <tr><th class="text-muted">{{ __('Title') }}</th><td>{{ $assessment->title }}</td></tr>
      @if($assessment->scope)<tr><th class="text-muted">{{ __('Scope') }}</th><td>{!! nl2br(e($assessment->scope)) !!}</td></tr>@endif
      <tr><th class="text-muted">{{ __('Period') }}</th><td>{{ $assessment->period_start ?: '—' }} → {{ $assessment->period_end ?: '—' }}</td></tr>
      <tr><th class="text-muted">{{ __('Assessed at') }}</th><td>{{ $assessment->assessed_at }}</td></tr>
      @if($assessment->signed_off_by)
        <tr><th class="text-muted">{{ __('Signed off') }}</th><td>{{ $assessment->signed_off_by }} ({{ $assessment->signed_off_at }})</td></tr>
      @endif
    </table>
  </div>
  <div class="col-md-4">
    <div class="card border-{{ $bandClass }}">
      <div class="card-body text-center">
        @if($pct !== null)
          <div class="display-4 text-{{ $bandClass }}">{{ $pct }}%</div>
          <div class="text-muted small">{{ rtrim(rtrim((string) $assessment->score_total, '0'), '.') }} / {{ rtrim(rtrim((string) $assessment->score_max, '0'), '.') }} weighted</div>
        @else
          <div class="text-muted">No score yet</div>
        @endif

        @if($assessment->status !== 'finalised')
          <form method="POST" action="{{ route('records.compliance.run-checks', $assessment->id) }}" class="mt-3">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-redo me-1"></i>{{ __('Re-run checks') }}</button>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header bg-light">Findings ({{ count($checks) }})</div>
  <table class="table table-sm mb-0">
    <thead class="table-light"><tr>
      <th style="width:8%">{{ __('Status') }}</th><th style="width:12%">{{ __('Ref') }}</th><th>{{ __('Check') }}</th><th class="text-end" style="width:6%">{{ __('Weight') }}</th><th>{{ __('Finding') }}</th>
    </tr></thead>
    <tbody>
    @forelse($checks as $c)
      <tr>
        <td>
          @if($c['status'] === 'pass') <span class="badge bg-success">{{ __('PASS') }}</span>
          @elseif($c['status'] === 'warn') <span class="badge bg-warning text-dark">{{ __('WARN') }}</span>
          @elseif($c['status'] === 'fail') <span class="badge bg-danger">{{ __('FAIL') }}</span>
          @else <span class="badge bg-secondary">N/A</span>
          @endif
        </td>
        <td><code class="small">{{ $c['check_ref'] }}</code></td>
        <td>{{ $c['label'] }}</td>
        <td class="text-end"><small>{{ $c['weight'] }}</small></td>
        <td><small>{{ $c['finding'] ?? '' }}</small></td>
      </tr>
    @empty
      <tr><td colspan="5" class="text-muted text-center py-3">No checks have run yet — click <em>Re-run checks</em>.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>

@if(! empty($recommendations))
  <div class="card mb-3 border-warning">
    <div class="card-header bg-warning text-dark"><i class="fas fa-bolt me-1"></i> Recommendations ({{ count($recommendations) }})</div>
    <ul class="list-group list-group-flush">
      @foreach($recommendations as $r)
        <li class="list-group-item small"><code>{{ $r['ref'] }}</code> — {{ $r['recommendation'] }}</li>
      @endforeach
    </ul>
  </div>
@endif

@if($assessment->status !== 'finalised')
  <div class="card border-success">
    <div class="card-header bg-success text-white"><i class="fas fa-stamp me-1"></i> {{ __('Sign off &amp; finalise') }}</div>
    <div class="card-body">
      <form method="POST" action="{{ route('records.compliance.finalize', $assessment->id) }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-6">
          <label class="form-label small mb-1">{{ __('Signed off by (name + role)') }}</label>
          <input type="text" name="signed_off_by" class="form-control form-control-sm" placeholder="{{ __('e.g. Jane Doe, Records Manager') }}" required>
        </div>
        <div class="col-md-6">
          <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Finalise this assessment? This locks the score and findings.');"><i class="fas fa-stamp me-1"></i>{{ __('Finalise') }}</button>
        </div>
      </form>
    </div>
  </div>
@endif
@endsection
