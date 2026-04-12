{{--
  Marketplace Admin — Listings

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/adminListingsSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Manage Listings') . ' - ' . __('Marketplace Admin'))
@section('body-class', 'admin marketplace listings')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.admin-dashboard') }}">{{ __('Marketplace Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Listings') }}</li>
  </ol>
</nav>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<h1 class="h3 mb-4">{{ __('Manage Listings') }}</h1>

<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small">{{ __('Status') }}</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">{{ __('All Statuses') }}</option>
          @foreach(['draft', 'pending_review', 'active', 'sold', 'suspended', 'expired', 'withdrawn'] as $s)
            <option value="{{ $s }}" {{ ($filters['status'] ?? '') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small">{{ __('Sector') }}</label>
        <select name="sector" class="form-select form-select-sm">
          <option value="">{{ __('All Sectors') }}</option>
          @foreach(['gallery', 'museum', 'archive', 'library', 'dam'] as $sec)
            <option value="{{ $sec }}" {{ ($filters['sector'] ?? '') === $sec ? 'selected' : '' }}>{{ ucfirst($sec) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small">{{ __('Search') }}</label>
        <input type="text" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Title, listing # or seller...') }}">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter me-1"></i>{{ __('Filter') }}</button>
      </div>
    </form>
  </div>
</div>

@if(empty($listings) || count($listings) === 0)
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-tags fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('No listings found') }}</h5>
      <p class="text-muted">{{ __('Try adjusting your filters.') }}</p>
    </div>
  </div>
@else
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:40px;">{{ __('ID') }}</th>
            <th style="width:50px;"></th>
            <th>{{ __('Title') }}</th>
            <th>{{ __('Seller') }}</th>
            <th>{{ __('Sector') }}</th>
            <th>{{ __('Type') }}</th>
            <th class="text-end">{{ __('Price') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Listed') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($listings as $listing)
            @php
              $statusClass = match($listing->status ?? '') {
                'draft' => 'secondary',
                'pending_review' => 'warning',
                'active' => 'success',
                'reserved' => 'info',
                'sold' => 'primary',
                'expired' => 'dark',
                'withdrawn' => 'secondary',
                'suspended' => 'danger',
                default => 'secondary',
              };
            @endphp
            <tr>
              <td class="small text-muted">{{ (int) $listing->id }}</td>
              <td>
                @if(!empty($listing->featured_image_path))
                  <img src="{{ $listing->featured_image_path }}" alt="" class="rounded" style="width:40px;height:40px;object-fit:cover;">
                @else
                  <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                    <i class="fas fa-image text-muted small"></i>
                  </div>
                @endif
              </td>
              <td>
                <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="text-decoration-none fw-semibold">{{ $listing->title ?? '-' }}</a>
                <br><small class="text-muted">{{ $listing->listing_number ?? '' }}</small>
              </td>
              <td class="small">{{ $listing->seller_name ?? '-' }}</td>
              <td><span class="badge bg-info">{{ ucfirst($listing->sector ?? '-') }}</span></td>
              <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $listing->listing_type ?? '-')) }}</span></td>
              <td class="text-end">
                @if(!empty($listing->price_on_request))
                  <span class="text-muted">{{ __('POR') }}</span>
                @elseif(!empty($listing->price))
                  {{ $listing->currency ?? '' }} {{ number_format((float) $listing->price, 2) }}
                @else
                  -
                @endif
              </td>
              <td><span class="badge bg-{{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $listing->status ?? '-')) }}</span></td>
              <td class="small text-muted">{{ $listing->created_at ? date('d M Y', strtotime($listing->created_at)) : '' }}</td>
              <td class="text-end text-nowrap">
                @if(($listing->status ?? '') === 'pending_review')
                  <a href="{{ route('ahgmarketplace.admin-listing-review', ['id' => $listing->id]) }}" class="btn btn-sm btn-outline-warning" title="{{ __('Review') }}">
                    <i class="fas fa-clipboard-check"></i>
                  </a>
                @endif
                <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="btn btn-sm btn-outline-primary" title="{{ __('View') }}">
                  <i class="fas fa-eye"></i>
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  @php $totalPages = (int) ceil(($total ?? 0) / 30); @endphp
  @if($totalPages > 1)
    @php
      $query = http_build_query(array_filter([
        'status' => $filters['status'] ?? '',
        'sector' => $filters['sector'] ?? '',
        'search' => $filters['search'] ?? '',
      ]));
    @endphp
    <nav class="mt-4" aria-label="{{ __('Pagination') }}">
      <ul class="pagination justify-content-center">
        <li class="page-item{{ ($page ?? 1) <= 1 ? ' disabled' : '' }}">
          <a class="page-link" href="?{{ $query }}&page={{ ($page ?? 1) - 1 }}">&laquo;</a>
        </li>
        @for($i = max(1, ($page ?? 1) - 2); $i <= min($totalPages, ($page ?? 1) + 2); $i++)
          <li class="page-item{{ $i === ($page ?? 1) ? ' active' : '' }}">
            <a class="page-link" href="?{{ $query }}&page={{ $i }}">{{ $i }}</a>
          </li>
        @endfor
        <li class="page-item{{ ($page ?? 1) >= $totalPages ? ' disabled' : '' }}">
          <a class="page-link" href="?{{ $query }}&page={{ ($page ?? 1) + 1 }}">&raquo;</a>
        </li>
      </ul>
    </nav>
  @endif
@endif
@endsection
