{{--
  Marketplace Admin — Verify Seller

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/adminSellerVerifySuccess.php.
  Payout currency is rendered per-row from $seller->payout_currency (international — no hardcoded default).
--}}
@extends('theme::layouts.1col')
@section('title', __('Verify Seller') . ' - ' . __('Marketplace Admin'))
@section('body-class', 'admin marketplace seller-verify')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.admin-dashboard') }}">{{ __('Marketplace Admin') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.admin-sellers') }}">{{ __('Sellers') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Verify Seller') }}</li>
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

@php
  $verifyClass = match($seller->verification_status ?? '') {
    'verified' => 'success',
    'pending' => 'warning',
    'suspended' => 'secondary',
    default => 'danger',
  };
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">{{ __('Verify Seller') }}</h1>
  <span class="badge bg-{{ $verifyClass }} fs-6">{{ ucfirst($seller->verification_status ?? 'unverified') }}</span>
</div>

<div class="row">
  <div class="col-lg-8">
    @if(!empty($seller->banner_path))
      <div class="card mb-4">
        <img src="{{ $seller->banner_path }}" alt="" class="card-img-top" style="max-height:200px;object-fit:cover;">
      </div>
    @endif

    <div class="card mb-4">
      <div class="card-header"><h5 class="card-title mb-0">{{ __('Seller Profile') }}</h5></div>
      <div class="card-body">
        <div class="d-flex align-items-center mb-4">
          @if(!empty($seller->avatar_path))
            <img src="{{ $seller->avatar_path }}" alt="" class="rounded-circle me-3" width="80" height="80" style="object-fit:cover;">
          @else
            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width:80px;height:80px;">
              <i class="fas fa-user fa-2x text-muted"></i>
            </div>
          @endif
          <div>
            <h4 class="mb-1">{{ $seller->display_name ?? '' }}</h4>
            <p class="text-muted mb-0">{{ $seller->tagline ?? '' }}</p>
          </div>
        </div>

        @php
          $locationParts = array_filter([$seller->city ?? '', $seller->state_province ?? '', $seller->country ?? '']);
        @endphp
        <table class="table table-sm mb-0">
          <tbody>
            <tr><th style="width:200px;">{{ __('Seller Type') }}</th><td><span class="badge bg-secondary">{{ ucfirst($seller->seller_type ?? '-') }}</span></td></tr>
            <tr><th>{{ __('Email') }}</th><td>{{ $seller->email ?? '-' }}</td></tr>
            <tr><th>{{ __('Phone') }}</th><td>{{ $seller->phone ?? '-' }}</td></tr>
            <tr><th>{{ __('Location') }}</th><td>{{ !empty($locationParts) ? implode(', ', $locationParts) : '-' }}</td></tr>
            <tr>
              <th>{{ __('Website') }}</th>
              <td>
                @if(!empty($seller->website))
                  <a href="{{ $seller->website }}" target="_blank" rel="noopener">{{ $seller->website }}</a>
                @else
                  -
                @endif
              </td>
            </tr>
            <tr><th>{{ __('Description') }}</th><td>{!! nl2br(e($seller->description ?? '-')) !!}</td></tr>
            <tr><th>{{ __('Payout Method') }}</th><td>{{ ucfirst(str_replace('_', ' ', $seller->payout_method ?? '-')) }}</td></tr>
            <tr><th>{{ __('Payout Currency') }}</th><td>{{ $seller->payout_currency ?? '-' }}</td></tr>
            <tr><th>{{ __('Joined') }}</th><td>{{ !empty($seller->created_at) ? date('d M Y H:i', strtotime($seller->created_at)) : '-' }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h5 class="card-title mb-0">{{ __('Admin Actions') }}</h5></div>
      <div class="card-body">
        <div class="row g-3">
          @if(($seller->verification_status ?? '') !== 'verified')
            <div class="col-md-6">
              <form method="POST" action="{{ route('ahgmarketplace.admin-seller-verify.post', ['id' => $seller->id ?? 0]) }}">
                @csrf
                <input type="hidden" name="form_action" value="verify">
                <input type="hidden" name="id" value="{{ $seller->id ?? 0 }}">
                <button type="submit" class="btn btn-success w-100" onclick="return confirm('{{ __('Verify this seller?') }}');">
                  <i class="fas fa-check-circle me-1"></i> {{ __('Verify Seller') }}
                </button>
              </form>
            </div>
          @endif
          @if(($seller->verification_status ?? '') !== 'suspended')
            <div class="col-md-6">
              <form method="POST" action="{{ route('ahgmarketplace.admin-seller-verify.post', ['id' => $seller->id ?? 0]) }}">
                @csrf
                <input type="hidden" name="form_action" value="suspend">
                <input type="hidden" name="id" value="{{ $seller->id ?? 0 }}">
                <button type="submit" class="btn btn-warning w-100" onclick="return confirm('{{ __('Suspend this seller?') }}');">
                  <i class="fas fa-ban me-1"></i> {{ __('Suspend Seller') }}
                </button>
              </form>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-4">
      <div class="card-header fw-semibold">{{ __('Seller Stats') }}</div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">{{ __('Total Sales') }}</span>
          <span class="fw-semibold">{{ number_format((int) ($seller->total_sales ?? 0)) }}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">{{ __('Revenue') }}</span>
          <span class="fw-semibold">{{ $seller->payout_currency ?? '' }} {{ number_format((float) ($seller->total_revenue ?? 0), 2) }}</span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">{{ __('Rating') }}</span>
          <span>
            {{ number_format((float) ($seller->average_rating ?? 0), 1) }}
            <i class="fas fa-star text-warning small"></i>
            <small class="text-muted">({{ (int) ($seller->rating_count ?? 0) }})</small>
          </span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span class="text-muted">{{ __('Followers') }}</span>
          <span>{{ number_format((int) ($seller->follower_count ?? 0)) }}</span>
        </li>
      </ul>
    </div>

    <div class="card">
      <div class="card-body">
        <a href="{{ route('ahgmarketplace.seller', ['slug' => $seller->slug ?? '']) }}" class="btn btn-outline-primary w-100 mb-2">
          <i class="fas fa-external-link-alt me-1"></i> {{ __('View Public Profile') }}
        </a>
        <a href="{{ route('ahgmarketplace.admin-sellers') }}" class="btn btn-outline-secondary w-100">
          <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Sellers') }}
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
