{{--
  Marketplace — Make an Offer

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/offerFormSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Make an Offer') . ' - ' . ($listing->title ?? ''))
@section('body-class', 'marketplace offer-form')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}">{{ $listing->title ?? '' }}</a></li>
    <li class="breadcrumb-item active">{{ __('Make an Offer') }}</li>
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

<div class="row">
  <div class="col-lg-8 mx-auto">
    <h1 class="h3 mb-4">{{ __('Make an Offer') }}</h1>

    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex">
          @if(!empty($primaryImage->file_path ?? null))
            <img src="{{ $primaryImage->file_path }}" alt="{{ $listing->title ?? '' }}" class="rounded me-3" style="width:100px;height:100px;object-fit:cover;">
          @elseif(!empty($listing->featured_image_path))
            <img src="{{ $listing->featured_image_path }}" alt="{{ $listing->title ?? '' }}" class="rounded me-3" style="width:100px;height:100px;object-fit:cover;">
          @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" style="width:100px;height:100px;">
              <i class="fas fa-image fa-2x text-muted"></i>
            </div>
          @endif
          <div>
            <h5 class="mb-1">{{ $listing->title ?? '' }}</h5>
            @if(!empty($listing->artist_name))
              <p class="text-muted mb-1">{{ __('by :name', ['name' => $listing->artist_name]) }}</p>
            @endif
            @if(!empty($listing->price) && empty($listing->price_on_request))
              <p class="h5 text-primary mb-0">{{ $listing->currency ?? '' }} {{ number_format((float) $listing->price, 2) }}</p>
            @elseif(!empty($listing->price_on_request))
              <p class="h5 text-muted mb-0">{{ __('Price on Request') }}</p>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0"><i class="fas fa-hand-holding-usd me-2"></i>{{ __('Your Offer') }}</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('ahgmarketplace.offer-form.post', ['slug' => $listing->slug ?? '']) }}">
          @csrf
          <div class="mb-3">
            <label for="offer_amount" class="form-label">{{ __('Offer Amount') }} <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">{{ $listing->currency ?? '' }}</span>
              <input type="number" class="form-control form-control-lg" id="offer_amount" name="offer_amount" step="0.01" min="{{ !empty($listing->minimum_offer) ? number_format((float) $listing->minimum_offer, 2, '.', '') : '0.01' }}" placeholder="{{ __('Enter your offer amount') }}" required>
            </div>
            @if(!empty($listing->minimum_offer))
              <div class="form-text">
                <i class="fas fa-info-circle me-1"></i>
                {{ __('Minimum offer: :c :a', ['c' => $listing->currency ?? '', 'a' => number_format((float) $listing->minimum_offer, 2)]) }}
              </div>
            @endif
          </div>
          <div class="mb-4">
            <label for="message" class="form-label">{{ __('Message to Seller') }} <span class="text-muted">({{ __('optional') }})</span></label>
            <textarea class="form-control" id="message" name="message" rows="4" placeholder="{{ __('Introduce yourself or explain your offer...') }}">{{ old('message') }}</textarea>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="btn btn-outline-secondary">
              <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Listing') }}
            </a>
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="fas fa-paper-plane me-1"></i> {{ __('Submit Offer') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
