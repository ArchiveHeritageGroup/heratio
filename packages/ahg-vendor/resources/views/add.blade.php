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
@php $formErrors = is_array($errors ?? null) ? $errors : []; $errors = new \Illuminate\Support\ViewErrorBag(); @endphp
@extends('theme::layouts.1col')

@section('title', 'Add Vendor')

@section('content')
<div class="container-fluid px-4">
    @if (session('notice'))
        <div class="alert alert-success">{{ session('notice') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.index') }}">Vendor Management</a></li>
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.list') }}">Vendors</a></li>
            <li class="breadcrumb-item active">Add Vendor</li>
        </ol>
    </nav>

    <h1 class="h2 mb-4"><i class="fas fa-plus-circle me-2"></i>{{ __('Add New Vendor') }}</h1>

    @if (!empty($formErrors))
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($formErrors as $field => $error)
            <li>{{ e(is_array($error) ? implode(', ', $error) : $error) }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="post" action="{{ route('ahgvendor.add') }}">
        @csrf
        <div class="row">
            <div class="col-md-8">
                {{-- Basic Information --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i>{{ __('Basic Information') }}
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">{{ __('Vendor Name *') }}</label>
                                <input type="text" name="name" class="form-control" value="{{ e($form['name'] ?? '') }}" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Type') }}</label>
                                <select name="vendor_type" class="form-select">
                                    @foreach (($vendorTypes ?? []) as $key => $label)
                                    <option value="{{ $key }}" {{ ($form['vendor_type'] ?? 'company') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Registration Number') }}</label>
                                <input type="text" name="registration_number" class="form-control" value="{{ e($form['registration_number'] ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('VAT Number') }}</label>
                                <input type="text" name="vat_number" class="form-control" value="{{ e($form['vat_number'] ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Address --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-map-marker-alt me-2"></i>{{ __('Address') }}
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Street Address') }}</label>
                            <textarea name="street_address" class="form-control" rows="2">{{ e($form['street_address'] ?? '') }}</textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('City') }}</label>
                                <input type="text" name="city" class="form-control" value="{{ e($form['city'] ?? '') }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Province') }}</label>
                                <input type="text" name="province" class="form-control" value="{{ e($form['province'] ?? '') }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Postal Code') }}</label>
                                <input type="text" name="postal_code" class="form-control" value="{{ e($form['postal_code'] ?? '') }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Country') }}</label>
                            <input type="text" name="country" class="form-control" value="{{ e($form['country'] ?? '') }}">
                        </div>
                    </div>
                </div>

                {{-- Contact --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-phone me-2"></i>{{ __('Contact Details') }}
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Phone') }}</label>
                                <input type="text" name="phone" class="form-control" value="{{ e($form['phone'] ?? '') }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Alt. Phone') }}</label>
                                <input type="text" name="phone_alt" class="form-control" value="{{ e($form['phone_alt'] ?? '') }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Fax') }}</label>
                                <input type="text" name="fax" class="form-control" value="{{ e($form['fax'] ?? '') }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Email') }}</label>
                                <input type="email" name="email" class="form-control" value="{{ e($form['email'] ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Website') }}</label>
                                <input type="url" name="website" class="form-control" value="{{ e($form['website'] ?? '') }}" placeholder="{{ __('https://') }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Banking --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-university me-2"></i>{{ __('Banking Details') }}
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Bank Name') }}</label>
                                <input type="text" name="bank_name" class="form-control" value="{{ e($form['bank_name'] ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Branch') }}</label>
                                <input type="text" name="bank_branch" class="form-control" value="{{ e($form['bank_branch'] ?? '') }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Account Number') }}</label>
                                <input type="text" name="bank_account_number" class="form-control" value="{{ e($form['bank_account_number'] ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Branch Code') }}</label>
                                <input type="text" name="bank_branch_code" class="form-control" value="{{ e($form['bank_branch_code'] ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                {{-- Insurance --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-shield-alt me-2"></i>{{ __('Insurance Details') }}
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input type="checkbox" name="has_insurance" value="1" class="form-check-input" id="hasInsurance" {{ ($form['has_insurance'] ?? 0) ? 'checked' : '' }}>
                            <label class="form-check-label" for="hasInsurance">{{ __('Vendor has insurance') }}</label>
                        </div>
                        <div id="insuranceDetails">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Insurance Provider') }}</label>
                                <input type="text" name="insurance_provider" class="form-control" value="{{ e($form['insurance_provider'] ?? '') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Policy Number') }}</label>
                                <input type="text" name="insurance_policy_number" class="form-control" value="{{ e($form['insurance_policy_number'] ?? '') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Expiry Date') }}</label>
                                <input type="date" name="insurance_expiry_date" class="form-control" value="{{ e($form['insurance_expiry_date'] ?? '') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Coverage Amount') }}</label>
                                <input type="number" name="insurance_coverage_amount" class="form-control" step="0.01" value="{{ e($form['insurance_coverage_amount'] ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Services --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-tools me-2"></i>{{ __('Services Offered') }}
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Select services this vendor provides:</p>
                        @foreach (($serviceTypes ?? []) as $service)
                        <div class="form-check mb-2">
                            <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" class="form-check-input" id="service_{{ $service->id }}">
                            <label class="form-check-label" for="service_{{ $service->id }}">{{ e($service->name) }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Notes --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-sticky-note me-2"></i>{{ __('Notes') }}
                    </div>
                    <div class="card-body">
                        <textarea name="notes" class="form-control" rows="5" placeholder="{{ __('Internal notes about this vendor...') }}">{{ e($form['notes'] ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>{{ __('Save Vendor') }}
                </button>
                <a href="{{ route('ahgvendor.list') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var hasInsurance = document.getElementById('hasInsurance');
    var insuranceDetails = document.getElementById('insuranceDetails');

    function toggleInsurance() {
        insuranceDetails.style.display = hasInsurance.checked ? 'block' : 'none';
    }

    hasInsurance.addEventListener('change', toggleInsurance);
    toggleInsurance();
});
</script>
@endsection
