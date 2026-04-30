{{--
  Marketplace — Seller Transaction Detail

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/sellerTransactionDetailSuccess.php.

  International framing: the PSIS "VAT" row is rendered as a generic "Tax" row (VAT is
  a European/SADC term; other markets use GST, Sales Tax, etc). Courier placeholder text
  removed PSIS's SA-specific examples (DHL, PostNet, Courier Guy) in favour of a generic
  hint.
--}}
@extends('theme::layouts.1col')
@section('title', __('Transaction Detail') . ' - ' . ($transaction->transaction_number ?? ''))
@section('body-class', 'marketplace seller-transaction-detail')

@php
  $steps = [
    'pending_payment' => ['icon' => 'fa-clock', 'label' => __('Created')],
    'paid' => ['icon' => 'fa-credit-card', 'label' => __('Paid')],
    'shipping' => ['icon' => 'fa-truck', 'label' => __('Shipping')],
    'delivered' => ['icon' => 'fa-box-open', 'label' => __('Delivered')],
    'completed' => ['icon' => 'fa-check-circle', 'label' => __('Completed')],
  ];
  $statusOrder = array_keys($steps);
  $currentIdx = array_search($transaction->status ?? '', $statusOrder, true);
  if ($currentIdx === false) $currentIdx = -1;

  $statusClass = match($transaction->status ?? '') {
    'pending_payment' => 'warning',
    'paid' => 'info',
    'shipping' => 'primary',
    'delivered' => 'success',
    'completed' => 'success',
    'cancelled' => 'danger',
    'disputed' => 'danger',
    'refunded' => 'secondary',
    default => 'secondary',
  };
  $shipClass = match($transaction->shipping_status ?? '') {
    'pending' => 'secondary',
    'preparing' => 'info',
    'shipped' => 'primary',
    'in_transit' => 'primary',
    'delivered' => 'success',
    'returned' => 'danger',
    default => 'secondary',
  };
  $payClass = match($transaction->payment_status ?? '') {
    'pending' => 'warning',
    'paid' => 'success',
    'failed' => 'danger',
    'refunded' => 'secondary',
    'disputed' => 'danger',
    default => 'secondary',
  };
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item active">{{ $transaction->transaction_number ?? '' }}</li>
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

<h1 class="h3 mb-4">{{ __('Transaction: :n', ['n' => $transaction->transaction_number ?? '']) }}</h1>

