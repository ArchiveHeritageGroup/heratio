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

@section('title', 'Export Permit Application')

@section('content')
<div class="container-fluid">
  <div class="row mb-4">
    <div class="col">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('nmmz.index') }}">NMMZ</a></li>
          <li class="breadcrumb-item"><a href="{{ route('nmmz.permits') }}">Export Permits</a></li>
          <li class="breadcrumb-item active">New Application</li>
        </ol>
      </nav>
      <h1><i class="fas fa-file-export me-2"></i>Export Permit Application</h1>
      <p class="text-muted">Apply to export antiquities or heritage objects</p>
    </div>
  </div>

  <form method="post" class="row g-4">
    @csrf
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Applicant Information</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Applicant Name <span class="text-danger">*</span></label>
              <input type="text" name="applicant_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Applicant Type</label>
              <select name="applicant_type" class="form-select">
                <option value="individual">Individual</option>
                <option value="institution">Institution/Museum</option>
                <option value="dealer">Art Dealer</option>
                <option value="researcher">Researcher</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Address</label>
              <textarea name="applicant_address" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="applicant_email" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="tel" name="applicant_phone" class="form-control">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Object to be Exported</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Linked Antiquity (if registered)</label>
              <input type="number" name="antiquity_id" class="form-control" placeholder="Antiquity ID (optional)">
            </div>
            <div class="col-md-6">
              <label class="form-label">Quantity</label>
              <input type="number" name="quantity" class="form-control" value="1" min="1">
            </div>
            <div class="col-12">
              <label class="form-label">Object Description <span class="text-danger">*</span></label>
              <textarea name="object_description" class="form-control" rows="4" required placeholder="Detailed description of the object(s) to be exported"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Estimated Value (USD)</label>
              <input type="number" name="estimated_value" class="form-control" min="0" step="0.01">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Export Details</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Export Purpose <span class="text-danger">*</span></label>
              <select name="export_purpose" class="form-select" required>
                <option value="">Select...</option>
                <option value="exhibition">Exhibition</option>
                <option value="research">Research/Study</option>
                <option value="conservation">Conservation/Restoration</option>
                <option value="sale">Sale</option>
                <option value="permanent">Permanent Export</option>
                <option value="loan">Temporary Loan</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Destination Country <span class="text-danger">*</span></label>
              <input type="text" name="destination_country" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Purpose Details</label>
              <textarea name="purpose_details" class="form-control" rows="3" placeholder="Additional details about the purpose of export"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Destination Institution</label>
              <input type="text" name="destination_institution" class="form-control" placeholder="Museum, gallery, or institution name">
            </div>
            <div class="col-md-6">
              <label class="form-label">Proposed Export Date</label>
              <input type="date" name="export_date_proposed" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Return Date (if temporary)</label>
              <input type="date" name="return_date" class="form-control">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="alert alert-info">
        <h6><i class="fas fa-info-circle me-1"></i> Important</h6>
        <ul class="small mb-0">
          <li>Export of antiquities requires jurisdictional approval</li>
          <li>Processing may take 2-4 weeks</li>
          <li>Fees apply per schedule</li>
          <li>False declarations are punishable by law</li>
        </ul>
      </div>

      <div class="card">
        <div class="card-body d-grid gap-2">
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-paper-plane me-2"></i>Submit Application
          </button>
          <a href="{{ route('nmmz.permits') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
