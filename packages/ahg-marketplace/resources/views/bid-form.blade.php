{{--
  Marketplace — Place a Bid

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/bidFormSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Place a Bid') . ' - ' . ($listing->title ?? ''))
@section('body-class', 'marketplace bid-form')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}">{{ $listing->title ?? '' }}</a></li>
    <li class="breadcrumb-item active">{{ __('Place a Bid') }}</li>
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
  <div class="col-lg-8 mx-auto">
    <h1 class="h3 mb-4">{{ __('Place a Bid') }}</h1>

    {{-- Listing summary --}}
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex">
          @if(!empty($primaryImage->file_path ?? null))
            <img src="{{ $primaryImage->file_path }}" alt="{{ $listing->title ?? '' }}" class="rounded me-3" style="width:100px;height:100px;object-fit:cover;">
          @elseif(!empty($listing->featured_image_path))
            <img src="{{ $listing->featured_image_path }}" alt="{{ $listing->title ?? '' }}" class="rounded me-3" style="width:100px;height:100px;object-fit:cover;">
          @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" style="width:100px;height:100px;">
              <i class="fas fa-image fa-2x text-muted"></i>
            </div>
          @endif
          <div class="flex-grow-1">
            <h5 class="mb-1">{{ $listing->title ?? '' }}</h5>
            @if(!empty($listing->artist_name))
              <p class="text-muted mb-1">{{ __('by :name', ['name' => $listing->artist_name]) }}</p>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-7">
        <div class="card border-primary mb-3">
          <div class="card-body text-center">
            <span class="text-muted d-block">{{ __('Current Bid') }}</span>
            <p class="h2 text-primary mb-1">{{ $listing->currency ?? '' }} {{ number_format((float) ($currentBid ?? 0), 2) }}</p>
            <span class="text-muted small">{{ __(':count bids', ['count' => (int) ($auction->bid_count ?? 0)]) }}</span>
          </div>
        </div>

        <div class="alert alert-warning text-center mb-3">
          <i class="fas fa-clock me-1"></i>
          <span>{{ __('Time Remaining') }}:</span>
          <strong id="bid-countdown" data-end="{{ $auction->end_time ?? '' }}">--</strong>
        </div>

        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-gavel me-2"></i>{{ __('Your Bid') }}</h5>
          </div>
          <div class="card-body">
            <form method="POST" action="{{ route('ahgmarketplace.bid-form.post', ['slug' => $listing->slug ?? '']) }}">
              @csrf
              <div class="mb-3">
                <label for="bid_amount" class="form-label">{{ __('Bid Amount') }} <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">{{ $listing->currency ?? '' }}</span>
                  <input type="number" class="form-control form-control-lg" id="bid_amount" name="bid_amount" step="0.01" min="{{ number_format($minBid ?? 0, 2, '.', '') }}" value="{{ number_format($minBid ?? 0, 2, '.', '') }}" required>
                </div>
                <div class="form-text">
                  {{ __('Minimum bid: :c :a', ['c' => $listing->currency ?? '', 'a' => number_format($minBid ?? 0, 2)]) }}
                  @if(!empty($auction->bid_increment))
                    ({{ __('increment: :inc', ['inc' => number_format((float) $auction->bid_increment, 2)]) }})
                  @endif
                </div>
              </div>
              <div class="mb-4">
                <label for="max_bid" class="form-label">
                  {{ __('Maximum Bid (Proxy Bidding)') }}
                  <span class="text-muted">({{ __('optional') }})</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text">{{ $listing->currency ?? '' }}</span>
                  <input type="number" class="form-control" id="max_bid" name="max_bid" step="0.01" placeholder="{{ __('Auto-bid up to this amount') }}">
                </div>
                <div class="form-text">
                  <i class="fas fa-info-circle me-1"></i>
                  {{ __('The system will automatically bid on your behalf up to this amount to keep you in the lead.') }}
                </div>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="btn btn-outline-secondary">
                  <i class="fas fa-arrow-left me-1"></i> {{ __('Back') }}
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                  <i class="fas fa-gavel me-1"></i> {{ __('Place Bid') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-5">
        <div class="card">
          <div class="card-header">
            <h6 class="card-title mb-0"><i class="fas fa-history me-2"></i>{{ __('Recent Bids') }}</h6>
          </div>
          <div class="card-body p-0">
            @if(!empty($bidHistory) && count($bidHistory) > 0)
              <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="small">{{ __('Bidder') }}</th>
                      <th class="small text-end">{{ __('Amount') }}</th>
                      <th class="small">{{ __('Time') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($bidHistory as $bid)
                      <tr class="{{ ($bid->is_winning ?? false) ? 'table-success' : '' }}">
                        <td class="small">
                          {{ __('Bidder #:id', ['id' => substr(md5($bid->user_id ?? ''), 0, 6)]) }}
                          @if(!empty($bid->is_winning))
                            <span class="badge bg-success ms-1">{{ __('Leading') }}</span>
                          @endif
                        </td>
                        <td class="small text-end fw-semibold">{{ $listing->currency ?? '' }} {{ number_format((float) ($bid->bid_amount ?? 0), 2) }}</td>
                        <td class="small text-muted">
                          @php
                            $bidTime = !empty($bid->created_at) ? strtotime($bid->created_at) : time();
                            $diff = time() - $bidTime;
                            if ($diff < 60) $timeLabel = $diff . 's ago';
                            elseif ($diff < 3600) $timeLabel = floor($diff / 60) . 'm ago';
                            elseif ($diff < 86400) $timeLabel = floor($diff / 3600) . 'h ago';
                            else $timeLabel = date('d M H:i', $bidTime);
                          @endphp
                          {{ $timeLabel }}
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @else
              <div class="p-3 text-center text-muted">
                <i class="fas fa-gavel fa-2x mb-2 d-block"></i>
                {{ __('No bids yet. Be the first!') }}
              </div>
            @endif
          </div>
        </div>

        @if(!empty($auction->buy_now_price))
          <div class="card mt-3">
            <div class="card-body text-center">
              <p class="small text-muted mb-1">{{ __('Buy Now Price') }}</p>
              <p class="h5 mb-2">{{ $listing->currency ?? '' }} {{ number_format((float) $auction->buy_now_price, 2) }}</p>
              @if(Route::has('ahgmarketplace.buy'))
                <a href="{{ route('ahgmarketplace.buy', ['slug' => $listing->slug ?? '']) }}" class="btn btn-outline-primary btn-sm w-100">
                  <i class="fas fa-bolt me-1"></i> {{ __('Buy Now') }}
                </a>
              @endif
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  var countdownEl = document.getElementById('bid-countdown');
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
@endsection
