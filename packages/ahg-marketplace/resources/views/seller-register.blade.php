{{--
  Marketplace — Register as Seller

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/sellerRegisterSuccess.php.
  Adds Stripe + Wise payout methods and international currency default.
--}}
@extends('theme::layouts.1col')
@section('title', __('Register as Seller') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace seller-register')

@php
  $baseCurrency = config('heratio.base_currency', 'ZAR');
  $types = ['artist' => __('Artist'), 'gallery' => __('Gallery'), 'institution' => __('Institution'), 'collector' => __('Collector'), 'estate' => __('Estate')];
  $sectorOptions = ['gallery' => __('Gallery'), 'museum' => __('Museum'), 'archive' => __('Archive'), 'library' => __('Library'), 'dam' => __('Digital Asset Management')];
  $methods = ['bank_transfer' => __('Bank Transfer'), 'paypal' => __('PayPal'), 'stripe' => __('Stripe'), 'wise' => __('Wise'), 'payfast' => __('PayFast')];
  $selectedSectors = old('sectors', []);
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Register as Seller') }}</li>
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
    <div class="card">
      <div class="card-header">
        <h1 class="h4 mb-0"><i class="fas fa-store me-2"></i>{{ __('Register as a Seller') }}</h1>
      </div>
      <div class="card-body">
        <p class="text-muted mb-4">{{ __('Fill in the details below to create your seller profile and start listing items on the marketplace.') }}</p>

        <form method="POST" action="{{ route('ahgmarketplace.seller-register.post') }}">
          @csrf

          <div class="mb-3">
            <label for="display_name" class="form-label">{{ __('Display Name') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="display_name" name="display_name" value="{{ old('display_name') }}" required maxlength="255">
            <div class="form-text">{{ __('This is the name buyers will see on your listings.') }}</div>
          </div>

          <div class="mb-3">
            <label for="seller_type" class="form-label">{{ __('Seller Type') }} <span class="text-danger">*</span></label>
            <select class="form-select" id="seller_type" name="seller_type" required>
              <option value="">{{ __('-- Select Type --') }}</option>
              @foreach($types as $val => $label)
                <option value="{{ $val }}" {{ old('seller_type') === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="email" class="form-label">{{ __('Email') }}</label>
              <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" maxlength="255">
            </div>
            <div class="col-md-6">
              <label for="phone" class="form-label">{{ __('Phone') }}</label>
              <input type="tel" class="form-control" id="phone" name="phone" value="{{ old('phone') }}" maxlength="50">
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="country" class="form-label">{{ __('Country') }}</label>
              <input type="text" class="form-control" id="country" name="country" value="{{ old('country') }}" maxlength="100">
            </div>
            <div class="col-md-6">
              <label for="city" class="form-label">{{ __('City') }}</label>
              <input type="text" class="form-control" id="city" name="city" value="{{ old('city') }}" maxlength="100">
            </div>
          </div>

          <div class="mb-3">
            <label for="bio" class="form-label">{{ __('Bio') }}</label>
            <textarea class="form-control" id="bio" name="bio" rows="4" placeholder="{{ __('Tell buyers about yourself or your gallery...') }}">{{ old('bio') }}</textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">{{ __('Sectors') }}</label>
            <div class="form-text mb-2">{{ __('Select the sectors you deal in.') }}</div>
            @foreach($sectorOptions as $val => $label)
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="sectors[]" value="{{ $val }}" id="sector-{{ $val }}" {{ (is_array($selectedSectors) && in_array($val, $selectedSectors)) ? 'checked' : '' }}>
                <label class="form-check-label" for="sector-{{ $val }}">{{ $label }}</label>
              </div>
            @endforeach
          </div>

          <hr class="my-4">
          <h5 class="mb-3">{{ __('Payout Preferences') }}</h5>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="payout_method" class="form-label">{{ __('Payout Method') }}</label>
              <select class="form-select" id="payout_method" name="payout_method">
                @foreach($methods as $val => $label)
                  <option value="{{ $val }}" {{ old('payout_method') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label for="payout_currency" class="form-label">{{ __('Payout Currency') }}</label>
              <select class="form-select" id="payout_currency" name="payout_currency">
                @foreach($currencies ?? [] as $cur)
                  <option value="{{ $cur->code }}" {{ old('payout_currency', $baseCurrency) === $cur->code ? 'selected' : '' }}>{{ $cur->code }} - {{ $cur->name }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <hr class="my-4">

          <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="accept_terms" name="accept_terms" value="1" required>
            <label class="form-check-label" for="accept_terms">
              {{ __('I accept the') }} <a href="{{ url('/marketplace/terms') }}" target="_blank">{{ __('Terms and Conditions') }}</a> {{ __('for sellers.') }} <span class="text-danger">*</span>
            </label>
          </div>

          <div class="d-flex justify-content-between">
            <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-outline-secondary">
              <i class="fas fa-arrow-left me-1"></i> {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="fas fa-store me-1"></i> {{ __('Register') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
