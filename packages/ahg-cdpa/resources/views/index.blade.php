{{-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems — johan@plainsailingisystems.co.za
     This file is part of Heratio. Licensed under the GNU Affero General Public License v3+. --}}
@extends('theme::layouts.1col')

@section('title', 'CDPA Compliance Dashboard')

@section('content')
@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $license = Schema::hasTable('cdpa_controller_license')
        ? DB::table('cdpa_controller_license')->orderByDesc('expiry_date')->first()
        : null;
    $dpo = Schema::hasTable('cdpa_dpo')
        ? DB::table('cdpa_dpo')->where('is_active', 1)->orderByDesc('appointment_date')->first()
        : null;

    $licenseDaysRemaining = null;
    $licenseStatusLabel = 'Not Registered';
    if ($license && $license->expiry_date) {
        $licenseDaysRemaining = (int) ((strtotime($license->expiry_date) - time()) / 86400);
        if ($licenseDaysRemaining < 0) {
            $licenseStatusLabel = 'Expired';
        } elseif ($licenseDaysRemaining <= 60) {
            $licenseStatusLabel = 'Expiring Soon';
        } else {
            $licenseStatusLabel = 'Active';
        }
    }

    $pendingRequests = Schema::hasTable('cdpa_data_subject_request')
        ? DB::table('cdpa_data_subject_request')->where('status', 'pending')->orderBy('due_date')->limit(5)->get()
        : collect();

    $dpiaPending = Schema::hasTable('cdpa_dpia')
        ? DB::table('cdpa_dpia')->where('status', '!=', 'completed')->count() : 0;
    $breachesThisYear = Schema::hasTable('cdpa_breach')
        ? DB::table('cdpa_breach')->whereYear('incident_date', now()->year)->count() : 0;

    $compliance = ['status' => 'compliant', 'issues' => [], 'warnings' => []];
    if (!$license) {
        $compliance['issues'][] = 'No data-protection controller license registered.';
    } elseif ($licenseDaysRemaining !== null && $licenseDaysRemaining < 0) {
        $compliance['issues'][] = 'Controller license is expired — renew with the regulator.';
    } elseif ($licenseDaysRemaining !== null && $licenseDaysRemaining <= 60) {
        $compliance['warnings'][] = "Controller license expires in {$licenseDaysRemaining} days.";
    }
    if (!$dpo) {
        $compliance['issues'][] = 'No active Data Protection Officer appointed.';
    }
    if (($stats['requests_overdue'] ?? 0) > 0) {
        $compliance['issues'][] = ($stats['requests_overdue']) . ' data subject request(s) past statutory response deadline.';
    }
    if (($stats['breaches_open'] ?? 0) > 0) {
        $compliance['warnings'][] = ($stats['breaches_open']) . ' open breach investigation(s).';
    }
    if (!empty($compliance['issues']))      { $compliance['status'] = 'non_compliant'; }
    elseif (!empty($compliance['warnings'])) { $compliance['status'] = 'warning'; }

    $statusColors = ['compliant' => 'success', 'warning' => 'warning', 'non_compliant' => 'danger'];
    $statusColor  = $statusColors[$compliance['status']] ?? 'secondary';
    $statusIcon   = $compliance['status'] === 'compliant' ? 'check-circle' : 'exclamation-triangle';
