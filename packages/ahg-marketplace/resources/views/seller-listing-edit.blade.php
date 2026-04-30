{{--
  Marketplace — Edit Listing (seller form)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/sellerListingEditSuccess.php.
  Same fields as seller-listing-create but pre-filled from $listing.
--}}
@extends('theme::layouts.1col')
@section('title', __('Edit Listing') . ': ' . ($listing->title ?? '') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace seller-listing-edit')

@php
  $sectorList = ['gallery' => __('Gallery'), 'museum' => __('Museum'), 'archive' => __('Archive'), 'library' => __('Library'), 'dam' => __('Digital Asset Management')];
  $conditions = ['mint' => __('Mint'), 'excellent' => __('Excellent'), 'good' => __('Good'), 'fair' => __('Fair'), 'poor' => __('Poor')];
  $listingTypes = [
    'fixed_price' => __('Fixed Price'),
    'auction'     => __('Auction'),
    'offer_only'  => __('Offer Only'),
    'licence'     => __('Licence'),
    '3d_model'    => __('3D Model'),
  ];
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.seller-listings') }}">{{ __('My Listings') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Edit') }}</li>
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
  <h1 class="h3 mb-0">{{ __('Edit Listing: :title', ['title' => $listing->title ?? '']) }}</h1>
  <div>
    <a href="{{ route('ahgmarketplace.seller-listing-images', ['id' => $listing->id ?? 0]) }}" class="btn btn-outline-secondary me-1">
      <i class="fas fa-images me-1"></i>{{ __('Manage Images') }}
    </a>
    <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="btn btn-outline-secondary" target="_blank">
      <i class="fas fa-external-link-alt me-1"></i>{{ __('Preview') }}
    </a>
  </div>
</div>

