{{--
  Marketplace — My Listings (seller dashboard)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/sellerListingsSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('My Listings') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace seller-listings')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item active">{{ __('My Listings') }}</li>
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
  <h1 class="h3 mb-0">{{ __('My Listings') }}</h1>
  <a href="{{ route('ahgmarketplace.seller-listing-create') }}" class="btn btn-primary">
    <i class="fas fa-plus me-1"></i> {{ __('Create New Listing') }}
  </a>
</div>

@php
  $statusTabs = [
    '' => __('All'),
    'draft' => __('Draft'),
    'active' => __('Active'),
    'pending_review' => __('Pending Review'),
    'sold' => __('Sold'),
    'expired' => __('Expired'),
  ];
  $currentStatus = request('status', '');
@endphp
<ul class="nav nav-tabs mb-4">
  @foreach($statusTabs as $val => $label)
    <li class="nav-item">
      <a class="nav-link {{ $currentStatus === $val ? 'active' : '' }}" href="?status={{ $val }}">{{ $label }}</a>
    </li>
  @endforeach
</ul>

@if(empty($listings) || count($listings) === 0)
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-tags fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('No listings found') }}</h5>
      <p class="text-muted">{{ __('Create your first listing to start selling.') }}</p>
      <a href="{{ route('ahgmarketplace.seller-listing-create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> {{ __('Create New Listing') }}
      </a>
    </div>
  </div>
@else
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:50px;"></th>
            <th>{{ __('Title / Listing #') }}</th>
            <th>{{ __('Sector') }}</th>
            <th>{{ __('Type') }}</th>
            <th class="text-end">{{ __('Price') }}</th>
            <th>{{ __('Status') }}</th>
            <th class="text-end">{{ __('Views') }}</th>
            <th>{{ __('Created') }}</th>
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
                <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="text-decoration-none fw-semibold">{{ $listing->title ?? '' }}</a>
                <br><small class="text-muted">{{ $listing->listing_number ?? '' }}</small>
              </td>
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
              <td class="text-end small text-muted">{{ number_format((int) ($listing->view_count ?? 0)) }}</td>
              <td class="small text-muted">{{ !empty($listing->created_at) ? date('d M Y', strtotime($listing->created_at)) : '' }}</td>
              <td class="text-end text-nowrap">
                <a href="{{ route('ahgmarketplace.seller-listing-edit', ['id' => $listing->id]) }}" class="btn btn-sm btn-outline-primary" title="{{ __('Edit') }}">
                  <i class="fas fa-edit"></i>
                </a>
                <a href="{{ route('ahgmarketplace.seller-listing-images', ['id' => $listing->id]) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Images') }}">
                  <i class="fas fa-images"></i>
                </a>
                @if(($listing->status ?? '') === 'draft' && Route::has('ahgmarketplace.seller-listing-publish'))
                  <form method="POST" action="{{ route('ahgmarketplace.seller-listing-publish', ['id' => $listing->id]) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Publish') }}" onclick="return confirm('{{ __('Publish this listing?') }}');">
                      <i class="fas fa-check"></i>
                    </button>
                  </form>
                @endif
                @if(($listing->status ?? '') === 'active' && Route::has('ahgmarketplace.seller-listing-withdraw'))
                  <form method="POST" action="{{ route('ahgmarketplace.seller-listing-withdraw', ['id' => $listing->id]) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Withdraw') }}" onclick="return confirm('{{ __('Withdraw this listing from the marketplace?') }}');">
                      <i class="fas fa-times"></i>
                    </button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  @php $totalPages = (int) ceil(($total ?? 0) / ($limit ?? 24)); @endphp
  @if($totalPages > 1)
    <nav class="mt-4" aria-label="{{ __('Pagination') }}">
      <ul class="pagination justify-content-center">
        <li class="page-item{{ ($page ?? 1) <= 1 ? ' disabled' : '' }}">
          <a class="page-link" href="?status={{ $currentStatus }}&page={{ ($page ?? 1) - 1 }}">&laquo;</a>
        </li>
        @for($i = max(1, ($page ?? 1) - 2); $i <= min($totalPages, ($page ?? 1) + 2); $i++)
          <li class="page-item{{ $i === ($page ?? 1) ? ' active' : '' }}">
            <a class="page-link" href="?status={{ $currentStatus }}&page={{ $i }}">{{ $i }}</a>
          </li>
        @endfor
        <li class="page-item{{ ($page ?? 1) >= $totalPages ? ' disabled' : '' }}">
          <a class="page-link" href="?status={{ $currentStatus }}&page={{ ($page ?? 1) + 1 }}">&raquo;</a>
        </li>
      </ul>
    </nav>
  @endif
@endif
@endsection
