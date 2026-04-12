{{--
  Marketplace — My Purchases

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/myPurchasesSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('My Purchases') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace my-purchases')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item active">{{ __('My Purchases') }}</li>
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

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">{{ __('My Purchases') }}</h1>
  <div>
    <a href="{{ route('ahgmarketplace.my-bids') }}" class="btn btn-outline-secondary btn-sm me-1">
      <i class="fas fa-gavel me-1"></i>{{ __('My Bids') }}
    </a>
    <a href="{{ route('ahgmarketplace.my-offers') }}" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-hand-holding-usd me-1"></i>{{ __('My Offers') }}
    </a>
  </div>
</div>

@if(empty($transactions) || count($transactions) === 0)
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-shopping-bag fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('No purchases yet') }}</h5>
      <p class="text-muted">{{ __('Browse the marketplace to find items you love.') }}</p>
      <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-primary">
        <i class="fas fa-search me-1"></i> {{ __('Browse Marketplace') }}
      </a>
    </div>
  </div>
@else
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Item') }}</th>
            <th>{{ __('Seller') }}</th>
            <th class="text-end">{{ __('Amount') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Tracking') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($transactions as $txn)
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
              <td>
                <div class="d-flex align-items-center">
                  @if(!empty($txn->featured_image_path))
                    <img src="{{ $txn->featured_image_path }}" alt="" class="rounded me-2" style="width:40px;height:40px;object-fit:cover;">
                  @else
                    <div class="bg-light rounded d-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;">
                      <i class="fas fa-image text-muted small"></i>
                    </div>
                  @endif
                  <a href="{{ route('ahgmarketplace.listing', ['slug' => $txn->slug ?? '']) }}" class="text-decoration-none fw-semibold">{{ $txn->title ?? '' }}</a>
                </div>
              </td>
              <td class="small">{{ $txn->seller_name ?? '-' }}</td>
              <td class="text-end fw-semibold">{{ $txn->currency ?? '' }} {{ number_format((float) ($txn->grand_total ?? 0), 2) }}</td>
              <td><span class="badge bg-{{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $txn->status ?? '-')) }}</span></td>
              <td class="small">
                @if(!empty($txn->tracking_number))
                  <span class="text-muted">{{ $txn->courier ?? '' }}</span>
                  <span class="fw-semibold">{{ $txn->tracking_number }}</span>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td class="text-end">
                @if(($txn->status ?? '') === 'delivered' || (($txn->status ?? '') === 'shipping' && !empty($txn->tracking_number)))
                  @if(empty($txn->buyer_confirmed_receipt))
                    <form method="POST" action="{{ route('ahgmarketplace.my-purchases.post') }}" class="d-inline">
                      @csrf
                      <input type="hidden" name="form_action" value="confirm_receipt">
                      <input type="hidden" name="transaction_id" value="{{ (int) $txn->id }}">
                      <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('{{ __('Confirm you have received this item?') }}');">
                        <i class="fas fa-check me-1"></i>{{ __('Confirm Receipt') }}
                      </button>
                    </form>
                  @endif
                @endif
                @if(($txn->status ?? '') === 'completed' && empty($reviewedMap[$txn->id] ?? null))
                  <a href="{{ route('ahgmarketplace.review-form', ['id' => $txn->id]) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-star me-1"></i>{{ __('Leave Review') }}
                  </a>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  @php $totalPages = (int) ceil(($total ?? 0) / ($limit ?? 20)); @endphp
  @if($totalPages > 1)
    <nav class="mt-4" aria-label="{{ __('Pagination') }}">
      <ul class="pagination justify-content-center">
        <li class="page-item {{ ($page ?? 1) <= 1 ? 'disabled' : '' }}">
          <a class="page-link" href="?page={{ ($page ?? 1) - 1 }}">&laquo;</a>
        </li>
        @for($p = 1; $p <= $totalPages; $p++)
          <li class="page-item {{ $p === ($page ?? 1) ? 'active' : '' }}">
            <a class="page-link" href="?page={{ $p }}">{{ $p }}</a>
          </li>
        @endfor
        <li class="page-item {{ ($page ?? 1) >= $totalPages ? 'disabled' : '' }}">
          <a class="page-link" href="?page={{ ($page ?? 1) + 1 }}">&raquo;</a>
        </li>
      </ul>
    </nav>
  @endif
@endif
@endsection
