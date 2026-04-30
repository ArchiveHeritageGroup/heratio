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

@section('title', 'Super Users')

@section('content')
@php
  $users = $users ?? [];
  $superUsers = collect($users)->filter(function ($u) {
    return !empty($u->is_super_user);
  })->values();
  $availableUsers = collect($users)->filter(function ($u) {
    return empty($u->is_super_user);
  })->values();
@endphp
<div class="container-fluid py-4">
  <div class="row">
    <div class="col-12">
      <nav aria-label="{{ __('breadcrumb') }}">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('tenant.index') }}">Tenant Administration</a></li>
          <li class="breadcrumb-item active">Super Users</li>
        </ol>
      </nav>

      <h1 class="mb-4">
        <i class="fas fa-star text-warning me-2"></i>
        {{ __('Super Users') }}
      </h1>

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
        <!-- Current Super Users -->
        <div class="col-md-6 mb-4">
          <div class="card h-100">
            <div class="card-header bg-warning text-dark">
              <h5 class="mb-0">
                <i class="fas fa-star me-2"></i>
                {{ __('Current Super Users') }}
              </h5>
            </div>
            <div class="card-body p-0">
              @if($superUsers->isNotEmpty())
                <ul class="list-group list-group-flush">
                  @foreach($superUsers as $user)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      <div>
                        <strong>{{ $user->name ?? ($user->username ?? '') }}</strong>
                        <br>
                        <small class="text-muted">{{ $user->email ?? '' }}</small>
                      </div>
                      <span class="badge bg-warning text-dark">
                        <i class="fas fa-star"></i>
                      </span>
                    </li>
                  @endforeach
                </ul>
              @else
                <div class="text-center text-muted py-4">
                  <i class="fas fa-user-slash fa-2x mb-2"></i>
                  <p>No super users assigned.</p>
                </div>
              @endif
            </div>
          </div>
        </div>

        <!-- Add Super User -->
        <div class="col-md-6 mb-4">
          <div class="card h-100">
            <div class="card-header bg-primary text-white">
              <h5 class="mb-0">
                <i class="fas fa-user-plus me-2"></i>
                {{ __('Available Users') }}
              </h5>
            </div>
            <div class="card-body p-0">
              @if($availableUsers->isNotEmpty())
                <ul class="list-group list-group-flush">
                  @foreach($availableUsers as $user)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      <div>
                        <strong>{{ $user->name ?? ($user->username ?? '') }}</strong>
                        <br>
                        <small class="text-muted">{{ $user->email ?? '' }}</small>
                      </div>
                    </li>
                  @endforeach
                </ul>
              @else
                <div class="text-center text-muted py-3">
                  <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                  <p>All users are already assigned.</p>
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>

      <div class="mt-2">
        <a href="{{ route('tenant.index') }}" class="btn btn-secondary">
          <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Tenant Administration') }}
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
