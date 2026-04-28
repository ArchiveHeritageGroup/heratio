{{--
  Partial: seller-badge (ported from atom-ahg-plugins/ahgMarketplacePlugin/_sellerBadge.php)

  Variables:
    $seller (object) display_name, slug, average_rating, verification_status, avatar_path
--}}
@php
  $initials = '';
  if (!empty($seller->display_name)) {
      $words = explode(' ', trim($seller->display_name));
      $initials = mb_strtoupper(mb_substr($words[0], 0, 1));
      if (count($words) > 1) {
          $initials .= mb_strtoupper(mb_substr(end($words), 0, 1));
      }
  }
@endphp
<span class="mkt-seller-badge d-inline-flex align-items-center">
  @if (!empty($seller->avatar_path))
    <img src="{{ $seller->avatar_path }}" alt="" class="mkt-seller-avatar rounded-circle me-1" width="24" height="24">
  @else
    <span class="mkt-seller-avatar rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center me-1" style="width:24px;height:24px;font-size:0.65rem;">
      {{ $initials }}
    </span>
  @endif

  <a href="{{ route('marketplace.seller.show', ['slug' => $seller->slug]) }}" class="text-decoration-none small fw-semibold">
    {{ $seller->display_name }}
  </a>

  @if ($seller->verification_status === 'verified')
    <i class="fas fa-check-circle text-primary ms-1" title="{{ __('Verified Seller') }}"></i>
  @endif

  @if (!empty($seller->average_rating) && $seller->average_rating > 0)
    <span class="mkt-seller-rating ms-1 small text-warning">
      <i class="fas fa-star"></i> {{ number_format((float) $seller->average_rating, 1) }}
    </span>
  @endif
</span>
