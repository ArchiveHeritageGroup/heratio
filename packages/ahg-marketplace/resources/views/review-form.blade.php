{{--
  Marketplace — Leave a Review

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/reviewFormSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Leave a Review') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace review-form')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.my-purchases') }}">{{ __('My Purchases') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Leave a Review') }}</li>
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
    <h1 class="h3 mb-4">{{ __('Leave a Review') }}</h1>

    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex">
          @if(!empty($transaction->featured_image_path))
            <img src="{{ $transaction->featured_image_path }}" alt="{{ $transaction->title ?? '' }}" class="rounded me-3" style="width:100px;height:100px;object-fit:cover;">
          @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" style="width:100px;height:100px;">
              <i class="fas fa-image fa-2x text-muted"></i>
            </div>
          @endif
          <div>
            <h5 class="mb-1">{{ $transaction->title ?? '' }}</h5>
            @if(!empty($transaction->seller_name))
              <p class="text-muted mb-1">{{ __('Sold by :name', ['name' => $transaction->seller_name]) }}</p>
            @endif
            <p class="mb-0">
              <span class="fw-semibold">{{ $transaction->currency ?? '' }} {{ number_format((float) ($transaction->grand_total ?? 0), 2) }}</span>
              <span class="text-muted ms-2">{{ __('Transaction #:n', ['n' => $transaction->transaction_number ?? '']) }}</span>
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0"><i class="fas fa-star me-2"></i>{{ __('Your Review') }}</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('ahgmarketplace.review-form.post', ['id' => $transaction->id ?? 0]) }}">
          @csrf
          <div class="mb-4">
            <label class="form-label">{{ __('Rating') }} <span class="text-danger">*</span></label>
            <div id="star-rating" class="d-flex gap-1">
              @for($s = 1; $s <= 5; $s++)
                <label class="star-label" style="cursor:pointer;font-size:2rem;">
                  <input type="radio" name="rating" value="{{ $s }}" class="d-none" required>
                  <i class="far fa-star text-warning star-icon" data-star="{{ $s }}"></i>
                </label>
              @endfor
            </div>
            <div class="form-text" id="rating-text">{{ __('Click a star to rate') }}</div>
          </div>
          <div class="mb-3">
            <label for="review_title" class="form-label">{{ __('Review Title') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="review_title" name="review_title" value="{{ old('review_title') }}" placeholder="{{ __('Summarize your experience') }}" maxlength="255" required>
          </div>
          <div class="mb-4">
            <label for="review_comment" class="form-label">{{ __('Your Review') }} <span class="text-muted">({{ __('optional') }})</span></label>
            <textarea class="form-control" id="review_comment" name="review_comment" rows="5" placeholder="{{ __('Share more details about your experience with this seller...') }}">{{ old('review_comment') }}</textarea>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <a href="{{ route('ahgmarketplace.my-purchases') }}" class="btn btn-outline-secondary">
              <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Purchases') }}
            </a>
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="fas fa-paper-plane me-1"></i> {{ __('Submit Review') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  var ratingLabels = ['', '{{ __('Poor') }}', '{{ __('Fair') }}', '{{ __('Good') }}', '{{ __('Very Good') }}', '{{ __('Excellent') }}'];
  var stars = document.querySelectorAll('#star-rating .star-icon');
  var ratingText = document.getElementById('rating-text');

  function highlightStars(count) {
    stars.forEach(function(star) {
      var val = parseInt(star.getAttribute('data-star'));
      star.className = (val <= count) ? 'fas fa-star text-warning star-icon' : 'far fa-star text-warning star-icon';
    });
  }

  stars.forEach(function(star) {
    star.addEventListener('mouseenter', function() {
      highlightStars(parseInt(this.getAttribute('data-star')));
    });
    star.parentElement.addEventListener('click', function() {
      var radio = this.querySelector('input');
      radio.checked = true;
      var val = parseInt(radio.value);
      highlightStars(val);
      ratingText.textContent = ratingLabels[val] || '';
    });
  });

  document.getElementById('star-rating').addEventListener('mouseleave', function() {
    var checked = document.querySelector('#star-rating input:checked');
    highlightStars(checked ? parseInt(checked.value) : 0);
  });
});
</script>
@endpush
@endsection
