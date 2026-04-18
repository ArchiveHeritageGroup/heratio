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

@section('title', 'Register Archaeological Site')

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
          <li class="breadcrumb-item"><a href="{{ route('nmmz.sites') }}">Archaeological Sites</a></li>
          <li class="breadcrumb-item active">Register Site</li>
        </ol>
      </nav>
      <h1><i class="fas fa-map-marker-alt me-2"></i>Register Archaeological Site</h1>
    </div>
  </div>

  <form method="post" class="row g-4">
    @csrf
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Site Information</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Site Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Site Type</label>
              <select name="site_type" class="form-select">
                <option value="">Select...</option>
                <option value="rock_art">Rock Art</option>
                <option value="settlement">Settlement</option>
                <option value="burial">Burial Site</option>
                <option value="industrial">Industrial</option>
                <option value="religious">Religious/Ceremonial</option>
                <option value="cave">Cave/Shelter</option>
                <option value="iron_age">Iron Age</option>
                <option value="stone_age">Stone Age</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="4"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Period</label>
              <input type="text" name="period" class="form-control" placeholder="e.g., Late Stone Age, Iron Age">
            </div>
            <div class="col-md-6">
              <label class="form-label">Research Potential</label>
              <select name="research_potential" class="form-select">
                <option value="">Select...</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
                <option value="unknown">Unknown</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Location</h5></div>
        <div class="card-body">
          <div class="row g-3">
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
            <div class="col-12">
              <label class="form-label">Location Description</label>
              <textarea name="location_description" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">GPS Latitude</label>
              <input type="text" name="gps_latitude" class="form-control" placeholder="-17.8252">
            </div>
            <div class="col-md-6">
              <label class="form-label">GPS Longitude</label>
              <input type="text" name="gps_longitude" class="form-control" placeholder="31.0335">
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">Discovery Information</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Discovery Date</label>
              <input type="date" name="discovery_date" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Discovered By</label>
              <input type="text" name="discovered_by" class="form-control">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Protection Status</h5></div>
        <div class="card-body">
          <select name="protection_status" class="form-select">
            <option value="proposed">Proposed</option>
            <option value="protected">Protected</option>
            <option value="at_risk">At Risk</option>
          </select>
        </div>
      </div>

      <div class="card">
        <div class="card-body d-grid gap-2">
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save me-2"></i>Register Site
          </button>
          <a href="{{ route('nmmz.sites') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
