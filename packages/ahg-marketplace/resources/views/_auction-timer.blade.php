{{--
  Partial: auction-timer (ported from atom-ahg-plugins/ahgMarketplacePlugin/_auctionTimer.php)

  Variables:
    $auction   (object) end_time (UTC datetime string), status
    $elementId (string) unique DOM id for this timer instance
--}}
@php
  $isEnded = ($auction->status === 'ended' || $auction->status === 'closed');
@endphp
<div class="mkt-timer" id="{{ $elementId }}">
  @if ($isEnded)
    <span class="mkt-timer-ended text-danger fw-bold">{{ __('ENDED') }}</span>
  @else
    <div class="d-flex gap-2 justify-content-center">
      <div class="mkt-timer-box text-center">
        <span class="mkt-timer-value" data-unit="days">00</span>
        <span class="mkt-timer-label">{{ __('Days') }}</span>
      </div>
      <div class="mkt-timer-box text-center">
        <span class="mkt-timer-value" data-unit="hours">00</span>
        <span class="mkt-timer-label">{{ __('Hrs') }}</span>
      </div>
      <div class="mkt-timer-box text-center">
        <span class="mkt-timer-value" data-unit="minutes">00</span>
        <span class="mkt-timer-label">{{ __('Min') }}</span>
      </div>
      <div class="mkt-timer-box text-center">
        <span class="mkt-timer-value" data-unit="seconds">00</span>
        <span class="mkt-timer-label">{{ __('Sec') }}</span>
      </div>
    </div>
  @endif
</div>

@if (!$isEnded)
<script @cspNonce>
(function() {
  var elId = {!! json_encode($elementId) !!};
  var endUTC = {!! json_encode($auction->end_time) !!};
  if (typeof window.initAuctionTimer === 'function') {
    window.initAuctionTimer(elId, endUTC);
  } else {
    document.addEventListener('DOMContentLoaded', function() {
      if (typeof window.initAuctionTimer === 'function') {
        window.initAuctionTimer(elId, endUTC);
      }
    });
  }
})();
</script>
@endif
