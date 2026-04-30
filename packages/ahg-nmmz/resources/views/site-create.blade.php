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
      <nav aria-label="{{ __('breadcrumb') }}">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('nmmz.index') }}">NMMZ</a></li>
          <li class="breadcrumb-item"><a href="{{ route('nmmz.sites') }}">Archaeological Sites</a></li>
          <li class="breadcrumb-item active">Register Site</li>
        </ol>
      </nav>
      <h1><i class="fas fa-map-marker-alt me-2"></i>{{ __('Register Archaeological Site') }}</h1>
    </div>
  </div>

  <form method="post" class="row g-4">
    @csrf
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Site Information') }}</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Site Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Site Type') }}</label>
              <select name="site_type" class="form-select">
                <option value="">{{ __('Select...') }}</option>
                <option value="rock_art">{{ __('Rock Art') }}</option>
                <option value="settlement">{{ __('Settlement') }}</option>
                <option value="burial">{{ __('Burial Site') }}</option>
                <option value="industrial">{{ __('Industrial') }}</option>
                <option value="religious">{{ __('Religious/Ceremonial') }}</option>
                <option value="cave">{{ __('Cave/Shelter') }}</option>
                <option value="iron_age">{{ __('Iron Age') }}</option>
                <option value="stone_age">{{ __('Stone Age') }}</option>
                <option value="other">{{ __('Other') }}</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Description') }}</label>
              <textarea name="description" class="form-control" rows="4"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Period') }}</label>
              <input type="text" name="period" class="form-control" placeholder="{{ __('e.g., Late Stone Age, Iron Age') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Research Potential') }}</label>
              <select name="research_potential" class="form-select">
                <option value="">{{ __('Select...') }}</option>
                <option value="high">{{ __('High') }}</option>
                <option value="medium">{{ __('Medium') }}</option>
                <option value="low">{{ __('Low') }}</option>
                <option value="unknown">{{ __('Unknown') }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Location') }}</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">{{ __('Province') }}</label>
              <select name="province" class="form-select">
                <option value="">{{ __('Select...') }}</option>
                @foreach($provinces as $p)
                  <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('District') }}</label>
              <input type="text" name="district" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Location Description') }}</label>
              <textarea name="location_description" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('GPS Latitude') }}</label>
              <input type="text" name="gps_latitude" class="form-control" placeholder="-17.8252">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('GPS Longitude') }}</label>
              <input type="text" name="gps_longitude" class="form-control" placeholder="31.0335">
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ __('Discovery Information') }}</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">{{ __('Discovery Date') }}</label>
              <input type="date" name="discovery_date" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Discovered By') }}</label>
              <input type="text" name="discovered_by" class="form-control">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Protection Status') }}</h5></div>
        <div class="card-body">
          <select name="protection_status" class="form-select">
            <option value="proposed">{{ __('Proposed') }}</option>
            <option value="protected">{{ __('Protected') }}</option>
            <option value="at_risk">{{ __('At Risk') }}</option>
          </select>
        </div>
      </div>

      <div class="card">
        <div class="card-body d-grid gap-2">
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save me-2"></i>{{ __('Register Site') }}
          </button>
          <a href="{{ route('nmmz.sites') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
