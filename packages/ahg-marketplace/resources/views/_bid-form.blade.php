{{--
  Partial: bid-form (ported from atom-ahg-plugins/ahgMarketplacePlugin/_bidForm.php)

  Variables:
    $auction (object) current_bid, bid_increment, buy_now_price, starting_bid
    $listing (object) id, slug, currency
--}}
@php
  $currencyDisplay = e($listing->currency ?? 'ZAR');
  $currentBid = (float) ($auction->current_bid ?? $auction->starting_bid ?? 0);
  $increment  = (float) ($auction->bid_increment ?? 1);
  $minNextBid = $currentBid + $increment;
@endphp
<div class="mkt-bid-form">

  {{-- Current bid display --}}
  <div class="text-center mb-3">
    <span class="small text-muted d-block">{{ __('Current Bid') }}</span>
    <span class="h4 text-primary" id="bid-current-{{ (int) $listing->id }}">
      {!! $currencyDisplay !!} {{ number_format($currentBid, 2) }}
    </span>
  </div>

  {{-- Bid input --}}
  <form id="bid-form-{{ (int) $listing->id }}" class="mb-2" method="post" action="{{ route('marketplace.bid.place', ['slug' => $listing->slug]) }}">
    @csrf
    <input type="hidden" name="listing_id" value="{{ (int) $listing->id }}">

    <div class="mb-2">
      <label for="bid-amount-{{ (int) $listing->id }}" class="form-label small fw-semibold">
        {{ __('Your Bid') }}
      </label>
      <div class="input-group">
        <span class="input-group-text">{!! $currencyDisplay !!}</span>
        <input type="number" class="form-control" id="bid-amount-{{ (int) $listing->id }}"
               name="bid_amount" step="0.01"
               min="{{ number_format($minNextBid, 2, '.', '') }}"
               value="{{ number_format($minNextBid, 2, '.', '') }}"
               required>
      </div>
      <div class="form-text small">
        {{ __('Minimum bid') }}: {!! $currencyDisplay !!} {{ number_format($minNextBid, 2) }}
        @if ($increment > 0)
          ({{ __('increment') }}: {{ number_format($increment, 2) }})
        @endif
      </div>
    </div>

    <div class="mb-3">
      <label for="bid-max-{{ (int) $listing->id }}" class="form-label small">
        {{ __('Max Bid (Proxy)') }}
        <span class="text-muted">({{ __('optional') }})</span>
      </label>
      <div class="input-group">
        <span class="input-group-text">{!! $currencyDisplay !!}</span>
        <input type="number" class="form-control" id="bid-max-{{ (int) $listing->id }}"
               name="max_bid" step="0.01"
               placeholder="{{ __('Auto-bid up to...') }}">
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 mb-2" id="btn-place-bid-{{ (int) $listing->id }}">
      <i class="fas fa-gavel me-1"></i> {{ __('Place Bid') }}
    </button>

    <div id="bid-result-{{ (int) $listing->id }}" class="small"></div>
  </form>

  @if (!empty($auction->buy_now_price) && (float) $auction->buy_now_price > 0)
    <hr class="my-2">
    <div class="text-center">
      <p class="small text-muted mb-1">{{ __('Buy Now Price') }}</p>
      <p class="h5 mb-2">{!! $currencyDisplay !!} {{ number_format((float) $auction->buy_now_price, 2) }}</p>
      <a href="{{ route('marketplace.listing.buy', ['slug' => $listing->slug]) }}" class="btn btn-outline-primary btn-sm w-100">
        <i class="fas fa-bolt me-1"></i> {{ __('Buy Now') }}
      </a>
    </div>
  @endif
</div>
