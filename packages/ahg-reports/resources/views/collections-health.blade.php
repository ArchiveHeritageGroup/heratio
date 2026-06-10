{{--
  Collections Health Dashboard - cross-collection KPI overview (issue #1215)

  Read-only aggregate view: record counts by GLAM domain, publication-status
  coverage, digital-object coverage and preservation-assessment coverage.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Collections Health')
@section('body-class', 'admin reports')

@section('sidebar')
<section class="card mb-3">
  <div class="card-body">
    <a href="{{ route('reports.dashboard') }}" class="btn btn-outline-secondary btn-sm w-100">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Reports') }}
    </a>
  </div>
</section>
<section class="card mb-3">
  <div class="card-header"><h6 class="mb-0">{{ __('About this dashboard') }}</h6></div>
  <div class="card-body small text-muted">
    {{ __('A single read-only overview of collection-wide health signals, computed live from the database. Coverage percentages are measured against the count of real archival descriptions.') }}
  </div>
</section>
@endsection

@section('title-block')
<h1>{{ __('Collections Health') }}</h1>
<p class="text-muted mb-0">{{ __('Cross-collection KPI overview') }}</p>
@endsection

@section('content')
@php
  $io = $stats['io'];
  $digital = $stats['digital'];
  $condition = $stats['condition'];
@endphp

{{-- Headline counts --}}
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100 text-center">
      <div class="card-body">
        <div class="display-6">{{ number_format($stats['total_objects']) }}</div>
        <div class="text-muted text-uppercase small">{{ __('Total objects') }}</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100 text-center">
      <div class="card-body">
        <div class="display-6">{{ number_format($io['total']) }}</div>
        <div class="text-muted text-uppercase small">{{ __('Archival descriptions') }}</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100 text-center">
      <div class="card-body">
        <div class="display-6">{{ $digital['pct'] }}%</div>
        <div class="text-muted text-uppercase small">{{ __('Digitised') }}</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100 text-center">
      <div class="card-body">
        <div class="display-6">{{ $condition['pct'] }}%</div>
        <div class="text-muted text-uppercase small">{{ __('Condition assessed') }}</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  {{-- Records by GLAM domain --}}
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h5 class="mb-0">{{ __('Records by domain') }}</h5></div>
      <div class="card-body">
        <table class="table table-sm table-striped mb-0">
          <thead>
            <tr>
              <th>{{ __('Domain') }}</th>
              <th class="text-muted">{{ __('Class') }}</th>
              <th class="text-end">{{ __('Count') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($stats['domains'] as $d)
            <tr>
              <td>{{ $d['label'] }}</td>
              <td class="text-muted small">{{ $d['class_name'] }}</td>
              <td class="text-end">{{ number_format($d['count']) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Publication status --}}
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h5 class="mb-0">{{ __('Publication status') }}</h5></div>
      <div class="card-body">
        <p class="text-muted small">{{ __('Across :n archival descriptions', ['n' => number_format($io['total'])]) }}</p>
        <div class="progress mb-3" style="height:1.5rem">
          <div class="progress-bar bg-success" role="progressbar"
               style="width: {{ $io['published_pct'] }}%"
               aria-valuenow="{{ $io['published_pct'] }}" aria-valuemin="0" aria-valuemax="100">
            {{ $io['published_pct'] }}%
          </div>
          <div class="progress-bar bg-warning text-dark" role="progressbar"
               style="width: {{ $io['draft_pct'] }}%"
               aria-valuenow="{{ $io['draft_pct'] }}" aria-valuemin="0" aria-valuemax="100">
            {{ $io['draft_pct'] }}%
          </div>
          <div class="progress-bar bg-secondary" role="progressbar"
               style="width: {{ $io['unassessed_pct'] }}%"
               aria-valuenow="{{ $io['unassessed_pct'] }}" aria-valuemin="0" aria-valuemax="100">
            {{ $io['unassessed_pct'] }}%
          </div>
        </div>
        <table class="table table-sm mb-0">
          <tbody>
            <tr>
              <td><span class="badge bg-success">&nbsp;</span> {{ __('Published') }}</td>
              <td class="text-end">{{ number_format($io['published']) }}</td>
              <td class="text-end text-muted">{{ $io['published_pct'] }}%</td>
            </tr>
            <tr>
              <td><span class="badge bg-warning">&nbsp;</span> {{ __('Draft') }}</td>
              <td class="text-end">{{ number_format($io['draft']) }}</td>
              <td class="text-end text-muted">{{ $io['draft_pct'] }}%</td>
            </tr>
            <tr>
              <td><span class="badge bg-secondary">&nbsp;</span> {{ __('Never assessed') }}</td>
              <td class="text-end">{{ number_format($io['unassessed']) }}</td>
              <td class="text-end text-muted">{{ $io['unassessed_pct'] }}%</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Digital-object coverage --}}
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h5 class="mb-0">{{ __('Digital-object coverage') }}</h5></div>
      <div class="card-body">
        <p class="text-muted small">{{ __('Archival descriptions carrying at least one digital object') }}</p>
        <div class="progress mb-3" style="height:1.5rem">
          <div class="progress-bar bg-primary" role="progressbar"
               style="width: {{ $digital['pct'] }}%"
               aria-valuenow="{{ $digital['pct'] }}" aria-valuemin="0" aria-valuemax="100">
            {{ $digital['pct'] }}%
          </div>
        </div>
        <table class="table table-sm mb-0">
          <tbody>
            <tr>
              <td>{{ __('With media') }}</td>
              <td class="text-end">{{ number_format($digital['with']) }}</td>
            </tr>
            <tr>
              <td>{{ __('Without media') }}</td>
              <td class="text-end">{{ number_format($digital['without']) }}</td>
            </tr>
            <tr class="table-light">
              <td>{{ __('Total') }}</td>
              <td class="text-end">{{ number_format($digital['total']) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Preservation-assessment coverage --}}
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h5 class="mb-0">{{ __('Preservation-assessment coverage') }}</h5></div>
      <div class="card-body">
        <p class="text-muted small">{{ __('Archival descriptions with at least one condition report') }}</p>
        <div class="progress mb-3" style="height:1.5rem">
          <div class="progress-bar bg-info" role="progressbar"
               style="width: {{ $condition['pct'] }}%"
               aria-valuenow="{{ $condition['pct'] }}" aria-valuemin="0" aria-valuemax="100">
            {{ $condition['pct'] }}%
          </div>
        </div>
        <table class="table table-sm mb-0">
          <tbody>
            <tr>
              <td>{{ __('Assessed') }}</td>
              <td class="text-end">{{ number_format($condition['with']) }}</td>
            </tr>
            <tr>
              <td>{{ __('Not assessed') }}</td>
              <td class="text-end">{{ number_format($condition['without']) }}</td>
            </tr>
            <tr class="table-light">
              <td>{{ __('Total') }}</td>
              <td class="text-end">{{ number_format($condition['total']) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
