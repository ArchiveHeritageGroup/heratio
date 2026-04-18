{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'CDPA Configuration')

@section('content')
@php
    $settings = $settings ?? collect();
    $cfgGet = function ($key, $default = '') use ($settings) {
        $row = null;
        if (is_object($settings) && method_exists($settings, 'get')) {
            $row = $settings->get($key);
        } elseif (is_array($settings) || $settings instanceof \ArrayAccess) {
            $row = $settings[$key] ?? null;
        }
        if (!$row) return $default;
        return $row->setting_value ?? $default;
    };
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.index') }}">CDPA</a></li>
                    <li class="breadcrumb-item active">Configuration</li>
                </ol>
            </nav>
            <h1><i class="fas fa-cog me-2"></i>CDPA Configuration</h1>
            <p class="text-muted">Configure plugin settings for CDPA compliance</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('notice'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('notice') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="post">
        @csrf
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Organization Details</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Organization Name</label>
                                <input type="text" name="organization_name" class="form-control"
                                       value="{{ $cfgGet('organization_name') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Organization Address</label>
                                <textarea name="organization_address" class="form-control" rows="2">{{ $cfgGet('organization_address') }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">DPO Email (for notifications)</label>
                                <input type="email" name="dpo_email" class="form-control"
                                       value="{{ $cfgGet('dpo_email') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">Compliance Deadlines</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Data Subject Request Response (days)</label>
                                <input type="number" name="response_deadline_days" class="form-control"
                                       value="{{ $cfgGet('response_deadline_days', 30) }}" min="1" max="90">
                                <small class="text-muted">CDPA requires response within 30 days</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Breach Notification (hours)</label>
                                <input type="number" name="breach_notification_hours" class="form-control"
                                       value="{{ $cfgGet('breach_notification_hours', 72) }}" min="1" max="168">
                                <small class="text-muted">CDPA requires notification within 72 hours</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">License Renewal Reminder (days)</label>
                                <input type="number" name="license_reminder_days" class="form-control"
                                       value="{{ $cfgGet('license_reminder_days', 90) }}" min="7" max="365">
                                <small class="text-muted">Days before expiry to show warning</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">DPIA Review Period (months)</label>
                                <input type="number" name="dpia_review_months" class="form-control"
                                       value="{{ $cfgGet('dpia_review_months', 12) }}" min="1" max="36">
                                <small class="text-muted">Recommended review frequency</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-body d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Save Configuration
                        </button>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="mb-0">CDPA Key Requirements</h5></div>
                    <div class="card-body small">
                        <ul class="mb-0">
                            <li><strong>Registration:</strong> Register with the regulator</li>
                            <li><strong>DPO:</strong> Appoint Data Protection Officer</li>
                            <li><strong>ROPA:</strong> Maintain processing records</li>
                            <li><strong>Rights:</strong> Respond to data subject requests</li>
                            <li><strong>Breaches:</strong> Report within 72 hours</li>
                            <li><strong>DPIA:</strong> Assess high-risk processing</li>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="mb-0">About</h5></div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">
                            <strong>CDPA Module</strong><br>
                            Consumer / Cyber Data Protection Act compliance module (per-jurisdiction).
                        </p>
                        <p class="small text-muted mb-0">
                            <strong>Version:</strong> 1.0.0<br>
                            <strong>Author:</strong> The Archive and Heritage Group
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
