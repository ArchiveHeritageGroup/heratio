{{--
  Registry — Standards Directory
  Cloned from PSIS standardBrowseSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Standards Directory'))
@section('body-class', 'registry registry-standard-browse')

@php
  $items = $result['items'] ?? collect();
  $total = (int) ($result['total'] ?? 0);
  $page  = (int) ($result['page'] ?? 1);
  $limit = 20;
  $q     = request('q', '');
  $catF  = request('category', '');
  $sectorF = request('sector', '');
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Standards') }}</li>
  </ol>
</nav>

<div class="row mb-4 align-items-center">
  <div class="col">
    <h1 class="h3 mb-1">{{ __('Standards Directory') }}</h1>
    <p class="text-muted mb-0">{{ __(':count standards listed', ['count' => number_format($total)]) }}</p>
  </div>
  <div class="col-auto">
    @if(Route::has('registry.erdBrowse'))
      <a href="{{ route('registry.erdBrowse') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-project-diagram me-1"></i>{{ __('Schema & ERD') }}</a>
    @endif
  </div>
</div>

<div class="mb-4">
  <form method="get" action="{{ route('registry.standardBrowse') }}">
    <div class="input-group">
      <input type="text" class="form-control" name="q" value="{{ e($q) }}" placeholder="{{ __('Search standards...') }}">
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
          <form method="get" action="{{ route('registry.standardBrowse') }}">
            <input type="hidden" name="q" value="{{ e($q) }}">
            <div class="mb-3">
              <label class="form-label small text-muted">{{ __('Category') }}</label>
              <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach(['' => __('All Categories'), 'descriptive' => __('Descriptive'), 'preservation' => __('Preservation'), 'rights' => __('Rights'), 'accounting' => __('Accounting'), 'compliance' => __('Compliance'), 'metadata' => __('Metadata'), 'interchange' => __('Interchange'), 'sector' => __('Sector')] as $v => $l)
                  <option value="{{ $v }}" {{ $catF === $v ? 'selected' : '' }}>{{ $l }}</option>
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
        @foreach($items as $item)
          <div class="col">
            <div class="card h-100">
              <div class="card-body">
                <h6 class="card-title mb-1">
                  @if(!empty($item->id) && Route::has('registry.standardView'))
                    <a href="{{ route('registry.standardView', ['id' => (int) $item->id]) }}" class="text-decoration-none">{{ $item->name ?? '' }}</a>
                  @else
                    {{ $item->name ?? '' }}
                  @endif
                  @if(!empty($item->acronym)) <small class="text-muted">({{ $item->acronym }})</small>@endif
                </h6>
                @if(!empty($item->category))<span class="badge bg-secondary">{{ ucfirst($item->category) }}</span>@endif
                @if(!empty($item->issuing_body))<span class="small text-muted ms-1">{{ $item->issuing_body }}</span>@endif
                @if(!empty($item->short_description))
                  <p class="card-text small text-muted mt-2 mb-0">{{ \Illuminate\Support\Str::limit(strip_tags($item->short_description), 140) }}</p>
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
            <li class="page-item{{ $page <= 1 ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.standardBrowse', array_merge(request()->query(), ['page' => $page - 1])) }}">&laquo;</a></li>
            @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
              <li class="page-item{{ $i === $page ? ' active' : '' }}"><a class="page-link" href="{{ route('registry.standardBrowse', array_merge(request()->query(), ['page' => $i])) }}">{{ $i }}</a></li>
            @endfor
            <li class="page-item{{ $page >= $totalPages ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.standardBrowse', array_merge(request()->query(), ['page' => $page + 1])) }}">&raquo;</a></li>
          </ul>
        </nav>
      @endif
    @else
      <div class="text-center py-5">
        <i class="fas fa-balance-scale fa-3x text-muted mb-3"></i>
        <h5>{{ __('No standards found') }}</h5>
        <p class="text-muted">{{ __('Try adjusting your filters or search terms.') }}</p>
        <a href="{{ route('registry.standardBrowse') }}" class="btn btn-primary">{{ __('Clear Filters') }}</a>
      </div>
    @endif
  </div>
</div>
@endsection
