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

@section('title', 'NMMZ Compliance Dashboard')

@section('content')
@php
    $stats = $stats ?? [];
    $compliance = $compliance ?? ['status' => 'compliant', 'issues' => [], 'warnings' => []];
    $config = $config ?? [];
    $recentMonuments = $recentMonuments ?? collect();
    $pendingPermits = $pendingPermits ?? collect();

    $statsMonuments = $stats['monuments'] ?? ['total' => 0, 'gazetted' => 0, 'at_risk' => 0, 'world_heritage' => 0];
    $statsAntiquities = $stats['antiquities'] ?? ['total' => 0, 'in_collection' => 0];
    $statsPermits = $stats['permits'] ?? ['total' => 0, 'pending' => 0, 'this_year' => 0];
    $statsSites = $stats['sites'] ?? ['total' => 0, 'at_risk' => 0];
    $statsHia = $stats['hia'] ?? ['total' => 0, 'pending' => 0];

    $complianceStatusValue = $compliance['status'] ?? 'compliant';
    $statusColors = ['compliant' => 'success', 'warning' => 'warning', 'non_compliant' => 'danger'];
    $statusColor = $statusColors[$complianceStatusValue] ?? 'secondary';
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-monument me-2"></i>NMMZ Compliance Dashboard</h1>
            <p class="text-muted">National Museums and Monuments of Zimbabwe Act [Chapter 25:11]</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('nmmz.reports') }}" class="btn btn-outline-primary">
                <i class="fas fa-file-alt me-1"></i> Reports
            </a>
            <a href="{{ route('nmmz.config') }}" class="btn btn-outline-secondary">
                <i class="fas fa-cog me-1"></i> Settings
            </a>
        </div>
    </div>

    {{-- Compliance Status --}}
    <div class="alert alert-{{ $statusColor }} mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-{{ $complianceStatusValue === 'compliant' ? 'check-circle' : 'exclamation-triangle' }} fa-2x me-3"></i>
            <div>
                <h5 class="mb-1">Compliance Status: {{ ucfirst($complianceStatusValue) }}</h5>
                @if(!empty($compliance['issues']))
                    <p class="mb-0">{{ count($compliance['issues']) }} issue(s) require attention</p>
                @elseif(!empty($compliance['warnings']))
                    <p class="mb-0">{{ count($compliance['warnings']) }} warning(s)</p>
                @else
                    <p class="mb-0">Heritage protection requirements met</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h3>{{ $statsMonuments['total'] ?? 0 }}</h3>
                    <p class="text-muted mb-0">National Monuments</p>
                    <small class="text-success">{{ $statsMonuments['gazetted'] ?? 0 }} gazetted</small>
                    @if(($statsMonuments['at_risk'] ?? 0) > 0)
                        <small class="text-danger ms-2">{{ $statsMonuments['at_risk'] }} at risk</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3>{{ $statsAntiquities['total'] ?? 0 }}</h3>
                    <p class="text-muted mb-0">Antiquities</p>
                    <small class="text-muted">{{ $statsAntiquities['in_collection'] ?? 0 }} in collection</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card {{ ($statsPermits['pending'] ?? 0) > 0 ? 'border-warning' : '' }}">
                <div class="card-body text-center">
                    <h3>{{ $statsPermits['pending'] ?? 0 }}</h3>
                    <p class="text-muted mb-0">Pending Permits</p>
                    <small class="text-muted">{{ $statsPermits['this_year'] ?? 0 }} this year</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3>{{ $statsSites['total'] ?? 0 }}</h3>
                    <p class="text-muted mb-0">Archaeological Sites</p>
                    @if(($statsSites['at_risk'] ?? 0) > 0)
                        <small class="text-danger">{{ $statsSites['at_risk'] }} at risk</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Quick Links --}}
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Actions</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="{{ route('nmmz.monuments') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-monument me-2"></i> National Monuments
                        <span class="badge bg-primary float-end">{{ $statsMonuments['total'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('nmmz.antiquities') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-vase me-2"></i> Antiquities Register
                        <span class="badge bg-secondary float-end">{{ $statsAntiquities['total'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('nmmz.permits') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-export me-2"></i> Export Permits
                        @if(($statsPermits['pending'] ?? 0) > 0)
                            <span class="badge bg-warning text-dark float-end">{{ $statsPermits['pending'] }} pending</span>
                        @endif
                    </a>
                    <a href="{{ route('nmmz.sites') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-map-marker-alt me-2"></i> Archaeological Sites
                    </a>
                    <a href="{{ route('nmmz.hia') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-clipboard-check me-2"></i> Heritage Impact Assessments
                        @if(($statsHia['pending'] ?? 0) > 0)
                            <span class="badge bg-info float-end">{{ $statsHia['pending'] }}</span>
                        @endif
                    </a>
                </div>
            </div>
        </div>

        {{-- Recent Monuments --}}
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-monument me-2"></i>Recent Monuments</h5>
                    <a href="{{ route('nmmz.monument.create') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    @if(empty($recentMonuments) || (is_object($recentMonuments) && method_exists($recentMonuments, 'isEmpty') && $recentMonuments->isEmpty()))
                        <div class="p-3 text-center text-muted">No monuments registered</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach($recentMonuments as $m)
                                <li class="list-group-item">
                                    <a href="{{ route('nmmz.monument.view', ['id' => $m->id]) }}">
                                        {{ $m->monument_number ?? '' }}
                                    </a>
                                    <br><small class="text-muted">{{ substr($m->name ?? '', 0, 40) }}</small>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        {{-- Pending Permits --}}
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-export me-2"></i>Pending Permits</h5>
                    <a href="{{ route('nmmz.permit.create') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    @if(empty($pendingPermits) || (is_object($pendingPermits) && method_exists($pendingPermits, 'isEmpty') && $pendingPermits->isEmpty()))
                        <div class="p-3 text-center text-success">
                            <i class="fas fa-check-circle"></i> No pending permits
                        </div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach($pendingPermits as $p)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <a href="{{ route('nmmz.permit.view', ['id' => $p->id]) }}">
                                                {{ $p->permit_number ?? '' }}
                                            </a>
                                            <br><small class="text-muted">{{ $p->applicant_name ?? '' }}</small>
                                        </div>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Issues & Warnings --}}
    @if(!empty($compliance['issues']) || !empty($compliance['warnings']))
        <div class="row">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Compliance Issues</h5>
                    </div>
                    <div class="card-body">
                        @if(!empty($compliance['issues']))
                            <h6 class="text-danger">Issues (Require Action)</h6>
                            <ul class="mb-3">
                                @foreach($compliance['issues'] as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if(!empty($compliance['warnings']))
                            <h6 class="text-warning">Warnings</h6>
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

    {{-- World Heritage --}}
    @if(($statsMonuments['world_heritage'] ?? 0) > 0)
        <div class="row mt-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6><i class="fas fa-globe me-2"></i>UNESCO World Heritage Sites: {{ $statsMonuments['world_heritage'] }}</h6>
                        <p class="mb-0 small text-muted">Zimbabwe has 5 inscribed World Heritage Sites including Great Zimbabwe, Khami Ruins, and Mana Pools.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
