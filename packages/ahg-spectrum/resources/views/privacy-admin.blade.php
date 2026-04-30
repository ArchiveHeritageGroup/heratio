@extends('theme::layouts.1col')

@section('title', __('Privacy Management (POPIA/PAIA/GDPR)'))

@section('content')
<div class="mb-3">
    <a href="{{ route('ahgspectrum.dashboard') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Dashboard') }}</a>
</div>

<h1 class="h3 mb-4"><i class="fas fa-user-shield me-2"></i>{{ __('Privacy Management (POPIA/PAIA/GDPR)') }}</h1>

<div class="row">
    <!-- ROPA -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>{{ __('ROPA') }}</h6>
            </div>
            <div class="card-body text-center">
                <div class="display-4 text-primary">{{ $ropaCount ?? 0 }}</div>
                <small class="text-muted">{{ __('Processing Activities') }}</small>
            </div>
            <div class="card-footer">
                <a href="{{ route('ahgspectrum.privacy-ropa') }}" class="btn btn-primary w-100">{{ __('Manage') }}</a>
            </div>
        </div>
    </div>

    <!-- DSAR -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0"><i class="fas fa-user-clock me-2"></i>{{ __('DSARs') }}</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <span class="h4 text-warning">{{ $dsarStats['pending'] ?? 0 }}</span>
                        <small class="d-block">{{ __('Pending') }}</small>
                    </div>
                    <div class="col-6">
                        <span class="h4 text-danger">{{ $dsarStats['overdue'] ?? 0 }}</span>
                        <small class="d-block">{{ __('Overdue') }}</small>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('ahgspectrum.privacy-dsar') }}" class="btn btn-warning w-100">{{ __('Manage') }}</a>
            </div>
        </div>
    </div>

    <!-- Breaches -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>{{ __('Breaches') }}</h6>
            </div>
            <div class="card-body text-center">
                <div class="row">
                    <div class="col-6">
                        <span class="h4 text-danger">{{ $breachStats['open'] ?? 0 }}</span>
                        <small class="d-block">{{ __('Open') }}</small>
                    </div>
                    <div class="col-6">
                        <span class="h4 text-success">{{ $breachStats['closed'] ?? 0 }}</span>
                        <small class="d-block">{{ __('Closed') }}</small>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('ahgspectrum.privacy-breaches') }}" class="btn btn-danger w-100">{{ __('Register') }}</a>
            </div>
        </div>
    </div>

    <!-- Templates -->
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>{{ __('Templates') }}</h6>
            </div>
            <div class="card-body text-center">
                <i class="fas fa-file-alt fa-3x text-info mb-2"></i>
                <p class="text-muted mb-0">PAIA manuals, notices, forms</p>
            </div>
            <div class="card-footer">
                <a href="{{ route('ahgspectrum.privacy-templates') }}" class="btn btn-info w-100">{{ __('Library') }}</a>
            </div>
        </div>
    </div>
</div>

<!-- Compliance Score -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>{{ __('Compliance Score') }}</h5></div>
            <div class="card-body text-center">
                @php
                $score = $complianceScore ?? 75;
                $scoreClass = $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger');
                @endphp
                <div class="display-1 text-{{ $scoreClass }}">{{ $score }}%</div>
                <div class="progress mt-3" style="height: 20px;">
                    <div class="progress-bar bg-{{ $scoreClass }}" style="width: {{ $score }}%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-clock me-2"></i>{{ __('Key Deadlines') }}</h5></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="fas fa-user-clock text-warning me-2"></i><strong>{{ __('DSAR:') }}</strong> 30 days (POPIA S25)</li>
                    <li class="mb-2"><i class="fas fa-bell text-danger me-2"></i><strong>{{ __('Breach:') }}</strong> 72 hours to regulator (POPIA S22)</li>
                    <li><i class="fas fa-balance-scale text-info me-2"></i><strong>{{ __('Regulator:') }}</strong> <a href="https://www.justice.gov.za/inforeg/" target="_blank">justice.gov.za/inforeg</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
