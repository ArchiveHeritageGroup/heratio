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

@section('title', 'Register Antiquity')

@section('content')
<div class="container-fluid">
  <div class="row mb-4">
    <div class="col">
      <nav aria-label="{{ __('breadcrumb') }}">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('nmmz.index') }}">NMMZ</a></li>
          <li class="breadcrumb-item"><a href="{{ route('nmmz.antiquities') }}">Antiquities</a></li>
          <li class="breadcrumb-item active">Register Antiquity</li>
        </ol>
      </nav>
      <h1><i class="fas fa-vase me-2"></i>{{ __('Register Antiquity') }}</h1>
      <p class="text-muted">Objects over 100 years old are protected</p>
    </div>
  </div>

  <form method="post" class="row g-4">
    @csrf
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Object Information') }}</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Name/Title <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Description') }}</label>
              <textarea name="description" class="form-control" rows="4"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Object Type') }}</label>
              <select name="object_type" class="form-select">
                <option value="">{{ __('Select...') }}</option>
                <option value="ceramic">{{ __('Ceramic') }}</option>
                <option value="stone">{{ __('Stone Tool/Object') }}</option>
                <option value="metal">{{ __('Metal Work') }}</option>
                <option value="bone">{{ __('Bone/Ivory') }}</option>
                <option value="textile">{{ __('Textile') }}</option>
                <option value="wooden">{{ __('Wooden') }}</option>
                <option value="document">{{ __('Document') }}</option>
                <option value="jewelry">{{ __('Jewelry') }}</option>
                <option value="sculpture">{{ __('Sculpture') }}</option>
                <option value="painting">{{ __('Painting') }}</option>
                <option value="other">{{ __('Other') }}</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Material') }}</label>
              <input type="text" name="material" class="form-control" placeholder="{{ __('e.g., Bronze, Soapstone, Wood') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Estimated Age (Years)') }}</label>
              <input type="number" name="estimated_age_years" class="form-control" min="100" placeholder="{{ __('Minimum 100 years') }}">
              <small class="text-muted">{{ __('Must be over 100 years to qualify as antiquity') }}</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Dimensions') }}</label>
              <input type="text" name="dimensions" class="form-control" placeholder="{{ __('e.g., 15cm x 10cm x 5cm') }}">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Provenance') }}</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">{{ __('Provenance/History') }}</label>
              <textarea name="provenance" class="form-control" rows="3" placeholder="{{ __('Origin and ownership history') }}"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Find Location') }}</label>
              <input type="text" name="find_location" class="form-control" placeholder="{{ __('Where was the object found/acquired?') }}">
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ __('Current Status') }}</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">{{ __('Current Location') }}</label>
              <input type="text" name="current_location" class="form-control" placeholder="{{ __('Museum, private collection, etc.') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Condition') }}</label>
              <select name="condition_rating" class="form-select">
                <option value="">{{ __('Select...') }}</option>
                <option value="excellent">{{ __('Excellent') }}</option>
                <option value="good">{{ __('Good') }}</option>
                <option value="fair">{{ __('Fair') }}</option>
                <option value="poor">{{ __('Poor') }}</option>
                <option value="fragmentary">{{ __('Fragmentary') }}</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Estimated Value (USD)') }}</label>
              <input type="number" name="estimated_value" class="form-control" min="0" step="0.01">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-body d-grid gap-2">
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save me-2"></i>{{ __('Register Antiquity') }}
          </button>
          <a href="{{ route('nmmz.antiquities') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
