{{--
  Privacy Compliance Dashboard
  Cloned from PSIS ahgPrivacyPlugin privacyAdmin/indexSuccess

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems.co.za
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')

@section('title', 'Privacy Compliance')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <h1 class="h2 mb-0"><i class="fas fa-user-shield me-2"></i>{{ __('Privacy Compliance') }}</h1>
        <div class="d-flex flex-wrap gap-2">
            {{-- Jurisdiction Selector --}}
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-globe me-1"></i>
                    @if($currentJurisdiction === 'all')
                        {{ __('All Jurisdictions') }}
                    @else
                        {{ $jurisdictions[$currentJurisdiction]['name'] ?? $currentJurisdiction }}
                    @endif
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('ahgprivacy.dashboard') }}">{{ __('All Jurisdictions') }}</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header"><i class="fas fa-globe-africa me-1"></i>{{ __('Africa') }}</h6></li>
                    <li><a class="dropdown-item" href="{{ route('ahgprivacy.dashboard', ['jurisdiction' => 'popia']) }}"><span class="fi fi-za me-2"></span>POPIA (South Africa)</a></li>
                    <li><a class="dropdown-item" href="{{ route('ahgprivacy.dashboard', ['jurisdiction' => 'ndpa']) }}"><span class="fi fi-ng me-2"></span>NDPA (Nigeria)</a></li>
                    <li><a class="dropdown-item" href="{{ route('ahgprivacy.dashboard', ['jurisdiction' => 'kenya_dpa']) }}"><span class="fi fi-ke me-2"></span>Kenya DPA</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header"><i class="fas fa-globe-europe me-1"></i>{{ __('International') }}</h6></li>
                    <li><a class="dropdown-item" href="{{ route('ahgprivacy.dashboard', ['jurisdiction' => 'gdpr']) }}"><span class="fi fi-eu me-2"></span>GDPR (EU)</a></li>
                    <li><a class="dropdown-item" href="{{ route('ahgprivacy.dashboard', ['jurisdiction' => 'pipeda']) }}"><span class="fi fi-ca me-2"></span>PIPEDA (Canada)</a></li>
                    <li><a class="dropdown-item" href="{{ route('ahgprivacy.dashboard', ['jurisdiction' => 'ccpa']) }}"><span class="fi fi-us me-2"></span>CCPA (California)</a></li>
                </ul>
            </div>
            <a href="{{ route('ahgprivacy.report') }}" class="btn btn-outline-secondary">
                <i class="fas fa-chart-bar me-1"></i><span class="d-none d-sm-inline">{{ __('Reports') }}</span>
            </a>
            <a href="{{ route('ahgprivacy.notifications') }}" class="btn btn-outline-secondary position-relative">
                <i class="fas fa-bell"></i>
                @php $notifCount = $notificationCount ?? 0; @endphp
                @if($notifCount > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $notifCount }}</span>
                @endif
            </a>
            <a href="{{ route('ahgprivacy.config') }}" class="btn btn-outline-secondary">
                <i class="fas fa-cog"></i><span class="d-none d-sm-inline ms-1">{{ __('Settings') }}</span>
            </a>
        </div>
    </div>

    {{-- Active Jurisdiction Banner --}}
    @if(isset($activeJurisdiction) && $activeJurisdiction)
    <div class="alert alert-primary d-flex align-items-center mb-4">
        <i class="fas fa-globe-africa fa-2x me-3"></i>
        <div class="flex-grow-1">
            <strong>{{ __('Active Jurisdiction') }}:</strong>
            {{ $activeJurisdiction->name }} -
            {{ $activeJurisdiction->full_name }}
            ({{ $activeJurisdiction->country }})
        </div>
        <a href="{{ route('ahgprivacy.jurisdictions') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-cog me-1"></i>{{ __('Manage') }}
        </a>
    </div>
    @else
    <div class="alert alert-warning d-flex align-items-center mb-4">
        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
        <div class="flex-grow-1">
            <strong>{{ __('No Active Jurisdiction') }}</strong> -
            {{ __('Install and activate a jurisdiction to enable compliance tracking.') }}
        </div>
        <a href="{{ route('ahgprivacy.jurisdictions') }}" class="btn btn-warning btn-sm">
            <i class="fas fa-globe me-1"></i>{{ __('Configure') }}
        </a>
    </div>
    @endif

    {{-- Compliance Score --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white text-center py-4">
                    <h5 class="mb-3">
                        @if($currentJurisdiction !== 'all' && isset($jurisdictions[$currentJurisdiction]))
                            {{ $jurisdictions[$currentJurisdiction]['name'] }}
                        @endif
                        {{ __('Compliance Score') }}
                    </h5>
                    <div class="display-1 fw-bold">{{ $stats['compliance_score'] ?? 0 }}%</div>
                    <div class="progress mt-3 mx-auto" style="max-width: 400px; height: 10px;">
                        <div class="progress-bar bg-light" style="width: {{ $stats['compliance_score'] ?? 0 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card h-100 border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">{{ __('DSARs') }}</h6>
                            <h2 class="mb-0">{{ $stats['dsar']['pending'] ?? 0 }}</h2>
                            <small class="text-muted">{{ __('pending') }}</small>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-file-alt fa-2x"></i>
                        </div>
                    </div>
                    @if(($stats['dsar']['overdue'] ?? 0) > 0)
                    <div class="mt-2 text-danger">
                        <i class="fas fa-exclamation-triangle me-1"></i>{{ $stats['dsar']['overdue'] }} {{ __('overdue') }}
                    </div>
                    @endif
                </div>
                <div class="card-footer bg-transparent">
                    <a href="{{ route('ahgprivacy.dsar-list', ['jurisdiction' => $currentJurisdiction]) }}" class="text-primary">
                        {{ __('View all') }} <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">{{ __('Breaches') }}</h6>
                            <h2 class="mb-0">{{ $stats['breach']['open'] ?? 0 }}</h2>
                            <small class="text-muted">{{ __('open') }}</small>
                        </div>
                        <div class="text-danger">
                            <i class="fas fa-exclamation-circle fa-2x"></i>
                        </div>
                    </div>
                    @if(($stats['breach']['critical'] ?? 0) > 0)
                    <div class="mt-2 text-danger">
                        <i class="fas fa-radiation me-1"></i>{{ $stats['breach']['critical'] }} {{ __('critical') }}
                    </div>
                    @endif
                </div>
                <div class="card-footer bg-transparent">
                    <a href="{{ route('ahgprivacy.breach-list', ['jurisdiction' => $currentJurisdiction]) }}" class="text-danger">
                        {{ __('View all') }} <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">{{ __('ROPA') }}</h6>
                            <h2 class="mb-0">{{ $stats['ropa']['approved'] ?? 0 }}</h2>
                            <small class="text-muted">{{ __('of ') }}{{ $stats['ropa']['total'] ?? 0 }} {{ __('approved') }}</small>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-clipboard-list fa-2x"></i>
                        </div>
                    </div>
                    @if(($stats['ropa']['requiring_dpia'] ?? 0) > 0)
                    <div class="mt-2 text-warning">
                        <i class="fas fa-clipboard-check me-1"></i>{{ $stats['ropa']['requiring_dpia'] }} {{ __('need DPIA') }}
                    </div>
                    @endif
                </div>
                <div class="card-footer bg-transparent">
                    <a href="{{ route('ahgprivacy.ropa-list', ['jurisdiction' => $currentJurisdiction]) }}" class="text-success">
                        {{ __('View all') }} <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">{{ __('Consents') }}</h6>
                            <h2 class="mb-0">{{ $stats['consent']['active'] ?? 0 }}</h2>
                            <small class="text-muted">{{ __('active') }}</small>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-handshake fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="{{ route('ahgprivacy.consent-list') }}" class="text-info">
                        {{ __('View all') }} <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Quick Actions') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2 col-6 text-center mb-3">
                            <a href="{{ route('ahgprivacy.dsar-add') }}" class="btn btn-outline-primary btn-lg w-100">
                                <i class="fas fa-plus-circle d-block mb-2 fa-2x"></i>
                                {{ __('New DSAR') }}
                            </a>
                        </div>
                        <div class="col-md-2 col-6 text-center mb-3">
                            <a href="{{ route('ahgprivacy.breach-add') }}" class="btn btn-outline-danger btn-lg w-100">
                                <i class="fas fa-exclamation-triangle d-block mb-2 fa-2x"></i>
                                {{ __('Report Breach') }}
                            </a>
                        </div>
                        <div class="col-md-2 col-6 text-center mb-3">
                            <a href="{{ route('ahgprivacy.ropa-add') }}" class="btn btn-outline-success btn-lg w-100">
                                <i class="fas fa-clipboard-list d-block mb-2 fa-2x"></i>
                                {{ __('Add Activity') }}
                            </a>
                        </div>
                        @if($currentJurisdiction === 'popia' || $currentJurisdiction === 'all')
                        <div class="col-md-2 col-6 text-center mb-3">
                            <a href="{{ route('ahgprivacy.paia-list') }}" class="btn btn-outline-warning btn-lg w-100">
                                <i class="fas fa-file-contract d-block mb-2 fa-2x"></i>
                                {{ __('PAIA Requests') }}
                            </a>
                        </div>
                        @endif
                        <div class="col-md-2 col-6 text-center mb-3">
                            <a href="{{ route('ahgprivacy.officer-list') }}" class="btn btn-outline-secondary btn-lg w-100">
                                <i class="fas fa-user-tie d-block mb-2 fa-2x"></i>
                                {{ __('Officers') }}
                            </a>
                        </div>
                        <div class="col-md-2 col-6 text-center mb-3">
                            <a href="{{ route('ahgprivacy.complaint-list') }}" class="btn btn-outline-warning btn-lg w-100">
                                <i class="fas fa-exclamation-circle d-block mb-2 fa-2x"></i>
                                {{ __('Complaints') }}
                            </a>
                        </div>
                        <div class="col-md-2 col-6 text-center mb-3">
                            <a href="{{ route('ahgprivacy.jurisdictions') }}" class="btn btn-outline-info btn-lg w-100">
                                <i class="fas fa-globe d-block mb-2 fa-2x"></i>
                                {{ __('Jurisdictions') }}
                            </a>
                        </div>
                        <div class="col-md-2 col-6 text-center mb-3">
                            <a href="{{ route('ahgprivacy.index') }}" class="btn btn-outline-dark btn-lg w-100" target="_blank">
                                <i class="fas fa-external-link-alt d-block mb-2 fa-2x"></i>
                                {{ __('Public Page') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Jurisdiction Info Cards --}}
    <div class="row">
        @php
            if ($currentJurisdiction === 'all') {
                $displayJurisdictions = array_filter($jurisdictions, fn($info) => ($info['region'] ?? '') === 'Africa');
            } else {
                $displayJurisdictions = isset($jurisdictions[$currentJurisdiction])
                    ? [$currentJurisdiction => $jurisdictions[$currentJurisdiction]]
                    : [];
            }
        @endphp
        @foreach($displayJurisdictions as $code => $info)
            @continue(!$info)
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">
                            <span class="fi fi-{{ $info['icon'] }} me-2"></span>
                            {{ $info['name'] }} ({{ $info['country'] }})
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">{{ $info['full_name'] }}</p>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                {{ __('DSAR Response Time') }}
                                <span class="badge bg-primary rounded-pill">{{ $info['dsar_days'] }} {{ __('days') }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                {{ __('Breach Notification') }}
                                <span class="badge bg-danger rounded-pill">{{ $info['breach_hours'] ?: 'ASAP' }} {{ $info['breach_hours'] ? __('hours') : '' }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                {{ __('Effective') }}
                                <span class="badge bg-secondary rounded-pill">{{ $info['effective_date'] }}</span>
                            </li>
                        </ul>
                        @if(!empty($info['regulator']))
                        <div class="mt-3">
                            <small class="text-muted">{{ __('Regulator:') }}</small>
                            <br>
                            <a href="{{ $info['regulator_url'] ?? '#' }}" target="_blank" class="small">
                                {{ $info['regulator'] }} <i class="fas fa-external-link-alt ms-1"></i>
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
