{{--
  Marketplace Admin Dashboard

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/adminDashboardSuccess.php.
  International framing: currency shown per-transaction from $txn->currency
  (PSIS hardcoded ZAR); monthly revenue fallback uses config('heratio.base_currency').
--}}
@extends('theme::layouts.1col')
@section('title', __('Marketplace Administration'))
@section('body-class', 'admin marketplace dashboard')

@php
  $baseCurrency = config('heratio.base_currency', 'ZAR');
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Administration') }}</li>
  </ol>
</nav>

@if(session('success') || session('notice'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') ?? session('notice') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<h1 class="h3 mb-4">{{ __('Marketplace Administration') }}</h1>

<div class="row">
  <div class="col-lg-9">
    {{-- Stats cards --}}
    <div class="row g-3 mb-4">
      <div class="col-6 col-md">
        <div class="card h-100 text-center">
          <div class="card-body py-3">
            <i class="fas fa-store text-primary mb-1 d-block" style="font-size:1.5rem;"></i>
            <div class="h4 mb-0">{{ number_format((int) ($stats['totalSellers'] ?? 0)) }}</div>
            <small class="text-muted">{{ __('Total Sellers') }}</small>
          </div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="card h-100 text-center">
          <div class="card-body py-3">
            <i class="fas fa-tags text-info mb-1 d-block" style="font-size:1.5rem;"></i>
            <div class="h4 mb-0">{{ number_format((int) ($stats['totalListings'] ?? 0)) }}</div>
            <small class="text-muted">{{ __('Total Listings') }}</small>
          </div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="card h-100 text-center">
          <div class="card-body py-3">
            <i class="fas fa-receipt text-success mb-1 d-block" style="font-size:1.5rem;"></i>
            <div class="h4 mb-0">{{ number_format((int) ($stats['totalTransactions'] ?? 0)) }}</div>
            <small class="text-muted">{{ __('Total Transactions') }}</small>
          </div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="card h-100 text-center">
          <div class="card-body py-3">
            <i class="fas fa-coins text-warning mb-1 d-block" style="font-size:1.5rem;"></i>
            <div class="h4 mb-0">{{ $baseCurrency }} {{ number_format((float) ($stats['totalRevenue'] ?? 0), 2) }}</div>
            <small class="text-muted">{{ __('Revenue') }}</small>
          </div>
        </div>
      </div>
    </div>

    {{-- Alert badges --}}
    <div class="row g-3 mb-4">
      @if(($stats['pendingListings'] ?? 0) > 0)
        <div class="col-auto">
          <a href="{{ route('ahgmarketplace.admin-listings', ['status' => 'pending_review']) }}" class="btn btn-warning">
            <i class="fas fa-clipboard-list me-1"></i> {{ __('Pending Listings') }}
            <span class="badge bg-dark ms-1">{{ (int) $stats['pendingListings'] }}</span>
          </a>
        </div>
      @endif
      @if(($stats['unverifiedSellers'] ?? 0) > 0)
        <div class="col-auto">
          <a href="{{ route('ahgmarketplace.admin-sellers', ['verification_status' => 'unverified']) }}" class="btn btn-warning">
            <i class="fas fa-user-clock me-1"></i> {{ __('Unverified Sellers') }}
            <span class="badge bg-dark ms-1">{{ (int) $stats['unverifiedSellers'] }}</span>
          </a>
        </div>
      @endif
      @if(($stats['pendingPayoutsCount'] ?? 0) > 0)
        <div class="col-auto">
          <a href="{{ route('ahgmarketplace.admin-payouts', ['status' => 'pending']) }}" class="btn btn-warning">
            <i class="fas fa-wallet me-1"></i> {{ __('Pending Payouts') }}
            <span class="badge bg-dark ms-1">{{ (int) $stats['pendingPayoutsCount'] }}</span>
          </a>
        </div>
      @endif
    </div>

    {{-- Recent transactions --}}
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">{{ __('Recent Transactions') }}</h5>
        <a href="{{ route('ahgmarketplace.admin-transactions') }}" class="btn btn-sm btn-outline-secondary">{{ __('View All') }}</a>
      </div>
      @if(!empty($recentTransactions) && count($recentTransactions) > 0)
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('TXN #') }}</th>
                <th>{{ __('Item') }}</th>
                <th>{{ __('Seller') }}</th>
                <th>{{ __('Buyer') }}</th>
                <th class="text-end">{{ __('Amount') }}</th>
                <th>{{ __('Status') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentTransactions as $txn)
                @php
                  $statusClass = match($txn->status ?? '') {
                    'pending_payment' => 'warning',
                    'paid' => 'info',
                    'shipping' => 'primary',
                    'delivered' => 'success',
                    'completed' => 'success',
                    'cancelled' => 'danger',
                    'refunded' => 'secondary',
                    'disputed' => 'danger',
                    default => 'secondary',
                  };
                @endphp
                <tr>
                  <td class="small text-muted">{{ !empty($txn->created_at) ? date('d M Y', strtotime($txn->created_at)) : '' }}</td>
                  <td class="small">{{ $txn->transaction_number ?? '' }}</td>
                  <td>{{ $txn->title ?? '-' }}</td>
                  <td class="small">{{ $txn->seller_name ?? '-' }}</td>
                  <td class="small">{{ $txn->buyer_name ?? '-' }}</td>
                  <td class="text-end fw-semibold">{{ $txn->currency ?? '' }} {{ number_format((float) ($txn->grand_total ?? 0), 2) }}</td>
                  <td><span class="badge bg-{{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $txn->status ?? '-')) }}</span></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="card-body text-center py-4">
          <p class="text-muted mb-0">{{ __('No transactions yet.') }}</p>
        </div>
      @endif
    </div>

    {{-- Monthly revenue --}}
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">{{ __('Monthly Revenue (Last 6 Months)') }}</h5>
      </div>
      @if(!empty($monthlyRevenue) && count($monthlyRevenue) > 0)
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Month') }}</th>
                <th class="text-end">{{ __('Revenue') }}</th>
                <th class="text-end">{{ __('Commission') }}</th>
                <th class="text-end">{{ __('Sales Count') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach(array_slice(is_array($monthlyRevenue) ? $monthlyRevenue : iterator_to_array($monthlyRevenue), 0, 6) as $month)
                <tr>
                  <td>{{ is_object($month) ? ($month->month ?? '-') : ($month['month'] ?? '-') }}</td>
                  <td class="text-end">{{ $baseCurrency }} {{ number_format((float) (is_object($month) ? ($month->revenue ?? 0) : ($month['revenue'] ?? 0)), 2) }}</td>
                  <td class="text-end">{{ $baseCurrency }} {{ number_format((float) (is_object($month) ? ($month->commission ?? 0) : ($month['commission'] ?? 0)), 2) }}</td>
                  <td class="text-end">{{ number_format((int) (is_object($month) ? ($month->sales_count ?? 0) : ($month['sales_count'] ?? 0))) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="card-body text-center py-4">
          <p class="text-muted mb-0">{{ __('No revenue data yet.') }}</p>
        </div>
      @endif
    </div>
  </div>

  {{-- Sidebar: Admin menu --}}
  <div class="col-lg-3">
    <div class="card">
      <div class="card-header fw-semibold">{{ __('Admin Menu') }}</div>
      <div class="list-group list-group-flush">
        <a href="{{ route('ahgmarketplace.admin-dashboard') }}" class="list-group-item list-group-item-action active">
          <i class="fas fa-tachometer-alt me-2"></i>{{ __('Dashboard') }}
        </a>
        <a href="{{ route('ahgmarketplace.admin-listings') }}" class="list-group-item list-group-item-action">
          <i class="fas fa-tags me-2"></i>{{ __('Listings') }}
        </a>
        <a href="{{ route('ahgmarketplace.admin-sellers') }}" class="list-group-item list-group-item-action">
          <i class="fas fa-store me-2"></i>{{ __('Sellers') }}
        </a>
        <a href="{{ route('ahgmarketplace.admin-transactions') }}" class="list-group-item list-group-item-action">
          <i class="fas fa-receipt me-2"></i>{{ __('Transactions') }}
        </a>
        <a href="{{ route('ahgmarketplace.admin-payouts') }}" class="list-group-item list-group-item-action">
          <i class="fas fa-wallet me-2"></i>{{ __('Payouts') }}
        </a>
        <a href="{{ route('ahgmarketplace.admin-reviews') }}" class="list-group-item list-group-item-action">
          <i class="fas fa-star me-2"></i>{{ __('Reviews') }}
        </a>
        <a href="{{ route('ahgmarketplace.admin-categories') }}" class="list-group-item list-group-item-action">
          <i class="fas fa-folder me-2"></i>{{ __('Categories') }}
        </a>
        <a href="{{ route('ahgmarketplace.admin-currencies') }}" class="list-group-item list-group-item-action">
          <i class="fas fa-money-bill me-2"></i>{{ __('Currencies') }}
        </a>
        @if(Route::has('ahgmarketplace.admin-settings'))
          <a href="{{ route('ahgmarketplace.admin-settings') }}" class="list-group-item list-group-item-action">
            <i class="fas fa-cog me-2"></i>{{ __('Settings') }}
          </a>
        @endif
        @if(Route::has('ahgmarketplace.admin-reports'))
          <a href="{{ route('ahgmarketplace.admin-reports') }}" class="list-group-item list-group-item-action">
            <i class="fas fa-chart-bar me-2"></i>{{ __('Reports') }}
          </a>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
