{{--
  Marketplace — Manage Listing Images

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/sellerListingImagesSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Manage Images') . ' - ' . ($listing->title ?? ''))
@section('body-class', 'marketplace seller-listing-images')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.seller-listings') }}">{{ __('My Listings') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.seller-listing-edit', ['id' => $listing->id ?? 0]) }}">{{ $listing->title ?? '' }}</a></li>
    <li class="breadcrumb-item active">{{ __('Images') }}</li>
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

<h1 class="h3 mb-2">{{ __('Manage Images for: :title', ['title' => $listing->title ?? '']) }}</h1>
<p class="text-muted mb-4">{{ __(':count of :max images uploaded', ['count' => count($images ?? []), 'max' => $maxImages ?? 20]) }}</p>

<div class="card mb-4">
  <div class="card-header fw-semibold">{{ __('Upload New Image') }}</div>
  <div class="card-body">
    <form method="POST" action="{{ route('ahgmarketplace.seller-listing-images.post', ['id' => $listing->id ?? 0]) }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="form_action" value="upload">
      <div class="row align-items-end">
        <div class="col-md-5">
          <label for="image_file" class="form-label">{{ __('Image File') }} <span class="text-danger">*</span></label>
          <input type="file" class="form-control" id="image_file" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp" required>
          <div class="form-text">{{ __('JPEG, PNG, GIF or WebP. Max 10MB.') }}</div>
        </div>
        <div class="col-md-5">
          <label for="caption" class="form-label">{{ __('Caption') }}</label>
          <input type="text" class="form-control" id="caption" name="caption" maxlength="500" placeholder="{{ __('Optional image caption') }}">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-upload me-1"></i> {{ __('Upload') }}
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

@if(!empty($images) && count($images) > 0)
  <div class="card">
    <div class="card-header fw-semibold">{{ __('Current Images') }}</div>
    <div class="card-body">
      <div class="row g-3">
        @foreach($images as $img)
          <div class="col-6 col-md-4 col-lg-3">
            <div class="card h-100">
              <div class="position-relative">
                <img src="{{ $img->file_path ?? '' }}" alt="{{ $img->caption ?? '' }}" class="card-img-top" style="height:180px;object-fit:cover;">
                @if(!empty($img->is_primary))
                  <span class="badge bg-success position-absolute top-0 start-0 m-2">{{ __('Primary') }}</span>
                @endif
                <span class="badge bg-secondary position-absolute top-0 end-0 m-2">#{{ (int) ($img->sort_order ?? 0) }}</span>
              </div>
              <div class="card-body py-2 px-3">
                @if(!empty($img->caption))
                  <p class="small mb-2">{{ $img->caption }}</p>
                @endif
                <div class="d-flex gap-1">
                  @if(empty($img->is_primary))
                    <form method="POST" action="{{ route('ahgmarketplace.seller-listing-images.post', ['id' => $listing->id ?? 0]) }}" class="d-inline">
                      @csrf
                      <input type="hidden" name="form_action" value="set_primary">
                      <input type="hidden" name="image_id" value="{{ (int) $img->id }}">
                      <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Set as Primary') }}">
                        <i class="fas fa-star"></i>
                      </button>
                    </form>
                  @endif
                  <form method="POST" action="{{ route('ahgmarketplace.seller-listing-images.post', ['id' => $listing->id ?? 0]) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="image_id" value="{{ (int) $img->id }}">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}" onclick="return confirm('{{ __('Delete this image?') }}');">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
@else
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-images fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('No images uploaded yet') }}</h5>
      <p class="text-muted">{{ __('Upload images to showcase your listing.') }}</p>
    </div>
  </div>
@endif

<div class="mt-4">
  <a href="{{ route('ahgmarketplace.seller-listing-edit', ['id' => $listing->id ?? 0]) }}" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Listing') }}
  </a>
</div>
@endsection
