{{-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems — johan@plainsailingisystems.co.za
     This file is part of Heratio. Heratio is free software: you can redistribute it and/or modify
     it under the terms of the GNU Affero General Public License v3+. --}}
@extends('theme::layouts.1col')

@section('title', 'NAZ Compliance Dashboard')

@section('content')
@php
    $statusColors  = ['compliant' => 'success', 'warning' => 'warning', 'non_compliant' => 'danger'];
    $statusColor   = $statusColors[$compliance['status']] ?? 'secondary';
    $statusIcon    = $compliance['status'] === 'compliant' ? 'check-circle' : 'exclamation-triangle';
@endphp

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-landmark me-2"></i>{{ __('NAZ Compliance Dashboard') }}</h1>
            <p class="text-muted">National Archives of Zimbabwe Act [Chapter 25:06] &mdash; 25-Year Rule</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('ahgnaz.reports') }}" class="btn atom-btn-outline-primary">
                <i class="fas fa-file-alt me-1"></i> {{ __('Reports') }}
            </a>
            <a href="{{ route('ahgnaz.config') }}" class="btn atom-btn-outline-secondary">
                <i class="fas fa-cog me-1"></i> {{ __('Settings') }}
            </a>
        </div>
    </div>

    <div class="alert alert-{{ $statusColor }} mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-{{ $statusIcon }} fa-2x me-3"></i>
            <div>
                <h5 class="mb-1">Compliance Status: {{ ucfirst(str_replace('_', ' ', $compliance['status'])) }}</h5>
                @if (!empty($compliance['issues']))
                    <p class="mb-0">{{ count($compliance['issues']) }} issue(s) require attention</p>
                @elseif (!empty($compliance['warnings']))
                    <p class="mb-0">{{ count($compliance['warnings']) }} warning(s) to review</p>
                @else
                    <p class="mb-0">All compliance requirements met</p>
                @endif
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h3>{{ $stats['closures']['active'] }}</h3>
                    <p class="text-muted mb-0">Active Closures</p>
                    @if ($stats['closures']['expiring_soon'] > 0)
                        <small class="text-warning">{{ $stats['closures']['expiring_soon'] }} expiring within 1 year</small>
                    @else
                        <small class="text-muted">25-year closure periods</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card {{ $stats['permits']['pending'] > 0 ? 'border-warning' : '' }}">
                <div class="card-body text-center">
                    <h3>{{ $stats['permits']['active'] }}</h3>
                    <p class="text-muted mb-0">Active Permits</p>
                    @if ($stats['permits']['pending'] > 0)
                        <small class="text-warning">{{ $stats['permits']['pending'] }} pending approval</small>
                    @else
                        <small class="text-muted">{{ __('Research permits') }}</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3>{{ $stats['researchers']['total'] }}</h3>
                    <p class="text-muted mb-0">Registered Researchers</p>
                    <small class="text-muted">{{ $stats['researchers']['local'] }} local, {{ $stats['researchers']['foreign'] }} foreign</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card {{ $stats['transfers']['pending'] > 0 ? 'border-info' : '' }}">
                <div class="card-body text-center">
                    <h3>{{ $stats['transfers']['pending'] }}</h3>
                    <p class="text-muted mb-0">Pending Transfers</p>
                    <small class="text-muted">{{ $stats['transfers']['this_year'] }} accessioned this year</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i>{{ __('Quick Actions') }}</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="{{ route('ahgnaz.closures') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-lock me-2"></i> Closure Periods
                        @if ($stats['closures']['expiring_soon'] > 0)
                            <span class="badge bg-warning text-dark float-end">{{ $stats['closures']['expiring_soon'] }} expiring</span>
                        @endif
                    </a>
                    <a href="{{ route('ahgnaz.permits') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-id-card me-2"></i> Research Permits
                        @if ($stats['permits']['pending'] > 0)
                            <span class="badge bg-warning text-dark float-end">{{ $stats['permits']['pending'] }} pending</span>
                        @endif
                    </a>
                    <a href="{{ route('ahgnaz.researchers') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i> {{ __('Researcher Registry') }}
                    </a>
                    <a href="{{ route('ahgnaz.schedules') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-alt me-2"></i> Records Schedules
                        <span class="badge bg-secondary float-end">{{ $stats['schedules'] }}</span>
                    </a>
                    <a href="{{ route('ahgnaz.transfers') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-truck me-2"></i> Records Transfers
                        @if ($stats['transfers']['pending'] > 0)
                            <span class="badge bg-info float-end">{{ $stats['transfers']['pending'] }}</span>
                        @endif
                    </a>
                    <a href="{{ route('ahgnaz.protected-records') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-shield-alt me-2"></i> Protected Records
                        @if ($stats['protected'] > 0)
                            <span class="badge bg-danger float-end">{{ $stats['protected'] }}</span>
                        @endif
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>{{ __('Pending Permits') }}</h5>
                    <a href="{{ route('ahgnaz.permit-create') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    @if ($pendingPermits->isEmpty())
                        <div class="p-3 text-center text-muted">
                            <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                            <p class="mb-0">No pending permit applications</p>
                        </div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($pendingPermits as $permit)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong>{{ $permit->first_name }} {{ $permit->last_name }}</strong>
                                            <br><small class="text-muted">
                                                {{ ucfirst($permit->researcher_type ?? '') }} |
                                                {{ $permit->permit_number }}
                                            </small>
                                        </div>
                                        <div>
                                            <a href="{{ route('ahgnaz.permit-view', $permit->id) }}" class="btn btn-sm atom-btn-outline-primary">
                                                Review
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if (!$pendingPermits->isEmpty())
                    <div class="card-footer text-center">
                        <a href="{{ route('ahgnaz.permits', ['status' => 'pending']) }}">View All Pending</a>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>{{ __('Expiring Closures') }}</h5>
                    <a href="{{ route('ahgnaz.closure-create') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    @if ($expiringClosures->isEmpty())
                        <div class="p-3 text-center text-muted">
                            <i class="fas fa-lock fa-2x mb-2"></i>
                            <p class="mb-0">No closures expiring within 1 year</p>
                        </div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($expiringClosures as $closure)
                                @php
                                    $daysLeft = floor((strtotime($closure->end_date) - time()) / 86400);
                                    $urgency = $daysLeft < 90 ? 'danger' : ($daysLeft < 180 ? 'warning' : 'info');
                                @endphp
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>{{ $closure->record_title ?? ('Record #' . $closure->information_object_id) }}</strong>
                                            <br><small class="text-muted">{{ ucfirst($closure->closure_type) }}</small>
                                        </div>
                                        <span class="badge bg-{{ $urgency }}">{{ $daysLeft }} days</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if (!$expiringClosures->isEmpty())
                    <div class="card-footer text-center">
                        <a href="{{ route('ahgnaz.closures') }}">View All Closures</a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if (!empty($compliance['issues']) || !empty($compliance['warnings']))
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>{{ __('Compliance Issues &amp; Warnings') }}</h5>
                    </div>
                    <div class="card-body">
                        @if (!empty($compliance['issues']))
                            <h6 class="text-danger"><i class="fas fa-times-circle me-1"></i>{{ __('Issues (Require Immediate Action)') }}</h6>
                            <ul class="list-unstyled mb-3">
                                @foreach ($compliance['issues'] as $issue)
                                    <li class="mb-1"><i class="fas fa-exclamation-circle text-danger me-1"></i> {{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if (!empty($compliance['warnings']))
                            <h6 class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>{{ __('Warnings') }}</h6>
                            <ul class="list-unstyled mb-0">
                                @foreach ($compliance['warnings'] as $warning)
                                    <li class="mb-1"><i class="fas fa-exclamation-triangle text-warning me-1"></i> {{ $warning }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row mt-4">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h6><i class="fas fa-gavel me-2"></i>{{ __('Key Legislation Reference') }}</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <strong>{{ __('Section 10 &mdash; Closure Period:') }}</strong>
                            <p class="mb-0 small text-muted">Records closed for {{ $config['closure_period_years'] }} years from date of creation</p>
                        </div>
                        <div class="col-md-4">
                            <strong>{{ __('Research Permit Fees:') }}</strong>
                            <p class="mb-0 small text-muted">Foreign researchers: US${{ $config['foreign_permit_fee_usd'] }}</p>
                        </div>
                        <div class="col-md-4">
                            <strong>{{ __('Permit Validity:') }}</strong>
                            <p class="mb-0 small text-muted">{{ $config['permit_validity_months'] }} months from approval</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
