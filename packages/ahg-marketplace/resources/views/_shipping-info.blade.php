{{--
  Partial: shipping-info (ported from atom-ahg-plugins/ahgMarketplacePlugin/_shippingInfo.php)

  Variables:
    $listing (object) requires_shipping, shipping_from_country, shipping_from_city,
                       shipping_domestic_price, free_shipping_domestic,
                       shipping_international_price, insurance_value, shipping_notes, currency
--}}
@php
  $currencyDisplay = e($listing->currency ?? 'ZAR');
@endphp
<div class="mkt-shipping-info">
  <h6 class="mb-2"><i class="fas fa-truck me-1"></i> {{ __('Shipping') }}</h6>

  @if (empty($listing->requires_shipping))
    <p class="mb-0 text-muted"><i class="fas fa-map-pin me-1"></i> {{ __('Collection only') }}</p>
  @else

    @if ($listing->shipping_from_country || $listing->shipping_from_city)
      <p class="small mb-1">
        <span class="text-muted">{{ __('Ships from') }}:</span>
        {{ implode(', ', array_filter([$listing->shipping_from_city ?? '', $listing->shipping_from_country ?? ''])) }}
      </p>
    @endif

    @if (!empty($listing->free_shipping_domestic))
      <p class="small mb-1"><span class="badge bg-success">{{ __('Free Domestic Shipping') }}</span></p>
    @elseif (!empty($listing->shipping_domestic_price))
      <p class="small mb-1">
        <span class="text-muted">{{ __('Domestic') }}:</span>
        {!! $currencyDisplay !!} {{ number_format((float) $listing->shipping_domestic_price, 2) }}
      </p>
    @endif

    @if (!empty($listing->shipping_international_price))
      <p class="small mb-1">
        <span class="text-muted">{{ __('International') }}:</span>
        {!! $currencyDisplay !!} {{ number_format((float) $listing->shipping_international_price, 2) }}
      </p>
    @endif

    @if (!empty($listing->insurance_value))
      <p class="small mb-1">
        <span class="text-muted">{{ __('Insurance value') }}:</span>
        {!! $currencyDisplay !!} {{ number_format((float) $listing->insurance_value, 2) }}
      </p>
    @endif

    @if (!empty($listing->shipping_notes))
      <p class="small text-muted mb-0">{!! nl2br(e($listing->shipping_notes)) !!}</p>
    @endif

  @endif
</div>
