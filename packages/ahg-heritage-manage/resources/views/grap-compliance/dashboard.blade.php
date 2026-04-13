{{--
  GRAP 103 Compliance Dashboard - Heratio

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')
@section('title', __('GRAP 103 Compliance Dashboard'))
@section('body-class', 'admin heritage')

@section('content')
@php
  $stats = $stats ?? ['total' => 0];
  $complianceSummary = $complianceSummary ?? [
    'total_assets' => 0,
    'compliant' => 0,
    'partially_compliant' => 0,
    'non_compliant' => 0,
  ];
  $recentAssets = $recentAssets ?? [];
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-3">
                <i class="fas fa-balance-scale me-2"></i>{{ __('GRAP 103 Compliance Dashboard') }}
            </h1>
            <p class="text-muted">{{ __('South African heritage asset compliance monitoring') }}</p>
        </div>
    </div>

    {{-- Statistics --}}
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="text-white-50">{{ __('GRAP Assets') }}</h6>
                    <h2 class="mb-0">{{ number_format($stats['total'] ?? 0) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="text-white-50">{{ __('Compliant') }}</h6>
                    <h2 class="mb-0">{{ number_format($complianceSummary['compliant']) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <h6 class="text-dark-50">{{ __('Partial') }}</h6>
                    <h2 class="mb-0">{{ number_format($complianceSummary['partially_compliant']) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <h6 class="text-white-50">{{ __('Non-Compliant') }}</h6>
                    <h2 class="mb-0">{{ number_format($complianceSummary['non_compliant']) }}</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Quick Actions --}}
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>{{ __('Quick Actions') }}</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('heritage.grap.batch-check') }}" class="btn btn-outline-primary">
                            <i class="fas fa-check-double me-2"></i>{{ __('Run Batch Compliance Check') }}
                        </a>
                        <a href="{{ route('heritage.grap.national-treasury-report') }}" class="btn btn-outline-success">
                            <i class="fas fa-file-alt me-2"></i>{{ __('National Treasury Report') }}
                        </a>
                        <a href="{{ route('heritage.accounting.browse', ['standard_id' => 1]) }}" class="btn btn-outline-info">
                            <i class="fas fa-list me-2"></i>{{ __('Browse GRAP Assets') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Compliance Overview --}}
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>{{ __('Compliance Overview') }}</h5>
                </div>
                <div class="card-body">
                    @if(($complianceSummary['total_assets'] ?? 0) > 0)
                        @php
                        $total = $complianceSummary['total_assets'];
                        $compliantPct = round(($complianceSummary['compliant'] / $total) * 100);
                        $partialPct = round(($complianceSummary['partially_compliant'] / $total) * 100);
                        $nonPct = round(($complianceSummary['non_compliant'] / $total) * 100);
                        @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-success">{{ __('Compliant') }}</span>
                                <span>{{ $compliantPct }}%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-success" style="width: {{ $compliantPct }}%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-warning">{{ __('Partially Compliant') }}</span>
                                <span>{{ $partialPct }}%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-warning" style="width: {{ $partialPct }}%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-danger">{{ __('Non-Compliant') }}</span>
                                <span>{{ $nonPct }}%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-danger" style="width: {{ $nonPct }}%"></div>
                            </div>
                        </div>
                    @else
                        <p class="text-muted text-center">{{ __('No GRAP assets recorded yet') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- GRAP References --}}
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-book me-2"></i>{{ __('GRAP 103 References') }}</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Recognition (103.14-25)</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Measurement (103.26-51)</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Depreciation (103.52-60)</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Impairment (103.61-67)</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Derecognition (103.68-73)</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Disclosure (103.74-82)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Assets --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>{{ __('Recent GRAP Assets') }}</h5>
                </div>
                <div class="card-body p-0">
                    @if(!empty($recentAssets) && count($recentAssets) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Identifier') }}</th>
                                        <th>{{ __('Title') }}</th>
                                        <th>{{ __('Class') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th class="text-end">{{ __('Carrying Amount') }}</th>
                                        <th class="text-center">{{ __('Compliance') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentAssets as $asset)
                                        <tr>
                                            <td>
                                                <a href="{{ route('heritage.accounting.view', ['id' => $asset->id]) }}">
                                                    {{ $asset->object_identifier ?: 'N/A' }}
                                                </a>
                                            </td>
                                            <td>{{ $asset->object_title ?: '-' }}</td>
                                            <td>{{ $asset->class_name ?: '-' }}</td>
                                            <td>
                                                @php
                                                $statusColors = ['recognised' => 'success', 'not_recognised' => 'secondary', 'pending' => 'warning', 'derecognised' => 'danger'];
                                                $color = $statusColors[$asset->recognition_status] ?? 'secondary';
                                                @endphp
                                                <span class="badge bg-{{ $color }}">{{ ucfirst(str_replace('_', ' ', (string) $asset->recognition_status)) }}</span>
                                            </td>
                                            <td class="text-end">{{ number_format((float) $asset->current_carrying_amount, 2) }}</td>
                                            <td class="text-center">
                                                <a href="{{ route('heritage.grap.check', ['id' => $asset->id]) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-clipboard-check"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-balance-scale fa-3x text-muted mb-3"></i>
                            <p class="text-muted">{{ __('No GRAP 103 assets recorded yet.') }}</p>
                            <a href="{{ route('heritage.accounting.add') }}" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>{{ __('Add First Asset') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ route('heritage.accounting.dashboard') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Heritage Accounting') }}
        </a>
    </div>
</div>
@endsection
