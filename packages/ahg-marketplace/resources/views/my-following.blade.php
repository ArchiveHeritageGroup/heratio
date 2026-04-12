{{--
  Marketplace — My Following (sellers followed by the current user)

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/myFollowingSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('Following') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace my-following')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Following') }}</li>
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
  <h1 class="h3 mb-0">
    {{ __('Sellers I Follow') }}
    @if(($total ?? 0) > 0)
      <span class="badge bg-secondary ms-2">{{ (int) $total }}</span>
    @endif
  </h1>
  <div>
    <a href="{{ route('ahgmarketplace.my-purchases') }}" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-shopping-bag me-1"></i>{{ __('My Purchases') }}
    </a>
  </div>
</div>

@if(empty($sellers) || count($sellers) === 0)
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-users fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('Not following any sellers yet') }}</h5>
      <p class="text-muted">{{ __('Follow sellers to stay updated on their new listings.') }}</p>
      <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-primary">
        <i class="fas fa-search me-1"></i> {{ __('Browse Marketplace') }}
      </a>
    </div>
  </div>
@else
  <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
    @foreach($sellers as $seller)
      <div class="col">
        <div class="card h-100 text-center">
          <div class="card-body">
            @if(!empty($seller->avatar_path))
              <img src="{{ $seller->avatar_path }}" alt="{{ $seller->display_name ?? '' }}" class="rounded-circle mb-3" style="width:80px;height:80px;object-fit:cover;">
            @else
              <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width:80px;height:80px;">
                <i class="fas fa-user fa-2x"></i>
              </div>
            @endif

            <h6 class="mb-1">
              <a href="{{ route('ahgmarketplace.seller', ['slug' => $seller->slug ?? '']) }}" class="text-decoration-none">{{ $seller->display_name ?? '' }}</a>
              @if(($seller->verification_status ?? '') === 'verified')
                <i class="fas fa-check-circle text-primary ms-1" title="{{ __('Verified') }}"></i>
              @endif
            </h6>

            @if(($seller->average_rating ?? 0) > 0)
              <div class="mb-2">
                @for($s = 1; $s <= 5; $s++)
                  <i class="fa{{ $s <= round($seller->average_rating) ? 's' : 'r' }} fa-star text-warning small"></i>
                @endfor
                <span class="small text-muted ms-1">({{ (int) ($seller->rating_count ?? 0) }})</span>
              </div>
            @endif

            <p class="small text-muted mb-3">
              <i class="fas fa-tag me-1"></i>
              {{ __(':count active listings', ['count' => (int) ($seller->listing_count ?? 0)]) }}
            </p>

            <div class="d-grid gap-2">
              <a href="{{ route('ahgmarketplace.seller', ['slug' => $seller->slug ?? '']) }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-store me-1"></i> {{ __('View Profile') }}
              </a>
              @if(Route::has('ahgmarketplace.follow'))
                <form method="POST" action="{{ route('ahgmarketplace.follow', ['seller' => $seller->slug ?? '']) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                    <i class="fas fa-user-minus me-1"></i> {{ __('Unfollow') }}
                  </button>
                </form>
              @endif
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  @php $totalPages = (int) ceil(($total ?? 0) / ($limit ?? 20)); @endphp
  @if($totalPages > 1)
    <nav class="mt-4" aria-label="{{ __('Pagination') }}">
      <ul class="pagination justify-content-center">
        <li class="page-item {{ ($page ?? 1) <= 1 ? 'disabled' : '' }}">
          <a class="page-link" href="?page={{ ($page ?? 1) - 1 }}">&laquo;</a>
        </li>
        @for($p = 1; $p <= $totalPages; $p++)
          <li class="page-item {{ $p === ($page ?? 1) ? 'active' : '' }}">
            <a class="page-link" href="?page={{ $p }}">{{ $p }}</a>
          </li>
        @endfor
        <li class="page-item {{ ($page ?? 1) >= $totalPages ? 'disabled' : '' }}">
          <a class="page-link" href="?page={{ ($page ?? 1) + 1 }}">&raquo;</a>
        </li>
      </ul>
    </nav>
  @endif
@endif
@endsection
