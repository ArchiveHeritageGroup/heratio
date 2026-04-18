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

@section('title', 'Heritage Impact Assessment')

@section('content')
@php
  $provinces = ['Bulawayo','Harare','Manicaland','Mashonaland Central','Mashonaland East','Mashonaland West','Masvingo','Matabeleland North','Matabeleland South','Midlands'];
@endphp
<div class="container-fluid">
  <div class="row mb-4">
    <div class="col">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('nmmz.index') }}">NMMZ</a></li>
          <li class="breadcrumb-item"><a href="{{ route('nmmz.hia') }}">Heritage Impact Assessments</a></li>
          <li class="breadcrumb-item active">New Assessment</li>
        </ol>
      </nav>
      <h1><i class="fas fa-clipboard-check me-2"></i>Heritage Impact Assessment</h1>
      <p class="text-muted">Submit HIA</p>
    </div>
  </div>

  <form method="post" class="row g-4">
    @csrf
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Project Information</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Project Name <span class="text-danger">*</span></label>
              <input type="text" name="project_name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Project Type</label>
              <select name="project_type" class="form-select">
                <option value="">Select...</option>
                <option value="construction">Construction</option>
                <option value="mining">Mining</option>
                <option value="infrastructure">Infrastructure</option>
                <option value="agriculture">Agriculture</option>
                <option value="tourism">Tourism Development</option>
                <option value="energy">Energy</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Project Description <span class="text-danger">*</span></label>
              <textarea name="project_description" class="form-control" rows="4" required></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Project Location</label>
              <textarea name="project_location" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Province</label>
              <select name="province" class="form-select">
                <option value="">Select...</option>
                @foreach($provinces as $p)
                  <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">District</label>
              <input type="text" name="district" class="form-control">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Developer Information</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Developer/Company Name <span class="text-danger">*</span></label>
              <input type="text" name="developer_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Contact Phone</label>
              <input type="tel" name="developer_contact" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Contact Email</label>
              <input type="email" name="developer_email" class="form-control">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Assessment Details</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Assessor Name</label>
              <input type="text" name="assessor_name" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Assessor Qualification</label>
              <input type="text" name="assessor_qualification" class="form-control" placeholder="e.g., PhD Archaeology">
            </div>
            <div class="col-md-6">
              <label class="form-label">Impact Level <span class="text-danger">*</span></label>
              <select name="impact_level" class="form-select" required>
                <option value="">Select...</option>
                <option value="low">Low - No significant heritage resources</option>
                <option value="medium">Medium - Some heritage resources present</option>
                <option value="high">High - Significant heritage resources at risk</option>
                <option value="critical">Critical - Major heritage site threatened</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Impact Description</label>
              <textarea name="impact_description" class="form-control" rows="4" placeholder="Describe potential impacts on heritage resources"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Proposed Mitigation Measures</label>
              <textarea name="mitigation_measures" class="form-control" rows="4" placeholder="Describe proposed measures to protect or mitigate impact"></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="alert alert-warning">
        <h6><i class="fas fa-exclamation-triangle me-1"></i> Important</h6>
        <ul class="small mb-0">
          <li>HIAs are required for developments that may impact heritage sites</li>
          <li>Assessment must be conducted by qualified professional</li>
          <li>Review required before project commencement</li>
        </ul>
      </div>

      <div class="card">
        <div class="card-body d-grid gap-2">
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-paper-plane me-2"></i>Submit Assessment
          </button>
          <a href="{{ route('nmmz.hia') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
