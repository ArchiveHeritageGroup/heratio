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

@section('title', 'Report Data Breach')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.index') }}">CDPA</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.breaches') }}">Breaches</a></li>
                    <li class="breadcrumb-item active">Report Breach</li>
                </ol>
            </nav>
            <h1><i class="fas fa-exclamation-triangle me-2"></i>Report Data Breach</h1>
        </div>
    </div>

    <div class="alert alert-danger mb-4">
        <h5><i class="fas fa-clock me-2"></i>72-Hour Notification Requirement</h5>
        <p class="mb-0">Under CDPA, data breaches must be reported to the regulator within 72 hours of discovery.</p>
    </div>

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Incident Details') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Incident Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="incident_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Discovery Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="discovery_date" class="form-control" required value="{{ \Carbon\Carbon::now()->format('Y-m-d\TH:i') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Breach Type <span class="text-danger">*</span></label>
                            <select name="breach_type" class="form-select" required>
                                <option value="">{{ __('Select...') }}</option>
                                <option value="unauthorized_access">{{ __('Unauthorized Access') }}</option>
                                <option value="data_theft">{{ __('Data Theft') }}</option>
                                <option value="accidental_disclosure">{{ __('Accidental Disclosure') }}</option>
                                <option value="loss">{{ __('Loss of Data/Equipment') }}</option>
                                <option value="ransomware">{{ __('Ransomware/Malware') }}</option>
                                <option value="phishing">{{ __('Phishing Attack') }}</option>
                                <option value="system_failure">{{ __('System Failure') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Severity <span class="text-danger">*</span></label>
                            <select name="severity" class="form-select" required>
                                <option value="">{{ __('Select...') }}</option>
                                <option value="low">{{ __('Low - Limited impact') }}</option>
                                <option value="medium">{{ __('Medium - Moderate impact') }}</option>
                                <option value="high">{{ __('High - Significant impact') }}</option>
                                <option value="critical">{{ __('Critical - Severe impact') }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" required placeholder="{{ __('Describe what happened...') }}"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Impact Assessment') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">{{ __('Data Types Affected') }}</label>
                            <input type="text" name="data_affected" class="form-control" placeholder="{{ __('e.g., Names, ID numbers, financial data') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Records Affected (estimated)') }}</label>
                            <input type="number" name="records_affected" class="form-control" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Data Subjects Affected (estimated)') }}</label>
                            <input type="number" name="data_subjects_affected" class="form-control" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Root Cause (if known)') }}</label>
                            <textarea name="root_cause" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-danger btn-lg">
                        <i class="fas fa-exclamation-triangle me-2"></i>{{ __('Report Breach') }}
                    </button>
                    <a href="{{ route('ahgcdpa.breaches') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
