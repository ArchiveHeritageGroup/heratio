{{--
  Marketplace per-sector landing page.
  Receives: $sector, $listings (Collection), $total, $categories, $page.
--}}
@extends('theme::layouts.1col')

@php
  $sectorMeta = [
    'gallery' => ['label' => 'Gallery',   'icon' => 'fa-palette',  'color' => 'info',
                  'tagline' => 'Original artworks, paintings, sculptures, and limited editions.'],
    'museum'  => ['label' => 'Museum',    'icon' => 'fa-landmark', 'color' => 'warning',
                  'tagline' => 'Museum-grade objects, antiquities, and curated collections.'],
    'archive' => ['label' => 'Archive',   'icon' => 'fa-archive',  'color' => 'success',
                  'tagline' => 'Archival material, manuscripts, ephemera, and historical records.'],
    'library' => ['label' => 'Library',   'icon' => 'fa-book',     'color' => 'primary',
                  'tagline' => 'Rare books, periodicals, and printed material.'],
    'dam'     => ['label' => 'Photo / DAM','icon' => 'fa-images',   'color' => 'danger',
                  'tagline' => 'Digital assets, photography, and licensable media.'],
  ];
  $meta = $sectorMeta[$sector] ?? ['label' => ucfirst($sector), 'icon' => 'fa-store', 'color' => 'secondary', 'tagline' => ''];
@endphp

@section('title', $meta['label'] . ' Marketplace')
@section('body-class', 'marketplace sector')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $meta['label'] }}</li>
  </ol>
</nav>

{{-- Sector hero --}}
<section class="rounded shadow-sm mb-4 p-4"
         style="background:linear-gradient(135deg, var(--ahg-primary, #1d6a52) 0%, #134537 100%); color:#fff;">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h1 class="h3 mb-1">
        <i class="fas {{ $meta['icon'] }} me-2"></i>{{ $meta['label'] }} Marketplace
      </h1>
      @if(!empty($meta['tagline']))
        <p class="mb-0 lh-base">{{ $meta['tagline'] }}</p>
      @endif
      <p class="mb-0 small mt-1 text-white-50">{{ number_format($total) }} {{ __('listings available') }}</p>
    </div>
    <a href="{{ route('ahgmarketplace.browse', ['sector' => [$sector]]) }}" class="btn btn-warning fw-semibold">
      <i class="fas fa-filter me-1"></i> {{ __('Browse with filters') }}
    </a>
  </div>
</section>

{{-- Categories within this sector --}}
@if(!empty($categories))
  <div class="d-flex flex-wrap gap-2 mb-4">
    <span class="text-muted small me-2">{{ __('Browse by category:') }}</span>
    @foreach($categories as $cat)
      <a href="{{ route('ahgmarketplace.browse', ['sector' => [$sector], 'category_id' => $cat->id]) }}"
         class="badge bg-light text-dark border text-decoration-none">
        {{ $cat->name }}
      </a>
    @endforeach
  </div>
@endif

{{-- Listings grid --}}
@if(empty($listings))
  <div class="card text-center py-5">
    <div class="card-body">
      <i class="fas fa-store fa-3x text-muted mb-3"></i>
      <h5>{{ __('No listings in :s yet', ['s' => $meta['label']]) }}</h5>
      <p class="text-muted">{{ __('Check back soon, or browse other sectors.') }}</p>
      <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-primary">
        <i class="fas fa-shopping-bag me-1"></i> {{ __('Browse all listings') }}
      </a>
    </div>
  </div>
@else
  <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3 mb-4">
    @foreach($listings as $listing)
      <div class="col">
        <div class="card h-100 shadow-sm">
          <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="text-decoration-none">
            @if(!empty($listing->featured_image_path))
              <img src="{{ $listing->featured_image_path }}" class="card-img-top"
                   alt="{{ $listing->title ?? '' }}" style="height:200px;object-fit:cover;" loading="lazy">
            @else
              <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:200px;">
                <i class="fas {{ $meta['icon'] }} fa-3x text-muted"></i>
              </div>
            @endif
          </a>
          <div class="card-body">
            <h6 class="card-title mb-1">
              <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="text-decoration-none">
                {{ \Illuminate\Support\Str::limit($listing->title ?? 'Untitled', 60) }}
              </a>
            </h6>
            @if(!empty($listing->artist_name))
              <p class="small text-muted mb-2">{{ __('by :n', ['n' => $listing->artist_name]) }}</p>
            @endif
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-bold">
                @if(!empty($listing->price_on_request))
                  <span class="text-muted">{{ __('Price on request') }}</span>
                @else
                  {{ $listing->currency ?? '' }} {{ number_format((float) ($listing->price ?? 0), 2) }}
                @endif
              </span>
              <span class="badge bg-{{ $meta['color'] }}">
                @if(($listing->listing_type ?? '') === 'auction')
                  <i class="fas fa-gavel me-1"></i>{{ __('Auction') }}
                @elseif(($listing->listing_type ?? '') === 'licence')
                  <i class="fas fa-file-contract me-1"></i>{{ __('Licence') }}
                @elseif(($listing->listing_type ?? '') === 'offer_only')
                  <i class="fas fa-hand-holding-usd me-1"></i>{{ __('Offer') }}
                @else
                  {{ __('Buy') }}
                @endif
              </span>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endif

<div class="d-flex justify-content-between mt-3">
  <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> {{ __('All sectors') }}
  </a>
  @if(!empty($listings))
    <a href="{{ route('ahgmarketplace.browse', ['sector' => [$sector]]) }}" class="btn btn-outline-primary">
      <i class="fas fa-list me-1"></i> {{ __('Filter by category, price, condition…') }}
    </a>
  @endif
</div>

@endsection
