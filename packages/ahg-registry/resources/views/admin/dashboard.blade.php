{{--
  Registry Admin Dashboard
  Cloned from PSIS ahgRegistryPlugin/modules/registry/templates/adminDashboardSuccess.php.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Registry Admin'))
@section('body-class', 'registry registry-admin-dashboard')

@php
  $stats = $stats ?? [];
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Admin') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4">{{ __('Registry Admin') }}</h1>

{{-- Stats cards --}}
<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mb-5">
  <div class="col">
    <div class="card h-100 border-start border-primary border-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small mb-1">{{ __('Institutions') }}</div>
            <div class="h3 mb-0">{{ number_format($stats['institutions'] ?? 0) }}</div>
          </div>
          <i class="fas fa-university fa-2x text-primary opacity-50"></i>
        </div>
        @if(($stats['institutions_pending'] ?? 0) > 0)
          <div class="mt-2">
            <span class="badge bg-warning text-dark">{{ (int) $stats['institutions_pending'] }} {{ __('pending verification') }}</span>
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card h-100 border-start border-success border-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small mb-1">{{ __('Vendors') }}</div>
            <div class="h3 mb-0">{{ number_format($stats['vendors'] ?? 0) }}</div>
          </div>
          <i class="fas fa-handshake fa-2x text-success opacity-50"></i>
        </div>
        @if(($stats['vendors_pending'] ?? 0) > 0)
          <div class="mt-2">
            <span class="badge bg-warning text-dark">{{ (int) $stats['vendors_pending'] }} {{ __('pending verification') }}</span>
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card h-100 border-start border-info border-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small mb-1">{{ __('Software') }}</div>
            <div class="h3 mb-0">{{ number_format($stats['software'] ?? 0) }}</div>
          </div>
          <i class="fas fa-code fa-2x text-info opacity-50"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card h-100 border-start border-secondary border-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small mb-1">{{ __('Instances') }}</div>
            <div class="h3 mb-0">{{ number_format($stats['instances'] ?? 0) }}</div>
          </div>
          <i class="fas fa-server fa-2x text-secondary opacity-50"></i>
        </div>
        @if(($stats['instances_online'] ?? 0) > 0)
          <div class="mt-2">
            <span class="badge bg-success">{{ (int) $stats['instances_online'] }} {{ __('online') }}</span>
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card h-100 border-start border-4" style="border-left-color:#6f42c1!important;">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small mb-1">{{ __('Groups') }}</div>
            <div class="h3 mb-0">{{ number_format($stats['groups'] ?? 0) }}</div>
          </div>
          <i class="fas fa-users fa-2x opacity-50" style="color:#6f42c1;"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card h-100 border-start border-warning border-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small mb-1">{{ __('Discussions') }}</div>
            <div class="h3 mb-0">{{ number_format($stats['discussions'] ?? 0) }}</div>
          </div>
          <i class="fas fa-comments fa-2x text-warning opacity-50"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card h-100 border-start border-danger border-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small mb-1">{{ __('Blog Posts') }}</div>
            <div class="h3 mb-0">{{ number_format($stats['blog_posts'] ?? 0) }}</div>
          </div>
          <i class="fas fa-blog fa-2x text-danger opacity-50"></i>
        </div>
        @if(($stats['blog_pending'] ?? 0) > 0)
          <div class="mt-2">
            <span class="badge bg-warning text-dark">{{ (int) $stats['blog_pending'] }} {{ __('pending review') }}</span>
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card h-100 border-start border-4" style="border-left-color:#fd7e14!important;">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small mb-1">{{ __('Reviews') }}</div>
            <div class="h3 mb-0">{{ number_format($stats['reviews'] ?? 0) }}</div>
          </div>
          <i class="fas fa-star fa-2x opacity-50" style="color:#fd7e14;"></i>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Quick links --}}
