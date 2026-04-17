{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Add Processing Activity')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.index') }}">CDPA</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.processing') }}">Processing</a></li>
                    <li class="breadcrumb-item active">Add Activity</li>
                </ol>
            </nav>
            <h1><i class="fas fa-cogs me-2"></i>Add Processing Activity</h1>
        </div>
    </div>

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Activity Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Activity Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="">Select...</option>
                                <option value="hr">HR/Employment</option>
                                <option value="customer">Customer Data</option>
                                <option value="marketing">Marketing</option>
                                <option value="research">Research</option>
                                <option value="operations">Operations</option>
                                <option value="archive">Archives</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Data Types Processed</label>
                            <input type="text" name="data_types" class="form-control" placeholder="e.g., Names, addresses, contact details, ID numbers">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Purpose of Processing <span class="text-danger">*</span></label>
                            <textarea name="purpose" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Legal Basis <span class="text-danger">*</span></label>
                            <select name="legal_basis" class="form-select" required>
                                <option value="">Select...</option>
                                <option value="consent">Consent</option>
                                <option value="contract">Contractual Necessity</option>
                                <option value="legal_obligation">Legal Obligation</option>
                                <option value="vital_interest">Vital Interest</option>
                                <option value="public_interest">Public Interest</option>
                                <option value="legitimate_interest">Legitimate Interest</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Retention Period</label>
                            <input type="text" name="retention_period" class="form-control" placeholder="e.g., 7 years, Until end of employment">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Storage & Security</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Storage Location</label>
                            <input type="text" name="storage_location" class="form-control" placeholder="e.g., Local server, Cloud (AWS), Physical files">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">International Transfer Country</label>
                            <input type="text" name="international_country" class="form-control" placeholder="If stored outside the local jurisdiction">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Security Safeguards</label>
                            <textarea name="safeguards" class="form-control" rows="2" placeholder="e.g., Encryption, access controls, backups"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">Special Categories</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cross_border" id="cross_border">
                                <label class="form-check-label" for="cross_border">Cross-border transfer</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="automated_decision" id="automated_decision">
                                <label class="form-check-label" for="automated_decision">Automated decision-making</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="children_data" id="children_data">
                                <label class="form-check-label" for="children_data">Children's data</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="biometric_data" id="biometric_data">
                                <label class="form-check-label" for="biometric_data">Biometric data</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="health_data" id="health_data">
                                <label class="form-check-label" for="health_data">Health data</label>
                            </div>
                        </div>
                        <div class="col-12" id="cross_border_safeguards_container" style="display:none;">
                            <label class="form-label">Cross-border Safeguards</label>
                            <textarea name="cross_border_safeguards" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Save Activity
                    </button>
                    <a href="{{ route('ahgcdpa.processing') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var cb = document.getElementById('cross_border');
    if (cb) {
        cb.addEventListener('change', function () {
            document.getElementById('cross_border_safeguards_container').style.display = this.checked ? 'block' : 'none';
        });
    }
});
</script>
@endsection
