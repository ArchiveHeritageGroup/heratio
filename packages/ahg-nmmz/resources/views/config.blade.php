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

@section('title', 'NMMZ Configuration')

@section('content')
<div class="container-fluid">
  <div class="row mb-4">
    <div class="col">
      <nav aria-label="{{ __('breadcrumb') }}">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('nmmz.index') }}">NMMZ</a></li>
          <li class="breadcrumb-item active">Configuration</li>
        </ol>
      </nav>
      <h1><i class="fas fa-cog me-2"></i>{{ __('NMMZ Configuration') }}</h1>
      <p class="text-muted">Configure module settings (jurisdictional plugin)</p>
    </div>
  </div>

  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="post">
    @csrf
    <div class="row">
      <div class="col-lg-8">
        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">{{ __('Antiquity Settings') }}</h5></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">{{ __('Minimum Age for Antiquity (Years)') }}</label>
                <input type="number" name="antiquity_age_years" class="form-control"
                       value="{{ $config['antiquity_age_years'] ?? '100' }}" min="1">
                <small class="text-muted">{{ __('Objects older than this are classified as antiquities') }}</small>
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">{{ __('Export Permit Settings') }}</h5></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">{{ __('Permit Application Fee (USD)') }}</label>
                <input type="number" name="export_permit_fee_usd" class="form-control"
                       value="{{ $config['export_permit_fee_usd'] ?? '' }}" min="0" step="0.01">
              </div>
              <div class="col-md-6">
                <label class="form-label">{{ __('Permit Validity Period (Days)') }}</label>
                <input type="number" name="export_permit_validity_days" class="form-control"
                       value="{{ $config['export_permit_validity_days'] ?? '90' }}" min="1">
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">{{ __('NMMZ Contact Information') }}</h5></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">{{ __('Contact Email') }}</label>
                <input type="email" name="nmmz_contact_email" class="form-control"
                       value="{{ $config['nmmz_contact_email'] ?? '' }}">
              </div>
              <div class="col-md-6">
                <label class="form-label">{{ __('Contact Phone') }}</label>
                <input type="tel" name="nmmz_contact_phone" class="form-control"
                       value="{{ $config['nmmz_contact_phone'] ?? '' }}">
              </div>
              <div class="col-md-6">
                <label class="form-label">{{ __('Director Name') }}</label>
                <input type="text" name="director_name" class="form-control"
                       value="{{ $config['director_name'] ?? '' }}">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card mb-4">
          <div class="card-body d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="fas fa-save me-2"></i>{{ __('Save Configuration') }}
            </button>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h5 class="mb-0">{{ __('About') }}</h5></div>
          <div class="card-body">
            <p class="small text-muted mb-2">
              <strong>{{ __('NMMZ Plugin') }}</strong><br>
              Jurisdictional compliance module for national museums and monuments regulations.
            </p>
            <p class="small text-muted mb-0">
              <strong>{{ __('Version:') }}</strong> 1.0.0
            </p>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
@endsection
