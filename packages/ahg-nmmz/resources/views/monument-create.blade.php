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

@section('title', 'Register Monument')

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
          <li class="breadcrumb-item"><a href="{{ route('nmmz.monuments') }}">National Monuments</a></li>
          <li class="breadcrumb-item active">Register Monument</li>
        </ol>
      </nav>
      <h1><i class="fas fa-monument me-2"></i>Register National Monument</h1>
    </div>
  </div>

  <form method="post" class="row g-4">
    @csrf
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Monument Information') }}</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Monument Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Category') }}</label>
              <select name="category_id" class="form-select">
                <option value="">{{ __('Select...') }}</option>
                @foreach($categories as $cat)
                  <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Description') }}</label>
              <textarea name="description" class="form-control" rows="4"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Historical Significance') }}</label>
              <textarea name="historical_significance" class="form-control" rows="3" placeholder="{{ __('Describe the historical and cultural significance') }}"></textarea>
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
        <div class="card-header"><h5 class="mb-0">{{ __('Legal &amp; Protection Status') }}</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">{{ __('Protection Level') }}</label>
              <select name="protection_level" class="form-select">
                <option value="national">{{ __('National') }}</option>
                <option value="provincial">{{ __('Provincial') }}</option>
                <option value="local">{{ __('Local') }}</option>
                <option value="world_heritage">{{ __('World Heritage') }}</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Legal Status') }}</label>
              <select name="legal_status" class="form-select">
                <option value="proposed">{{ __('Proposed') }}</option>
                <option value="provisional">{{ __('Provisional') }}</option>
                <option value="gazetted">{{ __('Gazetted') }}</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Ownership Type') }}</label>
              <select name="ownership_type" class="form-select">
                <option value="">{{ __('Select...') }}</option>
                <option value="state">{{ __('State') }}</option>
                <option value="private">{{ __('Private') }}</option>
                <option value="communal">{{ __('Communal') }}</option>
                <option value="church">{{ __('Church/Religious') }}</option>
                <option value="mixed">{{ __('Mixed') }}</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Condition Rating') }}</label>
              <select name="condition_rating" class="form-select">
                <option value="">{{ __('Select...') }}</option>
                <option value="excellent">{{ __('Excellent') }}</option>
                <option value="good">{{ __('Good') }}</option>
                <option value="fair">{{ __('Fair') }}</option>
                <option value="poor">{{ __('Poor') }}</option>
                <option value="critical">{{ __('Critical') }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-body d-grid gap-2">
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save me-2"></i>Register Monument
          </button>
          <a href="{{ route('nmmz.monuments') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