<form method="POST" action="{{ route('ahgmarketplace.seller-listing-edit.post', ['id' => $listing->id ?? 0]) }}" id="listing-form">
  @csrf
  <input type="hidden" name="id" value="{{ $listing->id ?? 0 }}">

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Basic Information') }}</div>
    <div class="card-body">
      <div class="mb-3">
        <label for="title" class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $listing->title ?? '') }}" required maxlength="500">
      </div>
      <div class="mb-3">
        <label for="short_description" class="form-label">{{ __('Short Description') }}</label>
        <input type="text" class="form-control" id="short_description" name="short_description" value="{{ old('short_description', $listing->short_description ?? '') }}" maxlength="1000">
      </div>
      <div class="mb-3">
        <label for="description" class="form-label">{{ __('Full Description') }}</label>
        <textarea class="form-control" id="description" name="description" rows="5">{{ old('description', $listing->description ?? '') }}</textarea>
      </div>
      <div class="row mb-3">
        <div class="col-md-6">
          <label for="sector" class="form-label">{{ __('Sector') }} <span class="text-danger">*</span></label>
          <select class="form-select" id="sector" name="sector" required>
            <option value="">{{ __('-- Select Sector --') }}</option>
            @foreach($sectorList as $val => $label)
              <option value="{{ $val }}" {{ old('sector', $listing->sector ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label for="category_id" class="form-label">{{ __('Category') }}</label>
          <select class="form-select" id="category_id" name="category_id">
            <option value="">{{ __('-- Select Category --') }}</option>
            @foreach($categories ?? [] as $cat)
              <option value="{{ (int) $cat->id }}" data-sector="{{ $cat->sector ?? '' }}" {{ old('category_id', $listing->category_id ?? '') == $cat->id ? 'selected' : '' }}>
                {{ $cat->name ?? '' }} ({{ ucfirst($cat->sector ?? '') }})
              </option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-4">
          <label for="medium" class="form-label">{{ __('Medium') }}</label>
          <input type="text" class="form-control" id="medium" name="medium" value="{{ old('medium', $listing->medium ?? '') }}" maxlength="255">
        </div>
        <div class="col-md-4">
          <label for="dimensions" class="form-label">{{ __('Dimensions') }}</label>
          <input type="text" class="form-control" id="dimensions" name="dimensions" value="{{ old('dimensions', $listing->dimensions ?? '') }}" maxlength="255">
        </div>
        <div class="col-md-4">
          <label for="weight_kg" class="form-label">{{ __('Weight (kg)') }}</label>
          <input type="number" class="form-control" id="weight_kg" name="weight_kg" value="{{ old('weight_kg', $listing->weight_kg ?? '') }}" min="0" step="0.01">
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Artwork Details') }}</div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-4">
          <label for="year_created" class="form-label">{{ __('Year Created') }}</label>
          <input type="text" class="form-control" id="year_created" name="year_created" value="{{ old('year_created', $listing->year_created ?? '') }}" maxlength="50">
        </div>
        <div class="col-md-4">
          <label for="artist_name" class="form-label">{{ __('Artist Name') }}</label>
          <input type="text" class="form-control" id="artist_name" name="artist_name" value="{{ old('artist_name', $listing->artist_name ?? '') }}" maxlength="255">
        </div>
        <div class="col-md-4">
          <label for="edition_info" class="form-label">{{ __('Edition Info') }}</label>
          <input type="text" class="form-control" id="edition_info" name="edition_info" value="{{ old('edition_info', $listing->edition_info ?? '') }}" maxlength="255">
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="is_signed" name="is_signed" value="1" {{ old('is_signed', $listing->is_signed ?? 0) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_signed">{{ __('Is Signed') }}</label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="is_framed" name="is_framed" value="1" {{ old('is_framed', $listing->is_framed ?? 0) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_framed">{{ __('Is Framed') }}</label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="certificate_of_authenticity" name="certificate_of_authenticity" value="1" {{ old('certificate_of_authenticity', $listing->certificate_of_authenticity ?? 0) ? 'checked' : '' }}>
            <label class="form-check-label" for="certificate_of_authenticity">{{ __('Certificate of Authenticity') }}</label>
          </div>
        </div>
      </div>
      <div class="mb-3" id="frame-description-group" style="{{ ($listing->is_framed ?? 0) ? '' : 'display:none;' }}">
        <label for="frame_description" class="form-label">{{ __('Frame Description') }}</label>
        <input type="text" class="form-control" id="frame_description" name="frame_description" value="{{ old('frame_description', $listing->frame_description ?? '') }}" maxlength="255">
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Pricing') }}</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">{{ __('Listing Type') }} <span class="text-danger">*</span></label>
        <div>
          @foreach($listingTypes as $val => $label)
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="listing_type" id="type-{{ $val }}" value="{{ $val }}" {{ old('listing_type', $listing->listing_type ?? 'fixed_price') === $val ? 'checked' : '' }} required>
              <label class="form-check-label" for="type-{{ $val }}">{{ $label }}</label>
            </div>
          @endforeach
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-4">
          <label for="currency" class="form-label">{{ __('Currency') }}</label>
          <select class="form-select" id="currency" name="currency">
            @foreach($currencies ?? [] as $cur)
              <option value="{{ $cur->code }}" {{ old('currency', $listing->currency ?? config('heratio.base_currency', 'ZAR')) === $cur->code ? 'selected' : '' }}>{{ $cur->code }} ({{ $cur->symbol ?? '' }})</option>
            @endforeach
          </select>
        </div>
      </div>

      <div id="fixed-price-fields">
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="price" class="form-label">{{ __('Price') }}</label>
            <input type="number" class="form-control" id="price" name="price" value="{{ old('price', $listing->price ?? '') }}" min="0" step="0.01">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="price_on_request" name="price_on_request" value="1" {{ old('price_on_request', $listing->price_on_request ?? 0) ? 'checked' : '' }}>
              <label class="form-check-label" for="price_on_request">{{ __('Price on Request') }}</label>
            </div>
          </div>
          <div class="col-md-4">
            <label for="minimum_offer" class="form-label">{{ __('Minimum Offer') }}</label>
            <input type="number" class="form-control" id="minimum_offer" name="minimum_offer" value="{{ old('minimum_offer', $listing->minimum_offer ?? '') }}" min="0" step="0.01">
          </div>
        </div>
      </div>

      <div id="auction-fields" style="{{ ($listing->listing_type ?? '') === 'auction' ? '' : 'display:none;' }}">
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="starting_bid" class="form-label">{{ __('Starting Bid') }}</label>
            <input type="number" class="form-control" id="starting_bid" name="starting_bid" value="{{ old('starting_bid', $listing->auction_start_price ?? '') }}" min="0" step="0.01">
          </div>
          <div class="col-md-4">
            <label for="reserve_price" class="form-label">{{ __('Reserve Price') }}</label>
            <input type="number" class="form-control" id="reserve_price" name="reserve_price" value="{{ old('reserve_price', $listing->auction_reserve_price ?? '') }}" min="0" step="0.01">
          </div>
          <div class="col-md-4">
            <label for="buy_now_price" class="form-label">{{ __('Buy Now Price') }}</label>
            <input type="number" class="form-control" id="buy_now_price" name="buy_now_price" value="{{ old('buy_now_price', $listing->auction_buy_now_price ?? '') }}" min="0" step="0.01">
          </div>
        </div>
      </div>

      <div id="offer-only-fields" style="{{ ($listing->listing_type ?? '') === 'offer_only' ? '' : 'display:none;' }}">
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="minimum_offer_only" class="form-label">{{ __('Minimum Offer') }}</label>
            <input type="number" class="form-control" id="minimum_offer_only" name="minimum_offer_for_offer_only" value="{{ old('minimum_offer_for_offer_only', $listing->minimum_offer ?? '') }}" min="0" step="0.01">
          </div>
        </div>
      </div>

      {{-- Licence-template fields — visible only when listing_type=licence --}}
      <div class="card mb-3 border-warning" id="licence-terms-card"
           style="{{ ($listing->listing_type ?? '') === 'licence' ? '' : 'display:none;' }}">
        <div class="card-header bg-warning bg-opacity-10 fw-semibold">
          <i class="fas fa-file-contract me-1 text-warning"></i> {{ __('Licence terms') }}
          <span class="small text-muted ms-1">{{ __('— template applied to new agreements; existing buyer agreements keep the terms they were issued under') }}</span>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">{{ __('Licence type') }}</label>
              <select name="licence_template_type" class="form-select">
                @php $lct = old('licence_template_type', $listing->licence_template_type ?? 'standard'); @endphp
                @foreach($licenceTypes ?? [] as $val => $label)
                  <option value="{{ $val }}" {{ $lct === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Duration (days)') }}</label>
              <input type="number" min="1" name="licence_template_duration_days" class="form-control"
                     value="{{ old('licence_template_duration_days', $listing->licence_template_duration_days ?? '') }}"
                     placeholder="{{ __('Leave blank for perpetual') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Territory') }}</label>
              <input type="text" name="licence_template_territory" class="form-control"
                     value="{{ old('licence_template_territory', $listing->licence_template_territory ?? 'Worldwide') }}" maxlength="100">
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Exclusivity') }}</label>
              <select name="licence_template_exclusivity" class="form-select">
                @php $lex = old('licence_template_exclusivity', $listing->licence_template_exclusivity ?? 'non-exclusive'); @endphp
                @foreach(['non-exclusive' => __('Non-exclusive'), 'exclusive' => __('Exclusive')] as $val => $label)
                  <option value="{{ $val }}" {{ $lex === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Max copies / impressions') }}</label>
              <input type="number" min="1" name="licence_template_max_copies" class="form-control"
                     value="{{ old('licence_template_max_copies', $listing->licence_template_max_copies ?? '') }}"
                     placeholder="{{ __('Unlimited if blank') }}">
            </div>
            <div class="col-md-12">
              <label class="form-label">{{ __('Scope of grant') }}</label>
              <textarea name="licence_template_scope" class="form-control" rows="3">{{ old('licence_template_scope', $listing->licence_template_scope ?? '') }}</textarea>
            </div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-md-4">
              <div class="form-check">
                <input type="hidden" name="licence_template_attribution_required" value="0">
                <input type="checkbox" class="form-check-input" id="lic-attr-edit" name="licence_template_attribution_required" value="1"
                       {{ old('licence_template_attribution_required', $listing->licence_template_attribution_required ?? 1) ? 'checked' : '' }}>
                <label class="form-check-label" for="lic-attr-edit">{{ __('Attribution required') }}</label>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-check">
                <input type="hidden" name="licence_template_modifications_allowed" value="0">
                <input type="checkbox" class="form-check-input" id="lic-mods-edit" name="licence_template_modifications_allowed" value="1"
                       {{ old('licence_template_modifications_allowed', $listing->licence_template_modifications_allowed ?? 0) ? 'checked' : '' }}>
                <label class="form-check-label" for="lic-mods-edit">{{ __('Modifications allowed') }}</label>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-check">
                <input type="hidden" name="licence_template_sublicensing_allowed" value="0">
                <input type="checkbox" class="form-check-input" id="lic-sublic-edit" name="licence_template_sublicensing_allowed" value="1"
                       {{ old('licence_template_sublicensing_allowed', $listing->licence_template_sublicensing_allowed ?? 0) ? 'checked' : '' }}>
                <label class="form-check-label" for="lic-sublic-edit">{{ __('Sub-licensing allowed') }}</label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Condition') }}</div>
    <div class="card-body">
      <div class="mb-3">
        <label for="condition_rating" class="form-label">{{ __('Condition Rating') }}</label>
        <select class="form-select" id="condition_rating" name="condition_rating">
          <option value="">{{ __('-- Select --') }}</option>
          @foreach($conditions as $val => $label)
            <option value="{{ $val }}" {{ old('condition_rating', $listing->condition_rating ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="mb-3">
        <label for="condition_description" class="form-label">{{ __('Condition Description') }}</label>
        <textarea class="form-control" id="condition_description" name="condition_description" rows="3">{{ old('condition_description', $listing->condition_description ?? '') }}</textarea>
      </div>
      <div class="mb-3">
        <label for="provenance" class="form-label">{{ __('Provenance') }}</label>
        <textarea class="form-control" id="provenance" name="provenance" rows="3">{{ old('provenance', $listing->provenance ?? '') }}</textarea>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Shipping') }}</div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="requires_shipping" name="requires_shipping" value="1" {{ old('requires_shipping', $listing->requires_shipping ?? 1) ? 'checked' : '' }}>
            <label class="form-check-label" for="requires_shipping">{{ __('Requires Shipping') }}</label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="free_shipping_domestic" name="free_shipping_domestic" value="1" {{ old('free_shipping_domestic', $listing->free_shipping_domestic ?? 0) ? 'checked' : '' }}>
            <label class="form-check-label" for="free_shipping_domestic">{{ __('Free Domestic Shipping') }}</label>
          </div>
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-6">
          <label for="shipping_from_country" class="form-label">{{ __('Shipping From (Country)') }}</label>
          <input type="text" class="form-control" id="shipping_from_country" name="shipping_from_country" value="{{ old('shipping_from_country', $listing->shipping_from_country ?? '') }}" maxlength="100">
        </div>
        <div class="col-md-6">
          <label for="shipping_from_city" class="form-label">{{ __('Shipping From (City)') }}</label>
          <input type="text" class="form-control" id="shipping_from_city" name="shipping_from_city" value="{{ old('shipping_from_city', $listing->shipping_from_city ?? '') }}" maxlength="100">
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-4">
          <label for="shipping_domestic_price" class="form-label">{{ __('Domestic Shipping Price') }}</label>
          <input type="number" class="form-control" id="shipping_domestic_price" name="shipping_domestic_price" value="{{ old('shipping_domestic_price', $listing->shipping_domestic_price ?? '') }}" min="0" step="0.01">
        </div>
        <div class="col-md-4">
          <label for="shipping_international_price" class="form-label">{{ __('International Shipping Price') }}</label>
          <input type="number" class="form-control" id="shipping_international_price" name="shipping_international_price" value="{{ old('shipping_international_price', $listing->shipping_international_price ?? '') }}" min="0" step="0.01">
        </div>
        <div class="col-md-4">
          <label for="insurance_value" class="form-label">{{ __('Insurance Value') }}</label>
          <input type="number" class="form-control" id="insurance_value" name="insurance_value" value="{{ old('insurance_value', $listing->insurance_value ?? '') }}" min="0" step="0.01">
        </div>
      </div>
      <div class="mb-3">
        <label for="shipping_notes" class="form-label">{{ __('Shipping Notes') }}</label>
        <textarea class="form-control" id="shipping_notes" name="shipping_notes" rows="2">{{ old('shipping_notes', $listing->shipping_notes ?? '') }}</textarea>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Tags') }}</div>
    <div class="card-body">
      <label for="tags" class="form-label">{{ __('Tags') }}</label>
      <input type="text" class="form-control" id="tags" name="tags" value="{{ old('tags', $listing->tags ?? '') }}" placeholder="{{ __('Comma-separated') }}">
    </div>
  </div>

  <div class="d-flex justify-content-between">
    <a href="{{ route('ahgmarketplace.seller-listings') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Listings') }}
    </a>
    <button type="submit" class="btn btn-primary btn-lg">
      <i class="fas fa-save me-1"></i> {{ __('Save Changes') }}
    </button>
  </div>
</form>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  var sectorSelect = document.getElementById('sector');
  var categorySelect = document.getElementById('category_id');
  var typeRadios = document.querySelectorAll('input[name="listing_type"]');
  var fixedFields = document.getElementById('fixed-price-fields');
  var auctionFields = document.getElementById('auction-fields');
  var offerFields = document.getElementById('offer-only-fields');
  var framedCheckbox = document.getElementById('is_framed');
  var frameGroup = document.getElementById('frame-description-group');

  if (sectorSelect && categorySelect) {
    var allOptions = Array.from(categorySelect.querySelectorAll('option[data-sector]'));
    sectorSelect.addEventListener('change', function() {
      var selected = this.value;
      allOptions.forEach(function(opt) {
        opt.style.display = (selected === '' || opt.getAttribute('data-sector') === selected) ? '' : 'none';
      });
    });
    sectorSelect.dispatchEvent(new Event('change'));
  }

  var licenceFields = document.getElementById('licence-terms-card');
  function togglePricingFields() {
    var checked = document.querySelector('input[name="listing_type"]:checked');
    var val = checked ? checked.value : 'fixed_price';
    fixedFields.style.display = (val === 'fixed_price' || val === 'licence') ? '' : 'none';
    auctionFields.style.display = (val === 'auction') ? '' : 'none';
    offerFields.style.display = (val === 'offer_only') ? '' : 'none';
    if (licenceFields) {
      licenceFields.style.display = (val === 'licence') ? '' : 'none';
    }
  }
  typeRadios.forEach(function(r) { r.addEventListener('change', togglePricingFields); });
  togglePricingFields();

  if (framedCheckbox && frameGroup) {
    framedCheckbox.addEventListener('change', function() {
      frameGroup.style.display = this.checked ? '' : 'none';
    });
  }
});
</script>
@endpush
@endsection
