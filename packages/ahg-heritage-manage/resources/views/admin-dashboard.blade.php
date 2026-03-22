@extends('theme::layouts.1col')

@section('title', 'Heritage Admin Dashboard')
@section('body-class', 'admin heritage')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="mb-0"><i class="fas fa-tachometer-alt"></i> Heritage Admin Dashboard</h1>
  </div>
  <p class="text-muted mb-4">Manage heritage site configuration, access control, and content</p>

  <div class="row">
    {{-- Sidebar --}}
    <div class="col-md-3">
      @include('ahg-heritage-manage::partials._admin-sidebar')
    </div>

    {{-- Main content --}}
    <div class="col-md-9">
      {{-- Quick Stats --}}
      <div class="row mb-4">
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-primary h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-users fa-2x text-primary"></i></div>
              <h3 class="mb-1">{{ number_format($totalUsers) }}</h3>
              <small class="text-muted">Total Users</small>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-success h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-user-check fa-2x text-success"></i></div>
              <h3 class="mb-1">{{ number_format($activeUsers) }}</h3>
              <small class="text-muted">Active Users (30d)</small>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-info h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-user-plus fa-2x text-info"></i></div>
              <h3 class="mb-1">{{ number_format($newThisMonth) }}</h3>
              <small class="text-muted">New This Month</small>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-warning h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-exclamation-triangle fa-2x text-warning"></i></div>
              <h3 class="mb-1">{{ number_format($activeAlerts) }}</h3>
              <small class="text-muted">Active Alerts</small>
            </div>
          </div>
        </div>
      </div>

      {{-- Quick Links --}}
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-link"></i> Quick Links</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-4 mb-2">
              <a href="{{ route('acl.access-requests') }}" class="btn atom-btn-white w-100">
                <i class="fas fa-key me-1"></i> Review Access Requests
              </a>
            </div>
            <div class="col-md-4 mb-2">
              <a href="{{ route('heritage.admin') }}" class="btn atom-btn-white w-100">
                <i class="fas fa-bell me-1"></i> View System Alerts
              </a>
            </div>
            <div class="col-md-4 mb-2">
              <a href="/" class="btn atom-btn-white w-100" target="_blank">
                <i class="fas fa-eye me-1"></i> Preview Landing Page
              </a>
            </div>
          </div>
        </div>
      </div>

      {{-- System Overview --}}
      <div class="card shadow-sm">
        <div class="card-header bg-white" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-info-circle"></i> System Overview</h5>
        </div>
        <div class="card-body">
          <table class="table table-bordered table-sm table-borderless mb-0">
            <tr>
              <td class="text-muted" style="width:200px;">Total Users</td>
              <td>{{ number_format($totalUsers) }}</td>
            </tr>
            <tr>
              <td class="text-muted">Active (Last 30 Days)</td>
              <td>{{ number_format($activeUsers) }}</td>
            </tr>
            <tr>
              <td class="text-muted">New This Month</td>
              <td>{{ number_format($newThisMonth) }}</td>
            </tr>
            <tr>
              <td class="text-muted">Active Alerts (7d)</td>
              <td>
                @if($activeAlerts > 0)
                  <span class="badge bg-warning text-dark">{{ number_format($activeAlerts) }}</span>
                @else
                  <span class="badge bg-success">None</span>
                @endif
              </td>
            </tr>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
