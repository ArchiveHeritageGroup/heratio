{{--
  Marketplace — Public Seller Profile

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/sellerSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', ($seller->display_name ?? 'Seller') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace seller-profile')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item active">{{ $seller->display_name ?? '' }}</li>
  </ol>
</nav>

@if(session('success') || session('notice'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') ?? session('notice') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

{{-- Banner + avatar --}}
<div class="position-relative mb-4">
  @if(!empty($seller->banner_path))
    <div class="rounded overflow-hidden" style="height:200px;">
      <img src="{{ $seller->banner_path }}" alt="" class="w-100 h-100" style="object-fit:cover;">
    </div>
  @else
    <div class="rounded bg-secondary" style="height:140px;"></div>
  @endif

  <div class="d-flex align-items-end ms-4" style="margin-top:-50px;position:relative;z-index:1;">
    @if(!empty($seller->avatar_path))
      <img src="{{ $seller->avatar_path }}" alt="" class="rounded-circle border border-3 border-white shadow" width="100" height="100" style="object-fit:cover;">
    @else
      <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center border border-3 border-white shadow" style="width:100px;height:100px;">
        <i class="fas fa-user fa-2x"></i>
      </div>
    @endif
    <div class="ms-3 mb-2">
      <h1 class="h4 mb-0">
        {{ $seller->display_name ?? '' }}
        @if(($seller->verification_status ?? '') === 'verified')
          <i class="fas fa-check-circle text-primary ms-1" title="{{ __('Verified') }}"></i>
        @endif
      </h1>
      <span class="badge bg-secondary">{{ ucfirst($seller->seller_type ?? '') }}</span>
      @if(!empty($seller->city) || !empty($seller->country))
        <span class="text-muted small ms-2">
          <i class="fas fa-map-marker-alt me-1"></i>{{ implode(', ', array_filter([$seller->city ?? '', $seller->country ?? ''])) }}
        </span>
      @endif
    </div>
  </div>
</div>

{{-- Stats row --}}
<div class="row text-center mb-4 g-3">
  <div class="col-6 col-md-3">
    <div class="card h-100">
      <div class="card-body py-3">
        <div class="h5 mb-0">{{ number_format($total ?? 0) }}</div>
        <small class="text-muted">{{ __('Listings') }}</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card h-100">
      <div class="card-body py-3">
        <div class="h5 mb-0">{{ number_format((int) ($seller->total_sales ?? 0)) }}</div>
        <small class="text-muted">{{ __('Sales') }}</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card h-100">
      <div class="card-body py-3">
        <div class="h5 mb-0">
          @if(($seller->average_rating ?? 0) > 0)
            @for($s = 1; $s <= 5; $s++)
              <i class="fa{{ $s <= round($seller->average_rating) ? 's' : 'r' }} fa-star text-warning" style="font-size:0.85rem;"></i>
            @endfor
          @else
            &mdash;
          @endif
        </div>
        <small class="text-muted">{{ __('Rating (:count)', ['count' => (int) ($seller->rating_count ?? 0)]) }}</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card h-100">
      <div class="card-body py-3">
        <div class="h5 mb-0">{{ number_format($followerCount ?? 0) }}</div>
        <small class="text-muted">{{ __('Followers') }}</small>
      </div>
    </div>
  </div>
</div>

{{-- Follow button (only shown when a follow route exists) --}}
@auth
  @if(Route::has('ahgmarketplace.follow'))
    <div class="mb-4">
      <form method="POST" action="{{ route('ahgmarketplace.follow', ['seller' => $seller->slug ?? '']) }}" class="d-inline">
        @csrf
        @if($isFollowing ?? false)
          <button type="submit" class="btn btn-outline-secondary">
            <i class="fas fa-user-check me-1"></i> {{ __('Following') }}
          </button>
        @else
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-user-plus me-1"></i> {{ __('Follow') }}
          </button>
        @endif
      </form>
    </div>
  @endif
@endauth

{{-- Bio --}}
@if(!empty($seller->bio))
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title">{{ __('About') }}</h5>
      <p class="mb-0">{!! nl2br(e($seller->bio)) !!}</p>
      @if(!empty($seller->website))
        <p class="mt-2 mb-0"><a href="{{ $seller->website }}" target="_blank" rel="noopener"><i class="fas fa-globe me-1"></i>{{ $seller->website }}</a></p>
      @endif
      @if(!empty($seller->instagram))
        <p class="mt-1 mb-0"><a href="https://instagram.com/{{ ltrim($seller->instagram, '@') }}" target="_blank" rel="noopener"><i class="fab fa-instagram me-1"></i>{{ $seller->instagram }}</a></p>
      @endif
    </div>
  </div>
@endif

{{-- Tabs --}}
<ul class="nav nav-tabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-listings" data-bs-toggle="tab" data-bs-target="#panel-listings" type="button" role="tab">
      {{ __('Active Listings') }} <span class="badge bg-secondary">{{ number_format($total ?? 0) }}</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-collections" data-bs-toggle="tab" data-bs-target="#panel-collections" type="button" role="tab">
      {{ __('Collections') }} <span class="badge bg-secondary">{{ count($collections ?? []) }}</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-reviews" data-bs-toggle="tab" data-bs-target="#panel-reviews" type="button" role="tab">
      {{ __('Reviews') }}
    </button>
  </li>
</ul>

<div class="tab-content border border-top-0 rounded-bottom p-4">
  {{-- Active Listings --}}
  <div class="tab-pane fade show active" id="panel-listings" role="tabpanel">
    @if(!empty($listings) && count($listings) > 0)
      <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
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
              <li class="page-item{{ $i === ($page ?? 1) ? ' active' : '' }}"><a class="page-link" href="?page={{ $i }}">{{ $i }}</a></li>
            @endfor
            <li class="page-item{{ ($page ?? 1) >= $totalPages ? ' disabled' : '' }}">
              <a class="page-link" href="?page={{ ($page ?? 1) + 1 }}">&raquo;</a>
            </li>
          </ul>
        </nav>
      @endif
    @else
      <p class="text-muted text-center py-3">{{ __('No active listings at this time.') }}</p>
    @endif
  </div>

  {{-- Collections --}}
  <div class="tab-pane fade" id="panel-collections" role="tabpanel">
    @if(!empty($collections) && count($collections) > 0)
      <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
        @foreach($collections as $col)
          <div class="col">
            <div class="card h-100">
              @if(!empty($col->cover_image_path))
                <img src="{{ $col->cover_image_path }}" class="card-img-top" alt="{{ $col->title ?? '' }}" style="height:160px;object-fit:cover;">
              @else
                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:160px;">
                  <i class="fas fa-layer-group fa-2x text-muted"></i>
                </div>
              @endif
              <div class="card-body">
                <h6 class="card-title mb-1">
                  <a href="{{ route('ahgmarketplace.collection', ['slug' => $col->slug ?? '']) }}" class="text-decoration-none">{{ $col->title ?? '' }}</a>
                </h6>
                @if(!empty($col->description))
                  <p class="card-text small text-muted mb-0">{{ \Illuminate\Support\Str::limit($col->description, 100) }}</p>
                @endif
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @else
      <p class="text-muted text-center py-3">{{ __('No public collections.') }}</p>
    @endif
  </div>

  {{-- Reviews --}}
  <div class="tab-pane fade" id="panel-reviews" role="tabpanel">
    @if(!empty($ratingStats) && ($seller->rating_count ?? 0) > 0)
      <div class="row mb-4">
        <div class="col-md-4 text-center mb-3 mb-md-0">
          <div class="h1 mb-0">{{ number_format((float) ($seller->average_rating ?? 0), 1) }}</div>
          <div>
            @for($s = 1; $s <= 5; $s++)
              <i class="fa{{ $s <= round($seller->average_rating ?? 0) ? 's' : 'r' }} fa-star text-warning"></i>
            @endfor
          </div>
          <small class="text-muted">{{ __(':count reviews', ['count' => (int) ($seller->rating_count ?? 0)]) }}</small>
        </div>
        <div class="col-md-8">
          @for($star = 5; $star >= 1; $star--)
            @php
              $count = isset($ratingStats[$star]) ? (int) $ratingStats[$star] : 0;
              $pct = ($seller->rating_count ?? 0) > 0 ? round(($count / $seller->rating_count) * 100) : 0;
            @endphp
            <div class="d-flex align-items-center mb-1">
              <span class="small text-nowrap me-2" style="width:45px;">{{ $star }} <i class="fas fa-star text-warning small"></i></span>
              <div class="progress flex-grow-1" style="height:8px;">
                <div class="progress-bar bg-warning" style="width:{{ $pct }}%;"></div>
              </div>
              <span class="small text-muted ms-2" style="width:30px;">{{ $count }}</span>
            </div>
          @endfor
        </div>
      </div>
      <hr>
    @endif

    @if(!empty($reviews) && count($reviews) > 0)
      @foreach($reviews as $review)
        <div class="mb-3 pb-3 border-bottom">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              @for($s = 1; $s <= 5; $s++)
                <i class="fa{{ $s <= (int) ($review->rating ?? 0) ? 's' : 'r' }} fa-star text-warning small"></i>
              @endfor
              @if(!empty($review->title))
                <strong class="ms-2">{{ $review->title }}</strong>
              @endif
            </div>
            <small class="text-muted">{{ $review->created_at ?? '' }}</small>
          </div>
          @if(!empty($review->comment))
            <p class="small mt-1 mb-0">{!! nl2br(e($review->comment)) !!}</p>
          @endif
        </div>
      @endforeach
    @else
      <p class="text-muted text-center py-3">{{ __('No reviews yet.') }}</p>
    @endif
  </div>
</div>
@endsection
