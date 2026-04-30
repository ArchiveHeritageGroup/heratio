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

@section('title', 'Log Data Subject Request')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.index') }}">CDPA</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.requests') }}">Requests</a></li>
                    <li class="breadcrumb-item active">New Request</li>
                </ol>
            </nav>
            <h1><i class="fas fa-user-clock me-2"></i>Log Data Subject Request</h1>
        </div>
    </div>

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Data Subject Information') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="data_subject_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="data_subject_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Phone') }}</label>
                            <input type="tel" name="data_subject_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('ID Number') }}</label>
                            <input type="text" name="data_subject_id_number" class="form-control">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Request Details') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Request Type <span class="text-danger">*</span></label>
                            <select name="request_type" class="form-select" required>
                                <option value="">{{ __('Select...') }}</option>
                                <option value="access">{{ __('Access - Obtain copy of personal data') }}</option>
                                <option value="rectification">{{ __('Rectification - Correct inaccurate data') }}</option>
                                <option value="erasure">{{ __('Erasure - Delete personal data') }}</option>
                                <option value="restriction">{{ __('Restriction - Limit processing') }}</option>
                                <option value="portability">{{ __('Portability - Data transfer') }}</option>
                                <option value="objection">{{ __('Objection - Object to processing') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Verification Method') }}</label>
                            <select name="verification_method" class="form-select">
                                <option value="">{{ __('Select...') }}</option>
                                <option value="id_document">{{ __('ID Document') }}</option>
                                <option value="email_verification">{{ __('Email Verification') }}</option>
                                <option value="in_person">{{ __('In Person') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Request Date <span class="text-danger">*</span></label>
                            <input type="date" name="request_date" class="form-control" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control" value="{{ \Carbon\Carbon::now()->addDays(30)->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" required placeholder="{{ __('Describe the request and what personal data is involved...') }}"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle me-1"></i> Response Deadline</h6>
                <p class="small mb-0">Data subject requests must be responded to within 30 days under CDPA.</p>
            </div>

            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Log Request
                    </button>
                    <a href="{{ route('ahgcdpa.requests') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
