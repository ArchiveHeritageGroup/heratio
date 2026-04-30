{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Heratio is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU Affero General Public License for more details.
--}}
@extends('theme::layouts.1col')

@section('title', 'Heritage Asset Management')

@section('content')
@php
    $stats = $stats ?? [];
    $compliance = $compliance ?? ['status' => 'compliant', 'issues' => [], 'warnings' => []];
    $config = $config ?? [];
    $recentAssets = $recentAssets ?? collect();
    $expiringInsurance = $expiringInsurance ?? collect();

    $statsAssets = $stats['assets'] ?? ['total' => 0, 'active' => 0];
    $statsValues = $stats['values'] ?? ['total' => 0, 'insured' => 0];
    $statsInsurance = $stats['insurance'] ?? ['active' => 0, 'expiring_soon' => 0];
    $statsValuationBasis = $stats['valuation_basis'] ?? [];
    $statsCategories = $stats['categories'] ?? collect();
    $statsRecentValuations = $stats['recent_valuations'] ?? 0;

    $complianceStatusValue = $compliance['status'] ?? 'compliant';
    $statusColors = ['compliant' => 'success', 'warning' => 'warning', 'non_compliant' => 'danger'];
    $statusColor = $statusColors[$complianceStatusValue] ?? 'secondary';

    $basisColors = [
        'historical_cost' => 'primary',
        'fair_value' => 'success',
        'nominal' => 'warning',
        'not_recognized' => 'secondary',
    ];
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-coins me-2"></i>{{ __('Heritage Asset Management') }}</h1>
            <p class="text-muted">IPSAS-Compliant Heritage Asset Accounting</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('ipsas.reports') }}" class="btn btn-outline-primary">
                <i class="fas fa-file-alt me-1"></i> {{ __('Reports') }}
            </a>
            <a href="{{ route('ipsas.config') }}" class="btn btn-outline-secondary">
                <i class="fas fa-cog me-1"></i> {{ __('Settings') }}
            </a>
        </div>
    </div>

    {{-- Compliance Status --}}
    <div class="alert alert-{{ $statusColor }} mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-{{ $complianceStatusValue === 'compliant' ? 'check-circle' : 'exclamation-triangle' }} fa-2x me-3"></i>
            <div>
                <h5 class="mb-1">IPSAS Compliance: {{ ucfirst($complianceStatusValue) }}</h5>
                @if(!empty($compliance['issues']))
                    <p class="mb-0">{{ count($compliance['issues']) }} issue(s) require attention</p>
                @elseif(!empty($compliance['warnings']))
                    <p class="mb-0">{{ count($compliance['warnings']) }} warning(s) to review</p>
                @else
                    <p class="mb-0">All IPSAS requirements met</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h3>{{ number_format($statsAssets['total'] ?? 0) }}</h3>
                    <p class="text-muted mb-0">Total Assets</p>
                    <small class="text-muted">{{ $statsAssets['active'] ?? 0 }} active</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3>${{ number_format($statsValues['total'] ?? 0, 0) }}</h3>
                    <p class="text-muted mb-0">Total Value</p>
                    <small class="text-muted">{{ $config['default_currency'] ?? 'USD' }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3>${{ number_format($statsValues['insured'] ?? 0, 0) }}</h3>
                    <p class="text-muted mb-0">Insured Value</p>
                    <small class="text-muted">{{ __('Total insurance coverage') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card {{ ($statsInsurance['expiring_soon'] ?? 0) > 0 ? 'border-warning' : '' }}">
                <div class="card-body text-center">
                    <h3>{{ $statsInsurance['expiring_soon'] ?? 0 }}</h3>
                    <p class="text-muted mb-0">Insurance Expiring</p>
                    <small class="text-warning">{{ __('Within 30 days') }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Quick Links --}}
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i>{{ __('Quick Actions') }}</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="{{ route('ipsas.assets') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-archive me-2"></i> Asset Register
                        <span class="badge bg-primary float-end">{{ $statsAssets['total'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('ipsas.asset.create') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-plus me-2"></i> {{ __('Add New Asset') }}
                    </a>
                    <a href="{{ route('ipsas.valuations') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-calculator me-2"></i> Valuations
                        <span class="badge bg-info float-end">{{ $statsRecentValuations }} this year</span>
                    </a>
                    <a href="{{ route('ipsas.impairments') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-line me-2"></i> {{ __('Impairment Reviews') }}
                    </a>
                    <a href="{{ route('ipsas.insurance') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-shield-alt me-2"></i> Insurance Policies
                        @if(($statsInsurance['expiring_soon'] ?? 0) > 0)
                            <span class="badge bg-warning text-dark float-end">{{ $statsInsurance['expiring_soon'] }} expiring</span>
                        @endif
                    </a>
                    <a href="{{ route('ipsas.financialYear') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-alt me-2"></i> {{ __('Financial Year Summary') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Valuation Basis Breakdown --}}
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>{{ __('Valuation Basis') }}</h5>
                </div>
                <div class="card-body">
                    @if(!empty($statsValuationBasis))
                        <ul class="list-group list-group-flush">
                            @foreach($statsValuationBasis as $basis => $count)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    {{ ucfirst(str_replace('_', ' ', $basis)) }}
                                    <span class="badge bg-{{ $basisColors[$basis] ?? 'secondary' }}">{{ $count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted text-center">No assets registered</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Category Breakdown --}}
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-tags me-2"></i>{{ __('By Category') }}</h5>
                </div>
                <div class="card-body">
                    @if(is_countable($statsCategories) && count($statsCategories) > 0)
                        <ul class="list-group list-group-flush">
                            @foreach($statsCategories as $cat)
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>{{ $cat->name ?? '-' }}</span>
                                    <span>
                                        <span class="badge bg-secondary">{{ $cat->count ?? 0 }}</span>
                                        <small class="text-muted">${{ number_format($cat->value ?? 0, 0) }}</small>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted text-center">No categorized assets</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Assets & Expiring Insurance --}}
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>{{ __('Recently Added') }}</h5>
                    <a href="{{ route('ipsas.asset.create') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    @if(empty($recentAssets) || (is_object($recentAssets) && method_exists($recentAssets, 'isEmpty') && $recentAssets->isEmpty()))
                        <div class="p-3 text-center text-muted">No assets registered yet</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach($recentAssets as $asset)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <a href="{{ route('ipsas.asset.view', ['id' => $asset->id]) }}">
                                                {{ $asset->asset_number ?? '' }}
                                            </a>
                                            <br><small class="text-muted">{{ substr($asset->title ?? '', 0, 40) }}</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-secondary">
                                                ${{ number_format($asset->current_value ?? 0, 0) }}
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>{{ __('Expiring Insurance') }}</h5>
                </div>
                <div class="card-body p-0">
                    @if(empty($expiringInsurance) || (is_object($expiringInsurance) && method_exists($expiringInsurance, 'isEmpty') && $expiringInsurance->isEmpty()))
                        <div class="p-3 text-center text-success">
                            <i class="fas fa-check-circle"></i> {{ __('No policies expiring soon') }}
                        </div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach($expiringInsurance as $policy)
                                @php $daysLeft = $policy->coverage_end ? (int) floor((strtotime($policy->coverage_end) - time()) / 86400) : 0; @endphp
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong>{{ $policy->policy_number ?? '' }}</strong>
                                            <br><small class="text-muted">{{ $policy->insurer ?? '' }}</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-{{ $daysLeft < 7 ? 'danger' : 'warning' }}">
                                                {{ $daysLeft }} days
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Compliance Issues --}}
    @if(!empty($compliance['issues']) || !empty($compliance['warnings']))
        <div class="row">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>{{ __('Compliance Issues') }}</h5>
                    </div>
                    <div class="card-body">
                        @if(!empty($compliance['issues']))
                            <h6 class="text-danger">{{ __('Issues (Require Action)') }}</h6>
                            <ul class="mb-3">
                                @foreach($compliance['issues'] as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if(!empty($compliance['warnings']))
                            <h6 class="text-warning">{{ __('Warnings') }}</h6>
                            <ul class="mb-0">
                                @foreach($compliance['warnings'] as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- IPSAS Reference --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h6><i class="fas fa-book me-2"></i>{{ __('IPSAS Reference') }}</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <strong>{{ __('IPSAS 17:') }}</strong>
                            <p class="mb-0 small text-muted">Property, Plant & Equipment - heritage asset guidance</p>
                        </div>
                        <div class="col-md-4">
                            <strong>{{ __('Valuation Policy:') }}</strong>
                            <p class="mb-0 small text-muted">Fair value revaluations every {{ $config['valuation_frequency_years'] ?? 5 }} years</p>
                        </div>
                        <div class="col-md-4">
                            <strong>{{ __('Current Standard:') }}</strong>
                            <p class="mb-0 small text-muted">{{ $config['accounting_standard'] ?? 'IPSAS' }} - {{ $config['organization_name'] ?? 'Not configured' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
