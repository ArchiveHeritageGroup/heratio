{{--
  Registry — Institutions Directory
  Cloned from PSIS institutionBrowseSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Institutions Directory'))
@section('body-class', 'registry registry-institution-browse')

@php
  $items = $result['items'] ?? collect();
  $total = (int) ($result['total'] ?? 0);
  $page  = (int) ($result['page'] ?? 1);
  $limit = 24;
  $q     = request('q', '');
  $typeF = request('type', '');
  $sizeF = request('size', '');
  $govF  = request('governance', '');
  $usesF = request('uses_atom', '');
  $countryF = request('country', '');
@endphp

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Institutions') }}</li>
  </ol>
</nav>

<div class="row mb-4 align-items-center">
  <div class="col">
    <h1 class="h3 mb-1">{{ __('Institutions Directory') }}</h1>
    <p class="text-muted mb-0">{{ __(':count institutions registered', ['count' => number_format($total)]) }}</p>
  </div>
  <div class="col-auto">
    @if(Route::has('registry.map'))
      <a href="{{ route('registry.map') }}" class="btn btn-outline-secondary btn-sm me-1"><i class="fas fa-map me-1"></i> {{ __('Map View') }}</a>
    @endif
    @if(Route::has('registry.institutionRegister'))
      <a href="{{ route('registry.institutionRegister') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> {{ __('Register') }}</a>
    @endif
  </div>
</div>

<div class="mb-4">
  <form method="get" action="{{ route('registry.institutionBrowse') }}">
    <div class="input-group">
      <input type="text" class="form-control" name="q" value="{{ e($q) }}" placeholder="{{ __('Search institutions...') }}">
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
          <form method="get" action="{{ route('registry.institutionBrowse') }}">
            <input type="hidden" name="q" value="{{ e($q) }}">
            <div class="mb-3">
              <label class="form-label small text-muted">{{ __('Type') }}</label>
              <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach(['' => __('All Types'), 'archive' => __('Archive'), 'library' => __('Library'), 'museum' => __('Museum'), 'gallery' => __('Gallery'), 'dam' => __('Digital Asset Management'), 'heritage_site' => __('Heritage Site'), 'research_centre' => __('Research Centre'), 'government' => __('Government'), 'university' => __('University'), 'other' => __('Other')] as $v => $l)
                  <option value="{{ $v }}" {{ $typeF === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted">{{ __('Size') }}</label>
              <select name="size" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach(['' => __('All Sizes'), 'small' => __('Small'), 'medium' => __('Medium'), 'large' => __('Large'), 'national' => __('National')] as $v => $l)
                  <option value="{{ $v }}" {{ $sizeF === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted">{{ __('Governance') }}</label>
              <select name="governance" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach(['' => __('All'), 'public' => __('Public'), 'private' => __('Private'), 'ngo' => __('NGO'), 'academic' => __('Academic'), 'government' => __('Government'), 'tribal' => __('Tribal'), 'community' => __('Community')] as $v => $l)
                  <option value="{{ $v }}" {{ $govF === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted">{{ __('Country') }}</label>
              <input type="text" name="country" class="form-control form-control-sm" value="{{ e($countryF) }}" placeholder="{{ __('e.g. ZA') }}">
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
        @foreach($items as $inst)
          <div class="col">
            <div class="card h-100">
              <div class="card-body">
                <h6 class="card-title mb-1">
                  @if(!empty($inst->id) && Route::has('registry.institutionView'))
                    <a href="{{ route('registry.institutionView', ['id' => (int) $inst->id]) }}" class="text-decoration-none">{{ $inst->name ?? '' }}</a>
                  @else
                    {{ $inst->name ?? '' }}
                  @endif
                </h6>
                @if(!empty($inst->type))
                  <span class="badge bg-secondary">{{ ucfirst($inst->type) }}</span>
                @endif
                @if(!empty($inst->country))
                  <span class="badge bg-light text-dark border">{{ $inst->country }}</span>
                @endif
                @if(!empty($inst->description))
                  <p class="card-text small text-muted mt-2 mb-0">{{ \Illuminate\Support\Str::limit(strip_tags($inst->description), 120) }}</p>
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
            <li class="page-item{{ $page <= 1 ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.institutionBrowse', array_merge(request()->query(), ['page' => $page - 1])) }}">&laquo;</a></li>
            @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
              <li class="page-item{{ $i === $page ? ' active' : '' }}"><a class="page-link" href="{{ route('registry.institutionBrowse', array_merge(request()->query(), ['page' => $i])) }}">{{ $i }}</a></li>
            @endfor
            <li class="page-item{{ $page >= $totalPages ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.institutionBrowse', array_merge(request()->query(), ['page' => $page + 1])) }}">&raquo;</a></li>
          </ul>
        </nav>
      @endif
    @else
      <div class="text-center py-5">
        <i class="fas fa-university fa-3x text-muted mb-3"></i>
        <h5>{{ __('No institutions found') }}</h5>
        <p class="text-muted">{{ __('Try adjusting your filters or search terms.') }}</p>
        <a href="{{ route('registry.institutionBrowse') }}" class="btn btn-primary">{{ __('Clear Filters') }}</a>
      </div>
    @endif
  </div>
</div>
@endsection
