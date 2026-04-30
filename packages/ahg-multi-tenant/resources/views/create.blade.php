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

@section('title', 'Create Tenant')

@section('content')
@php
  $repositories = $repositories ?? [];
  $users = $users ?? [];
@endphp
<div class="container-fluid py-4">
  <div class="row">
    <div class="col-12 col-lg-8 offset-lg-2">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">
          <i class="fas fa-plus-circle me-2"></i>
          {{ __('Create Tenant') }}
        </h1>
        <a href="{{ route('tenant.index') }}" class="btn btn-outline-secondary">
          <i class="fas fa-arrow-left me-2"></i>{{ __('Back to List') }}
        </a>
      </div>

      @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
          {{ session('error') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      <form action="{{ route('tenant.create') }}" method="post">
        @csrf
        <div class="card mb-4">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-building me-2"></i>{{ __('Tenant Information') }}</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="name" class="form-label">Tenant Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" required autofocus>
                <small class="form-text text-muted">{{ __('Display name for the tenant') }}</small>
              </div>
              <div class="col-md-6 mb-3">
                <label for="code" class="form-label">{{ __('Code') }}</label>
                <input type="text" class="form-control" id="code" name="code" pattern="[a-z0-9-]+" maxlength="50">
                <small class="form-text text-muted">{{ __('Unique identifier (auto-generated if empty). Lowercase letters, numbers, and hyphens only.') }}</small>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="domain" class="form-label">{{ __('Domain') }}</label>
                <input type="text" class="form-control" id="domain" name="domain" placeholder="{{ __('example.com') }}">
                <small class="form-text text-muted">{{ __('Custom domain for the tenant (optional)') }}</small>
              </div>
              <div class="col-md-6 mb-3">
                <label for="database_name" class="form-label">{{ __('Database Name') }}</label>
                <input type="text" class="form-control" id="database_name" name="database_name" placeholder="{{ __('tenant_db') }}">
                <small class="form-text text-muted">{{ __('Optional separate database name') }}</small>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="repository_id" class="form-label">{{ __('Link to Repository') }}</label>
                <select class="form-select" id="repository_id" name="repository_id">
                  <option value="">-- None --</option>
                  @foreach($repositories as $repo)
                    <option value="{{ $repo->id ?? '' }}">
                      {{ $repo->name ?? ($repo->identifier ?? ('Repository #' . ($repo->id ?? ''))) }}
                    </option>
                  @endforeach
                </select>
                <small class="form-text text-muted">{{ __('Link tenant to an existing repository') }}</small>
              </div>
              <div class="col-md-6 mb-3">
                <label for="is_active" class="form-label">{{ __('Initial Status') }}</label>
                <select class="form-select" id="is_active" name="is_active">
                  <option value="1" selected>{{ __('Active') }}</option>
                  <option value="0">{{ __('Suspended') }}</option>
                </select>
              </div>
            </div>

            <div class="mb-3">
              <label for="description" class="form-label">{{ __('Description') }}</label>
              <textarea class="form-control" id="description" name="description" rows="3"></textarea>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-address-card me-2"></i>{{ __('Contact Information') }}</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="contact_email" class="form-label">{{ __('Contact Email') }}</label>
                <input type="email" class="form-control" id="contact_email" name="contact_email">
              </div>
              <div class="col-md-6 mb-3">
                <label for="contact_phone" class="form-label">{{ __('Contact Phone') }}</label>
                <input type="text" class="form-control" id="contact_phone" name="contact_phone">
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>{{ __('Limits') }}</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="max_users" class="form-label">{{ __('Max Users') }}</label>
                <input type="number" class="form-control" id="max_users" name="max_users" min="1">
              </div>
              <div class="col-md-6 mb-3">
                <label for="max_storage_gb" class="form-label">{{ __('Max Storage (GB)') }}</label>
                <input type="number" class="form-control" id="max_storage_gb" name="max_storage_gb" min="1">
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
          <a href="{{ route('tenant.index') }}" class="btn btn-outline-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>{{ __('Create Tenant') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
