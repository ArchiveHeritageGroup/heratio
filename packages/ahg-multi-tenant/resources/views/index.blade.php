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

@section('title', 'Multi-Tenant Administration')

@section('content')
@php
  $tenants = $tenants ?? [];
  $statistics = $statistics ?? [
    'total' => is_countable($tenants) ? count($tenants) : 0,
    'active' => 0,
    'trial' => 0,
    'suspended' => 0,
    'trial_expiring_soon' => 0,
    'trial_expired' => 0,
  ];
  $repositories = $repositories ?? [];
  $statusFilter = $statusFilter ?? '';
  $searchFilter = $searchFilter ?? '';
@endphp
<div class="container-fluid py-4">
  <div class="row">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">
          <i class="fas fa-building me-2"></i>
          Multi-Tenant Administration
        </h1>
        <a href="{{ route('tenant.create') }}" class="btn btn-primary">
          <i class="fas fa-plus me-2"></i>Create Tenant
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

      <!-- Statistics Cards -->
      <div class="row mb-4">
        <div class="col-md-2">
          <div class="card bg-primary text-white">
            <div class="card-body text-center">
              <h3 class="mb-0">{{ $statistics['total'] ?? 0 }}</h3>
              <small>Total Tenants</small>
            </div>
          </div>
        </div>
        <div class="col-md-2">
          <div class="card bg-success text-white">
            <div class="card-body text-center">
              <h3 class="mb-0">{{ $statistics['active'] ?? 0 }}</h3>
              <small>Active</small>
            </div>
          </div>
        </div>
        <div class="col-md-2">
          <div class="card bg-info text-white">
            <div class="card-body text-center">
              <h3 class="mb-0">{{ $statistics['trial'] ?? 0 }}</h3>
              <small>Trial</small>
            </div>
          </div>
        </div>
        <div class="col-md-2">
          <div class="card bg-danger text-white">
            <div class="card-body text-center">
              <h3 class="mb-0">{{ $statistics['suspended'] ?? 0 }}</h3>
              <small>Suspended</small>
            </div>
          </div>
        </div>
        <div class="col-md-2">
          <div class="card bg-warning text-dark">
            <div class="card-body text-center">
              <h3 class="mb-0">{{ $statistics['trial_expiring_soon'] ?? 0 }}</h3>
              <small>Expiring Soon</small>
            </div>
          </div>
        </div>
        <div class="col-md-2">
          <div class="card bg-secondary text-white">
            <div class="card-body text-center">
              <h3 class="mb-0">{{ $statistics['trial_expired'] ?? 0 }}</h3>
              <small>Expired</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Tenants Table -->
      <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <i class="fas fa-building me-2"></i>
            Tenants
          </h5>
          <form class="d-flex gap-2" method="get" action="{{ route('tenant.index') }}">
            <select name="status" class="form-select form-select-sm" style="width: 150px;">
              <option value="">All Status</option>
              <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Active</option>
              <option value="trial" {{ $statusFilter === 'trial' ? 'selected' : '' }}>Trial</option>
              <option value="suspended" {{ $statusFilter === 'suspended' ? 'selected' : '' }}>Suspended</option>
            </select>
            <input type="text" name="search" class="form-control form-control-sm" style="width: 200px;" placeholder="Search..." value="{{ $searchFilter }}">
            <button type="submit" class="btn btn-sm btn-light">
              <i class="fas fa-search"></i>
            </button>
          </form>
        </div>
        <div class="card-body p-0">
          <table class="table table-striped table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Code</th>
                <th>Name</th>
                <th>Status</th>
                <th class="text-center">Users</th>
                <th>Repository</th>
                <th>Contact</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($tenants as $tenant)
                <tr>
                  <td>{{ $tenant->id ?? '' }}</td>
                  <td><code>{{ $tenant->code ?? '' }}</code></td>
                  <td>
                    <strong>{{ $tenant->name ?? '' }}</strong>
                    @if(!empty($tenant->domain))
                      <br><small class="text-muted">{{ $tenant->domain }}</small>
                    @endif
                  </td>
                  <td>
                    @php $status = $tenant->status ?? (isset($tenant->is_active) ? ($tenant->is_active ? 'active' : 'suspended') : 'active'); @endphp
                    @if($status === 'active')
                      <span class="badge bg-success">Active</span>
                    @elseif($status === 'trial')
                      <span class="badge bg-info">Trial</span>
                      @if(!empty($tenant->trial_ends_at))
                        <br><small class="text-muted">Ends: {{ \Carbon\Carbon::parse($tenant->trial_ends_at)->format('M j, Y') }}</small>
                      @endif
                    @else
                      <span class="badge bg-danger">Suspended</span>
                      @if(!empty($tenant->suspended_reason))
                        <br><small class="text-muted" title="{{ $tenant->suspended_reason }}">
                          {{ \Illuminate\Support\Str::limit($tenant->suspended_reason, 30) }}
                        </small>
                      @endif
                    @endif
                  </td>
                  <td class="text-center">
                    <span class="badge bg-secondary">{{ $tenant->user_count ?? 0 }}</span>
                  </td>
                  <td>
                    @if(!empty($tenant->repository_id))
                      <small class="text-muted">ID: {{ $tenant->repository_id }}</small>
                    @else
                      <small class="text-muted">-</small>
                    @endif
                  </td>
                  <td>
                    @if(!empty($tenant->contact_email))
                      <small>{{ $tenant->contact_email }}</small>
                    @else
                      <small class="text-muted">-</small>
                    @endif
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <a href="{{ route('tenant.edit', ['id' => $tenant->id]) }}" class="btn btn-outline-primary" title="Edit Tenant">
                        <i class="fas fa-edit"></i>
                      </a>
                      <a href="{{ route('tenant.users', ['tenantId' => $tenant->id]) }}" class="btn btn-outline-info" title="Manage Users">
                        <i class="fas fa-users"></i>
                      </a>
                      <a href="{{ route('tenant.branding', ['tenantId' => $tenant->id]) }}" class="btn btn-outline-secondary" title="Branding">
                        <i class="fas fa-palette"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="text-center text-muted py-4">
                    No tenants found. <a href="{{ route('tenant.create') }}">Create your first tenant</a>.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="mt-4">
        <div class="card bg-light">
          <div class="card-body">
            <h6><i class="fas fa-info-circle me-2"></i> About Multi-Tenancy</h6>
            <ul class="mb-0 small">
              <li><strong>Tenant:</strong> An organization or customer with their own settings, users, and access controls</li>
              <li><strong>Status:</strong>
                <span class="badge bg-success">Active</span> Full access |
                <span class="badge bg-info">Trial</span> Limited time access |
                <span class="badge bg-danger">Suspended</span> No access
              </li>
              <li><strong>Roles:</strong> Owner &gt; Super User &gt; Editor &gt; Contributor &gt; Viewer</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
