{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', __('Live Auctions') . ' - ' . __('Marketplace'))

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Live Auctions') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4">
  <i class="fas fa-gavel me-2 text-primary"></i>{{ __('Live Auctions') }}
</h1>

@if(!empty($endingSoon))
  <div class="mb-5">
    <h4 class="mb-3"><i class="fas fa-fire text-danger me-1"></i> {{ __('Ending Soon') }}</h4>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
      @foreach($endingSoon as $auc)
        <div class="col">
          <div class="card h-100 border-danger">
            <a href="{{ route('ahgmarketplace.listing', ['slug' => $auc->slug]) }}">
              @if(!empty($auc->featured_image_path))
                <img src="{{ $auc->featured_image_path }}" class="card-img-top" alt="{{ $auc->title }}" style="height: 180px; object-fit: cover;">
              @else
                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 180px;">
                  <i class="fas fa-gavel fa-2x text-muted"></i>
                </div>
              @endif
            </a>
            <div class="card-body">
              <h6 class="card-title mb-1">
                <a href="{{ route('ahgmarketplace.listing', ['slug' => $auc->slug]) }}" class="text-decoration-none">
                  {{ \Illuminate\Support\Str::limit($auc->title, 60) }}
                </a>
              </h6>
              <p class="h6 text-primary mb-1">
                {{ $auc->currency ?? 'USD' }} {{ number_format((float) ($auc->current_bid ?? $auc->starting_bid), 2) }}
              </p>
              <p class="small text-muted mb-1">{{ (int) ($auc->bid_count ?? 0) }} {{ __('bids') }}</p>
              <div class="alert alert-danger py-1 px-2 mb-0 small">
                <i class="fas fa-clock me-1"></i>
                <span class="auction-timer" data-end="{{ $auc->end_time }}">--</span>
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">{{ __('All Active Auctions') }} <span class="badge bg-secondary">{{ number_format($total ?? 0) }}</span></h4>
</div>

@if(!empty($auctions))
  <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
    @foreach($auctions as $auc)
      <div class="col">
        <div class="card h-100">
          <a href="{{ route('ahgmarketplace.listing', ['slug' => $auc->slug]) }}">
            @if(!empty($auc->featured_image_path))
              <img src="{{ $auc->featured_image_path }}" class="card-img-top" alt="{{ $auc->title }}" style="height: 200px; object-fit: cover;">
            @else
              <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                <i class="fas fa-gavel fa-2x text-muted"></i>
              </div>
            @endif
          </a>
          <div class="card-body">
            <h6 class="card-title mb-1">
              <a href="{{ route('ahgmarketplace.listing', ['slug' => $auc->slug]) }}" class="text-decoration-none">
                {{ \Illuminate\Support\Str::limit($auc->title, 60) }}
              </a>
            </h6>
            @if(!empty($auc->artist_name))
              <p class="small text-muted mb-1">{{ $auc->artist_name }}</p>
            @endif
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="small text-muted">{{ __('Current Bid') }}</span>
              <span class="fw-semibold text-primary">{{ $auc->currency ?? 'USD' }} {{ number_format((float) ($auc->current_bid ?? $auc->starting_bid), 2) }}</span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <span class="small text-muted">{{ (int) ($auc->bid_count ?? 0) }} {{ __('bids') }}</span>
              <span class="small">
                <i class="fas fa-clock text-warning me-1"></i>
                <span class="auction-timer" data-end="{{ $auc->end_time }}">--</span>
              </span>
            </div>
          </div>
          <div class="card-footer bg-transparent">
            <a href="{{ route('ahgmarketplace.listing', ['slug' => $auc->slug]) }}" class="btn btn-outline-primary btn-sm w-100">
              {{ __('View & Bid') }}
            </a>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  @if(($total ?? 0) > 24)
    @php $totalPages = (int) ceil(($total ?? 0) / 24); $currentPage = $page ?? 1; @endphp
    <nav class="mt-4">
      <ul class="pagination justify-content-center">
        <li class="page-item{{ $currentPage <= 1 ? ' disabled' : '' }}">
          <a class="page-link" href="{{ route('ahgmarketplace.auction-browse', ['page' => $currentPage - 1]) }}">&laquo;</a>
        </li>
        @for($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++)
          <li class="page-item{{ $i === $currentPage ? ' active' : '' }}">
            <a class="page-link" href="{{ route('ahgmarketplace.auction-browse', ['page' => $i]) }}">{{ $i }}</a>
          </li>
        @endfor
        <li class="page-item{{ $currentPage >= $totalPages ? ' disabled' : '' }}">
          <a class="page-link" href="{{ route('ahgmarketplace.auction-browse', ['page' => $currentPage + 1]) }}">&raquo;</a>
        </li>
      </ul>
    </nav>
  @endif
@else
  <div class="text-center py-5">
    <i class="fas fa-gavel fa-3x text-muted mb-3"></i>
    <h5>{{ __('No active auctions') }}</h5>
    <p class="text-muted">{{ __('Check back soon for new auctions.') }}</p>
    <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-primary">{{ __('Browse Marketplace') }}</a>
  </div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function() {
  function updateTimers() {
    document.querySelectorAll('.auction-timer').forEach(function(el) {
      var endTime = new Date(el.getAttribute('data-end')).getTime();
      var now = new Date().getTime();
      var diff = endTime - now;
      if (diff <= 0) {
        el.textContent = 'Ended';
        el.classList.add('text-danger');
        return;
      }
      var d = Math.floor(diff / 86400000);
      var h = Math.floor((diff % 86400000) / 3600000);
      var m = Math.floor((diff % 3600000) / 60000);
      var s = Math.floor((diff % 60000) / 1000);
      var parts = [];
      if (d > 0) parts.push(d + 'd');
      parts.push(h + 'h');
      parts.push(m + 'm');
      parts.push(s + 's');
      el.textContent = parts.join(' ');
    });
  }
  updateTimers();
  setInterval(updateTimers, 1000);
});
</script>
@endsection
