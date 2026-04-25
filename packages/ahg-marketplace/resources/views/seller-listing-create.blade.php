{{--
  Marketplace — Create New Listing (seller form)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/sellerListingCreateSuccess.php.
  Currency default falls back to config('heratio.base_currency') not hardcoded ZAR.
--}}
@extends('theme::layouts.1col')
@section('title', __('Create New Listing') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace seller-listing-create')

@php
  $baseCurrency = config('heratio.base_currency', 'ZAR');
  $pf = $prefill ?? null;
  $sectorList = ['gallery' => __('Gallery'), 'museum' => __('Museum'), 'archive' => __('Archive'), 'library' => __('Library'), 'dam' => __('Digital Asset Management')];
  $conditions = ['mint' => __('Mint'), 'excellent' => __('Excellent'), 'good' => __('Good'), 'fair' => __('Fair'), 'poor' => __('Poor')];
  $listingTypes = ['fixed_price' => __('Fixed Price'), 'auction' => __('Auction'), 'offer_only' => __('Offer Only')];
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.seller-listings') }}">{{ __('My Listings') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Create Listing') }}</li>
  </ol>
</nav>

@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<h1 class="h3 mb-4">{{ __('Create New Listing') }}</h1>

@if($pf)
  <div class="alert alert-info">
    <i class="fas fa-link me-1"></i>
    {{ __('Creating listing from archival record:') }} <strong>{{ $pf->title ?? '' }}</strong>
    <a href="{{ url('/' . ($pf->slug ?? '')) }}" class="ms-2" target="_blank"><i class="fas fa-external-link-alt"></i> {{ __('View Record') }}</a>
  </div>
@endif

