{{--
  Registry — Vendors Directory
  Cloned from PSIS vendorBrowseSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Vendors Directory'))
@section('body-class', 'registry registry-vendor-browse')

@php
  $items = $result['items'] ?? collect();
  $total = (int) ($result['total'] ?? 0);
  $page  = (int) ($result['page'] ?? 1);
  $limit = 24;
  $q     = request('q', '');
  $typeF = request('type', '');
  $specF = request('specialization', '');
  $countryF = request('country', '');
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Vendors') }}</li>
  </ol>
</nav>

<div class="row mb-4 align-items-center">
  <div class="col">
    <h1 class="h3 mb-1">{{ __('Vendors Directory') }}</h1>
    <p class="text-muted mb-0">{{ __(':count vendors registered', ['count' => number_format($total)]) }}</p>
  </div>
  <div class="col-auto">
    @if(Route::has('registry.vendorRegister'))
      <a href="{{ route('registry.vendorRegister') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> {{ __('Register as Vendor') }}</a>
    @endif
  </div>
</div>

<div class="mb-4">
  <form method="get" action="{{ route('registry.vendorBrowse') }}">
    <div class="input-group">
      <input type="text" class="form-control" name="q" value="{{ e($q) }}" placeholder="{{ __('Search vendors...') }}">
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
          <form method="get" action="{{ route('registry.vendorBrowse') }}">
            <input type="hidden" name="q" value="{{ e($q) }}">
            <div class="mb-3">
              <label class="form-label small text-muted">{{ __('Vendor Type') }}</label>
              <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach(['' => __('All Types'), 'developer' => __('Developer'), 'integrator' => __('Integrator'), 'consultant' => __('Consultant'), 'service_provider' => __('Service Provider'), 'hosting' => __('Hosting'), 'digitization' => __('Digitization'), 'training' => __('Training'), 'other' => __('Other')] as $v => $l)
                  <option value="{{ $v }}" {{ $typeF === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted">{{ __('Specialization') }}</label>
              <select name="specialization" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach(['' => __('All'), 'archives' => __('Archives'), 'libraries' => __('Libraries'), 'museums' => __('Museums'), 'galleries' => __('Galleries'), 'dam' => __('Digital Asset Management'), 'preservation' => __('Digital Preservation')] as $v => $l)
                  <option value="{{ $v }}" {{ $specF === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted">{{ __('Country') }}</label>
              <input type="text" name="country" class="form-control form-control-sm" value="{{ e($countryF) }}">
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
        @foreach($items as $v)
          <div class="col">
            <div class="card h-100">
              <div class="card-body">
                <h6 class="card-title mb-1">
                  @if(!empty($v->id) && Route::has('registry.vendorView'))
                    <a href="{{ route('registry.vendorView', ['id' => (int) $v->id]) }}" class="text-decoration-none">{{ $v->name ?? '' }}</a>
                  @else
                    {{ $v->name ?? '' }}
                  @endif
                </h6>
                @if(!empty($v->vendor_type))<span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $v->vendor_type)) }}</span>@endif
                @if(!empty($v->country))<span class="badge bg-light text-dark border">{{ $v->country }}</span>@endif
                @if(!empty($v->description))
                  <p class="card-text small text-muted mt-2 mb-0">{{ \Illuminate\Support\Str::limit(strip_tags($v->description), 120) }}</p>
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
            <li class="page-item{{ $page <= 1 ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.vendorBrowse', array_merge(request()->query(), ['page' => $page - 1])) }}">&laquo;</a></li>
            @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
              <li class="page-item{{ $i === $page ? ' active' : '' }}"><a class="page-link" href="{{ route('registry.vendorBrowse', array_merge(request()->query(), ['page' => $i])) }}">{{ $i }}</a></li>
            @endfor
            <li class="page-item{{ $page >= $totalPages ? ' disabled' : '' }}"><a class="page-link" href="{{ route('registry.vendorBrowse', array_merge(request()->query(), ['page' => $page + 1])) }}">&raquo;</a></li>
          </ul>
        </nav>
      @endif
    @else
      <div class="text-center py-5">
        <i class="fas fa-handshake fa-3x text-muted mb-3"></i>
        <h5>{{ __('No vendors found') }}</h5>
        <p class="text-muted">{{ __('Try adjusting your filters or search terms.') }}</p>
        <a href="{{ route('registry.vendorBrowse') }}" class="btn btn-primary">{{ __('Clear Filters') }}</a>
      </div>
    @endif
  </div>
</div>
@endsection