<h2 class="h5 mb-3">{{ __('Quick Links') }}</h2>
<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
  <div class="col">
    <a href="{{ route('registry.admin.institutions') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-check-circle fa-2x text-primary mb-2"></i>
        <h6 class="card-title">{{ __('Verify Institutions') }}</h6>
        @if(($stats['institutions_pending'] ?? 0) > 0)
          <span class="badge bg-warning text-dark">{{ (int) $stats['institutions_pending'] }} {{ __('pending') }}</span>
        @endif
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.vendors') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-check-double fa-2x text-success mb-2"></i>
        <h6 class="card-title">{{ __('Verify Vendors') }}</h6>
        @if(($stats['vendors_pending'] ?? 0) > 0)
          <span class="badge bg-warning text-dark">{{ (int) $stats['vendors_pending'] }} {{ __('pending') }}</span>
        @endif
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.blog') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-blog fa-2x text-danger mb-2"></i>
        <h6 class="card-title">{{ __('Moderate Blog') }}</h6>
        @if(($stats['blog_pending'] ?? 0) > 0)
          <span class="badge bg-warning text-dark">{{ (int) $stats['blog_pending'] }} {{ __('pending') }}</span>
        @endif
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.sync') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-sync-alt fa-2x text-info mb-2"></i>
        <h6 class="card-title">{{ __('Sync Dashboard') }}</h6>
        @if(($stats['instances_online'] ?? 0) > 0)
          <span class="badge bg-success">{{ (int) $stats['instances_online'] }} {{ __('online') }}</span>
        @endif
      </div>
    </a>
  </div>
</div>

<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mt-1">
  <div class="col">
    <a href="{{ route('registry.admin.users') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-user-check fa-2x text-primary mb-2"></i>
        <h6 class="card-title">{{ __('User Approval') }}</h6>
        @if(($stats['users_pending'] ?? 0) > 0)
          <span class="badge bg-warning text-dark">{{ (int) $stats['users_pending'] }} {{ __('pending') }}</span>
        @endif
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.users') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-users-cog fa-2x text-secondary mb-2"></i>
        <h6 class="card-title">{{ __('Manage Users') }}</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.software') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-code fa-2x text-info mb-2"></i>
        <h6 class="card-title">{{ __('Manage Software') }}</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.standards') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-balance-scale fa-2x text-danger mb-2"></i>
        <h6 class="card-title">{{ __('Manage Standards') }}</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.dropdowns') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-list fa-2x text-info mb-2"></i>
        <h6 class="card-title">{{ __('Dropdown Manager') }}</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.setupGuides') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-book-open fa-2x text-secondary mb-2"></i>
        <h6 class="card-title">{{ __('Setup Guides') }}</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.erd') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-project-diagram fa-2x text-primary mb-2"></i>
        <h6 class="card-title">{{ __('ERD Documentation') }}</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.groups') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-users fa-2x mb-2" style="color:#6f42c1;"></i>
        <h6 class="card-title">{{ __('Manage Groups') }}</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.discussions') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-comments fa-2x text-warning mb-2"></i>
        <h6 class="card-title">{{ __('Moderate Discussions') }}</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.newsletters') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-newspaper fa-2x text-success mb-2"></i>
        <h6 class="card-title">{{ __('Newsletters') }}</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.subscribers') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-envelope-open-text fa-2x mb-2" style="color:#fd7e14;"></i>
        <h6 class="card-title">{{ __('Subscribers') }}</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.email') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-at fa-2x text-danger mb-2"></i>
        <h6 class="card-title">{{ __('Email Settings') }}</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.footer') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-shoe-prints fa-2x text-info mb-2"></i>
        <h6 class="card-title">{{ __('Footer') }}</h6>
      </div>
    </a>
  </div>
  <div class="col">
    <a href="{{ route('registry.admin.settings') }}" class="card text-decoration-none h-100">
      <div class="card-body text-center">
        <i class="fas fa-cog fa-2x text-secondary mb-2"></i>
        <h6 class="card-title">{{ __('Settings') }}</h6>
      </div>
    </a>
  </div>
</div>
@endsection
