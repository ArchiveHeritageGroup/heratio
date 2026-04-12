{{--
  Marketplace — Seller: Respond to Offer (accept / reject / counter)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/sellerOfferRespondSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Respond to Offer') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace seller-offer-respond')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Respond to Offer') }}</li>
  </ol>
</nav>

@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<div class="row">
  <div class="col-lg-8 mx-auto">
    <h1 class="h3 mb-4">{{ __('Respond to Offer') }}</h1>

    {{-- Offer detail card --}}
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex">
          @if(!empty($listing->featured_image_path))
            <img src="{{ $listing->featured_image_path }}" alt="" class="rounded me-3" style="width:100px;height:100px;object-fit:cover;">
          @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" style="width:100px;height:100px;">
              <i class="fas fa-image fa-2x text-muted"></i>
            </div>
          @endif
          <div class="flex-grow-1">
            <h5 class="mb-1">
              <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="text-decoration-none">{{ $listing->title ?? '' }}</a>
            </h5>
            @if(!empty($listing->price) && empty($listing->price_on_request))
              <p class="text-muted mb-1">{{ __('Listing Price: :c :a', ['c' => $listing->currency ?? '', 'a' => number_format((float) $listing->price, 2)]) }}</p>
            @endif
            <p class="h5 text-primary mb-1">{{ __('Offer Amount: :c :a', ['c' => $offer->currency ?? '', 'a' => number_format((float) ($offer->offer_amount ?? 0), 2)]) }}</p>
            <p class="small text-muted mb-0">{{ __('From: :name', ['name' => $buyerName ?? '-']) }} &mdash; {{ !empty($offer->created_at) ? date('d M Y H:i', strtotime($offer->created_at)) : '' }}</p>
          </div>
        </div>
        @if(!empty($offer->message))
          <div class="mt-3 p-3 bg-light rounded">
            <strong class="small">{{ __('Buyer Message:') }}</strong>
            <p class="mb-0 small">{!! nl2br(e($offer->message)) !!}</p>
          </div>
        @endif
        @if(!empty($offer->counter_amount))
          <div class="mt-2 p-3 bg-warning bg-opacity-10 rounded">
            <strong class="small">{{ __('Previous Counter-Offer:') }}</strong>
            <span class="fw-semibold">{{ $offer->currency ?? '' }} {{ number_format((float) $offer->counter_amount, 2) }}</span>
          </div>
        @endif
      </div>
    </div>

    {{-- Response form --}}
    <div class="card">
      <div class="card-header fw-semibold">{{ __('Your Response') }}</div>
      <div class="card-body">
        {{-- Accept --}}
        <form method="POST" action="{{ route('ahgmarketplace.seller-offer-respond.post', ['id' => $offer->id ?? 0]) }}" class="mb-3">
          @csrf
          <input type="hidden" name="form_action" value="accept">
          <button type="submit" class="btn btn-success btn-lg w-100" onclick="return confirm('{{ __('Accept this offer of :c :a?', ['c' => $offer->currency ?? '', 'a' => number_format((float) ($offer->offer_amount ?? 0), 2)]) }}');">
            <i class="fas fa-check me-1"></i> {{ __('Accept Offer (:c :a)', ['c' => $offer->currency ?? '', 'a' => number_format((float) ($offer->offer_amount ?? 0), 2)]) }}
          </button>
        </form>

        <hr class="my-4">

        {{-- Reject --}}
        <form method="POST" action="{{ route('ahgmarketplace.seller-offer-respond.post', ['id' => $offer->id ?? 0]) }}" class="mb-4">
          @csrf
          <input type="hidden" name="form_action" value="reject">
          <div class="mb-3">
            <label for="reject_message" class="form-label">{{ __('Rejection Message') }} <span class="text-muted">({{ __('optional') }})</span></label>
            <textarea class="form-control" id="reject_message" name="seller_response" rows="2" placeholder="{{ __('Optional message to the buyer...') }}"></textarea>
          </div>
          <button type="submit" class="btn btn-danger w-100" onclick="return confirm('{{ __('Reject this offer?') }}');">
            <i class="fas fa-times me-1"></i> {{ __('Reject Offer') }}
          </button>
        </form>

        <hr class="my-4">

        {{-- Counter-offer --}}
        <h6 class="mb-3">{{ __('Counter-Offer') }}</h6>
        <form method="POST" action="{{ route('ahgmarketplace.seller-offer-respond.post', ['id' => $offer->id ?? 0]) }}">
          @csrf
          <input type="hidden" name="form_action" value="counter">
          <div class="mb-3">
            <label for="counter_amount" class="form-label">{{ __('Counter Amount') }} <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">{{ $offer->currency ?? '' }}</span>
              <input type="number" class="form-control" id="counter_amount" name="counter_amount" min="0.01" step="0.01" required placeholder="{{ __('Your counter-offer amount') }}">
            </div>
          </div>
          <div class="mb-3">
            <label for="counter_message" class="form-label">{{ __('Message') }} <span class="text-muted">({{ __('optional') }})</span></label>
            <textarea class="form-control" id="counter_message" name="seller_response" rows="3" placeholder="{{ __('Explain your counter-offer...') }}"></textarea>
          </div>
          <button type="submit" class="btn btn-warning w-100">
            <i class="fas fa-exchange-alt me-1"></i> {{ __('Send Counter-Offer') }}
          </button>
        </form>
      </div>
    </div>

    <div class="mt-4">
      <a href="{{ route('ahgmarketplace.dashboard') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Dashboard') }}
      </a>
    </div>
  </div>
</div>
@endsection
