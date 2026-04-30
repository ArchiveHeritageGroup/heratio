{{--
  Registry Admin — ERD Documentation
  Cloned from PSIS adminErdSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('ERD Documentation') . ' — ' . __('Admin'))
@section('body-class', 'registry registry-admin-erd')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('ERD') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0"><i class="fas fa-project-diagram me-2"></i>{{ __('ERD Documentation') }}</h1>
  <span class="badge bg-secondary fs-6">{{ $items->count() }} {{ __('plugins') }}</span>
</div>

<div class="mb-4">
  <form method="get" action="{{ route('registry.admin.erd') }}">
    <div class="input-group">
      <input type="text" class="form-control" name="q" value="{{ e($q ?? '') }}" placeholder="{{ __('Search plugins...') }}">
      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
    </div>
  </form>
</div>

@if($items->isNotEmpty())
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
  @foreach($items as $item)
  <div class="col">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <i class="{{ $item->icon ?? 'fas fa-database' }} fa-2x me-3 text-{{ $item->color ?? 'primary' }}"></i>
          <div>
            <h5 class="card-title mb-0">{{ $item->display_name ?? '' }}</h5>
            <small class="text-muted">{{ $item->plugin_name ?? '' }}</small>
          </div>
        </div>
        <p class="text-muted small">{{ \Illuminate\Support\Str::limit($item->description ?? '', 120) }}</p>
        <div class="d-flex justify-content-between align-items-center">
          <span class="badge bg-secondary">{{ $item->category ?? '' }}</span>
          @if(Route::has('registry.admin.erdEdit'))
            <a href="{{ route('registry.admin.erdEdit', ['id' => (int) $item->id]) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
          @endif
        </div>
      </div>
    </div>
  </div>
  @endforeach
</div>
@else
<div class="text-center py-5">
  <i class="fas fa-project-diagram fa-3x text-muted mb-3"></i>
  <h5>{{ __('No ERDs found') }}</h5>
</div>
@endif
@endsection
