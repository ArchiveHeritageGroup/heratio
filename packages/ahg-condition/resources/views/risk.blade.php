{{--
  Condition Risk Assessment

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Surfaces heritage objects with poor or critical condition assessments
  for conservation prioritisation.
--}}
@extends('theme::layouts.1col')

@section('title', __('Condition Risk Assessment'))
@section('body-class', 'admin condition risk')

@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>{{ __('Condition Risk Assessment') }}</h1>
    <a href="{{ url('/admin/condition') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Condition Dashboard') }}
    </a>
  </div>

  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card text-white bg-warning">
        <div class="card-body text-center">
          <h3 class="mb-0">{{ number_format($counts['poor'] ?? 0) }}</h3>
          <small>{{ __('Poor Condition') }}</small>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-white bg-danger">
        <div class="card-body text-center">
          <h3 class="mb-0">{{ number_format($counts['critical'] ?? 0) }}</h3>
          <small>{{ __('Critical Condition') }}</small>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-white bg-dark">
        <div class="card-body text-center">
          <h3 class="mb-0">{{ number_format(($counts['poor'] ?? 0) + ($counts['critical'] ?? 0)) }}</h3>
          <small>{{ __('Total At-Risk') }}</small>
        </div>
      </div>
    </div>
  </div>

  <form method="GET" class="card mb-3">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">{{ __('Risk Level') }}</label>
          <select name="level" class="form-select form-select-sm">
            <option value="all" {{ $level === 'all' ? 'selected' : '' }}>{{ __('All At-Risk (Poor + Critical)') }}</option>
            <option value="poor" {{ $level === 'poor' ? 'selected' : '' }}>{{ __('Poor only') }}</option>
            <option value="critical" {{ $level === 'critical' ? 'selected' : '' }}>{{ __('Critical only') }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>{{ __('Filter') }}</button>
        </div>
      </div>
    </div>
  </form>

  <div class="card">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-list me-2"></i>{{ __('At-Risk Records') }} ({{ count($rows) }})</h5></div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('Reference') }}</th>
            <th>{{ __('Object') }}</th>
            <th>{{ __('Condition') }}</th>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Checked By') }}</th>
            <th>{{ __('Notes') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $check)
            <tr>
              <td><code>{{ $check->condition_check_reference ?? '' }}</code></td>
              <td>{{ \Illuminate\Support\Str::limit($check->object_title ?? 'Untitled', 40) }}</td>
              <td>
                <span class="badge bg-{{ $check->overall_condition === 'critical' ? 'danger' : 'warning text-dark' }}">
                  {{ ucfirst($check->overall_condition ?? '') }}
                </span>
              </td>
              <td>{{ $check->check_date ?? '' }}</td>
              <td>{{ $check->checked_by ?? '' }}</td>
              <td>{{ \Illuminate\Support\Str::limit($check->notes ?? '', 60) }}</td>
              <td>
                <a href="{{ url('/condition/' . $check->id . '/view') }}" class="btn btn-sm btn-outline-primary" title="{{ __('View') }}">
                  <i class="fas fa-eye"></i>
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-muted text-center py-4">{{ __('No at-risk condition records found.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
