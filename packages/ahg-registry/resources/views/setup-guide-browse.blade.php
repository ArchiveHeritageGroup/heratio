{{--
  Registry — Setup Guides
  Cloned from PSIS setupGuideBrowseSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Setup Guides') . (!empty($software->name) ? ' — ' . $software->name : ''))
@section('body-class', 'registry registry-setup-guide-browse')

@php
  $items = $result['items'] ?? collect();
  $total = (int) ($result['total'] ?? 0);
  $page  = (int) ($result['page'] ?? 1);
  $limit = 20;
  $catF  = request('category', '');
  $softwareId = isset($software->id) ? (int) $software->id : null;
  $guideCatBg = [
    'security' => 'bg-danger',
    'deployment' => 'bg-primary',
    'configuration' => 'bg-info text-dark',
    'optimization' => 'bg-success',
    'troubleshooting' => 'bg-warning text-dark',
    'integration' => 'bg-dark',
  ];
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.softwareBrowse') }}">{{ __('Software') }}</a></li>
    @if(!empty($software->name) && Route::has('registry.softwareView') && $softwareId)
      <li class="breadcrumb-item"><a href="{{ route('registry.softwareView', ['id' => $softwareId]) }}">{{ $software->name }}</a></li>
    @endif
    <li class="breadcrumb-item active">{{ __('Setup Guides') }}</li>
  </ol>
</nav>

<div class="row mb-4 align-items-center">
  <div class="col">
    <h1 class="h3 mb-1">
      @if(!empty($software->name))
        {{ __('Setup Guides for :name', ['name' => $software->name]) }}
      @else
        {{ __('Setup Guides') }}
      @endif
    </h1>
    <p class="text-muted mb-0">{{ __(':count guides available', ['count' => number_format($total)]) }}</p>
  </div>
  @if($softwareId && Route::has('registry.softwareView'))
    <div class="col-auto">
      <a href="{{ route('registry.softwareView', ['id' => $softwareId]) }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Software') }}
      </a>
    </div>
  @endif
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
          <form method="get">
            <div class="mb-3">
              <label class="form-label small text-muted">{{ __('Category') }}</label>
              <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach(['' => __('All Categories'), 'security' => __('Security'), 'deployment' => __('Deployment'), 'configuration' => __('Configuration'), 'optimization' => __('Optimization'), 'troubleshooting' => __('Troubleshooting'), 'integration' => __('Integration')] as $v => $l)
                  <option value="{{ $v }}" {{ $catF === $v ? 'selected' : '' }}>{{ $l }}</option>
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
      <div class="row row-cols-1 row-cols-md-2 g-3">
        @foreach($items as $guide)
          <div class="col">
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <h6 class="card-title mb-0">
                    @if(!empty($guide->id) && Route::has('registry.setupGuideView'))
                      <a href="{{ route('registry.setupGuideView', ['id' => (int) $guide->id]) }}" class="text-decoration-none stretched-link">
                        {{ $guide->title ?? '' }}
                      </a>
                    @else
                      {{ $guide->title ?? '' }}
                    @endif
                  </h6>
                  @if(!empty($guide->is_featured))
                    <span class="badge bg-warning text-dark flex-shrink-0 ms-2"><i class="fas fa-award"></i></span>
                  @endif
                </div>
                @php $gCat = $guide->category ?? ''; $gCatClass = $guideCatBg[strtolower($gCat)] ?? 'bg-secondary'; @endphp
                @if($gCat)
                  <div class="mb-2"><span class="badge {{ $gCatClass }}">{{ ucfirst($gCat) }}</span></div>
                @endif
                @if(!empty($guide->short_description))
                  <p class="card-text small text-muted mb-2">{{ \Illuminate\Support\Str::limit(strip_tags($guide->short_description), 150) }}</p>
                @endif
                <div class="d-flex justify-content-between align-items-center small text-muted">
                  <div>
                    @if(!empty($guide->author_name))
                      <i class="fas fa-user me-1"></i>{{ $guide->author_name }}
                    @endif
                  </div>
                  <div>
                    @if(!empty($guide->view_count))
                      <i class="fas fa-eye me-1"></i>{{ number_format((int) $guide->view_count) }}
                    @endif
                    @if(!empty($guide->updated_at))
                      <span class="ms-2"><i class="fas fa-clock me-1"></i>{{ date('M j, Y', strtotime($guide->updated_at)) }}</span>
                    @endif
                  </div>
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      @if($total > $limit)
        @php $totalPages = (int) ceil($total / $limit); @endphp
        <nav aria-label="{{ __('Page navigation') }}" class="mt-4">
          <ul class="pagination justify-content-center">
            <li class="page-item{{ $page <= 1 ? ' disabled' : '' }}"><a class="page-link" href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->query(), ['page' => $page - 1])) }}">&laquo;</a></li>
            @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
              <li class="page-item{{ $i === $page ? ' active' : '' }}"><a class="page-link" href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->query(), ['page' => $i])) }}">{{ $i }}</a></li>
            @endfor
            <li class="page-item{{ $page >= $totalPages ? ' disabled' : '' }}"><a class="page-link" href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->query(), ['page' => $page + 1])) }}">&raquo;</a></li>
          </ul>
        </nav>
      @endif
    @else
      <div class="text-center py-5">
        <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
        <h5>{{ __('No setup guides found') }}</h5>
        <p class="text-muted">{{ __('No guides are available for this software yet.') }}</p>
      </div>
    @endif
  </div>
</div>
@endsection
