{{--
  Marketplace — Create / Edit Collection

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/sellerCollectionCreateSuccess.php.
  Dual-mode: create if $collection is null, edit if populated.
--}}
@extends('theme::layouts.1col')
@php
  $isEdit = !empty($collection);
  $pageTitle = $isEdit ? __('Edit Collection') : __('Create Collection');
  $types = ['curated' => __('Curated'), 'exhibition' => __('Exhibition'), 'seasonal' => __('Seasonal'), 'featured' => __('Featured'), 'genre' => __('Genre'), 'sale' => __('Sale')];
  $currentType = $isEdit ? ($collection->collection_type ?? 'curated') : old('collection_type', 'curated');
@endphp
@section('title', $pageTitle . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace seller-collection-create')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.seller-collections') }}">{{ __('My Collections') }}</a></li>
    <li class="breadcrumb-item active">{{ $pageTitle }}</li>
  </ol>
</nav>

@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<div class="row">
  <div class="col-lg-8 mx-auto">
    <h1 class="h3 mb-4">{{ $pageTitle }}</h1>

    <div class="card">
      <div class="card-body">
        <form method="POST" action="{{ $isEdit ? route('ahgmarketplace.seller-collection-create.post', ['id' => $collection->id]) : route('ahgmarketplace.seller-collection-create.post') }}" enctype="multipart/form-data">
          @csrf
          @if($isEdit)
            <input type="hidden" name="id" value="{{ $collection->id }}">
          @endif

          <div class="mb-3">
            <label for="title" class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $isEdit ? $collection->title : '') }}" required maxlength="255">
          </div>

          <div class="mb-3">
            <label for="description" class="form-label">{{ __('Description') }}</label>
            <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $isEdit ? ($collection->description ?? '') : '') }}</textarea>
          </div>

          <div class="mb-3">
            <label for="cover_image" class="form-label">{{ __('Cover Image') }}</label>
            @if($isEdit && !empty($collection->cover_image_path))
              <div class="mb-2">
                <img src="{{ $collection->cover_image_path }}" alt="" class="rounded" style="max-height:120px;">
              </div>
            @endif
            <input type="file" class="form-control" id="cover_image" name="cover_image" accept="image/*">
            <div class="form-text">{{ __('Recommended: landscape orientation, at least 800x400px.') }}</div>
          </div>

          <div class="mb-3">
            <label for="collection_type" class="form-label">{{ __('Collection Type') }}</label>
            <select class="form-select" id="collection_type" name="collection_type">
              @foreach($types as $val => $label)
                <option value="{{ $val }}" {{ $currentType === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="is_public" name="is_public" value="1" {{ ($isEdit ? !empty($collection->is_public) : true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_public">{{ __('Public (visible to everyone)') }}</label>
          </div>

          <div class="d-flex justify-content-between">
            <a href="{{ route('ahgmarketplace.seller-collections') }}" class="btn btn-outline-secondary">
              <i class="fas fa-arrow-left me-1"></i> {{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i> {{ $isEdit ? __('Save Changes') : __('Create Collection') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
