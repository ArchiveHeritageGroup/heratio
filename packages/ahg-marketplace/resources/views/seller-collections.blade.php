{{--
  Marketplace — Seller Collections List

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/sellerCollectionsSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('My Collections') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace seller-collections')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item active">{{ __('My Collections') }}</li>
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

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">{{ __('My Collections') }}</h1>
  <a href="{{ route('ahgmarketplace.seller-collection-create') }}" class="btn btn-primary">
    <i class="fas fa-plus me-1"></i> {{ __('Create Collection') }}
  </a>
</div>

@if(empty($collections) || count($collections) === 0)
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-layer-group fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('No collections yet') }}</h5>
      <p class="text-muted">{{ __('Create collections to group and showcase your listings.') }}</p>
      <a href="{{ route('ahgmarketplace.seller-collection-create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> {{ __('Create Collection') }}
      </a>
    </div>
  </div>
@else
  <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
    @foreach($collections as $col)
      <div class="col">
        <div class="card h-100">
          @if(!empty($col->cover_image_path))
            <img src="{{ $col->cover_image_path }}" class="card-img-top" alt="{{ $col->title ?? '' }}" style="height:180px;object-fit:cover;">
          @else
            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:180px;">
              <i class="fas fa-layer-group fa-3x text-muted"></i>
            </div>
          @endif
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h6 class="card-title mb-0">{{ $col->title ?? '' }}</h6>
              <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $col->collection_type ?? 'curated')) }}</span>
            </div>
            @if(isset($col->item_count))
              <p class="small text-muted mb-2">{{ __(':count items', ['count' => (int) $col->item_count]) }}</p>
            @endif
            @if(!empty($col->description))
              <p class="small text-muted mb-0">{{ \Illuminate\Support\Str::limit($col->description, 100) }}</p>
            @endif
          </div>
          <div class="card-footer bg-transparent d-flex gap-2">
            <a href="{{ route('ahgmarketplace.seller-collection-create', ['id' => $col->id]) }}" class="btn btn-sm btn-outline-primary flex-grow-1">
              <i class="fas fa-edit me-1"></i>{{ __('Edit') }}
            </a>
            <form method="POST" action="{{ route('ahgmarketplace.seller-collections.post') }}" class="d-inline">
              @csrf
              <input type="hidden" name="form_action" value="delete">
              <input type="hidden" name="collection_id" value="{{ (int) $col->id }}">
              <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('{{ __('Delete this collection?') }}');">
                <i class="fas fa-trash"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endif
@endsection
