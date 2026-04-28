{{--
  Partial: listing-card (ported from atom-ahg-plugins/ahgMarketplacePlugin/_listingCard.php)

  Variables:
    $listing (object) title, slug, price, currency, featured_image_path,
                       seller_name, seller_slug, listing_type, status, sector,
                       artist_name, seller_rating, seller_verified, condition_rating,
                       price_on_request
--}}
@php
  $typeBadges = [
      'fixed_price' => ['bg-primary',           __('Buy Now')],
      'auction'     => ['bg-warning text-dark', __('Auction')],
      'offer_only'  => ['bg-info text-dark',    __('Make an Offer')],
  ];
  $badge = $typeBadges[$listing->listing_type]
      ?? ['bg-secondary', ucfirst(str_replace('_', ' ', $listing->listing_type))];
@endphp
<div class="col">
  <div class="card mkt-card h-100 position-relative">
    @if ($listing->status === 'sold')
      <div class="mkt-card-sold position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center rounded" style="z-index:2;">
        <span class="badge bg-dark fs-5 px-3 py-2">{{ __('SOLD') }}</span>
      </div>
    @endif

    <a href="{{ route('marketplace.listing.show', ['slug' => $listing->slug]) }}">
      @if ($listing->featured_image_path)
        <img src="{{ $listing->featured_image_path }}" class="card-img-top mkt-card-image" alt="{{ $listing->title }}">
      @else
        <div class="card-img-top mkt-card-image bg-light d-flex align-items-center justify-content-center">
          <i class="fas fa-image fa-3x text-muted"></i>
        </div>
      @endif
    </a>

    <div class="card-body pb-2">
      <span class="badge {{ $badge[0] }} mb-2">{{ $badge[1] }}</span>
      <h6 class="card-title mb-1">
        <a href="{{ route('marketplace.listing.show', ['slug' => $listing->slug]) }}" class="text-decoration-none text-dark">
          {{ mb_strimwidth($listing->title, 0, 70, '...') }}
        </a>
      </h6>
      @if (!empty($listing->artist_name))
        <p class="small text-muted mb-1">{{ $listing->artist_name }}</p>
      @endif

      @if (!empty($listing->price_on_request))
        <p class="mkt-price-por mb-0">{{ __('Price on Request') }}</p>
      @else
        <p class="mkt-price mb-0">
          {{ $listing->currency ?? 'ZAR' }} {{ number_format((float) ($listing->price ?? 0), 2) }}
        </p>
      @endif
    </div>

    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center small">
      <span>
        <a href="{{ route('marketplace.seller.show', ['slug' => $listing->seller_slug]) }}" class="text-decoration-none text-muted">
          {{ $listing->seller_name }}
        </a>
        @if (!empty($listing->seller_verified))
          <i class="fas fa-check-circle text-primary ms-1" title="{{ __('Verified') }}"></i>
        @endif
      </span>
      <span>
        @if (!empty($listing->sector))
          <span class="badge bg-secondary">{{ ucfirst($listing->sector) }}</span>
        @endif
        @if (!empty($listing->condition_rating))
          <span class="badge bg-outline-secondary border text-muted ms-1">{{ ucfirst($listing->condition_rating) }}</span>
        @endif
      </span>
    </div>
  </div>
</div>