@endphp

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-shield-alt me-2"></i>CDPA Compliance Dashboard</h1>
            <p class="text-muted">Cyber and Data Protection Act [Chapter 12:07] &mdash; regulator-administered</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('ahgcdpa.reports') }}" class="btn atom-btn-outline-primary">
                <i class="fas fa-file-alt me-1"></i> {{ __('Reports') }}
            </a>
            <a href="{{ route('ahgcdpa.config') }}" class="btn atom-btn-outline-secondary">
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
            <div class="card {{ $license ? 'border-success' : 'border-danger' }}">
                <div class="card-body text-center">
                    <h3>{{ $license ? max(0, (int) $licenseDaysRemaining) : '-' }}</h3>
                    <p class="text-muted mb-0">License Days Remaining</p>
                    <small class="text-{{ $license ? 'success' : 'danger' }}">{{ $licenseStatusLabel }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card {{ ($stats['requests_overdue'] ?? 0) > 0 ? 'border-danger' : '' }}">
                <div class="card-body text-center">
                    <h3>{{ $stats['requests_pending'] ?? 0 }}</h3>
                    <p class="text-muted mb-0">Pending Requests</p>
                    @if (($stats['requests_overdue'] ?? 0) > 0)
                        <small class="text-danger">{{ $stats['requests_overdue'] }} overdue</small>
                    @else
                        <small class="text-success">{{ __('None overdue') }}</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card {{ ($stats['breaches_open'] ?? 0) > 0 ? 'border-warning' : '' }}">
                <div class="card-body text-center">
                    <h3>{{ $stats['breaches_open'] ?? 0 }}</h3>
                    <p class="text-muted mb-0">Open Breaches</p>
                    <small class="text-muted">{{ $breachesThisYear }} this year</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3>{{ $stats['processing_active'] ?? 0 }}</h3>
                    <p class="text-muted mb-0">Processing Activities</p>
                    <small class="text-muted">{{ $stats['consent_active'] ?? 0 }} active consents</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Actions</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="{{ route('ahgcdpa.license') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-id-card me-2"></i> Controller License
                        @if (!$license)
                            <span class="badge bg-danger float-end">{{ __('Required') }}</span>
                        @endif
                    </a>
                    <a href="{{ route('ahgcdpa.dpo') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-user-shield me-2"></i> Data Protection Officer
                        @if (!$dpo)
                            <span class="badge bg-danger float-end">{{ __('Required') }}</span>
                        @endif
                    </a>
                    <a href="{{ route('ahgcdpa.requests') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-user-clock me-2"></i> Data Subject Requests
                        @if (($stats['requests_pending'] ?? 0) > 0)
                            <span class="badge bg-warning text-dark float-end">{{ $stats['requests_pending'] }}</span>
                        @endif
                    </a>
                    <a href="{{ route('ahgcdpa.processing') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-cogs me-2"></i> {{ __('Processing Register') }}
                    </a>
                    <a href="{{ route('ahgcdpa.dpia') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-clipboard-check me-2"></i> DPIA
                        @if ($dpiaPending > 0)
                            <span class="badge bg-info float-end">{{ $dpiaPending }}</span>
                        @endif
                    </a>
                    <a href="{{ route('ahgcdpa.consent') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-check-circle me-2"></i> {{ __('Consent Management') }}
                    </a>
                    <a href="{{ route('ahgcdpa.breaches') }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-exclamation-triangle me-2"></i> Breach Register
                        @if (($stats['breaches_open'] ?? 0) > 0)
                            <span class="badge bg-danger float-end">{{ $stats['breaches_open'] }}</span>
                        @endif
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-user-clock me-2"></i>Pending Requests</h5>
                    <a href="{{ route('ahgcdpa.request-create') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    @if ($pendingRequests->isEmpty())
                        <div class="p-3 text-center text-muted">
                            <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                            <p class="mb-0">No pending requests</p>
                        </div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($pendingRequests as $req)
                                @php $isOverdue = strtotime($req->due_date) < time(); @endphp
                                <li class="list-group-item {{ $isOverdue ? 'list-group-item-danger' : '' }}">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong>{{ $req->data_subject_name }}</strong>
                                            <br><small class="text-muted">{{ ucfirst($req->request_type) }}</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-{{ $isOverdue ? 'danger' : 'warning' }}">
                                                {{ $isOverdue ? 'OVERDUE' : 'Due: ' . $req->due_date }}
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if (!$pendingRequests->isEmpty())
                    <div class="card-footer text-center">
                        <a href="{{ route('ahgcdpa.requests', ['status' => 'pending']) }}">View All Pending</a>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Issues &amp; Warnings</h5>
                </div>
                <div class="card-body">
                    @if (empty($compliance['issues']) && empty($compliance['warnings']))
                        <div class="text-center text-success">
                            <i class="fas fa-check-circle fa-3x mb-2"></i>
                            <p>All compliance requirements met!</p>
                        </div>
                    @else
                        @if (!empty($compliance['issues']))
                            <h6 class="text-danger"><i class="fas fa-times-circle me-1"></i>Issues</h6>
                            <ul class="list-unstyled mb-3">
                                @foreach ($compliance['issues'] as $issue)
                                    <li class="mb-1"><i class="fas fa-exclamation-circle text-danger me-1"></i> {{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if (!empty($compliance['warnings']))
                            <h6 class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Warnings</h6>
                            <ul class="list-unstyled mb-0">
                                @foreach ($compliance['warnings'] as $warning)
                                    <li class="mb-1"><i class="fas fa-exclamation-triangle text-warning me-1"></i> {{ $warning }}</li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Controller License</h5>
                    <a href="{{ route('ahgcdpa.license-edit') }}" class="btn btn-sm atom-btn-outline-primary">
                        <i class="fas fa-edit"></i>
                    </a>
                </div>
                <div class="card-body">
                    @if ($license)
                        <table class="table table-sm mb-0">
                            <tr><th>{{ __('License Number') }}</th><td>{{ $license->license_number }}</td></tr>
                            <tr><th>{{ __('Tier') }}</th><td><span class="badge bg-info">{{ strtoupper($license->tier) }}</span></td></tr>
                            <tr><th>{{ __('Organization') }}</th><td>{{ $license->organization_name }}</td></tr>
                            <tr><th>{{ __('Expiry Date') }}</th><td>{{ $license->expiry_date }}</td></tr>
                            <tr><th>{{ __('Status') }}</th><td>
                                <span class="badge bg-{{ $licenseStatusLabel === 'Active' ? 'success' : 'warning' }}">
                                    {{ $licenseStatusLabel }}
                                </span>
                            </td></tr>
                        </table>
                    @else
                        <div class="text-center text-danger">
                            <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                            <p>No controller license registered</p>
                            <a href="{{ route('ahgcdpa.license-edit') }}" class="btn btn-danger">Register License</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Data Protection Officer</h5>
                    <a href="{{ route('ahgcdpa.dpo-edit') }}" class="btn btn-sm atom-btn-outline-primary">
                        <i class="fas fa-edit"></i>
                    </a>
                </div>
                <div class="card-body">
                    @if ($dpo)
                        <table class="table table-sm mb-0">
                            <tr><th>{{ __('Name') }}</th><td>{{ $dpo->name }}</td></tr>
                            <tr><th>{{ __('Email') }}</th><td>{{ $dpo->email }}</td></tr>
                            <tr><th>{{ __('Phone') }}</th><td>{{ $dpo->phone ?? '-' }}</td></tr>
                            <tr><th>{{ __('Appointed') }}</th><td>{{ $dpo->appointment_date }}</td></tr>
                            <tr><th>{{ __('DPO Registration') }}</th><td>
                                <span class="badge bg-{{ $dpo->form_dp2_submitted ? 'success' : 'warning' }}">
                                    {{ $dpo->form_dp2_submitted ? 'Submitted' : 'Not Submitted' }}
                                </span>
                            </td></tr>
                        </table>
                    @else
                        <div class="text-center text-danger">
                            <i class="fas fa-user-slash fa-2x mb-2"></i>
                            <p>No DPO appointed</p>
                            <a href="{{ route('ahgcdpa.dpo-edit') }}" class="btn btn-danger">Appoint DPO</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