{{-- Status timeline --}}
<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex justify-content-between position-relative px-4">
      @foreach($steps as $key => $step)
        @php
          $idx = array_search($key, $statusOrder, true);
          $isActive = ($idx !== false && $idx <= $currentIdx);
          $isCurrent = ($key === ($transaction->status ?? ''));
        @endphp
        <div class="text-center" style="flex:1;">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-1 {{ $isActive ? 'bg-primary text-white' : 'bg-light text-muted' }}" style="width:40px;height:40px;">
            <i class="fas {{ $step['icon'] }}"></i>
          </div>
          <div class="small {{ $isCurrent ? 'fw-bold' : '' }}">{{ $step['label'] }}</div>
        </div>
      @endforeach
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card mb-4">
      <div class="card-header fw-semibold">{{ __('Transaction Summary') }}</div>
      <div class="card-body">
        <table class="table table-sm table-borderless mb-0">
          <tr>
            <td class="text-muted" style="width:200px;">{{ __('Transaction Number') }}</td>
            <td class="fw-semibold">{{ $transaction->transaction_number ?? '' }}</td>
          </tr>
          <tr>
            <td class="text-muted">{{ __('Date') }}</td>
            <td>{{ !empty($transaction->created_at) ? date('d M Y H:i', strtotime($transaction->created_at)) : '' }}</td>
          </tr>
          <tr>
            <td class="text-muted">{{ __('Source') }}</td>
            <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $transaction->source ?? '')) }}</span></td>
          </tr>
          <tr>
            <td class="text-muted">{{ __('Status') }}</td>
            <td><span class="badge bg-{{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $transaction->status ?? '')) }}</span></td>
          </tr>
        </table>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header fw-semibold">{{ __('Item') }}</div>
      <div class="card-body">
        <div class="d-flex">
          @if(!empty($listing->featured_image_path))
            <img src="{{ $listing->featured_image_path }}" alt="" class="rounded me-3" style="width:80px;height:80px;object-fit:cover;">
          @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" style="width:80px;height:80px;">
              <i class="fas fa-image fa-2x text-muted"></i>
            </div>
          @endif
          <div>
            <h6 class="mb-1">{{ $listing->title ?? '-' }}</h6>
            <p class="text-muted small mb-0">{{ $listing->listing_number ?? '' }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header fw-semibold">{{ __('Financial Breakdown') }}</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr>
            <td>{{ __('Sale Price') }}</td>
            <td class="text-end">{{ $transaction->currency ?? '' }} {{ number_format((float) ($transaction->sale_price ?? 0), 2) }}</td>
          </tr>
          <tr>
            <td>{{ __('Platform Commission (:pct%)', ['pct' => number_format((float) ($transaction->platform_commission_rate ?? 0), 1)]) }}</td>
            <td class="text-end text-danger">- {{ $transaction->currency ?? '' }} {{ number_format((float) ($transaction->platform_commission_amount ?? 0), 2) }}</td>
          </tr>
          <tr class="fw-semibold">
            <td>{{ __('Seller Amount') }}</td>
            <td class="text-end text-success">{{ $transaction->currency ?? '' }} {{ number_format((float) ($transaction->seller_amount ?? 0), 2) }}</td>
          </tr>
          <tr class="table-light">
            <td colspan="2"></td>
          </tr>
          <tr>
            <td>{{ __('Tax') }}</td>
            <td class="text-end">{{ $transaction->currency ?? '' }} {{ number_format((float) ($transaction->vat_amount ?? 0), 2) }}</td>
          </tr>
          <tr>
            <td>{{ __('Shipping') }}</td>
            <td class="text-end">{{ $transaction->currency ?? '' }} {{ number_format((float) ($transaction->shipping_cost ?? 0), 2) }}</td>
          </tr>
          <tr class="fw-bold">
            <td>{{ __('Grand Total (Buyer Paid)') }}</td>
            <td class="text-end">{{ $transaction->currency ?? '' }} {{ number_format((float) ($transaction->grand_total ?? 0), 2) }}</td>
          </tr>
        </table>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header fw-semibold">{{ __('Shipping & Delivery') }}</div>
      <div class="card-body">
        <p class="mb-3">
          {{ __('Current Status:') }}
          <span class="badge bg-{{ $shipClass }}">{{ ucfirst(str_replace('_', ' ', $transaction->shipping_status ?? '')) }}</span>
        </p>

        @if(!empty($transaction->tracking_number))
          <p class="small mb-1"><strong>{{ __('Courier:') }}</strong> {{ $transaction->courier ?? '-' }}</p>
          <p class="small mb-3"><strong>{{ __('Tracking Number:') }}</strong> {{ $transaction->tracking_number }}</p>
        @endif

        @if(($transaction->payment_status ?? '') === 'paid' && in_array($transaction->shipping_status ?? '', ['pending', 'preparing']))
          <hr>
          <h6 class="mb-3">{{ __('Update Shipping') }}</h6>
          <form method="POST" action="{{ route('ahgmarketplace.seller-transaction-detail.post', ['id' => $transaction->id ?? 0]) }}">
            @csrf
            <input type="hidden" name="form_action" value="update_shipping">
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="tracking_number" class="form-label">{{ __('Tracking Number') }}</label>
                <input type="text" class="form-control" id="tracking_number" name="tracking_number" value="{{ $transaction->tracking_number ?? '' }}">
              </div>
              <div class="col-md-6">
                <label for="courier" class="form-label">{{ __('Courier') }}</label>
                <input type="text" class="form-control" id="courier" name="courier" value="{{ $transaction->courier ?? '' }}" placeholder="{{ __('e.g. DHL, FedEx, UPS, local courier') }}">
              </div>
            </div>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-truck me-1"></i> {{ __('Mark as Shipped') }}
            </button>
          </form>
        @endif

        <div class="mt-4">
          <h6 class="mb-3">{{ __('Delivery Timeline') }}</h6>
          <ul class="list-unstyled">
            @if(!empty($transaction->created_at))
              <li class="mb-2">
                <i class="fas fa-circle text-success me-2" style="font-size:0.5rem;vertical-align:middle;"></i>
                <strong class="small">{{ __('Created') }}</strong>
                <span class="small text-muted ms-2">{{ date('d M Y H:i', strtotime($transaction->created_at)) }}</span>
              </li>
            @endif
            @if(!empty($transaction->paid_at))
              <li class="mb-2">
                <i class="fas fa-circle text-success me-2" style="font-size:0.5rem;vertical-align:middle;"></i>
                <strong class="small">{{ __('Paid') }}</strong>
                <span class="small text-muted ms-2">{{ date('d M Y H:i', strtotime($transaction->paid_at)) }}</span>
              </li>
            @endif
            @if(!empty($transaction->shipped_at))
              <li class="mb-2">
                <i class="fas fa-circle text-success me-2" style="font-size:0.5rem;vertical-align:middle;"></i>
                <strong class="small">{{ __('Shipped') }}</strong>
                <span class="small text-muted ms-2">{{ date('d M Y H:i', strtotime($transaction->shipped_at)) }}</span>
              </li>
            @endif
            @if(!empty($transaction->delivered_at))
              <li class="mb-2">
                <i class="fas fa-circle text-success me-2" style="font-size:0.5rem;vertical-align:middle;"></i>
                <strong class="small">{{ __('Delivered') }}</strong>
                <span class="small text-muted ms-2">{{ date('d M Y H:i', strtotime($transaction->delivered_at)) }}</span>
              </li>
            @endif
            @if(!empty($transaction->completed_at))
              <li class="mb-2">
                <i class="fas fa-circle text-success me-2" style="font-size:0.5rem;vertical-align:middle;"></i>
                <strong class="small">{{ __('Completed') }}</strong>
                <span class="small text-muted ms-2">{{ date('d M Y H:i', strtotime($transaction->completed_at)) }}</span>
              </li>
            @endif
          </ul>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-4">
      <div class="card-header fw-semibold">{{ __('Buyer Information') }}</div>
      <div class="card-body">
        <p class="mb-1"><strong>{{ $buyerName ?? '-' }}</strong></p>
        @if(!empty($buyerEmail))
          <p class="small text-muted mb-0"><i class="fas fa-envelope me-1"></i>{{ $buyerEmail }}</p>
        @endif
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header fw-semibold">{{ __('Payment') }}</div>
      <div class="card-body">
        <table class="table table-sm table-borderless mb-0">
          <tr>
            <td class="text-muted">{{ __('Status') }}</td>
            <td><span class="badge bg-{{ $payClass }}">{{ ucfirst($transaction->payment_status ?? '') }}</span></td>
          </tr>
          @if(!empty($transaction->payment_gateway))
            <tr>
              <td class="text-muted">{{ __('Gateway') }}</td>
              <td>{{ ucfirst($transaction->payment_gateway) }}</td>
            </tr>
          @endif
          @if(!empty($transaction->paid_at))
            <tr>
              <td class="text-muted">{{ __('Paid At') }}</td>
              <td class="small">{{ date('d M Y H:i', strtotime($transaction->paid_at)) }}</td>
            </tr>
          @endif
        </table>
      </div>
    </div>
  </div>
</div>

<div class="mt-2">
  <a href="{{ route('ahgmarketplace.dashboard') }}" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Dashboard') }}
  </a>
</div>
@endsection
