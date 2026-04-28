{{--
  Partial: price-display (ported from atom-ahg-plugins/ahgMarketplacePlugin/_priceDisplay.php)

  Variables:
    $price          (float|null)  numeric price
    $currency       (string)      currency code, e.g. 'ZAR', 'USD'
    $priceOnRequest (bool)        true if price is not disclosed
    $listingType    (string)      fixed_price | auction | offer_only
--}}
@php
  $currencyDisplay = e($currency ?? 'ZAR');
@endphp
@if (!empty($priceOnRequest))
  <span class="mkt-price-por fst-italic text-muted">{{ __('Price on Request') }}</span>
@elseif ($listingType === 'auction')
  @if ($price && $price > 0)
    <span class="mkt-price">{{ __('Current Bid') }}: {!! $currencyDisplay !!} {{ number_format((float) $price, 2) }}</span>
  @else
    <span class="mkt-price text-muted">{{ __('Starting at') }}: {!! $currencyDisplay !!} {{ number_format((float) $price, 2) }}</span>
  @endif
@else
  <span class="mkt-price">{!! $currencyDisplay !!} {{ number_format((float) $price, 2) }}</span>
@endif
