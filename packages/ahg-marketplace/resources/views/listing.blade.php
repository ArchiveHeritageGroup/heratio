{{--
  Marketplace — Public Listing Detail

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/listingSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', ($listing->title ?? 'Listing') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace listing-detail')

@php
  $sectorLabel = ucfirst($listing->sector ?? '');
  $categoryLabel = $listing->category_name ?? '';
  $primaryImage = null;
  $thumbs = [];
  if (!empty($images)) {
    foreach ($images as $img) {
      if (!empty($img->is_primary)) { $primaryImage = $img; }
      $thumbs[] = $img;
    }
    if (!$primaryImage && count($thumbs) > 0) { $primaryImage = $thumbs[0]; }
  }
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.sector', ['sector' => $listing->sector ?? '']) }}">{{ $sectorLabel }}</a></li>
    @if($categoryLabel)
      <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.category', ['sector' => $listing->sector ?? '', 'slug' => $listing->category_slug ?? '']) }}">{{ $categoryLabel }}</a></li>
    @endif
    <li class="breadcrumb-item active">{{ $listing->title ?? '' }}</li>
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
  {{-- Image gallery --}}
  <div class="col-md-7 mb-4">
    <div class="position-relative mb-3">
      <div class="border rounded overflow-hidden bg-light text-center" style="min-height:400px;">
        @if($primaryImage)
          <img src="{{ $primaryImage->file_path }}" alt="{{ $listing->title ?? '' }}" class="img-fluid" id="main-image" style="max-height:500px;object-fit:contain;">
        @elseif(!empty($listing->featured_image_path))
          <img src="{{ $listing->featured_image_path }}" alt="{{ $listing->title ?? '' }}" class="img-fluid" id="main-image" style="max-height:500px;object-fit:contain;">
        @else
          <div class="d-flex align-items-center justify-content-center h-100 py-5">
            <i class="fas fa-image fa-5x text-muted"></i>
          </div>
        @endif
      </div>

      @auth
        <button type="button" class="btn btn-light position-absolute top-0 end-0 m-2 rounded-circle shadow-sm" id="btn-favourite" data-listing-id="{{ (int) ($listing->id ?? 0) }}" title="{{ ($isFavourited ?? false) ? __('Remove from favourites') : __('Add to favourites') }}">
          <i class="{{ ($isFavourited ?? false) ? 'fas' : 'far' }} fa-heart text-danger"></i>
        </button>
      @endauth
    </div>

    @if(count($thumbs) > 1)
      <div class="d-flex flex-wrap gap-2">
        @foreach($thumbs as $idx => $thumb)
          <div class="border rounded overflow-hidden listing-thumb" style="width:80px;height:80px;cursor:pointer;" data-src="{{ $thumb->file_path }}">
            <img src="{{ $thumb->file_path }}" alt="{{ $thumb->caption ?? __('Image :n', ['n' => $idx + 1]) }}" class="w-100 h-100" style="object-fit:cover;">
          </div>
        @endforeach
      </div>
    @endif
  </div>

  {{-- Listing details --}}
  <div class="col-md-5 mb-4">
    <h1 class="h4 mb-2">{{ $listing->title ?? '' }}</h1>

    @if(!empty($listing->artist_name))
      <p class="text-muted mb-2">
        {{ __('by :name', ['name' => $listing->artist_name]) }}
        @if(!empty($listing->artist_id))
          <span class="badge bg-info text-dark ms-1" title="Sold on behalf of this artist by the seller">
            <i class="fas fa-handshake me-1"></i>via broker
          </span>
        @endif
      </p>
    @endif

    @if(!empty($listing->listing_number))
      <p class="small text-muted mb-3">{{ __('Listing #:number', ['number' => $listing->listing_number]) }}</p>
    @endif

    {{-- Price / Bid section --}}
    <div class="card mb-3">
      <div class="card-body">
        @if(!empty($listing->price_on_request))
          <p class="h5 mb-2">{{ __('Price on Request') }}</p>
          <a href="{{ route('ahgmarketplace.enquiry-form', ['slug' => $listing->slug ?? '']) }}" class="btn btn-primary w-100">
            <i class="fas fa-envelope me-1"></i> {{ __('Enquire') }}
          </a>

        @elseif(($listing->listing_type ?? '') === 'licence')
          <p class="small text-muted mb-1">{{ __('Licence fee') }}</p>
          <p class="h4 text-primary mb-1">{{ $listing->currency ?? '' }} {{ number_format((float) ($listing->price ?? 0), 2) }}</p>
          @if($listing->licence_template_duration_days)
            <p class="small text-muted mb-3">
              <i class="fas fa-clock me-1"></i>{{ __(':n-day term', ['n' => (int) $listing->licence_template_duration_days]) }}
            </p>
          @else
            <p class="small text-muted mb-3"><i class="fas fa-infinity me-1"></i>{{ __('Perpetual licence') }}</p>
          @endif
          @auth
            <form method="POST" action="{{ route('cart.listing-add', ['listingId' => $listing->id]) }}" class="mb-2">
              @csrf
              <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-cart-plus me-1"></i> {{ __('Add licence to cart') }}
              </button>
            </form>
            @php $ecommerceEnabled = app(\AhgCart\Services\EcommerceService::class)->isEcommerceEnabled(); @endphp
            @if($ecommerceEnabled)
              <form method="POST" action="{{ route('ahgmarketplace.checkout-buy', ['listingId' => $listing->id]) }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary w-100">
                  <i class="fas fa-file-contract me-1"></i> {{ __('Buy Licence Now') }}
                </button>
              </form>
            @else
              <button type="button" class="btn btn-outline-primary w-100"
                      data-bs-toggle="modal" data-bs-target="#dummySaleModal"
                      data-dummy-title="{{ $listing->title ?? '' }} (licence)"
                      data-dummy-price="{{ (string) (float) ($listing->price ?? 0) }}"
                      data-dummy-currency="{{ $listing->currency ?: 'ZAR' }}">
                <i class="fas fa-file-contract me-1"></i> {{ __('Buy Licence Now') }}
              </button>
            @endif
          @else
            <a href="{{ route('login') }}" class="btn btn-primary w-100">
              <i class="fas fa-sign-in-alt me-1"></i> {{ __('Sign in to Licence') }}
            </a>
          @endauth

        @elseif(($listing->listing_type ?? '') === 'fixed_price')
          <p class="h4 text-primary mb-1">{{ $listing->currency ?? '' }} {{ number_format((float) ($listing->price ?? 0), 2) }}</p>
          @if(!empty($listing->condition_rating))
            <p class="small text-muted mb-3">{{ __('Condition: :c', ['c' => ucfirst($listing->condition_rating)]) }}</p>
          @endif
          @php
            $ecommerceEnabled = app(\AhgCart\Services\EcommerceService::class)->isEcommerceEnabled();
            $reservation = app(\AhgMarketplace\Services\MarketplaceService::class)->getActiveReservationForListing((int) $listing->id);
            $iAmHolder = $reservation && Auth::id() && (int) $reservation->user_id === (int) Auth::id();
            $isHeldByOther = $reservation && !$iAmHolder;
          @endphp

          @if($reservation)
            <div class="alert {{ $iAmHolder ? 'alert-success' : 'alert-warning' }} small mb-3">
              @if($iAmHolder)
                <i class="fas fa-clock me-1"></i>
                <strong>You have this reserved.</strong>
                Hold expires <span data-countdown="{{ $reservation->expires_at }}">{{ \Carbon\Carbon::parse($reservation->expires_at)->diffForHumans() }}</span>.
                Complete the purchase below to keep it.
              @else
                <i class="fas fa-lock me-1"></i>
                <strong>Reserved by another buyer</strong> &mdash; hold released
                <span data-countdown="{{ $reservation->expires_at }}">{{ \Carbon\Carbon::parse($reservation->expires_at)->diffForHumans() }}</span>.
              @endif
            </div>
          @endif

          @auth
            <form method="POST" action="{{ route('cart.listing-add', ['listingId' => $listing->id]) }}" class="mb-2">
              @csrf
              <button type="submit" class="btn btn-primary w-100" {{ $isHeldByOther ? 'disabled' : '' }}>
                <i class="fas fa-cart-plus me-1"></i> {{ __('Add to cart') }}
              </button>
            </form>

            @if($ecommerceEnabled)
              <form method="POST" action="{{ route('ahgmarketplace.checkout-buy', ['listingId' => $listing->id]) }}" class="mb-2">
                @csrf
                <button type="submit" class="btn btn-outline-primary w-100" {{ $isHeldByOther ? 'disabled' : '' }}>
                  <i class="fas fa-bolt me-1"></i> {{ __('Buy Now') }}
                </button>
              </form>
            @else
              <button type="button" class="btn btn-outline-primary w-100 mb-2"
                      {{ $isHeldByOther ? 'disabled' : '' }}
                      data-bs-toggle="modal" data-bs-target="#dummySaleModal"
                      data-dummy-title="{{ $listing->title ?? '' }}"
                      data-dummy-price="{{ (string) (float) ($listing->price ?? 0) }}"
                      data-dummy-currency="{{ $listing->currency ?: 'ZAR' }}">
                <i class="fas fa-bolt me-1"></i> {{ __('Buy Now') }}
              </button>
            @endif

            {{-- Reserve / Release --}}
            @if($iAmHolder)
              <form method="POST" action="{{ route('ahgmarketplace.reservation-cancel', ['reservationId' => $reservation->id]) }}" class="mb-2"
                    onsubmit="return confirm('Release your reservation? Other buyers will be able to purchase this item.');">
                @csrf
                <button type="submit" class="btn btn-outline-secondary w-100">
                  <i class="fas fa-unlock me-1"></i> {{ __('Release reservation') }}
                </button>
              </form>
            @elseif(!$isHeldByOther)
              <form method="POST" action="{{ route('ahgmarketplace.reserve', ['listingId' => $listing->id]) }}" class="mb-2">
                @csrf
                <button type="submit" class="btn btn-outline-warning w-100"
                        title="Hold this listing for 12 hours (max 2 per 24 hours).">
                  <i class="fas fa-clock me-1"></i> {{ __('Reserve for 12 hours') }}
                </button>
              </form>
            @endif
          @else
            <a href="{{ route('login') }}" class="btn btn-primary w-100 mb-2">
              <i class="fas fa-sign-in-alt me-1"></i> {{ __('Sign in to Buy') }}
            </a>
          @endauth
          @if(!is_null($listing->minimum_offer ?? null))
            <a href="{{ route('ahgmarketplace.offer-form', ['slug' => $listing->slug ?? '']) }}" class="btn btn-outline-primary w-100">
              <i class="fas fa-hand-holding-usd me-1"></i> {{ __('Make an Offer') }}
            </a>
          @endif

        @elseif(($listing->listing_type ?? '') === 'auction' && !empty($auction))
          <div class="mb-2">
            <span class="small text-muted">{{ __('Current Bid') }}</span>
            <p class="h4 text-primary mb-0">{{ $listing->currency ?? '' }} {{ number_format((float) ($auction->current_bid ?? $auction->starting_bid ?? 0), 2) }}</p>
            <span class="small text-muted">{{ __(':count bids', ['count' => (int) ($auction->bid_count ?? 0)]) }}</span>
          </div>

          <div class="alert alert-warning py-2 mb-3">
            <i class="fas fa-clock me-1"></i>
            <span class="small">{{ __('Ends') }}:</span>
            <strong id="auction-countdown" data-end="{{ $auction->end_time ?? '' }}">--</strong>
          </div>

          @auth
            <form method="POST" action="{{ route('ahgmarketplace.bid-form', ['slug' => $listing->slug ?? '']) }}">
              @csrf
              @php $minBid = (float) ($auction->current_bid ?? $auction->starting_bid ?? 0) + (float) ($auction->bid_increment ?? 1); @endphp
              <div class="input-group mb-2">
                <span class="input-group-text">{{ $listing->currency ?? '' }}</span>
                <input type="number" class="form-control" name="bid_amount" placeholder="{{ __('Your bid') }}" min="{{ $minBid }}" step="0.01" required>
                <button type="submit" class="btn btn-primary">{{ __('Place Bid') }}</button>
              </div>
              <p class="small text-muted">{{ __('Minimum bid: :c :a', ['c' => $listing->currency ?? '', 'a' => number_format($minBid, 2)]) }}</p>
            </form>
          @else
            <a href="{{ route('login') }}" class="btn btn-primary w-100">{{ __('Log in to Bid') }}</a>
          @endauth

          @if(!empty($auction->buy_now_price))
            <hr>
            <p class="small text-muted mb-1">{{ __('Buy Now Price') }}</p>
            <p class="h5 mb-2">{{ $listing->currency ?? '' }} {{ number_format((float) $auction->buy_now_price, 2) }}</p>
            @auth
              @if($ecommerceEnabled ?? app(\AhgCart\Services\EcommerceService::class)->isEcommerceEnabled())
                <form method="POST" action="{{ route('ahgmarketplace.checkout-buy', ['listingId' => $listing->id]) }}">
                  @csrf
                  <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="fas fa-bolt me-1"></i> {{ __('Buy Now') }}
                  </button>
                </form>
              @else
                <button type="button" class="btn btn-outline-primary w-100"
                        data-bs-toggle="modal" data-bs-target="#dummySaleModal"
                        data-dummy-title="{{ $listing->title ?? '' }}"
                        data-dummy-price="{{ (string) (float) $auction->buy_now_price }}"
                        data-dummy-currency="{{ $listing->currency ?: 'ZAR' }}">
                  <i class="fas fa-bolt me-1"></i> {{ __('Buy Now') }}
                </button>
              @endif
            @endauth
          @endif

        @elseif(($listing->listing_type ?? '') === 'offer_only')
          <p class="h5 mb-2">{{ __('Accepting Offers') }}</p>
          @if(!empty($listing->minimum_offer))
            <p class="small text-muted mb-3">{{ __('Minimum offer: :c :a', ['c' => $listing->currency ?? '', 'a' => number_format((float) $listing->minimum_offer, 2)]) }}</p>
          @endif
          <a href="{{ route('ahgmarketplace.offer-form', ['slug' => $listing->slug ?? '']) }}" class="btn btn-primary w-100 mb-2">
            <i class="fas fa-hand-holding-usd me-1"></i> {{ __('Make an Offer') }}
          </a>
          <a href="{{ route('ahgmarketplace.enquiry-form', ['slug' => $listing->slug ?? '']) }}" class="btn btn-outline-secondary w-100">
            <i class="fas fa-envelope me-1"></i> {{ __('Enquire') }}
          </a>
        @endif
      </div>
    </div>

    {{-- Licence terms preview (visible to all visitors before purchase) --}}
    @if(($listing->listing_type ?? '') === 'licence')
      <div class="card mb-3 border-warning">
        <div class="card-header bg-warning bg-opacity-10 fw-bold">
          <i class="fas fa-file-contract me-1 text-warning"></i> {{ __('Licence terms') }}
        </div>
        <ul class="list-group list-group-flush small">
          <li class="list-group-item">
            <span class="text-muted">{{ __('Type') }}:</span>
            <strong>{{ ucfirst(str_replace('_', ' ', $listing->licence_template_type ?? 'standard')) }}</strong>
          </li>
          <li class="list-group-item">
            <span class="text-muted">{{ __('Term') }}:</span>
            @if($listing->licence_template_duration_days)
              <strong>{{ (int) $listing->licence_template_duration_days }} {{ __('days from purchase') }}</strong>
            @else
              <strong>{{ __('Perpetual') }}</strong>
            @endif
          </li>
          <li class="list-group-item">
            <span class="text-muted">{{ __('Territory') }}:</span>
            <strong>{{ $listing->licence_template_territory ?? 'Worldwide' }}</strong>
          </li>
          <li class="list-group-item">
            <span class="text-muted">{{ __('Exclusivity') }}:</span>
            <strong>{{ ucfirst($listing->licence_template_exclusivity ?? 'non-exclusive') }}</strong>
          </li>
          @if($listing->licence_template_max_copies)
            <li class="list-group-item">
              <span class="text-muted">{{ __('Max copies / impressions') }}:</span>
              <strong>{{ number_format((int) $listing->licence_template_max_copies) }}</strong>
            </li>
          @endif
          <li class="list-group-item">
            <span class="text-muted">{{ __('Attribution required') }}:</span>
            @if($listing->licence_template_attribution_required ?? 1)
              <i class="fas fa-check-circle text-success"></i> Yes
            @else
              <i class="fas fa-times-circle text-secondary"></i> No
            @endif
          </li>
          <li class="list-group-item">
            <span class="text-muted">{{ __('Modifications allowed') }}:</span>
            @if($listing->licence_template_modifications_allowed ?? 0)
              <i class="fas fa-check-circle text-success"></i> Yes
            @else
              <i class="fas fa-times-circle text-secondary"></i> No
            @endif
          </li>
          <li class="list-group-item">
            <span class="text-muted">{{ __('Sub-licensing') }}:</span>
            @if($listing->licence_template_sublicensing_allowed ?? 0)
              <i class="fas fa-check-circle text-success"></i> Allowed
            @else
              <i class="fas fa-times-circle text-secondary"></i> Not allowed
            @endif
          </li>
          @if($listing->licence_template_scope)
            <li class="list-group-item">
              <div class="text-muted mb-1">{{ __('Scope of grant') }}:</div>
              {{ $listing->licence_template_scope }}
            </li>
          @endif
        </ul>
        <div class="card-footer small text-muted">
          <i class="fas fa-info-circle me-1"></i>
          {{ __('A signed agreement will be issued to your account on payment.') }}
        </div>
      </div>
    @endif

    {{-- Seller info card --}}
    @if(!empty($seller))
      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            @if(!empty($seller->avatar_path))
              <img src="{{ $seller->avatar_path }}" alt="" class="rounded-circle me-2" width="40" height="40" style="object-fit:cover;">
            @else
              <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;">
                <i class="fas fa-user"></i>
              </div>
            @endif
            <div>
              <a href="{{ route('ahgmarketplace.seller', ['slug' => $seller->slug ?? '']) }}" class="fw-semibold text-decoration-none">{{ $seller->display_name ?? '' }}</a>
              @if(($seller->verification_status ?? '') === 'verified')
                <i class="fas fa-check-circle text-primary ms-1" title="{{ __('Verified Seller') }}"></i>
              @endif
              <div class="small text-muted">
                @if(($seller->average_rating ?? 0) > 0)
                  @for($s = 1; $s <= 5; $s++)
                    <i class="fa{{ $s <= round($seller->average_rating) ? 's' : 'r' }} fa-star text-warning"></i>
                  @endfor
                  <span class="ms-1">({{ (int) ($seller->rating_count ?? 0) }})</span>
                @endif
              </div>
            </div>
          </div>
          <a href="{{ route('ahgmarketplace.seller', ['slug' => $seller->slug ?? '']) }}" class="btn btn-outline-secondary btn-sm w-100">{{ __('View Seller Profile') }}</a>
        </div>
      </div>
    @endif

    {{-- Quick details --}}
    <div class="card mb-3">
      <div class="card-body">
        <table class="table table-sm table-borderless mb-0">
          @if(!empty($listing->condition_rating))
            <tr><td class="text-muted">{{ __('Condition') }}</td><td>{{ ucfirst($listing->condition_rating) }}</td></tr>
          @endif
          @if(!empty($listing->medium))
            <tr><td class="text-muted">{{ __('Medium') }}</td><td>{{ $listing->medium }}</td></tr>
          @endif
          @if(!empty($listing->dimensions))
            <tr><td class="text-muted">{{ __('Dimensions') }}</td><td>{{ $listing->dimensions }}</td></tr>
          @endif
          @if(!empty($listing->year_created))
            <tr><td class="text-muted">{{ __('Year') }}</td><td>{{ $listing->year_created }}</td></tr>
          @endif
          @if(!empty($listing->edition_info))
            <tr><td class="text-muted">{{ __('Edition') }}</td><td>{{ $listing->edition_info }}</td></tr>
          @endif
          @if(!empty($listing->is_signed))
            <tr><td class="text-muted">{{ __('Signed') }}</td><td><i class="fas fa-check text-success"></i> {{ __('Yes') }}</td></tr>
          @endif
          @if(!empty($listing->certificate_of_authenticity))
            <tr><td class="text-muted">{{ __('COA') }}</td><td><i class="fas fa-check text-success"></i> {{ __('Certificate of Authenticity') }}</td></tr>
          @endif
          @if(!empty($listing->is_framed))
            <tr><td class="text-muted">{{ __('Framed') }}</td><td>{{ $listing->frame_description ?: __('Yes') }}</td></tr>
          @endif
        </table>
      </div>
    </div>

    {{-- Shipping info --}}
    @if(!empty($listing->requires_shipping))
      <div class="card mb-3">
        <div class="card-body">
          <h6 class="mb-2"><i class="fas fa-truck me-1"></i> {{ __('Shipping') }}</h6>
          @if(!empty($listing->free_shipping_domestic))
            <p class="mb-1"><span class="badge bg-success">{{ __('Free Domestic Shipping') }}</span></p>
          @elseif(!empty($listing->shipping_domestic_price))
            <p class="mb-1 small">{{ __('Domestic: :c :a', ['c' => $listing->currency ?? '', 'a' => number_format((float) $listing->shipping_domestic_price, 2)]) }}</p>
          @endif
          @if(!empty($listing->shipping_international_price))
            <p class="mb-1 small">{{ __('International: :c :a', ['c' => $listing->currency ?? '', 'a' => number_format((float) $listing->shipping_international_price, 2)]) }}</p>
          @endif
          @if(!empty($listing->shipping_from_country))
            @php
              $shipFrom = !empty($listing->shipping_from_city)
                ? $listing->shipping_from_city . ', ' . $listing->shipping_from_country
                : $listing->shipping_from_country;
            @endphp
            <p class="mb-0 small text-muted">{{ __('Ships from: :loc', ['loc' => $shipFrom]) }}</p>
          @endif
        </div>
      </div>
    @endif
  </div>
</div>

{{-- Detail tabs --}}
<div class="row mt-2">
  <div class="col-12">
    <ul class="nav nav-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#panel-description" type="button" role="tab">{{ __('Description') }}</button>
      </li>
      @if(!empty($listing->provenance))
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#panel-provenance" type="button" role="tab">{{ __('Provenance') }}</button></li>
      @endif
      @if(!empty($listing->condition_description))
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#panel-condition" type="button" role="tab">{{ __('Condition Report') }}</button></li>
      @endif
      @if(!empty($listing->requires_shipping) && !empty($listing->shipping_notes))
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#panel-shipping" type="button" role="tab">{{ __('Shipping') }}</button></li>
      @endif
      @if(!empty($seller))
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#panel-seller" type="button" role="tab">{{ __('Seller Info') }}</button></li>
      @endif
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom p-4">
      <div class="tab-pane fade show active" id="panel-description" role="tabpanel">
        @if(!empty($listing->description))
          <div class="listing-description">{!! nl2br(e($listing->description)) !!}</div>
        @else
          <p class="text-muted">{{ __('No description provided.') }}</p>
        @endif
      </div>
      @if(!empty($listing->provenance))
        <div class="tab-pane fade" id="panel-provenance" role="tabpanel">{!! nl2br(e($listing->provenance)) !!}</div>
      @endif
      @if(!empty($listing->condition_description))
        <div class="tab-pane fade" id="panel-condition" role="tabpanel">{!! nl2br(e($listing->condition_description)) !!}</div>
      @endif
      @if(!empty($listing->requires_shipping) && !empty($listing->shipping_notes))
        <div class="tab-pane fade" id="panel-shipping" role="tabpanel">
          {!! nl2br(e($listing->shipping_notes)) !!}
          @if(!empty($listing->insurance_value))
            <p class="mt-2"><strong>{{ __('Insurance Value') }}:</strong> {{ $listing->currency ?? '' }} {{ number_format((float) $listing->insurance_value, 2) }}</p>
          @endif
        </div>
      @endif
      @if(!empty($seller))
        <div class="tab-pane fade" id="panel-seller" role="tabpanel">
          <div class="d-flex align-items-start">
            @if(!empty($seller->avatar_path))
              <img src="{{ $seller->avatar_path }}" alt="" class="rounded-circle me-3" width="60" height="60" style="object-fit:cover;">
            @endif
            <div>
              <h6>
                {{ $seller->display_name ?? '' }}
                @if(($seller->verification_status ?? '') === 'verified')
                  <i class="fas fa-check-circle text-primary ms-1"></i>
                @endif
              </h6>
              <p class="small text-muted mb-1">{{ ucfirst($seller->seller_type ?? '') }}</p>
              @if(!empty($seller->city) || !empty($seller->country))
                <p class="small text-muted mb-2"><i class="fas fa-map-marker-alt me-1"></i> {{ implode(', ', array_filter([$seller->city ?? '', $seller->country ?? ''])) }}</p>
              @endif
              @if(!empty($seller->bio))
                <p class="small">{!! nl2br(e($seller->bio)) !!}</p>
              @endif
              <a href="{{ route('ahgmarketplace.seller', ['slug' => $seller->slug ?? '']) }}" class="btn btn-outline-primary btn-sm">{{ __('View Full Profile') }}</a>
            </div>
          </div>
        </div>
      @endif
    </div>
  </div>
</div>

{{-- Related listings --}}
@if(!empty($relatedListings) && count($relatedListings) > 0)
  <div class="mt-5">
    <h4 class="mb-3">{{ __('Related Listings') }}</h4>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
      @foreach($relatedListings as $related)
        <div class="col">
          <div class="card h-100">
            @if(!empty($related->featured_image_path))
              <img src="{{ $related->featured_image_path }}" class="card-img-top" alt="" style="height:160px;object-fit:cover;">
            @else
              <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:160px;">
                <i class="fas fa-image fa-2x text-muted"></i>
              </div>
            @endif
            <div class="card-body">
              <h6 class="card-title">
                <a href="{{ route('ahgmarketplace.listing', ['slug' => $related->slug ?? '']) }}" class="text-decoration-none">{{ \Illuminate\Support\Str::limit($related->title ?? '', 40) }}</a>
              </h6>
              <div class="fw-bold small">{{ $related->currency ?? '' }} {{ number_format((float) ($related->price ?? 0), 2) }}</div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
@endif

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.listing-thumb').forEach(function(thumb) {
    thumb.addEventListener('click', function() {
      var mainImg = document.getElementById('main-image');
      if (mainImg) mainImg.src = this.getAttribute('data-src');
    });
  });

  var favBtn = document.getElementById('btn-favourite');
  if (favBtn) {
    favBtn.addEventListener('click', function() {
      var listingId = this.getAttribute('data-listing-id');
      var icon = this.querySelector('i');
      fetch('/marketplace/api/' + listingId + '/favourite', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''}
      }).then(function(r) { return r.json(); })
        .then(function(data) {
          icon.className = data.favourited ? 'fas fa-heart text-danger' : 'far fa-heart text-danger';
        }).catch(function() {});
    });
  }

  var countdownEl = document.getElementById('auction-countdown');
  if (countdownEl) {
    var endTime = new Date(countdownEl.getAttribute('data-end')).getTime();
    function updateCountdown() {
      var now = new Date().getTime();
      var diff = endTime - now;
      if (diff <= 0) { countdownEl.textContent = '{{ __('Ended') }}'; return; }
      var d = Math.floor(diff / 86400000);
      var h = Math.floor((diff % 86400000) / 3600000);
      var m = Math.floor((diff % 3600000) / 60000);
      var s = Math.floor((diff % 60000) / 1000);
      var parts = [];
      if (d > 0) parts.push(d + 'd');
      parts.push(h + 'h');
      parts.push(m + 'm');
      parts.push(s + 's');
      countdownEl.textContent = parts.join(' ');
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);
  }
});
</script>
@endpush

@include('ahg-cart::_dummy-sale-modal')

@endsection
