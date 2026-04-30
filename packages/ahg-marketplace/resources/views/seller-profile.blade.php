{{--
  Marketplace — Edit Seller Profile

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/sellerProfileSuccess.php.

  International framing: the default payout currency falls back to config('heratio.base_currency')
  not hardcoded ZAR. Payout method list is sorted alphabetically (bank_transfer, paypal, payfast).
--}}
@extends('theme::layouts.1col')
@section('title', __('Edit Seller Profile') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace seller-profile-edit')

@php
  $baseCurrency = config('heratio.base_currency', 'ZAR');
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Edit Profile') }}</li>
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
    <h1 class="h3 mb-4">{{ __('Edit Seller Profile') }}</h1>

    <form method="POST" action="{{ route('ahgmarketplace.seller-profile.post') }}" enctype="multipart/form-data">
      @csrf

      <div class="card mb-4">
        <div class="card-header fw-semibold">{{ __('Profile Images') }}</div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="avatar" class="form-label">{{ __('Avatar') }}</label>
              @if(!empty($seller->avatar_path))
                <div class="mb-2">
                  <img src="{{ $seller->avatar_path }}" alt="" class="rounded-circle" width="80" height="80" style="object-fit:cover;">
                </div>
              @endif
              <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
              <div class="form-text">{{ __('Square image recommended. Max 2MB.') }}</div>
            </div>
            <div class="col-md-6">
              <label for="banner" class="form-label">{{ __('Banner Image') }}</label>
              @if(!empty($seller->banner_path))
                <div class="mb-2">
                  <img src="{{ $seller->banner_path }}" alt="" class="rounded" width="200" height="60" style="object-fit:cover;">
                </div>
              @endif
              <input type="file" class="form-control" id="banner" name="banner" accept="image/*">
              <div class="form-text">{{ __('Recommended size: 1200x300px. Max 5MB.') }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header fw-semibold">{{ __('Basic Information') }}</div>
        <div class="card-body">
          <div class="mb-3">
            <label for="display_name" class="form-label">{{ __('Display Name') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="display_name" name="display_name" value="{{ $seller->display_name ?? '' }}" required maxlength="255">
          </div>
          <div class="mb-3">
            <label for="seller_type" class="form-label">{{ __('Seller Type') }}</label>
            <select class="form-select" id="seller_type" name="seller_type">
              @php $types = ['artist' => __('Artist'), 'gallery' => __('Gallery'), 'institution' => __('Institution'), 'collector' => __('Collector'), 'estate' => __('Estate')]; @endphp
              @foreach($types as $val => $label)
                <option value="{{ $val }}" {{ ($seller->seller_type ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label for="bio" class="form-label">{{ __('Bio') }}</label>
            <textarea class="form-control" id="bio" name="bio" rows="4">{{ $seller->bio ?? '' }}</textarea>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header fw-semibold">{{ __('Contact Information') }}</div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="email" class="form-label">{{ __('Email') }}</label>
              <input type="email" class="form-control" id="email" name="email" value="{{ $seller->email ?? '' }}" maxlength="255">
            </div>
            <div class="col-md-6">
              <label for="phone" class="form-label">{{ __('Phone') }}</label>
              <input type="tel" class="form-control" id="phone" name="phone" value="{{ $seller->phone ?? '' }}" maxlength="50">
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="website" class="form-label">{{ __('Website') }}</label>
              <input type="url" class="form-control" id="website" name="website" value="{{ $seller->website ?? '' }}" placeholder="{{ __('https://') }}" maxlength="255">
            </div>
            <div class="col-md-6">
              <label for="instagram" class="form-label">{{ __('Instagram') }}</label>
              <div class="input-group">
                <span class="input-group-text">@</span>
                <input type="text" class="form-control" id="instagram" name="instagram" value="{{ ltrim($seller->instagram ?? '', '@') }}" maxlength="255">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header fw-semibold">{{ __('Location') }}</div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <label for="country" class="form-label">{{ __('Country') }}</label>
              <input type="text" class="form-control" id="country" name="country" value="{{ $seller->country ?? '' }}" maxlength="100">
            </div>
            <div class="col-md-6">
              <label for="city" class="form-label">{{ __('City') }}</label>
              <input type="text" class="form-control" id="city" name="city" value="{{ $seller->city ?? '' }}" maxlength="100">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header fw-semibold">{{ __('Payout Settings') }}</div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="payout_method" class="form-label">{{ __('Payout Method') }}</label>
              <select class="form-select" id="payout_method" name="payout_method">
                @php $methods = ['bank_transfer' => __('Bank Transfer'), 'paypal' => __('PayPal'), 'payfast' => __('PayFast'), 'stripe' => __('Stripe'), 'wise' => __('Wise')]; @endphp
                @foreach($methods as $val => $label)
                  <option value="{{ $val }}" {{ ($seller->payout_method ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label for="payout_currency" class="form-label">{{ __('Payout Currency') }}</label>
              <select class="form-select" id="payout_currency" name="payout_currency">
                @foreach($currencies ?? [] as $cur)
                  <option value="{{ $cur->code }}" {{ ($seller->payout_currency ?? $baseCurrency) === $cur->code ? 'selected' : '' }}>
                    {{ $cur->code }} - {{ $cur->name }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>

          @php
            $payoutDetails = [];
            if (!empty($seller->payout_details)) {
              $payoutDetails = is_string($seller->payout_details)
                ? (json_decode($seller->payout_details, true) ?? [])
                : (array) $seller->payout_details;
            }
          @endphp
          <fieldset class="border rounded p-3">
            <legend class="w-auto px-2 fs-6 fw-semibold">{{ __('Bank Details') }}</legend>
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="account_name" class="form-label">{{ __('Account Name') }}</label>
                <input type="text" class="form-control" id="account_name" name="payout_details[account_name]" value="{{ $payoutDetails['account_name'] ?? '' }}">
              </div>
              <div class="col-md-6">
                <label for="account_number" class="form-label">{{ __('Account Number') }}</label>
                <input type="text" class="form-control" id="account_number" name="payout_details[account_number]" value="{{ $payoutDetails['account_number'] ?? '' }}">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <label for="bank_name" class="form-label">{{ __('Bank Name') }}</label>
                <input type="text" class="form-control" id="bank_name" name="payout_details[bank_name]" value="{{ $payoutDetails['bank_name'] ?? '' }}">
              </div>
              <div class="col-md-6">
                <label for="branch_code" class="form-label">{{ __('Branch Code / IBAN / SWIFT') }}</label>
                <input type="text" class="form-control" id="branch_code" name="payout_details[branch_code]" value="{{ $payoutDetails['branch_code'] ?? '' }}">
              </div>
            </div>
          </fieldset>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header fw-semibold">{{ __('Sectors') }}</div>
        <div class="card-body">
          @php
            $sellerSectors = [];
            if (!empty($seller->sectors)) {
              $sellerSectors = is_string($seller->sectors)
                ? (json_decode($seller->sectors, true) ?? [])
                : (array) $seller->sectors;
            }
            $sectorOptions = ['gallery' => __('Gallery'), 'museum' => __('Museum'), 'archive' => __('Archive'), 'library' => __('Library'), 'dam' => __('Digital Asset Management')];
          @endphp
          @foreach($sectorOptions as $val => $label)
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="sectors[]" value="{{ $val }}" id="sector-{{ $val }}" {{ in_array($val, $sellerSectors, true) ? 'checked' : '' }}>
              <label class="form-check-label" for="sector-{{ $val }}">{{ $label }}</label>
            </div>
          @endforeach
        </div>
      </div>

      {{-- Reservation notification preferences --}}
      <h5 class="mt-4 mb-2">
        <i class="fas fa-bell me-1 text-primary"></i> {{ __('Reservation notifications') }}
      </h5>
      <p class="small text-muted mb-3">
        {{ __('Email me when buyers reserve my listings (12-hour holds, max 2 per buyer per 24h).') }}
      </p>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <div class="form-check form-switch">
            <input type="hidden" name="notify_on_reservation" value="0">
            <input class="form-check-input" type="checkbox" id="notify_on_reservation" name="notify_on_reservation" value="1"
                   {{ ((int) ($seller->notify_on_reservation ?? 1) === 1) ? 'checked' : '' }}>
            <label class="form-check-label" for="notify_on_reservation">
              <strong>{{ __('On new reservation') }}</strong>
              <span class="d-block small text-muted">{{ __('Email me when a buyer places a 12-hour hold on one of my listings.') }}</span>
            </label>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-check form-switch">
            <input type="hidden" name="notify_reservation_reminders" value="0">
            <input class="form-check-input" type="checkbox" id="notify_reservation_reminders" name="notify_reservation_reminders" value="1"
                   {{ ((int) ($seller->notify_reservation_reminders ?? 1) === 1) ? 'checked' : '' }}>
            <label class="form-check-label" for="notify_reservation_reminders">
              <strong>{{ __('Reminder updates (6h / 1h before expiry)') }}</strong>
              <span class="d-block small text-muted">{{ __('Email me when a buyer\'s hold is about to expire.') }}</span>
            </label>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-check form-switch">
            <input type="hidden" name="notify_on_reservation_expiry" value="0">
            <input class="form-check-input" type="checkbox" id="notify_on_reservation_expiry" name="notify_on_reservation_expiry" value="1"
                   {{ ((int) ($seller->notify_on_reservation_expiry ?? 1) === 1) ? 'checked' : '' }}>
            <label class="form-check-label" for="notify_on_reservation_expiry">
              <strong>{{ __('When a hold expires unconverted') }}</strong>
              <span class="d-block small text-muted">{{ __('Email me when a buyer\'s 12-hour hold expires without a purchase.') }}</span>
            </label>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between">
        <a href="{{ route('ahgmarketplace.dashboard') }}" class="btn btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Dashboard') }}
        </a>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save me-1"></i> {{ __('Save Profile') }}
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
