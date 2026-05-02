{{--
  Marketplace — Public Browse

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/browseSuccess.php.
  Renders sector/category/type/price/condition filters + paginated listing grid.
--}}
@extends('theme::layouts.1col')
@section('title', __('Marketplace'))
@section('body-class', 'marketplace browse')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Marketplace') }}</li>
  </ol>
</nav>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<div class="row mb-4 align-items-center">
  <div class="col">
    <h1 class="h3 mb-1">{{ __('Marketplace') }}</h1>
    <p class="text-muted mb-0">{{ __(':count listings available', ['count' => number_format($total ?? 0)]) }}</p>
  </div>
  @auth
  <div class="col-auto">
    <a href="{{ route('ahgmarketplace.my-favourites') }}" class="btn btn-outline-danger me-1" title="{{ __('My favourites') }}">
      <i class="fas fa-heart me-1"></i>{{ __('My Favourites') }}
    </a>
    <a href="{{ route('ahgmarketplace.dashboard') }}" class="btn btn-primary">
      <i class="fas fa-store me-1"></i> {{ __('Sell') }}
    </a>
  </div>
  @endauth
</div>

<div class="row">
  {{-- Filter sidebar --}}
  <div class="col-lg-3 mb-4">
    <div class="d-lg-none mb-3">
      <button class="btn btn-outline-secondary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterSidebar" aria-expanded="false">
        <i class="fas fa-filter me-1"></i> {{ __('Filters') }}
      </button>
    </div>
    <div class="collapse d-lg-block" id="filterSidebar">
      <form method="GET" id="marketplace-filter-form">
        @auth
        <div class="card mb-3">
          <div class="card-body">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="favourites" value="1" id="favourites-only" {{ !empty($favouritesOnly) ? 'checked' : '' }}>
              <label class="form-check-label fw-semibold" for="favourites-only">
                <i class="fas fa-heart text-danger me-1"></i>{{ __('My favourites only') }}
              </label>
            </div>
          </div>
        </div>
        @endauth
        <div class="card mb-3">
          <div class="card-header fw-semibold">{{ __('Sector') }}</div>
          <div class="card-body">
            @foreach($sectors ?? [] as $s)
              @php
                $checked = isset($filters['sector']) && (
                  (is_array($filters['sector']) && in_array($s, $filters['sector']))
                  || $filters['sector'] === $s
                );
              @endphp
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="sector[]" value="{{ $s }}" id="sector-{{ $s }}" {{ $checked ? 'checked' : '' }}>
                <label class="form-check-label" for="sector-{{ $s }}">
                  {{ ucfirst($s) }}
                  @if(isset($facets['sectors'][$s]))
                    <span class="badge bg-secondary ms-1">{{ (int) $facets['sectors'][$s] }}</span>
                  @endif
                </label>
              </div>
            @endforeach
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header fw-semibold">{{ __('Category') }}</div>
          <div class="card-body" style="max-height:200px;overflow-y:auto;">
            @foreach($categories ?? [] as $cat)
              @php
                $checked = isset($filters['category_id']) && (
                  (is_array($filters['category_id']) && in_array($cat->id, $filters['category_id']))
                  || $filters['category_id'] == $cat->id
                );
              @endphp
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="category_id[]" value="{{ (int) $cat->id }}" id="cat-{{ (int) $cat->id }}" {{ $checked ? 'checked' : '' }}>
                <label class="form-check-label" for="cat-{{ (int) $cat->id }}">{{ $cat->name ?? '' }}</label>
              </div>
            @endforeach
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header fw-semibold">{{ __('Listing Type') }}</div>
          <div class="card-body">
            @php $types = ['fixed_price' => __('Buy Now'), 'auction' => __('Auction'), 'offer_only' => __('Make an Offer')]; @endphp
            @foreach($types as $val => $label)
              <div class="form-check">
                <input class="form-check-input" type="radio" name="listing_type" value="{{ $val }}" id="type-{{ $val }}" {{ (($filters['listing_type'] ?? '') === $val) ? 'checked' : '' }}>
                <label class="form-check-label" for="type-{{ $val }}">{{ $label }}</label>
              </div>
            @endforeach
            <div class="form-check">
              <input class="form-check-input" type="radio" name="listing_type" value="" id="type-all" {{ empty($filters['listing_type']) ? 'checked' : '' }}>
              <label class="form-check-label" for="type-all">{{ __('All Types') }}</label>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header fw-semibold">{{ __('Price Range') }}</div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-6">
                <input type="number" class="form-control form-control-sm" name="price_min" placeholder="{{ __('Min') }}" value="{{ $filters['price_min'] ?? '' }}" min="0" step="0.01">
              </div>
              <div class="col-6">
                <input type="number" class="form-control form-control-sm" name="price_max" placeholder="{{ __('Max') }}" value="{{ $filters['price_max'] ?? '' }}" min="0" step="0.01">
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header fw-semibold">{{ __('Condition') }}</div>
          <div class="card-body">
            <select name="condition_rating" class="form-select form-select-sm">
              <option value="">{{ __('Any Condition') }}</option>
              @php $conditions = ['mint' => __('Mint'), 'excellent' => __('Excellent'), 'good' => __('Good'), 'fair' => __('Fair'), 'poor' => __('Poor')]; @endphp
              @foreach($conditions as $val => $label)
                <option value="{{ $val }}" {{ (($filters['condition_rating'] ?? '') === $val) ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 mb-2">{{ __('Apply Filters') }}</button>
        <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-outline-secondary w-100">{{ __('Clear Filters') }}</a>
      </form>
    </div>
  </div>

  {{-- Main content --}}
  <div class="col-lg-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="d-flex align-items-center gap-2">
        <label class="form-label mb-0 text-nowrap">{{ __('Sort by') }}:</label>
        <select class="form-select form-select-sm" id="marketplace-sort" style="width:auto;">
          @php $sortOptions = ['newest' => __('Newest'), 'price_asc' => __('Price: Low to High'), 'price_desc' => __('Price: High to Low'), 'popular' => __('Popular'), 'ending_soon' => __('Ending Soon')]; @endphp
          @foreach($sortOptions as $val => $label)
            <option value="{{ $val }}" {{ (($filters['sort'] ?? '') === $val) ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="btn-group" role="group" aria-label="{{ __('View mode') }}">
        <button type="button" class="btn btn-sm btn-outline-secondary active" id="view-grid" title="{{ __('Grid view') }}"><i class="fas fa-th"></i></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="view-list" title="{{ __('List view') }}"><i class="fas fa-list"></i></button>
      </div>
    </div>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3" id="listings-grid">
      @forelse($listings ?? [] as $listing)
        <div class="col">
          <div class="card h-100 position-relative">
            @auth
              @php $favOn = in_array((int) $listing->id, $favouritedIds ?? [], true); @endphp
              <button type="button"
                      class="btn btn-light position-absolute top-0 end-0 m-2 rounded-circle shadow-sm fav-toggle"
                      style="z-index:2;width:36px;height:36px;padding:0;"
                      data-listing-id="{{ (int) $listing->id }}"
                      title="{{ $favOn ? __('Remove from favourites') : __('Add to favourites') }}">
                <i class="{{ $favOn ? 'fas' : 'far' }} fa-heart text-danger"></i>
              </button>
            @endauth
            <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="text-decoration-none">
              @if(!empty($listing->featured_image_path))
                <img src="{{ $listing->featured_image_path }}" class="card-img-top" alt="{{ $listing->title ?? '' }}" style="height:200px;object-fit:cover;">
              @else
                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:200px;">
                  <i class="fas fa-image fa-3x text-muted"></i>
                </div>
              @endif
            </a>
            <div class="card-body">
              <h6 class="card-title">
                <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="text-decoration-none">{{ \Illuminate\Support\Str::limit($listing->title ?? 'Untitled', 50) }}</a>
              </h6>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold">
                  @if(!empty($listing->price_on_request))
                    <span class="text-muted">{{ __('POR') }}</span>
                  @else
                    {{ $listing->currency ?? '' }} {{ number_format((float) ($listing->price ?? 0), 2) }}
                  @endif
                </span>
                <span class="badge bg-info">{{ ucfirst($listing->sector ?? '') }}</span>
                @if(!empty($listing->has_3d))
                  <span class="badge bg-dark ms-1" title="{{ __('3D model available') }}"><i class="fas fa-cube me-1"></i>3D</span>
                @endif
              </div>
              @auth
                @if(($listing->listing_type ?? '') === 'fixed_price' && empty($listing->price_on_request))
                  <form action="{{ route('cart.listing-add', ['listingId' => (int) $listing->id]) }}" method="POST" class="d-grid">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success">
                      <i class="fas fa-cart-plus me-1"></i>{{ __('Add to cart') }}
                    </button>
                  </form>
                @elseif(($listing->listing_type ?? '') === 'auction')
                  <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="btn btn-sm btn-outline-warning d-block">
                    <i class="fas fa-gavel me-1"></i>{{ __('Place bid') }}
                  </a>
                @elseif(($listing->listing_type ?? '') === 'offer_only')
                  <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="btn btn-sm btn-outline-primary d-block">
                    <i class="fas fa-handshake me-1"></i>{{ __('Make offer') }}
                  </a>
                @endif
              @endauth
            </div>
          </div>
        </div>
      @empty
        <div class="col-12">
          <div class="text-center py-5">
            <i class="fas fa-store fa-3x text-muted mb-3"></i>
            <h5>{{ __('No listings found') }}</h5>
            <p class="text-muted">{{ __('Try adjusting your filters or browse all listings.') }}</p>
            <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-primary">{{ __('Browse All') }}</a>
          </div>
        </div>
      @endforelse
    </div>

    @php $totalPages = (int) ceil(($total ?? 0) / ($limit ?? 24)); @endphp
    @if($totalPages > 1)
      <nav aria-label="{{ __('Page navigation') }}" class="mt-4">
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
  </div>
</div>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  var sortSelect = document.getElementById('marketplace-sort');
  if (sortSelect) {
    sortSelect.addEventListener('change', function() {
      var url = new URL(window.location.href);
      url.searchParams.set('sort', this.value);
      url.searchParams.delete('page');
      window.location.href = url.toString();
    });
  }
  var grid = document.getElementById('listings-grid');
  var btnGrid = document.getElementById('view-grid');
  var btnList = document.getElementById('view-list');
  if (btnGrid && btnList && grid) {
    btnGrid.addEventListener('click', function() {
      grid.className = 'row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3';
      btnGrid.classList.add('active');
      btnList.classList.remove('active');
    });
    btnList.addEventListener('click', function() {
      grid.className = 'row row-cols-1 g-3';
      btnList.classList.add('active');
      btnGrid.classList.remove('active');
    });
  }

  // Heart toggle (fav) — shared on browse / sector / listing pages
  var csrf = document.querySelector('meta[name="csrf-token"]');
  document.querySelectorAll('.fav-toggle').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var id = btn.getAttribute('data-listing-id');
      if (!id) return;
      var icon = btn.querySelector('i');
      btn.disabled = true;
      fetch('/marketplace/api/' + id + '/favourite', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
          'Accept': 'application/json',
        },
      })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.favourited) {
          icon.classList.remove('far'); icon.classList.add('fas');
          btn.title = 'Remove from favourites';
        } else {
          icon.classList.remove('fas'); icon.classList.add('far');
          btn.title = 'Add to favourites';
        }
      })
      .catch(function () { /* ignore */ })
      .finally(function () { btn.disabled = false; });
    });
  });
});
</script>
@endpush
@endsection