<form method="POST" action="{{ route('ahgmarketplace.seller-listing-create.post') }}" id="listing-form">
  @csrf
  <input type="hidden" name="information_object_id" id="information_object_id" value="{{ $pf->information_object_id ?? '' }}">

  {{-- Link to Archive Record --}}
  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Link to Archive Record') }} <small class="text-muted fw-normal">({{ __('Optional') }})</small></div>
    <div class="card-body">
      <div class="mb-0 position-relative">
        <label for="io_search" class="form-label">{{ __('Search by title') }}</label>
        <input type="text" class="form-control" id="io_search" autocomplete="off" placeholder="{{ __('Start typing to search archival records...') }}" value="{{ $pf->title ?? '' }}">
        <div id="io_results" class="list-group position-absolute w-100 shadow-sm" style="z-index:1050;max-height:250px;overflow-y:auto;display:none;"></div>
        <div class="form-text">{{ __('Search and select an existing record to auto-fill title, description, and metadata.') }}</div>
        <div class="mt-2" id="io_linked" style="{{ $pf ? '' : 'display:none;' }}">
          <span class="badge bg-info" id="io_linked_label">@if($pf)<i class="fas fa-link me-1"></i>{{ $pf->title }}@endif</span>
          <button type="button" class="btn btn-sm btn-link text-danger" id="io_unlink"><i class="fas fa-times"></i> {{ __('Unlink') }}</button>
        </div>
      </div>
    </div>
  </div>

  {{-- Basic Info --}}
  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Basic Information') }}</div>
    <div class="card-body">
      <div class="mb-3">
        <label for="title" class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $pf->title ?? '') }}" required maxlength="500">
      </div>
      <div class="mb-3">
        <label for="short_description" class="form-label">{{ __('Short Description') }}</label>
        <input type="text" class="form-control" id="short_description" name="short_description" value="{{ old('short_description') }}" maxlength="1000">
        <div class="form-text">{{ __('Brief summary shown in listing cards.') }}</div>
      </div>
      <div class="mb-3">
        <label for="description" class="form-label">{{ __('Full Description') }}</label>
        <textarea class="form-control" id="description" name="description" rows="5">{{ old('description', $pf->description ?? '') }}</textarea>
      </div>
      <div class="row mb-3">
        <div class="col-md-6">
          <label for="sector" class="form-label">{{ __('Sector') }} <span class="text-danger">*</span></label>
          <select class="form-select" id="sector" name="sector" required>
            <option value="">{{ __('-- Select Sector --') }}</option>
            @foreach($sectorList as $val => $label)
              <option value="{{ $val }}" {{ old('sector', $pf->sector ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label for="category_id" class="form-label">{{ __('Category') }}</label>
          <select class="form-select" id="category_id" name="category_id">
            <option value="">{{ __('-- Select Category --') }}</option>
            @foreach($categories ?? [] as $cat)
              <option value="{{ (int) $cat->id }}" data-sector="{{ $cat->sector ?? '' }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                {{ $cat->name ?? '' }} ({{ ucfirst($cat->sector ?? '') }})
              </option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-4">
          <label for="medium" class="form-label">{{ __('Medium') }}</label>
          <input type="text" class="form-control" id="medium" name="medium" value="{{ old('medium') }}" maxlength="255">
        </div>
        <div class="col-md-4">
          <label for="dimensions" class="form-label">{{ __('Dimensions') }}</label>
          <input type="text" class="form-control" id="dimensions" name="dimensions" value="{{ old('dimensions') }}" placeholder="{{ __('e.g. 60 x 80 cm') }}" maxlength="255">
        </div>
        <div class="col-md-4">
          <label for="weight_kg" class="form-label">{{ __('Weight (kg)') }}</label>
          <input type="number" class="form-control" id="weight_kg" name="weight_kg" value="{{ old('weight_kg') }}" min="0" step="0.01">
        </div>
      </div>
    </div>
  </div>

  {{-- Artwork Details --}}
  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Artwork Details') }}</div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-4">
          <label for="year_created" class="form-label">{{ __('Year Created') }}</label>
          <input type="text" class="form-control" id="year_created" name="year_created" value="{{ old('year_created') }}" maxlength="50">
        </div>
        <div class="col-md-4">
          <label for="artist_name" class="form-label">{{ __('Artist Name') }}</label>
          <input type="text" class="form-control" id="artist_name" name="artist_name" value="{{ old('artist_name') }}" maxlength="255">
        </div>
        <div class="col-md-4">
          <label for="edition_info" class="form-label">{{ __('Edition Info') }}</label>
          <input type="text" class="form-control" id="edition_info" name="edition_info" value="{{ old('edition_info') }}" placeholder="{{ __('e.g. 1/50') }}" maxlength="255">
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="is_signed" name="is_signed" value="1" {{ old('is_signed') ? 'checked' : '' }}>
            <label class="form-check-label" for="is_signed">{{ __('Is Signed') }}</label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="is_framed" name="is_framed" value="1" {{ old('is_framed') ? 'checked' : '' }}>
            <label class="form-check-label" for="is_framed">{{ __('Is Framed') }}</label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="certificate_of_authenticity" name="certificate_of_authenticity" value="1" {{ old('certificate_of_authenticity') ? 'checked' : '' }}>
            <label class="form-check-label" for="certificate_of_authenticity">{{ __('Certificate of Authenticity') }}</label>
          </div>
        </div>
      </div>
      <div class="mb-3" id="frame-description-group" style="display:none;">
        <label for="frame_description" class="form-label">{{ __('Frame Description') }}</label>
        <input type="text" class="form-control" id="frame_description" name="frame_description" value="{{ old('frame_description') }}" maxlength="255">
      </div>
    </div>
  </div>

  {{-- Broker / Artist + markup (only when seller represents artists) --}}
  @if(isset($brokerArtists) && $brokerArtists->isNotEmpty())
    <div class="card mb-4 border-info">
      <div class="card-header fw-semibold bg-info bg-opacity-10">
        <i class="fas fa-palette me-1 text-info"></i> {{ __('Broker / Artist') }}
        <span class="small text-muted ms-1">{{ __('— select an artist from your roster to apply markup pricing') }}</span>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="artist_id" class="form-label">{{ __('Artist') }}</label>
            <select id="artist_id" name="artist_id" class="form-select">
              <option value="">{{ __('— Not on behalf of an artist (sell as yourself)') }}</option>
              @foreach($brokerArtists as $bA)
                <option value="{{ $bA->id }}"
                        data-markup-type="{{ $bA->default_markup_type }}"
                        data-markup-value="{{ $bA->default_markup_value }}"
                        {{ old('artist_id') == $bA->id ? 'selected' : '' }}>
                  {{ $bA->display_name }}{{ $bA->nationality ? ' (' . $bA->nationality . ')' : '' }}
                </option>
              @endforeach
            </select>
            <small class="text-muted">{{ __('Manage your roster on the') }}
              <a href="{{ route('ahgmarketplace.seller-artists') }}">{{ __('Artists page') }}</a>.
            </small>
          </div>
          <div class="col-md-3">
            <label for="artist_base_price" class="form-label">{{ __('Artist base price') }}</label>
            <input type="number" step="0.01" min="0" class="form-control"
                   id="artist_base_price" name="artist_base_price"
                   value="{{ old('artist_base_price') }}"
                   placeholder="{{ __('e.g. 5000.00') }}">
            <small class="text-muted">{{ __('What the artist receives.') }}</small>
          </div>
          <div class="col-md-2">
            <label for="markup_type" class="form-label">{{ __('Markup') }}</label>
            <select id="markup_type" name="markup_type" class="form-select">
              <option value="percentage" {{ old('markup_type', 'percentage') === 'percentage' ? 'selected' : '' }}>%</option>
              <option value="fixed"      {{ old('markup_type') === 'fixed' ? 'selected' : '' }}>Fixed</option>
              <option value="none"       {{ old('markup_type') === 'none' ? 'selected' : '' }}>None</option>
            </select>
          </div>
          <div class="col-md-1">
            <label for="markup_value" class="form-label">{{ __('Value') }}</label>
            <input type="number" step="0.01" min="0" class="form-control"
                   id="markup_value" name="markup_value"
                   value="{{ old('markup_value') }}">
          </div>
        </div>
        <div class="mt-2 small">
          <span class="text-muted">{{ __('Computed listing price:') }}</span>
          <strong id="markup-preview" class="text-info">—</strong>
          <span class="text-muted">{{ __('(overrides the manual price below)') }}</span>
        </div>
      </div>
    </div>

    <script>
    (function () {
      var sel = document.getElementById('artist_id');
      var base = document.getElementById('artist_base_price');
      var type = document.getElementById('markup_type');
      var val  = document.getElementById('markup_value');
      var preview = document.getElementById('markup-preview');
      if (!sel) return;

      function applyArtistDefaults() {
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) return;
        if (!val.value) { val.value = opt.getAttribute('data-markup-value') || ''; }
        if (type.value === 'percentage') {
          var defType = opt.getAttribute('data-markup-type');
          if (defType) { type.value = defType; }
        }
      }
      function recompute() {
        var b = parseFloat(base.value || '0');
        if (!b) { preview.textContent = '—'; return; }
        var v = parseFloat(val.value || '0');
        var t = type.value;
        var p = b;
        if (t === 'percentage') p = b * (1 + v / 100);
        else if (t === 'fixed') p = b + v;
        preview.textContent = p.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
      sel.addEventListener('change', function () { applyArtistDefaults(); recompute(); });
      [base, type, val].forEach(function (el) { el.addEventListener('input', recompute); });
      recompute();
    })();
    </script>
  @endif

  {{-- Pricing --}}
  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Pricing') }}</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">{{ __('Listing Type') }} <span class="text-danger">*</span></label>
        <div>
          @foreach($listingTypes as $val => $label)
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="listing_type" id="type-{{ $val }}" value="{{ $val }}" {{ old('listing_type', 'fixed_price') === $val ? 'checked' : '' }} required>
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
              <option value="{{ $cur->code }}" {{ old('currency', $baseCurrency) === $cur->code ? 'selected' : '' }}>{{ $cur->code }} ({{ $cur->symbol ?? '' }})</option>
            @endforeach
          </select>
        </div>
      </div>

      <div id="fixed-price-fields">
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="price" class="form-label">{{ __('Price') }}</label>
            <input type="number" class="form-control" id="price" name="price" value="{{ old('price') }}" min="0" step="0.01">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="price_on_request" name="price_on_request" value="1" {{ old('price_on_request') ? 'checked' : '' }}>
              <label class="form-check-label" for="price_on_request">{{ __('Price on Request') }}</label>
            </div>
          </div>
          <div class="col-md-4">
            <label for="minimum_offer" class="form-label">{{ __('Minimum Offer') }}</label>
            <input type="number" class="form-control" id="minimum_offer" name="minimum_offer" value="{{ old('minimum_offer') }}" min="0" step="0.01">
            <div class="form-text">{{ __('Leave blank to disable offers.') }}</div>
          </div>
        </div>
      </div>

      <div id="auction-fields" style="display:none;">
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="starting_bid" class="form-label">{{ __('Starting Bid') }}</label>
            <input type="number" class="form-control" id="starting_bid" name="starting_bid" value="{{ old('starting_bid') }}" min="0" step="0.01">
          </div>
          <div class="col-md-4">
            <label for="reserve_price" class="form-label">{{ __('Reserve Price') }}</label>
            <input type="number" class="form-control" id="reserve_price" name="reserve_price" value="{{ old('reserve_price') }}" min="0" step="0.01">
            <div class="form-text">{{ __('Optional. Item will not sell below this price.') }}</div>
          </div>
          <div class="col-md-4">
            <label for="buy_now_price" class="form-label">{{ __('Buy Now Price') }}</label>
            <input type="number" class="form-control" id="buy_now_price" name="buy_now_price" value="{{ old('buy_now_price') }}" min="0" step="0.01">
            <div class="form-text">{{ __('Optional. Allow immediate purchase at this price.') }}</div>
          </div>
        </div>
      </div>

      <div id="offer-only-fields" style="display:none;">
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="minimum_offer_only" class="form-label">{{ __('Minimum Offer') }}</label>
            <input type="number" class="form-control" id="minimum_offer_only" name="minimum_offer_for_offer_only" value="{{ old('minimum_offer_for_offer_only') }}" min="0" step="0.01">
            <div class="form-text">{{ __('Optional minimum offer amount.') }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Condition --}}
  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Condition') }}</div>
    <div class="card-body">
      <div class="mb-3">
        <label for="condition_rating" class="form-label">{{ __('Condition Rating') }}</label>
        <select class="form-select" id="condition_rating" name="condition_rating">
          <option value="">{{ __('-- Select --') }}</option>
          @foreach($conditions as $val => $label)
            <option value="{{ $val }}" {{ old('condition_rating') === $val ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="mb-3">
        <label for="condition_description" class="form-label">{{ __('Condition Description') }}</label>
        <textarea class="form-control" id="condition_description" name="condition_description" rows="3">{{ old('condition_description') }}</textarea>
      </div>
      <div class="mb-3">
        <label for="provenance" class="form-label">{{ __('Provenance') }}</label>
        <textarea class="form-control" id="provenance" name="provenance" rows="3" placeholder="{{ __('History of ownership...') }}">{{ old('provenance', $pf->provenance ?? '') }}</textarea>
      </div>
    </div>
  </div>

  {{-- Shipping --}}
  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Shipping') }}</div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="is_physical" name="is_physical" value="1" {{ old('is_physical', '1') ? 'checked' : '' }}>
            <label class="form-check-label" for="is_physical">{{ __('Physical Item') }}</label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="is_digital" name="is_digital" value="1" {{ old('is_digital') ? 'checked' : '' }}>
            <label class="form-check-label" for="is_digital">{{ __('Digital Item') }}</label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="requires_shipping" name="requires_shipping" value="1" {{ old('requires_shipping', '1') ? 'checked' : '' }}>
            <label class="form-check-label" for="requires_shipping">{{ __('Requires Shipping') }}</label>
          </div>
        </div>
      </div>

      <div id="shipping-details">
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="shipping_from_country" class="form-label">{{ __('Shipping From (Country)') }}</label>
            <input type="text" class="form-control" id="shipping_from_country" name="shipping_from_country" value="{{ old('shipping_from_country') }}" maxlength="100">
          </div>
          <div class="col-md-6">
            <label for="shipping_from_city" class="form-label">{{ __('Shipping From (City)') }}</label>
            <input type="text" class="form-control" id="shipping_from_city" name="shipping_from_city" value="{{ old('shipping_from_city') }}" maxlength="100">
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="shipping_domestic_price" class="form-label">{{ __('Domestic Shipping Price') }}</label>
            <input type="number" class="form-control" id="shipping_domestic_price" name="shipping_domestic_price" value="{{ old('shipping_domestic_price') }}" min="0" step="0.01">
          </div>
          <div class="col-md-4">
            <label for="shipping_international_price" class="form-label">{{ __('International Shipping Price') }}</label>
            <input type="number" class="form-control" id="shipping_international_price" name="shipping_international_price" value="{{ old('shipping_international_price') }}" min="0" step="0.01">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="free_shipping_domestic" name="free_shipping_domestic" value="1" {{ old('free_shipping_domestic') ? 'checked' : '' }}>
              <label class="form-check-label" for="free_shipping_domestic">{{ __('Free Domestic Shipping') }}</label>
            </div>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="insurance_value" class="form-label">{{ __('Insurance Value') }}</label>
            <input type="number" class="form-control" id="insurance_value" name="insurance_value" value="{{ old('insurance_value') }}" min="0" step="0.01">
          </div>
        </div>
        <div class="mb-3">
          <label for="shipping_notes" class="form-label">{{ __('Shipping Notes') }}</label>
          <textarea class="form-control" id="shipping_notes" name="shipping_notes" rows="2">{{ old('shipping_notes') }}</textarea>
        </div>
      </div>
    </div>
  </div>

  {{-- Tags --}}
  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Tags') }}</div>
    <div class="card-body">
      <label for="tags" class="form-label">{{ __('Tags') }}</label>
      <input type="text" class="form-control" id="tags" name="tags" value="{{ old('tags') }}" placeholder="{{ __('Comma-separated, e.g. oil painting, landscape, contemporary') }}">
      <div class="form-text">{{ __('Separate tags with commas.') }}</div>
    </div>
  </div>

  <div class="d-flex justify-content-between">
    <a href="{{ route('ahgmarketplace.seller-listings') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Listings') }}
    </a>
    <button type="submit" class="btn btn-primary btn-lg">
      <i class="fas fa-save me-1"></i> {{ __('Save as Draft') }}
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

  // Filter categories by sector
  if (sectorSelect && categorySelect) {
    var allOptions = Array.from(categorySelect.querySelectorAll('option[data-sector]'));
    sectorSelect.addEventListener('change', function() {
      var selected = this.value;
      categorySelect.value = '';
      allOptions.forEach(function(opt) {
        opt.style.display = (selected === '' || opt.getAttribute('data-sector') === selected) ? '' : 'none';
      });
    });
    sectorSelect.dispatchEvent(new Event('change'));
  }

  function togglePricingFields() {
    var checked = document.querySelector('input[name="listing_type"]:checked');
    var val = checked ? checked.value : 'fixed_price';
    fixedFields.style.display = (val === 'fixed_price') ? '' : 'none';
    auctionFields.style.display = (val === 'auction') ? '' : 'none';
    offerFields.style.display = (val === 'offer_only') ? '' : 'none';
  }
  typeRadios.forEach(function(radio) { radio.addEventListener('change', togglePricingFields); });
  togglePricingFields();

  if (framedCheckbox && frameGroup) {
    framedCheckbox.addEventListener('change', function() {
      frameGroup.style.display = this.checked ? '' : 'none';
    });
    frameGroup.style.display = framedCheckbox.checked ? '' : 'none';
  }

  // Archive record autocomplete
  var ioSearch = document.getElementById('io_search');
  var ioResults = document.getElementById('io_results');
  var ioHidden = document.getElementById('information_object_id');
  var ioLinked = document.getElementById('io_linked');
  var ioLabel = document.getElementById('io_linked_label');
  var ioUnlink = document.getElementById('io_unlink');
  var searchTimer = null;

  if (ioSearch) {
    ioSearch.addEventListener('input', function() {
      clearTimeout(searchTimer);
      var q = this.value.trim();
      if (q.length < 2) { ioResults.style.display = 'none'; return; }
      searchTimer = setTimeout(function() {
        fetch('/informationobject/autocomplete?query=' + encodeURIComponent(q) + '&limit=10')
          .then(function(r) { return r.json(); })
          .then(function(data) {
            ioResults.innerHTML = '';
            var items = data.results || data;
            if (!items.length) {
              ioResults.innerHTML = '<div class="list-group-item text-muted small">No records found</div>';
              ioResults.style.display = '';
              return;
            }
            items.forEach(function(item) {
              var a = document.createElement('a');
              a.href = '#';
              a.className = 'list-group-item list-group-item-action small';
              a.textContent = item.title || item.label || item.name || 'Untitled';
              a.dataset.id = item.id || item.object_id || '';
              a.dataset.title = item.title || item.label || item.name || '';
              a.addEventListener('click', function(e) {
                e.preventDefault();
                ioHidden.value = this.dataset.id;
                ioSearch.value = this.dataset.title;
                ioResults.style.display = 'none';
                var titleField = document.getElementById('title');
                if (titleField && !titleField.value) titleField.value = this.dataset.title;
                if (ioLabel) ioLabel.innerHTML = '<i class="fas fa-link me-1"></i>' + this.dataset.title;
                if (ioLinked) ioLinked.style.display = '';
              });
              ioResults.appendChild(a);
            });
            ioResults.style.display = '';
          })
          .catch(function() { ioResults.style.display = 'none'; });
      }, 300);
    });
    ioSearch.addEventListener('blur', function() { setTimeout(function() { ioResults.style.display = 'none'; }, 200); });
  }

  if (ioUnlink) {
    ioUnlink.addEventListener('click', function() {
      ioHidden.value = '';
      ioSearch.value = '';
      if (ioLinked) ioLinked.style.display = 'none';
    });
  }
});
</script>
@endpush
@endsection
