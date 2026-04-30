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

@section('title', 'Edit Tenant')

@section('content')
@php
  $tenant = $tenant ?? (object) [];
  $repositories = $repositories ?? [];
  $users = $users ?? [];
  $availableUsers = $availableUsers ?? [];
  $roles = $roles ?? [];
  $status = $tenant->status ?? (isset($tenant->is_active) ? ($tenant->is_active ? 'active' : 'suspended') : 'active');
@endphp
<div class="container-fluid py-4">
  <div class="row">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">
          <i class="fas fa-edit me-2"></i>
          Edit Tenant: {{ $tenant->name ?? '' }}
        </h1>
        <a href="{{ route('tenant.index') }}" class="btn btn-outline-secondary">
          <i class="fas fa-arrow-left me-2"></i>Back to List
        </a>
      </div>

      @if(session('notice'))
        <div class="alert alert-success alert-dismissible fade show">
          {{ session('notice') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
          {{ session('error') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      <div class="row">
        <!-- Left Column: Tenant Details -->
        <div class="col-lg-6">
          <form action="{{ route('tenant.edit', ['id' => $tenant->id ?? 0]) }}" method="post">
            @csrf
            <div class="card mb-4">
              <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-building me-2"></i>Tenant Information</h5>
                <span class="badge bg-{{ $status === 'active' ? 'success' : ($status === 'trial' ? 'info' : 'danger') }}">
                  {{ ucfirst($status) }}
                </span>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <label for="name" class="form-label">Tenant Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="name" name="name" value="{{ $tenant->name ?? '' }}" required>
                </div>

                <div class="mb-3">
                  <label for="code" class="form-label">{{ __('Code') }}</label>
                  <input type="text" class="form-control" id="code" name="code" value="{{ $tenant->code ?? '' }}" pattern="[a-z0-9-]+" maxlength="50">
                  <small class="form-text text-muted">Lowercase letters, numbers, and hyphens only</small>
                </div>

                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="domain" class="form-label">{{ __('Domain') }}</label>
                    <input type="text" class="form-control" id="domain" name="domain" value="{{ $tenant->domain ?? '' }}">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="database_name" class="form-label">{{ __('Database Name') }}</label>
                    <input type="text" class="form-control" id="database_name" name="database_name" value="{{ $tenant->database_name ?? '' }}">
                  </div>
                </div>

                <div class="mb-3">
                  <label for="description" class="form-label">{{ __('Description') }}</label>
                  <textarea class="form-control" id="description" name="description" rows="3">{{ $tenant->description ?? '' }}</textarea>
                </div>

                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="contact_email" class="form-label">{{ __('Contact Email') }}</label>
                    <input type="email" class="form-control" id="contact_email" name="contact_email" value="{{ $tenant->contact_email ?? '' }}">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="contact_phone" class="form-label">{{ __('Contact Phone') }}</label>
                    <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="{{ $tenant->contact_phone ?? '' }}">
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="max_users" class="form-label">{{ __('Max Users') }}</label>
                    <input type="number" class="form-control" id="max_users" name="max_users" value="{{ $tenant->max_users ?? '' }}" min="1">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="max_storage_gb" class="form-label">{{ __('Max Storage (GB)') }}</label>
                    <input type="number" class="form-control" id="max_storage_gb" name="max_storage_gb" value="{{ $tenant->max_storage_gb ?? '' }}" min="1">
                  </div>
                </div>

                <div class="mb-3">
                  <label for="is_active" class="form-label">{{ __('Status') }}</label>
                  <select class="form-select" id="is_active" name="is_active">
                    <option value="1" {{ !empty($tenant->is_active) ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ empty($tenant->is_active) ? 'selected' : '' }}>Suspended</option>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label">{{ __('Created') }}</label>
                  <p class="form-control-plaintext">
                    {{ !empty($tenant->created_at) ? \Carbon\Carbon::parse($tenant->created_at)->format('F j, Y g:i A') : '-' }}
                  </p>
                </div>
              </div>
              <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save me-2"></i>Save Changes
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- Right Column: User Management -->
        <div class="col-lg-6">
          <div class="card mb-4">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
              <h5 class="mb-0"><i class="fas fa-users me-2"></i>Tenant Users</h5>
              <span class="badge bg-light text-dark">{{ is_countable($users) ? count($users) : 0 }} users</span>
            </div>
            <div class="card-body p-0">
              <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>{{ __('User') }}</th>
                    <th>{{ __('Role') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($users as $user)
                    <tr>
                      <td>
                        {{ $user->name ?? ($user->username ?? '') }}
                        <br><small class="text-muted">{{ $user->email ?? '' }}</small>
                      </td>
                      <td>
                        @if(!empty($roles))
                          <select class="form-select form-select-sm" style="width: auto;" disabled>
                            @foreach($roles as $roleValue => $roleLabel)
                              <option value="{{ $roleValue }}" {{ ($user->role ?? '') === $roleValue ? 'selected' : '' }}>
                                {{ $roleLabel }}
                              </option>
                            @endforeach
                          </select>
                        @else
                          <small class="text-muted">{{ $user->role ?? '-' }}</small>
                        @endif
                      </td>
                      <td class="text-end">
                        <small class="text-muted">-</small>
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="3" class="text-center text-muted py-3">No users assigned</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
            <div class="card-footer">
              <a href="{{ route('tenant.users', ['tenantId' => $tenant->id ?? 0]) }}" class="btn btn-sm btn-outline-info">
                <i class="fas fa-users me-1"></i>Manage Users
              </a>
              <a href="{{ route('tenant.branding', ['tenantId' => $tenant->id ?? 0]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-palette me-1"></i>Branding
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
