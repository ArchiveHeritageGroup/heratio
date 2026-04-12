{{--
  Marketplace — Search

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/searchSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Search Marketplace'))
@section('body-class', 'marketplace search')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Search') }}</li>
  </ol>
</nav>

<div class="row justify-content-center mb-4">
  <div class="col-lg-8">
    <form method="GET" action="{{ route('ahgmarketplace.search') }}">
      <div class="input-group input-group-lg">
        <input type="text" class="form-control" name="query" value="{{ $query ?? '' }}" placeholder="{{ __('Search listings, artists, categories...') }}" autofocus>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-search me-1"></i> {{ __('Search') }}
        </button>
      </div>
    </form>
  </div>
</div>

@if(!empty($query))
  <p class="text-muted mb-3">{{ __(':count results for ":q"', ['count' => number_format($total ?? 0), 'q' => $query]) }}</p>
@endif

<div class="row">
  <div class="col-lg-3 mb-4">
    <div class="d-lg-none mb-3">
      <button class="btn btn-outline-secondary btn-sm w-100" type="button" data-bs-toggle="collapse" data-bs-target="#searchFilters">
        <i class="fas fa-filter me-1"></i> {{ __('Filters') }}
      </button>
    </div>
    <div class="collapse d-lg-block" id="searchFilters">
      <form method="GET" action="{{ route('ahgmarketplace.search') }}">
        <input type="hidden" name="query" value="{{ $query ?? '' }}">

        <div class="card mb-3">
          <div class="card-header py-2 fw-semibold small">{{ __('Listing Type') }}</div>
          <div class="card-body py-2">
            @php $types = ['' => __('All'), 'fixed_price' => __('Buy Now'), 'auction' => __('Auction'), 'offer_only' => __('Offers')]; @endphp
            @foreach($types as $val => $label)
              <div class="form-check form-check-sm">
                <input class="form-check-input" type="radio" name="listing_type" value="{{ $val }}" id="st-{{ $val ?: 'all' }}" {{ ($filters['listing_type'] ?? '') === $val ? 'checked' : '' }}>
                <label class="form-check-label small" for="st-{{ $val ?: 'all' }}">{{ $label }}</label>
              </div>
            @endforeach
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header py-2 fw-semibold small">{{ __('Price Range') }}</div>
          <div class="card-body py-2">
            <div class="row g-2">
              <div class="col-6"><input type="number" class="form-control form-control-sm" name="price_min" placeholder="{{ __('Min') }}" value="{{ $filters['price_min'] ?? '' }}" min="0" step="0.01"></div>
              <div class="col-6"><input type="number" class="form-control form-control-sm" name="price_max" placeholder="{{ __('Max') }}" value="{{ $filters['price_max'] ?? '' }}" min="0" step="0.01"></div>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header py-2 fw-semibold small">{{ __('Sector') }}</div>
          <div class="card-body py-2">
            @foreach(['gallery', 'museum', 'archive', 'library', 'dam'] as $s)
              @php
                $checked = isset($filters['sector']) && (
                  (is_array($filters['sector']) && in_array($s, $filters['sector']))
                  || $filters['sector'] === $s
                );
              @endphp
              <div class="form-check form-check-sm">
                <input class="form-check-input" type="checkbox" name="sector[]" value="{{ $s }}" id="ss-{{ $s }}" {{ $checked ? 'checked' : '' }}>
                <label class="form-check-label small" for="ss-{{ $s }}">
                  {{ ucfirst($s) }}
                  @if(isset($facets['sectors'][$s]))
                    <span class="badge bg-secondary">{{ (int) $facets['sectors'][$s] }}</span>
                  @endif
                </label>
              </div>
            @endforeach
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header py-2 fw-semibold small">{{ __('Condition') }}</div>
          <div class="card-body py-2">
            <select name="condition_rating" class="form-select form-select-sm">
              <option value="">{{ __('Any') }}</option>
              @foreach(['mint' => __('Mint'), 'excellent' => __('Excellent'), 'good' => __('Good'), 'fair' => __('Fair'), 'poor' => __('Poor')] as $val => $label)
                <option value="{{ $val }}" {{ ($filters['condition_rating'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">{{ __('Apply') }}</button>
        <a href="{{ route('ahgmarketplace.search', ['query' => $query ?? '']) }}" class="btn btn-outline-secondary btn-sm w-100">{{ __('Clear') }}</a>
      </form>
    </div>
  </div>

  <div class="col-lg-9">
    @if(!empty($results) && count($results) > 0)
      <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3">
        @foreach($results as $listing)
          <div class="col">
            <div class="card h-100">
              @if(!empty($listing->featured_image_path))
                <img src="{{ $listing->featured_image_path }}" class="card-img-top" alt="" style="height:180px;object-fit:cover;">
              @else
                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:180px;">
                  <i class="fas fa-image fa-3x text-muted"></i>
                </div>
              @endif
              <div class="card-body">
                <h6 class="card-title">
                  <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="text-decoration-none">{{ \Illuminate\Support\Str::limit($listing->title ?? '', 50) }}</a>
                </h6>
                <div class="fw-bold small">
                  @if(!empty($listing->price_on_request))
                    <span class="text-muted">{{ __('POR') }}</span>
                  @else
                    {{ $listing->currency ?? '' }} {{ number_format((float) ($listing->price ?? 0), 2) }}
                  @endif
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      @php $totalPages = (int) ceil(($total ?? 0) / 24); @endphp
      @if($totalPages > 1)
        <nav aria-label="{{ __('Page navigation') }}" class="mt-4">
          <ul class="pagination justify-content-center">
            <li class="page-item{{ ($page ?? 1) <= 1 ? ' disabled' : '' }}">
              <a class="page-link" href="?query={{ urlencode($query ?? '') }}&page={{ ($page ?? 1) - 1 }}">&laquo;</a>
            </li>
            @for($i = max(1, ($page ?? 1) - 2); $i <= min($totalPages, ($page ?? 1) + 2); $i++)
              <li class="page-item{{ $i === ($page ?? 1) ? ' active' : '' }}">
                <a class="page-link" href="?query={{ urlencode($query ?? '') }}&page={{ $i }}">{{ $i }}</a>
              </li>
            @endfor
            <li class="page-item{{ ($page ?? 1) >= $totalPages ? ' disabled' : '' }}">
              <a class="page-link" href="?query={{ urlencode($query ?? '') }}&page={{ ($page ?? 1) + 1 }}">&raquo;</a>
            </li>
          </ul>
        </nav>
      @endif
    @else
      <div class="text-center py-5">
        <i class="fas fa-search fa-3x text-muted mb-3"></i>
        <h5>{{ __('No results found') }}</h5>
        @if(!empty($query))
          <p class="text-muted">{{ __('No listings match ":q". Try different keywords or broaden your filters.', ['q' => $query]) }}</p>
        @endif
        <div class="mt-3">
          <p class="small text-muted mb-2">{{ __('Suggestions:') }}</p>
          <ul class="list-unstyled small text-muted">
            <li>{{ __('Check for typos or use more general terms') }}</li>
            <li>{{ __('Remove some filters to see more results') }}</li>
            <li>{{ __('Try browsing by sector or category') }}</li>
          </ul>
        </div>
        <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-primary mt-2">{{ __('Browse All Listings') }}</a>
      </div>
    @endif
  </div>
</div>
@endsection
