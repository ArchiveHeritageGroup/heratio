{{--
  Marketplace Admin — Listing Review

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/adminListingReviewSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Review Listing') . ' - ' . __('Marketplace Admin'))
@section('body-class', 'admin marketplace listing-review')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.admin-dashboard') }}">{{ __('Marketplace Admin') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.admin-listings') }}">{{ __('Listings') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Review') }}</li>
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
  <h1 class="h3 mb-0">{{ __('Review Listing') }}</h1>
  <span class="badge bg-{{ ($listing->status ?? '') === 'pending_review' ? 'warning' : 'secondary' }} fs-6">
    {{ ucfirst(str_replace('_', ' ', $listing->status ?? '-')) }}
  </span>
</div>

<div class="row">
  <div class="col-lg-8">
    @if(!empty($images) && count($images) > 0)
      <div class="card mb-4">
        <div class="card-header"><h5 class="card-title mb-0">{{ __('Images') }}</h5></div>
        <div class="card-body">
          <div class="row g-2">
            @foreach($images as $img)
              <div class="col-4 col-md-3">
                <img src="{{ $img->image_path ?? '' }}" alt="{{ $img->alt_text ?? '' }}" class="img-fluid rounded border" style="width:100%;height:120px;object-fit:cover;">
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    <div class="card mb-4">
      <div class="card-header"><h5 class="card-title mb-0">{{ $listing->title ?? '' }}</h5></div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tbody>
            <tr><th style="width:200px;">{{ __('Listing Number') }}</th><td>{{ $listing->listing_number ?? '' }}</td></tr>
            <tr><th>{{ __('Sector') }}</th><td><span class="badge bg-info">{{ ucfirst($listing->sector ?? '') }}</span></td></tr>
            <tr><th>{{ __('Category') }}</th><td>{{ $listing->category_name ?? '-' }}</td></tr>
            <tr><th>{{ __('Listing Type') }}</th><td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $listing->listing_type ?? '-')) }}</span></td></tr>
            <tr>
              <th>{{ __('Price') }}</th>
              <td>
                @if(!empty($listing->price_on_request))
                  <span class="text-muted">{{ __('Price on Request') }}</span>
                @elseif(!empty($listing->price))
                  <strong>{{ $listing->currency ?? '' }} {{ number_format((float) $listing->price, 2) }}</strong>
                @else
                  -
                @endif
              </td>
            </tr>
            @if(($listing->listing_type ?? '') === 'auction')
              <tr><th>{{ __('Starting Bid') }}</th><td>{{ $listing->currency ?? '' }} {{ number_format((float) ($listing->auction_start_price ?? 0), 2) }}</td></tr>
              <tr><th>{{ __('Reserve Price') }}</th><td>{{ !empty($listing->auction_reserve_price) ? ($listing->currency ?? '') . ' ' . number_format((float) $listing->auction_reserve_price, 2) : '-' }}</td></tr>
            @endif
            <tr><th>{{ __('Condition') }}</th><td>{{ ucfirst(str_replace('_', ' ', $listing->condition ?? '-')) }}</td></tr>
            <tr><th>{{ __('Description') }}</th><td>{!! nl2br(e($listing->description ?? '-')) !!}</td></tr>
            <tr><th>{{ __('Provenance') }}</th><td>{!! nl2br(e($listing->provenance ?? '-')) !!}</td></tr>
            <tr><th>{{ __('Dimensions') }}</th><td>{{ $listing->dimensions ?? '-' }}</td></tr>
            <tr><th>{{ __('Created') }}</th><td>{{ !empty($listing->created_at) ? date('d M Y H:i', strtotime($listing->created_at)) : '-' }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h5 class="card-title mb-0">{{ __('Admin Actions') }}</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <form method="POST" action="{{ route('ahgmarketplace.admin-listing-review.post', ['id' => $listing->id ?? 0]) }}">
              @csrf
              <input type="hidden" name="form_action" value="approve">
              <input type="hidden" name="id" value="{{ $listing->id ?? 0 }}">
              <button type="submit" class="btn btn-success w-100" onclick="return confirm('{{ __('Approve this listing and make it active?') }}');">
                <i class="fas fa-check me-1"></i> {{ __('Approve') }}
              </button>
            </form>
          </div>
          <div class="col-md-4">
            <form method="POST" action="{{ route('ahgmarketplace.admin-listing-review.post', ['id' => $listing->id ?? 0]) }}">
              @csrf
              <input type="hidden" name="form_action" value="suspend">
              <input type="hidden" name="id" value="{{ $listing->id ?? 0 }}">
              <button type="submit" class="btn btn-warning w-100" onclick="return confirm('{{ __('Suspend this listing?') }}');">
                <i class="fas fa-pause me-1"></i> {{ __('Suspend') }}
              </button>
            </form>
          </div>
          <div class="col-md-4">
            <form method="POST" action="{{ route('ahgmarketplace.admin-listing-review.post', ['id' => $listing->id ?? 0]) }}">
              @csrf
              <input type="hidden" name="form_action" value="reject">
              <input type="hidden" name="id" value="{{ $listing->id ?? 0 }}">
              <button type="submit" class="btn btn-danger w-100" onclick="return confirm('{{ __('Reject this listing and return it to draft?') }}');">
                <i class="fas fa-times me-1"></i> {{ __('Reject') }}
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    @if(!empty($seller))
      @php
        $verifyClass = match($seller->verification_status ?? '') {
          'verified' => 'success',
          'pending' => 'warning',
          'suspended' => 'secondary',
          default => 'danger',
        };
      @endphp
      <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">{{ __('Seller Info') }}</h5></div>
        <div class="card-body text-center">
          @if(!empty($seller->avatar_path))
            <img src="{{ $seller->avatar_path }}" alt="" class="rounded-circle mb-3" width="80" height="80" style="object-fit:cover;">
          @else
            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width:80px;height:80px;">
              <i class="fas fa-user fa-2x text-muted"></i>
            </div>
          @endif
          <h6>{{ $seller->display_name ?? '' }}</h6>
          <p class="small text-muted mb-2">{{ $seller->email ?? '' }}</p>
          <span class="badge bg-{{ $verifyClass }}">{{ ucfirst($seller->verification_status ?? 'unverified') }}</span>
        </div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between">
            <span class="text-muted">{{ __('Type') }}</span>
            <span>{{ ucfirst($seller->seller_type ?? '-') }}</span>
          </li>
          <li class="list-group-item d-flex justify-content-between">
            <span class="text-muted">{{ __('Total Sales') }}</span>
            <span>{{ number_format((int) ($seller->total_sales ?? 0)) }}</span>
          </li>
          <li class="list-group-item d-flex justify-content-between">
            <span class="text-muted">{{ __('Rating') }}</span>
            <span>{{ number_format((float) ($seller->average_rating ?? 0), 1) }} <i class="fas fa-star text-warning small"></i></span>
          </li>
          <li class="list-group-item d-flex justify-content-between">
            <span class="text-muted">{{ __('Joined') }}</span>
            <span class="small">{{ !empty($seller->created_at) ? date('d M Y', strtotime($seller->created_at)) : '' }}</span>
          </li>
        </ul>
        <div class="card-body">
          <a href="{{ route('ahgmarketplace.admin-seller-verify', ['id' => $seller->id ?? 0]) }}" class="btn btn-sm btn-outline-primary w-100">
            <i class="fas fa-eye me-1"></i> {{ __('View Seller Profile') }}
          </a>
        </div>
      </div>
    @endif
  </div>
</div>
@endsection
