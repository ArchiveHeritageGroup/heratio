{{--
  Condition Reports Administration

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Condition Reports Administration'))
@section('body-class', 'admin condition')

@php
  $conditionColors = [
    'excellent' => 'success', 'good' => 'primary', 'fair' => 'info',
    'poor' => 'warning', 'critical' => 'danger', 'pending' => 'secondary',
  ];
  $totalChecks = $stats['total_checks'] ?? ($stats['totalChecks'] ?? 0);
  $totalPhotos = $stats['total_photos'] ?? ($stats['totalPhotos'] ?? 0);
  $totalAnnotations = $stats['total_annotations'] ?? ($stats['totalAnnotations'] ?? 0);
@endphp

@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0"><i class="fas fa-clipboard-check me-2"></i>{{ __('Condition Reports Administration') }}</h1>
    <div>
      <a href="{{ url('/admin/condition/risk') }}" class="btn btn-outline-warning">
        <i class="fas fa-exclamation-triangle me-1"></i>{{ __('Risk Assessment') }}
      </a>
      <a href="{{ url('/condition/templates') }}" class="btn btn-outline-secondary">
        <i class="fas fa-clipboard me-1"></i>{{ __('Templates') }}
      </a>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card text-white bg-primary">
        <div class="card-body text-center">
          <h3 class="mb-0">{{ number_format($totalChecks) }}</h3>
          <small>{{ __('Total Condition Checks') }}</small>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-white bg-info">
        <div class="card-body text-center">
          <h3 class="mb-0">{{ number_format($totalPhotos) }}</h3>
          <small>{{ __('Condition Photos') }}</small>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-white bg-success">
        <div class="card-body text-center">
          <h3 class="mb-0">{{ number_format($totalAnnotations) }}</h3>
          <small>{{ __('Annotations') }}</small>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-4 mb-4">
      <div class="card h-100">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>{{ __('By Condition') }}</h5></div>
        <div class="card-body">
          @forelse($byCondition ?? [] as $row)
            @php $color = $conditionColors[$row->overall_condition ?? ''] ?? 'secondary'; @endphp
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge bg-{{ $color }}">{{ ucfirst($row->overall_condition ?? 'Unknown') }}</span>
              <span class="badge bg-dark rounded-pill">{{ number_format($row->count) }}</span>
            </div>
          @empty
            <p class="text-muted mb-0">{{ __('No condition checks recorded.') }}</p>
          @endforelse
        </div>
      </div>
    </div>

    <div class="col-md-8 mb-4">
      <div class="card h-100">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-history me-2"></i>{{ __('Recent Condition Checks') }}</h5></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead>
                <tr>
                  <th>{{ __('Reference') }}</th>
                  <th>{{ __('Object') }}</th>
                  <th>{{ __('Condition') }}</th>
                  <th>{{ __('Date') }}</th>
                  <th>{{ __('Checked By') }}</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @forelse($recentChecks ?? [] as $check)
                  @php $color = $conditionColors[$check->overall_condition ?? ''] ?? 'secondary'; @endphp
                  <tr>
                    <td><code>{{ $check->condition_check_reference ?? '' }}</code></td>
                    <td>{{ \Illuminate\Support\Str::limit($check->object_title ?? 'Untitled', 40) }}</td>
                    <td><span class="badge bg-{{ $color }}">{{ ucfirst($check->overall_condition ?? '') }}</span></td>
                    <td>{{ $check->check_date ?? '' }}</td>
                    <td>{{ $check->checked_by ?? '' }}</td>
                    <td>
                      <a href="{{ url('/condition/check/' . $check->id . '/photos') }}" class="btn btn-sm btn-outline-primary" title="{{ __('View photos') }}">
                        <i class="fas fa-camera"></i>
                      </a>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="text-muted text-center py-3">{{ __('No condition checks recorded yet.') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
