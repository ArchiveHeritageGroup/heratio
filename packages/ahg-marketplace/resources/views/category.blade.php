{{--
  Marketplace — Category Browse

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/categorySuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', ($category->name ?? 'Category') . ' - ' . ucfirst($sector ?? '') . ' ' . __('Marketplace'))
@section('body-class', 'marketplace category')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.sector', ['sector' => $sector ?? '']) }}">{{ ucfirst($sector ?? '') }}</a></li>
    <li class="breadcrumb-item active">{{ $category->name ?? '' }}</li>
  </ol>
</nav>

<div class="row mb-4">
  <div class="col">
    <h1 class="h3 mb-1">{{ $category->name ?? '' }}</h1>
    @if(!empty($category->description))
      <p class="text-muted mb-1">{{ $category->description }}</p>
    @endif
    <p class="small text-muted">{{ __(':count listings', ['count' => number_format($total ?? 0)]) }}</p>
  </div>
</div>

<div class="row">
  <div class="col-lg-3 mb-4">
    <div class="d-lg-none mb-3">
      <button class="btn btn-outline-secondary btn-sm w-100" type="button" data-bs-toggle="collapse" data-bs-target="#catFilters">
        <i class="fas fa-filter me-1"></i> {{ __('Filters') }}
      </button>
    </div>
    <div class="collapse d-lg-block" id="catFilters">
      <form method="GET">
        <div class="card mb-3">
          <div class="card-header py-2 fw-semibold small">{{ __('Listing Type') }}</div>
          <div class="card-body py-2">
            <select name="listing_type" class="form-select form-select-sm">
              <option value="">{{ __('All Types') }}</option>
              <option value="fixed_price" {{ request('listing_type') === 'fixed_price' ? 'selected' : '' }}>{{ __('Buy Now') }}</option>
              <option value="auction" {{ request('listing_type') === 'auction' ? 'selected' : '' }}>{{ __('Auction') }}</option>
              <option value="offer_only" {{ request('listing_type') === 'offer_only' ? 'selected' : '' }}>{{ __('Make an Offer') }}</option>
            </select>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header py-2 fw-semibold small">{{ __('Price Range') }}</div>
          <div class="card-body py-2">
            <div class="row g-2">
              <div class="col-6">
                <input type="number" class="form-control form-control-sm" name="price_min" placeholder="{{ __('Min') }}" value="{{ request('price_min') }}" min="0" step="0.01">
              </div>
              <div class="col-6">
                <input type="number" class="form-control form-control-sm" name="price_max" placeholder="{{ __('Max') }}" value="{{ request('price_max') }}" min="0" step="0.01">
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header py-2 fw-semibold small">{{ __('Condition') }}</div>
          <div class="card-body py-2">
            <select name="condition_rating" class="form-select form-select-sm">
              <option value="">{{ __('Any') }}</option>
              @foreach(['mint', 'excellent', 'good', 'fair', 'poor'] as $cond)
                <option value="{{ $cond }}" {{ request('condition_rating') === $cond ? 'selected' : '' }}>{{ ucfirst($cond) }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header py-2 fw-semibold small">{{ __('Sort') }}</div>
          <div class="card-body py-2">
            <select name="sort" class="form-select form-select-sm">
              <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>{{ __('Newest') }}</option>
              <option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>{{ __('Price: Low to High') }}</option>
              <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>{{ __('Price: High to Low') }}</option>
              <option value="popular" {{ request('sort') === 'popular' ? 'selected' : '' }}>{{ __('Popular') }}</option>
            </select>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('Apply') }}</button>
      </form>
    </div>
  </div>

  <div class="col-lg-9">
    @if(!empty($listings) && count($listings) > 0)
      <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3">
        @foreach($listings as $listing)
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
        <nav class="mt-4">
          <ul class="pagination justify-content-center">
            <li class="page-item{{ ($page ?? 1) <= 1 ? ' disabled' : '' }}">
              <a class="page-link" href="?page={{ ($page ?? 1) - 1 }}">&laquo;</a>
            </li>
            @for($i = max(1, ($page ?? 1) - 2); $i <= min($totalPages, ($page ?? 1) + 2); $i++)
              <li class="page-item{{ $i === ($page ?? 1) ? ' active' : '' }}">
                <a class="page-link" href="?page={{ $i }}">{{ $i }}</a>
              </li>
            @endfor
            <li class="page-item{{ ($page ?? 1) >= $totalPages ? ' disabled' : '' }}">
              <a class="page-link" href="?page={{ ($page ?? 1) + 1 }}">&raquo;</a>
            </li>
          </ul>
        </nav>
      @endif
    @else
      <div class="text-center py-5">
        <i class="fas fa-tag fa-3x text-muted mb-3"></i>
        <h5>{{ __('No listings in this category') }}</h5>
        <p class="text-muted">{{ __('Check back later or browse other categories.') }}</p>
        <a href="{{ route('ahgmarketplace.sector', ['sector' => $sector ?? '']) }}" class="btn btn-primary">{{ __('Back to :sector', ['sector' => ucfirst($sector ?? '')]) }}</a>
      </div>
    @endif
  </div>
</div>
@endsection
