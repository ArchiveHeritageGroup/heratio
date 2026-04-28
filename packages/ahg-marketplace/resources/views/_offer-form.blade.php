{{--
  Partial: offer-form (ported from atom-ahg-plugins/ahgMarketplacePlugin/_offerForm.php)

  Variables:
    $listing (object) id, slug, minimum_offer, currency
--}}
@php
  $currencyDisplay = e($listing->currency ?? 'ZAR');
  $minOffer = !empty($listing->minimum_offer)
      ? number_format((float) $listing->minimum_offer, 2, '.', '')
      : '0.01';
@endphp
<form method="post" action="{{ route('marketplace.offer.submit', ['slug' => $listing->slug]) }}" class="mkt-offer-form">
  @csrf

  <div class="mb-2">
    <label for="inline-offer-amount-{{ (int) $listing->id }}" class="form-label small fw-semibold">
      {{ __('Your Offer') }}
    </label>
    <div class="input-group">
      <span class="input-group-text">{!! $currencyDisplay !!}</span>
      <input type="number" class="form-control" id="inline-offer-amount-{{ (int) $listing->id }}"
             name="offer_amount" step="0.01" min="{{ $minOffer }}"
             placeholder="{{ __('Amount') }}" required>
    </div>
    @if (!empty($listing->minimum_offer))
      <div class="form-text small">
        {{ __('Minimum') }}: {!! $currencyDisplay !!} {{ number_format((float) $listing->minimum_offer, 2) }}
      </div>
    @endif
  </div>

  <div class="mb-2">
    <textarea class="form-control form-control-sm" name="message" rows="2"
              placeholder="{{ __('Message to seller (optional)') }}"></textarea>
  </div>

  <button type="submit" class="btn btn-primary btn-sm w-100">
    <i class="fas fa-hand-holding-usd me-1"></i> {{ __('Submit Offer') }}
  </button>

</form>
