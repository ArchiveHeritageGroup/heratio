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
              <small class="text-muted">{{ __('Total Users') }}</small>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-success h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-user-check fa-2x text-success"></i></div>
              <h3 class="mb-1">{{ number_format($activeUsers) }}</h3>
              <small class="text-muted">{{ __('Active Users (30d)') }}</small>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-info h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-user-plus fa-2x text-info"></i></div>
              <h3 class="mb-1">{{ number_format($newThisMonth) }}</h3>
              <small class="text-muted">{{ __('New This Month') }}</small>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-{{ $activeAlerts > 0 ? 'danger' : 'secondary' }} h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-bell fa-2x text-{{ $activeAlerts > 0 ? 'danger' : 'secondary' }}"></i></div>
              <h3 class="mb-1">{{ number_format($activeAlerts) }}</h3>
              <small class="text-muted">{{ __('Active Alerts') }}</small>
            </div>
          </div>
        </div>
      </div>

      {{-- Heritage Asset Stats --}}
      @if(($totalAssets ?? 0) > 0)
      <div class="row mb-4">
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-dark h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-landmark fa-2x text-dark"></i></div>
              <h3 class="mb-1">{{ number_format($totalAssets) }}</h3>
              <small class="text-muted">{{ __('Heritage Assets') }}</small>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-success h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-check-circle fa-2x text-success"></i></div>
              <h3 class="mb-1">{{ number_format($recognisedAssets) }}</h3>
              <small class="text-muted">{{ __('Recognised') }}</small>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-warning h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-hourglass-half fa-2x text-warning"></i></div>
              <h3 class="mb-1">{{ number_format($pendingAssets) }}</h3>
              <small class="text-muted">{{ __('Pending') }}</small>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-info h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-coins fa-2x text-info"></i></div>
              <h3 class="mb-1">R{{ number_format($totalAssetValue, 2) }}</h3>
              <small class="text-muted">{{ __('Total Carrying Value') }}</small>
            </div>
          </div>
        </div>
      </div>
      @endif

      {{-- Tenants --}}
      @if(isset($tenants) && $tenants->count() > 0)
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-building"></i> Tenants</h5>
        </div>
        <div class="card-body p-0">
          <table class="table table-bordered table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Code') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Contact') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($tenants as $tenant)
              <tr>
                <td>{{ $tenant->name }}</td>
                <td><code>{{ $tenant->code }}</code></td>
                <td>
                  @php
                    $statusColor = match($tenant->status) {
                      'active' => 'success',
                      'trial' => 'info',
                      'suspended' => 'danger',
                      default => 'secondary',
                    };
                  @endphp
                  <span class="badge bg-{{ $statusColor }}">{{ ucfirst($tenant->status) }}</span>
                </td>
                <td>{{ $tenant->contact_email ?? '-' }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif

      {{-- Quick Actions + Trust Level Distribution --}}
      <div class="row g-4 mb-4">
        <div class="col-md-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent">
              <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
              <div class="d-grid gap-2">
                <a href="{{ route('heritage.admin-access-requests') }}" class="btn btn-outline-primary text-start">
                  <i class="fas fa-key me-2"></i>{{ __('Review Access Requests') }}
                </a>
                <a href="{{ route('heritage.custodian-batch') }}" class="btn btn-outline-primary text-start">
                  <i class="fas fa-layer-group me-2"></i>{{ __('Create Batch Operation') }}
                </a>
                <a href="{{ route('heritage.analytics-alerts') }}" class="btn btn-outline-primary text-start">
                  <i class="fas fa-bell me-2"></i>{{ __('View System Alerts') }}
                </a>
                <a href="{{ route('heritage.landing') }}" class="btn btn-outline-secondary text-start" target="_blank">
                  <i class="fas fa-eye me-2"></i>{{ __('Preview Landing Page') }}
                </a>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent">
              <h5 class="mb-0"><i class="fas fa-users me-2"></i>Trust Level Distribution</h5>
            </div>
            <div class="card-body">
              @if(!empty($trustDistribution) && count($trustDistribution) > 0)
                @foreach($trustDistribution as $trust)
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span>{{ e($trust->name ?? 'Unknown') }}</span>
                  <span class="badge bg-secondary">{{ $trust->count ?? 0 }}</span>
                </div>
                @endforeach
              @else
                <p class="text-muted mb-0">No trust level assignments yet.</p>
              @endif
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
                  <span class="badge bg-success">{{ __('None') }}</span>
                @endif
              </td>
            </tr>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
