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

@section('title', ($license ?? null) ? 'Edit License' : 'Register License')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.index') }}">CDPA</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.license') }}">License</a></li>
                    <li class="breadcrumb-item active">{{ ($license ?? null) ? 'Edit' : 'Register' }}</li>
                </ol>
            </nav>
            <h1><i class="fas fa-id-card me-2"></i>{{ ($license ?? null) ? 'Edit' : 'Register' }} Controller License</h1>
        </div>
    </div>

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">License Information</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">License Number <span class="text-danger">*</span></label>
                            <input type="text" name="license_number" class="form-control"
                                   value="{{ $license->license_number ?? '' }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tier <span class="text-danger">*</span></label>
                            <select name="tier" class="form-select" required>
                                <option value="">Select...</option>
                                <option value="tier1" {{ ($license->tier ?? '') === 'tier1' ? 'selected' : '' }}>Tier 1 - Small Scale</option>
                                <option value="tier2" {{ ($license->tier ?? '') === 'tier2' ? 'selected' : '' }}>Tier 2 - Medium Scale</option>
                                <option value="tier3" {{ ($license->tier ?? '') === 'tier3' ? 'selected' : '' }}>Tier 3 - Large Scale</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Organization Name <span class="text-danger">*</span></label>
                            <input type="text" name="organization_name" class="form-control"
                                   value="{{ $license->organization_name ?? '' }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Regulator Reference</label>
                            <input type="text" name="potraz_ref" class="form-control"
                                   value="{{ $license->potraz_ref ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data Subjects Count</label>
                            <input type="number" name="data_subjects_count" class="form-control"
                                   value="{{ $license->data_subjects_count ?? '' }}" min="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Dates</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Registration Date</label>
                            <input type="date" name="registration_date" class="form-control"
                                   value="{{ $license->registration_date ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Issue Date</label>
                            <input type="date" name="issue_date" class="form-control"
                                   value="{{ $license->issue_date ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" class="form-control"
                                   value="{{ $license->expiry_date ?? '' }}" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">Notes</h5></div>
                <div class="card-body">
                    <textarea name="notes" class="form-control" rows="4">{{ $license->notes ?? '' }}</textarea>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Save License
                    </button>
                    <a href="{{ route('ahgcdpa.license') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
