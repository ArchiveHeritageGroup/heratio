{{--
  Marketplace registration chooser — buyer or seller (or both).
  Receives: $isAuthenticated, $existingSeller.
--}}
@extends('theme::layouts.1col')

@section('title', 'Join the Marketplace')
@section('body-class', 'marketplace register')

@section('content')

  <div class="text-center mb-4">
    <h1 class="mb-2">
      <i class="fas fa-store me-2 text-primary"></i>
      Join the Heratio Marketplace
    </h1>
    <p class="lead text-muted">Buy, sell, or both — choose how you want to get started.</p>
  </div>

  <div class="row g-4 mb-4">

    {{-- Buyer card --}}
    <div class="col-md-6">
      <div class="card h-100 shadow-sm">
        <div class="card-body p-4">
          <div class="text-center mb-3">
            <i class="fas fa-shopping-bag fa-3x text-success"></i>
          </div>
          <h2 class="h4 text-center mb-3">{{ __('Register as a Buyer') }}</h2>
          <ul class="small mb-4">
            <li>Browse all listings &mdash; gallery, museum, archive, library, DAM</li>
            <li>Place bids on auctions</li>
            <li>Make offers on fixed-price items</li>
            <li>Send enquiries to sellers</li>
            <li>Track favourites &amp; purchases</li>
          </ul>
          <p class="small text-muted mb-4">
            <i class="fas fa-info-circle me-1"></i>
            No extra signup needed &mdash; your Heratio account is your buyer account.
          </p>
          <div class="d-grid">
            @if($isAuthenticated)
              <a href="{{ route('ahgmarketplace.buyer-start') }}" class="btn btn-success btn-lg">
                <i class="fas fa-shopping-bag me-1"></i> Start buying
              </a>
            @else
              <a href="{{ route('login') }}?next={{ urlencode(route('ahgmarketplace.buyer-start')) }}" class="btn btn-success btn-lg">
                <i class="fas fa-sign-in-alt me-1"></i> Sign in to start buying
              </a>
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- Seller card --}}
    <div class="col-md-6">
      <div class="card h-100 shadow-sm">
        <div class="card-body p-4">
          <div class="text-center mb-3">
            <i class="fas fa-tag fa-3x text-primary"></i>
          </div>
          <h2 class="h4 text-center mb-3">{{ __('Register as a Seller') }}</h2>
          <ul class="small mb-4">
            <li>List items at fixed price, by offer, or as auctions</li>
            <li>Receive payments via PayFast (and other gateways)</li>
            <li>Manage offers, bids, and enquiries</li>
            <li>Build a seller profile, collections, and reviews</li>
            <li>Track sales, payouts, and analytics</li>
          </ul>
          <p class="small text-muted mb-4">
            <i class="fas fa-info-circle me-1"></i>
            Sellers complete a short profile (display name, contact, payout method) and accept the marketplace terms.
          </p>
          <div class="d-grid">
            @if($existingSeller)
              <a href="{{ route('ahgmarketplace.dashboard') }}" class="btn btn-primary btn-lg">
                <i class="fas fa-tachometer-alt me-1"></i> Open my seller dashboard
              </a>
            @elseif($isAuthenticated)
              <a href="{{ route('ahgmarketplace.seller-register') }}" class="btn btn-primary btn-lg">
                <i class="fas fa-tag me-1"></i> Set up seller profile
              </a>
            @else
              <a href="{{ route('login') }}?next={{ urlencode(route('ahgmarketplace.seller-register')) }}" class="btn btn-primary btn-lg">
                <i class="fas fa-sign-in-alt me-1"></i> Sign in to set up selling
              </a>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="text-center text-muted small">
    <i class="fas fa-lightbulb me-1"></i>
    You can be both &mdash; register as a buyer now, and add a seller profile any time from your dashboard.
  </div>

@endsection
