{{--
  Marketplace Admin — Transactions List

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/adminTransactionsSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Manage Transactions') . ' - ' . __('Marketplace Admin'))
@section('body-class', 'admin marketplace transactions')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.admin-dashboard') }}">{{ __('Marketplace Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Transactions') }}</li>
  </ol>
</nav>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<h1 class="h3 mb-4">{{ __('Manage Transactions') }}</h1>

{{-- Filter row --}}
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small">{{ __('Order Status') }}</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">{{ __('All Statuses') }}</option>
          @foreach(['pending_payment', 'paid', 'shipping', 'delivered', 'completed', 'cancelled', 'refunded', 'disputed'] as $s)
            <option value="{{ $s }}" {{ ($filters['status'] ?? '') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small">{{ __('Payment Status') }}</label>
        <select name="payment_status" class="form-select form-select-sm">
          <option value="">{{ __('All') }}</option>
          @foreach(['pending', 'paid', 'failed', 'refunded', 'disputed'] as $ps)
            <option value="{{ $ps }}" {{ ($filters['payment_status'] ?? '') === $ps ? 'selected' : '' }}>{{ ucfirst($ps) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small">{{ __('Search') }}</label>
        <input type="text" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('TXN #, seller, buyer...') }}">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-sm w-100">
          <i class="fas fa-filter me-1"></i> {{ __('Filter') }}
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Transactions table --}}
@if(empty($transactions) || count($transactions) === 0)
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-receipt fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('No transactions found') }}</h5>
      <p class="text-muted">{{ __('Try adjusting your filters.') }}</p>
    </div>
  </div>
@else
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('TXN #') }}</th>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Item') }}</th>
            <th>{{ __('Seller') }}</th>
            <th>{{ __('Buyer') }}</th>
            <th class="text-end">{{ __('Amount') }}</th>
            <th class="text-end">{{ __('Commission') }}</th>
            <th class="text-end">{{ __('Seller Amt') }}</th>
            <th>{{ __('Payment') }}</th>
            <th>{{ __('Order') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($transactions as $txn)
            @php
              $payClass = match($txn->payment_status ?? '') {
                'pending' => 'warning',
                'paid' => 'success',
                'failed' => 'danger',
                'refunded' => 'secondary',
                'disputed' => 'danger',
                default => 'secondary',
              };
              $orderClass = match($txn->status ?? '') {
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
              <td class="small fw-semibold">{{ $txn->transaction_number ?? '' }}</td>
              <td class="small text-muted">{{ $txn->created_at ? date('d M Y', strtotime($txn->created_at)) : '' }}</td>
              <td>
                @if(!empty($txn->listing_slug))
                  <a href="{{ route('ahgmarketplace.listing', ['slug' => $txn->listing_slug]) }}" class="text-decoration-none">{{ $txn->title ?? '-' }}</a>
                @else
                  {{ $txn->title ?? '-' }}
                @endif
              </td>
              <td class="small">{{ $txn->seller_name ?? '-' }}</td>
              <td class="small">{{ $txn->buyer_name ?? '-' }}</td>
              <td class="text-end fw-semibold">{{ $txn->currency ?? '' }} {{ number_format((float) ($txn->sale_price ?? 0), 2) }}</td>
              <td class="text-end small text-muted">{{ $txn->currency ?? '' }} {{ number_format((float) ($txn->platform_commission_amount ?? 0), 2) }}</td>
              <td class="text-end small">{{ $txn->currency ?? '' }} {{ number_format((float) ($txn->seller_amount ?? 0), 2) }}</td>
              <td><span class="badge bg-{{ $payClass }}">{{ ucfirst($txn->payment_status ?? '-') }}</span></td>
              <td><span class="badge bg-{{ $orderClass }}">{{ ucfirst(str_replace('_', ' ', $txn->status ?? '-')) }}</span></td>
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
        'status' => $filters['status'] ?? '',
        'payment_status' => $filters['payment_status'] ?? '',
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
