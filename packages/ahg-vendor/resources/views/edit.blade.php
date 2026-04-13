{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@php $formErrors = is_array($errors ?? null) ? $errors : []; $errors = new \Illuminate\Support\ViewErrorBag(); @endphp
@extends('theme::layouts.1col')

@section('title', 'Edit Vendor')

@section('content')
<div class="container-fluid px-4">
    @if (session('notice'))
        <div class="alert alert-success">{{ session('notice') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.index') }}">Vendor Management</a></li>
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.list') }}">Vendors</a></li>
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.view', ['slug' => $vendor->slug]) }}">{{ e($vendor->name) }}</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <i class="fas fa-edit me-2"></i>Edit Vendor: {{ e($vendor->name) }}
        </h1>
    </div>

    @if (!empty($formErrors))
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($formErrors as $field => $error)
            <li>{{ e(is_array($error) ? implode(', ', $error) : $error) }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="post" action="{{ route('ahgvendor.edit', ['slug' => $vendor->slug]) }}" class="needs-validation" novalidate>
        @csrf
        <input type="hidden" name="id" value="{{ $vendor->id }}">

        <div class="row">
            <div class="col-md-8">
                {{-- Basic Information --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i>Basic Information
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Vendor Name *</label>
                                <input type="text" name="name" class="form-control" value="{{ e($vendor->name ?? '') }}" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Vendor Code</label>
                                <input type="text" name="vendor_code" class="form-control" value="{{ e($vendor->vendor_code ?? '') }}" placeholder="Auto-generated if empty">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vendor Type *</label>
                                <select name="vendor_type" class="form-select" required>
                                    <option value="">Select Type...</option>
                                    @foreach (($vendorTypes ?? []) as $key => $label)
                                    <option value="{{ $key }}" {{ ($vendor->vendor_type ?? '') === $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    @foreach (($vendorStatuses ?? []) as $code => $label)
                                    <option value="{{ $code }}" {{ ($vendor->status ?? '') === $code ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Registration Number</label>
                                <input type="text" name="registration_number" class="form-control" value="{{ e($vendor->registration_number ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">VAT Number</label>
                                <input type="text" name="vat_number" class="form-control" value="{{ e($vendor->vat_number ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Contact Information --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-phone me-2"></i>Contact Information
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="{{ e($vendor->phone ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Alternative Phone</label>
                                <input type="text" name="phone_alt" class="form-control" value="{{ e($vendor->phone_alt ?? '') }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="{{ e($vendor->email ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Website</label>
                                <input type="url" name="website" class="form-control" value="{{ e($vendor->website ?? '') }}" placeholder="https://">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Address --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-map-marker-alt me-2"></i>Address
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Street Address</label>
                            <textarea name="street_address" class="form-control" rows="2">{{ e($vendor->street_address ?? '') }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control" value="{{ e($vendor->city ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Province/State</label>
                                <input type="text" name="province" class="form-control" value="{{ e($vendor->province ?? '') }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" name="postal_code" class="form-control" value="{{ e($vendor->postal_code ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" name="country" class="form-control" value="{{ e($vendor->country ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Banking Details --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-university me-2"></i>Banking Details
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" value="{{ e($vendor->bank_name ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Branch Name</label>
                                <input type="text" name="bank_branch" class="form-control" value="{{ e($vendor->bank_branch ?? '') }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Number</label>
                                <input type="text" name="bank_account_number" class="form-control" value="{{ e($vendor->bank_account_number ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Branch Code</label>
                                <input type="text" name="bank_branch_code" class="form-control" value="{{ e($vendor->bank_branch_code ?? '') }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Account Type</label>
                            <select name="bank_account_type" class="form-select">
                                <option value="">Select...</option>
                                <option value="cheque" {{ ($vendor->bank_account_type ?? '') === 'cheque' ? 'selected' : '' }}>Cheque Account</option>
                                <option value="savings" {{ ($vendor->bank_account_type ?? '') === 'savings' ? 'selected' : '' }}>Savings Account</option>
                                <option value="transmission" {{ ($vendor->bank_account_type ?? '') === 'transmission' ? 'selected' : '' }}>Transmission Account</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-sticky-note me-2"></i>Notes
                    </div>
                    <div class="card-body">
                        <textarea name="notes" class="form-control" rows="4">{{ e($vendor->notes ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                {{-- Services --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-tools me-2"></i>Services Provided
                    </div>
                    <div class="card-body">
                        <div class="services-list" style="max-height: 300px; overflow-y: auto;">
                            @php
                            $vendorServiceIds = [];
                            if (!empty($vendorServices)) {
                                foreach ($vendorServices as $vs) {
                                    $vendorServiceIds[] = is_object($vs) ? ($vs->service_type_id ?? $vs->id) : ($vs['service_type_id'] ?? $vs['id']);
                                }
                            }
                            @endphp
                            @foreach (($serviceTypes ?? []) as $service)
                            <div class="form-check mb-2">
                                <input type="checkbox" name="service_ids[]" value="{{ $service->id }}"
                                       class="form-check-input" id="service_{{ $service->id }}"
                                       {{ in_array($service->id, $vendorServiceIds) ? 'checked' : '' }}>
                                <label class="form-check-label" for="service_{{ $service->id }}">
                                    {{ e($service->name) }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Insurance --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-shield-alt me-2"></i>Insurance Details
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input type="checkbox" name="has_insurance" value="1" class="form-check-input" id="hasInsurance"
                                   {{ !empty($vendor->has_insurance) ? 'checked' : '' }}>
                            <label class="form-check-label" for="hasInsurance">Has Insurance</label>
                        </div>

                        <div id="insuranceFields">
                            <div class="mb-3">
                                <label class="form-label">Insurance Provider</label>
                                <input type="text" name="insurance_provider" class="form-control" value="{{ e($vendor->insurance_provider ?? '') }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Policy Number</label>
                                <input type="text" name="insurance_policy_number" class="form-control" value="{{ e($vendor->insurance_policy_number ?? '') }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" name="insurance_expiry_date" class="form-control" value="{{ $vendor->insurance_expiry_date ?? '' }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Coverage Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">R</span>
                                    <input type="number" name="insurance_coverage_amount" class="form-control" step="0.01" value="{{ $vendor->insurance_coverage_amount ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Additional Options --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-cog me-2"></i>Options
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-2">
                            <input type="checkbox" name="is_preferred" value="1" class="form-check-input" id="isPreferred"
                                   {{ !empty($vendor->is_preferred) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isPreferred">Preferred Vendor</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="is_bbbee_compliant" value="1" class="form-check-input" id="isBBBEE"
                                   {{ !empty($vendor->is_bbbee_compliant) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isBBBEE">B-BBEE Compliant</label>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                            <a href="{{ route('ahgvendor.view', ['slug' => $vendor->slug]) }}" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var hasInsurance = document.getElementById('hasInsurance');
    var insuranceFields = document.getElementById('insuranceFields');

    function toggleInsurance() {
        insuranceFields.style.display = hasInsurance.checked ? 'block' : 'none';
    }

    hasInsurance.addEventListener('change', toggleInsurance);
    toggleInsurance();

    var form = document.querySelector('.needs-validation');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    }
});
</script>
@endsection
