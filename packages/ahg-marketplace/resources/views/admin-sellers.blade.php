{{--
  Marketplace Admin — Sellers List

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/adminSellersSuccess.php.
  Currency rendered from $seller->payout_currency (no hardcoded default).
--}}
@extends('theme::layouts.1col')
@section('title', __('Manage Sellers') . ' - ' . __('Marketplace Admin'))
@section('body-class', 'admin marketplace sellers')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.admin-dashboard') }}">{{ __('Marketplace Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Sellers') }}</li>
  </ol>
</nav>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<h1 class="h3 mb-4">{{ __('Manage Sellers') }}</h1>

{{-- Filter row --}}
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small">{{ __('Verification Status') }}</label>
        <select name="verification_status" class="form-select form-select-sm">
          <option value="">{{ __('All Statuses') }}</option>
          @foreach(['unverified', 'pending', 'verified', 'suspended'] as $vs)
            <option value="{{ $vs }}" {{ ($filters['verification_status'] ?? '') === $vs ? 'selected' : '' }}>{{ ucfirst($vs) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-5">
        <label class="form-label small">{{ __('Search') }}</label>
        <input type="text" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Name, email or slug...') }}">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-sm w-100">
          <i class="fas fa-filter me-1"></i> {{ __('Filter') }}
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Sellers table --}}
@if(empty($sellers) || count($sellers) === 0)
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-store fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('No sellers found') }}</h5>
      <p class="text-muted">{{ __('Try adjusting your filters.') }}</p>
    </div>
  </div>
@else
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:40px;">{{ __('ID') }}</th>
            <th style="width:50px;"></th>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Email') }}</th>
            <th>{{ __('Verification') }}</th>
            <th class="text-end">{{ __('Sales') }}</th>
            <th class="text-end">{{ __('Revenue') }}</th>
            <th>{{ __('Joined') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($sellers as $seller)
            @php
              $verifyClass = match($seller->verification_status ?? '') {
                'verified' => 'success',
                'pending' => 'warning',
                'suspended' => 'secondary',
                default => 'danger',
              };
            @endphp
            <tr>
              <td class="small text-muted">{{ (int) $seller->id }}</td>
              <td>
                @if(!empty($seller->avatar_path))
                  <img src="{{ $seller->avatar_path }}" alt="" class="rounded-circle" style="width:40px;height:40px;object-fit:cover;">
                @else
                  <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                    <i class="fas fa-user text-muted small"></i>
                  </div>
                @endif
              </td>
              <td>
                <a href="{{ route('ahgmarketplace.seller', ['slug' => $seller->slug ?? '']) }}" class="text-decoration-none fw-semibold">
                  {{ $seller->display_name ?? '-' }}
                </a>
              </td>
              <td><span class="badge bg-secondary">{{ ucfirst($seller->seller_type ?? '-') }}</span></td>
              <td class="small">{{ $seller->email ?? '-' }}</td>
              <td><span class="badge bg-{{ $verifyClass }}">{{ ucfirst($seller->verification_status ?? 'unverified') }}</span></td>
              <td class="text-end small">{{ number_format((int) ($seller->total_sales ?? 0)) }}</td>
              <td class="text-end small">{{ $seller->payout_currency ?? '' }} {{ number_format((float) ($seller->total_revenue ?? 0), 2) }}</td>
              <td class="small text-muted">{{ $seller->created_at ? date('d M Y', strtotime($seller->created_at)) : '' }}</td>
              <td class="text-end text-nowrap">
                @if(($seller->verification_status ?? '') !== 'verified')
                  <a href="{{ route('ahgmarketplace.admin-seller-verify', ['id' => $seller->id]) }}" class="btn btn-sm btn-outline-success" title="{{ __('Verify') }}">
                    <i class="fas fa-check-circle"></i>
                  </a>
                @endif
                <a href="{{ route('ahgmarketplace.admin-seller-verify', ['id' => $seller->id]) }}" class="btn btn-sm btn-outline-primary" title="{{ __('View') }}">
                  <i class="fas fa-eye"></i>
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  @php $totalPages = (int) ceil(($total ?? 0) / 30); @endphp
  @if($totalPages > 1)
    @php
      $query = http_build_query(array_filter([
        'verification_status' => $filters['verification_status'] ?? '',
        'search' => $filters['search'] ?? '',
      ]));
    @endphp
    <nav class="mt-4" aria-label="{{ __('Pagination') }}">
      <ul class="pagination justify-content-center">
        <li class="page-item{{ ($page ?? 1) <= 1 ? ' disabled' : '' }}">
          <a class="page-link" href="?{{ $query }}&page={{ ($page ?? 1) - 1 }}">&laquo;</a>
        </li>
        @for($i = max(1, ($page ?? 1) - 2); $i <= min($totalPages, ($page ?? 1) + 2); $i++)
          <li class="page-item{{ $i === ($page ?? 1) ? ' active' : '' }}">
            <a class="page-link" href="?{{ $query }}&page={{ $i }}">{{ $i }}</a>
          </li>
        @endfor
        <li class="page-item{{ ($page ?? 1) >= $totalPages ? ' disabled' : '' }}">
          <a class="page-link" href="?{{ $query }}&page={{ ($page ?? 1) + 1 }}">&raquo;</a>
        </li>
      </ul>
    </nav>
  @endif
@endif
@endsection
