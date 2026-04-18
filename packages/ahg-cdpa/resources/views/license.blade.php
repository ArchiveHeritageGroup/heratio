{{-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems — johan@plainsailingisystems.co.za
     This file is part of Heratio. Licensed under the GNU Affero General Public License v3+. --}}
@extends('theme::layouts.1col')

@section('title', 'Controller License')

@section('content')
@php
    $license = $licenses->first();
    $licenseStatus = 'inactive';
    $daysRemaining = null;
    if ($license && $license->expiry_date) {
        $daysRemaining = (int) ((strtotime($license->expiry_date) - time()) / 86400);
        if ($daysRemaining < 0)      { $licenseStatus = 'expired'; }
        elseif ($daysRemaining <= 60) { $licenseStatus = 'expiring_soon'; }
        else                           { $licenseStatus = 'active'; }
    }
    $statusColors = ['active' => 'success', 'expiring_soon' => 'warning', 'expired' => 'danger'];
    $color = $statusColors[$licenseStatus] ?? 'secondary';
@endphp

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.index') }}">CDPA</a></li>
                    <li class="breadcrumb-item active">Controller License</li>
                </ol>
            </nav>
            <h1><i class="fas fa-id-card me-2"></i>Controller License</h1>
            <p class="text-muted">Data Controller Registration under CDPA [Chapter 12:07]</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('ahgcdpa.license-edit') }}" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i> {{ $license ? 'Edit' : 'Register' }} License
            </a>
        </div>
    </div>

    @if ($license)
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">License Details</h5></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">License Number</dt>
                            <dd class="col-sm-8"><strong>{{ $license->license_number }}</strong></dd>

                            <dt class="col-sm-4">Organization Name</dt>
                            <dd class="col-sm-8">{{ $license->organization_name }}</dd>

                            <dt class="col-sm-4">Tier</dt>
                            <dd class="col-sm-8"><span class="badge bg-info fs-6">{{ strtoupper($license->tier) }}</span></dd>

                            <dt class="col-sm-4">Registration Date</dt>
                            <dd class="col-sm-8">{{ $license->registration_date ? date('j F Y', strtotime($license->registration_date)) : '-' }}</dd>

                            <dt class="col-sm-4">Issue Date</dt>
                            <dd class="col-sm-8">{{ $license->issue_date ? date('j F Y', strtotime($license->issue_date)) : '-' }}</dd>

                            <dt class="col-sm-4">Expiry Date</dt>
                            <dd class="col-sm-8">{{ $license->expiry_date ? date('j F Y', strtotime($license->expiry_date)) : '-' }}</dd>

                            <dt class="col-sm-4">Regulator Reference</dt>
                            <dd class="col-sm-8">{{ $license->potraz_ref ?? '-' }}</dd>

                            <dt class="col-sm-4">Data Subjects Count</dt>
                            <dd class="col-sm-8">{{ $license->data_subjects_count ? number_format($license->data_subjects_count) : '-' }}</dd>

                            @if ($license->notes ?? null)
                                <dt class="col-sm-4">Notes</dt>
                                <dd class="col-sm-8">{!! nl2br(e($license->notes)) !!}</dd>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Status</h5></div>
                    <div class="card-body text-center">
                        <span class="badge bg-{{ $color }} fs-4 px-4 py-3">
                            {{ ucfirst(str_replace('_', ' ', $licenseStatus)) }}
                        </span>
                        @if ($license->expiry_date)
                            <p class="mt-3 mb-0">
                                @if ($daysRemaining > 0)
                                    <strong>{{ $daysRemaining }}</strong> days remaining
                                @elseif ($daysRemaining === 0)
                                    <span class="text-danger">Expires today</span>
                                @else
                                    <span class="text-danger">Expired {{ abs($daysRemaining) }} days ago</span>
                                @endif
                            </p>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="mb-0">License Tiers</h5></div>
                    <div class="card-body small">
                        <p class="mb-2"><strong>Tier 1:</strong> Small Scale (&lt;1,000 subjects)</p>
                        <p class="mb-2"><strong>Tier 2:</strong> Medium Scale (1,000-10,000)</p>
                        <p class="mb-0"><strong>Tier 3:</strong> Large Scale (&gt;10,000 subjects)</p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-id-card fa-4x text-muted mb-3"></i>
                <h4>No Controller License Registered</h4>
                <p class="text-muted mb-4">Data controllers must register with the regulator under the Cyber and Data Protection Act.</p>
                <a href="{{ route('ahgcdpa.license-edit') }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>Register License
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
