{{--
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems / AGPL v3+
  Featured marketplace listings + collections.
--}}
@extends('theme::layouts.1col')

@section('title', __('Featured'))

@section('content')
<div class="container my-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="fas fa-star text-warning me-2"></i>{{ __('Featured') }}</h1>
    <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-sm btn-outline-secondary">
      {{ __('Browse all') }} <i class="fas fa-arrow-right ms-1"></i>
    </a>
  </div>

  @if(!empty($featuredListings))
    <h2 class="h5 mt-4 mb-2">{{ __('Featured listings') }}</h2>
    <div class="row g-3">
      @foreach($featuredListings as $listing)
        <div class="col-sm-6 col-md-4 col-lg-3">
          <div class="card h-100 shadow-sm">
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
                <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="text-decoration-none">
                  {{ \Illuminate\Support\Str::limit($listing->title ?? 'Untitled', 50) }}
                </a>
              </h6>
              @if(!empty($listing->seller_name))
                <small class="text-muted d-block mb-1">{{ __('By') }} {{ $listing->seller_name }}</small>
              @endif
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold">
                  @if(!empty($listing->price_on_request))
                    <span class="text-muted">{{ __('POR') }}</span>
                  @else
                    {{ $listing->currency ?? '' }} {{ number_format((float) ($listing->price ?? 0), 2) }}
                  @endif
                </span>
                @if(!empty($listing->sector))
                  <span class="badge bg-info">{{ ucfirst($listing->sector) }}</span>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @else
    <div class="alert alert-light border text-center my-4">
      <i class="fas fa-star fa-2x text-muted mb-2 d-block"></i>
      <p class="mb-1"><strong>{{ __('No featured listings yet.') }}</strong></p>
      <small class="text-muted">{{ __('Featured items appear here once sellers are flagged "featured" or listings cross 50 views.') }}</small>
    </div>
  @endif

  @if(!empty($featuredCollections))
    <h2 class="h5 mt-4 mb-2">{{ __('Featured collections') }}</h2>
    <div class="row g-3">
      @foreach($featuredCollections as $collection)
        <div class="col-sm-6 col-md-4">
          <div class="card h-100 shadow-sm">
            <a href="{{ route('ahgmarketplace.collection', ['id' => $collection->id ?? null]) }}" class="text-decoration-none text-dark">
              <div class="card-body">
                <h6 class="card-title mb-1">
                  <i class="fas fa-layer-group me-1 text-secondary"></i>
                  {{ $collection->title ?? $collection->name ?? __('Untitled collection') }}
                </h6>
                @if(!empty($collection->description))
                  <p class="text-muted small mb-2">{{ \Illuminate\Support\Str::limit($collection->description, 120) }}</p>
                @endif
                @if(isset($collection->item_count))
                  <span class="badge bg-secondary">{{ $collection->item_count }} {{ __('items') }}</span>
                @endif
              </div>
            </a>
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>
@endsection
