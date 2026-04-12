{{--
  Registry — Software Directory
  Cloned from PSIS softwareBrowseSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Software Directory'))
@section('body-class', 'registry registry-software-browse')

@php
  $items = $result['items'] ?? collect();
  $total = (int) ($result['total'] ?? 0);
  $page  = (int) ($result['page'] ?? 1);
  $limit = 24;
  $q     = request('q', '');
  $catF  = request('category', '');
  $licF  = request('license', '');
  $priceF = request('pricing', '');
  $sectorF = request('sector', '');
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Software') }}</li>
  </ol>
</nav>

<div class="row mb-4 align-items-center">
  <div class="col">
    <h1 class="h3 mb-1">{{ __('Software Directory') }}</h1>
    <p class="text-muted mb-0">{{ __(':count software products listed', ['count' => number_format($total)]) }}</p>
  </div>
  @auth
  <div class="col-auto">
    @if(Route::has('registry.myVendorSoftwareAdd'))
      <a href="{{ route('registry.myVendorSoftwareAdd') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i> {{ __('Add Software') }}</a>
    @endif
  </div>
  @endauth
</div>

<div class="mb-4">
  <form method="get" action="{{ route('registry.softwareBrowse') }}">
    <div class="input-group">
      <input type="text" class="form-control" name="q" value="{{ e($q) }}" placeholder="{{ __('Search software...') }}">
      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
    </div>
  </form>
</div>

<div class="row">
  <div class="col-lg-3 mb-4">
    <div class="d-lg-none mb-3">
      <button class="btn btn-outline-secondary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterSidebar"><i class="fas fa-filter me-1"></i> {{ __('Filters') }}</button>
    </div>
    <div class="collapse d-lg-block" id="filterSidebar">
      <div class="card">
        <div class="card-header fw-semibold">{{ __('Filters') }}</div>
        <div class="card-body">
          <form method="get" action="{{ route('registry.softwareBrowse') }}">
            <input type="hidden" name="q" value="{{ e($q) }}">
            <div class="mb-3">
              <label class="form-label small text-muted">{{ __('Category') }}</label>
              <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach(['' => __('All Categories'), 'ams' => __('AMS (Archival Management System)'), 'ims' => __('IMS (Information Management)'), 'dam' => __('DAM (Digital Asset Management)'), 'dams' => __('DAMS'), 'cms' => __('CMS'), 'preservation' => __('Digital Preservation'), 'digitization' => __('Digitization'), 'discovery' => __('Discovery'), 'utility' => __('Utility'), 'plugin' => __('Plugin/Extension'), 'theme' => __('Theme'), 'integration' => __('Integration'), 'other' => __('Other')] as $v => $l)
                  <option value="{{ $v }}" {{ $catF === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted">{{ __('License') }}</label>
              <select name="license" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach(['' => __('All Licenses'), 'GPL-3.0' => 'GPL-3.0', 'GPL-2.0' => 'GPL-2.0', 'MIT' => 'MIT', 'Apache-2.0' => 'Apache 2.0', 'BSD-3-Clause' => 'BSD 3-Clause', 'AGPL-3.0' => 'AGPL-3.0', 'proprietary' => __('Proprietary')] as $v => $l)
                  <option value="{{ $v }}" {{ $licF === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted">{{ __('Pricing') }}</label>
              <select name="pricing" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach(['' => __('All'), 'free' => __('Free'), 'open_source' => __('Open Source'), 'freemium' => __('Freemium'), 'subscription' => __('Subscription'), 'one_time' => __('One-Time License'), 'contact' => __('Contact for Pricing')] as $v => $l)
                  <option value="{{ $v }}" {{ $priceF === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted">{{ __('GLAM Sector') }}</label>
              <select name="sector" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach(['' => __('All Sectors'), 'archive' => __('Archive'), 'library' => __('Library'), 'museum' => __('Museum'), 'gallery' => __('Gallery'), 'dam' => __('DAM')] as $v => $l)
                  <option value="{{ $v }}" {{ $sectorF === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Apply') }}</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-9">
    @if(!empty($items) && count($items))
      <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3">
        @foreach($items as $sw)
          <div class="col">
            <div class="card h-100">
              <div class="card-body">
                <h6 class="card-title mb-1">
                  @if(!empty($sw->id) && Route::has('registry.softwareView'))
                    <a href="{{ route('registry.softwareView', ['id' => (int) $sw->id]) }}" class="text-decoration-none">{{ $sw->name ?? '' }}</a>
                  @else
                    {{ $sw->name ?? '' }}
                  @endif
                </h6>
                @if(!empty($sw->category))<span class="badge bg-secondary">{{ strtoupper($sw->category) }}</span>@endif
                @if(!empty($sw->license))<span class="badge bg-info text-dark">{{ $sw->license }}</span>@endif
                @if(!empty($sw->short_description))
                  <p class="card-text small text-muted mt-2 mb-0">{{ \Illuminate\Support\Str::limit(strip_tags($sw->short_description), 120) }}</p>
                @endif
              </div>
            </div>
          </div>
        @endforeach
      </div>

      @if($total > $limit)
        @php $totalPages = (int) ceil($total / $limit); @endphp
        <nav aria-label="{{ __('Page navigation') }}" class="mt-4">
          <ul class="pagination justify-content-center">
            <li class="page-item{{ $page <= 1 ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.softwareBrowse', array_merge(request()->query(), ['page' => $page - 1])) }}">&laquo;</a></li>
            @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
              <li class="page-item{{ $i === $page ? ' active' : '' }}"><a class="page-link" href="{{ route('registry.softwareBrowse', array_merge(request()->query(), ['page' => $i])) }}">{{ $i }}</a></li>
            @endfor
            <li class="page-item{{ $page >= $totalPages ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.softwareBrowse', array_merge(request()->query(), ['page' => $page + 1])) }}">&raquo;</a></li>
          </ul>
        </nav>
      @endif
    @else
      <div class="text-center py-5">
        <i class="fas fa-code fa-3x text-muted mb-3"></i>
        <h5>{{ __('No software found') }}</h5>
        <p class="text-muted">{{ __('Try adjusting your filters or search terms.') }}</p>
        <a href="{{ route('registry.softwareBrowse') }}" class="btn btn-primary">{{ __('Clear Filters') }}</a>
      </div>
    @endif
  </div>
</div>
@endsection
