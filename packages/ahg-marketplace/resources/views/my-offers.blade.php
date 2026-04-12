{{--
  Marketplace — My Offers

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.

  Cloned from PSIS ahgMarketplacePlugin/marketplace/myOffersSuccess.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('My Offers') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace my-offers')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item active">{{ __('My Offers') }}</li>
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
  <h1 class="h3 mb-0">{{ __('My Offers') }}</h1>
  <div>
    <a href="{{ route('ahgmarketplace.my-purchases') }}" class="btn btn-outline-secondary btn-sm me-1">
      <i class="fas fa-shopping-bag me-1"></i>{{ __('My Purchases') }}
    </a>
    <a href="{{ route('ahgmarketplace.my-bids') }}" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-gavel me-1"></i>{{ __('My Bids') }}
    </a>
  </div>
</div>

@if(empty($offers) || count($offers) === 0)
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-hand-holding-usd fa-3x text-muted mb-3 d-block"></i>
      <h5>{{ __('No offers yet') }}</h5>
      <p class="text-muted">{{ __('Browse listings and make offers on items you are interested in.') }}</p>
      <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-primary">
        <i class="fas fa-search me-1"></i> {{ __('Browse Marketplace') }}
      </a>
    </div>
  </div>
@else
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Item') }}</th>
            <th class="text-end">{{ __('Offer Amount') }}</th>
            <th class="text-end">{{ __('Counter Amount') }}</th>
            <th>{{ __('Status') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($offers as $offer)
            @php
              $statusClass = match($offer->status ?? '') {
                'pending' => 'warning',
                'accepted' => 'success',
                'rejected' => 'danger',
                'countered' => 'info',
                'withdrawn' => 'secondary',
                'expired' => 'secondary',
                default => 'secondary',
              };
            @endphp
            <tr>
              <td class="small text-muted">{{ !empty($offer->created_at) ? date('d M Y', strtotime($offer->created_at)) : '' }}</td>
              <td>
                <div class="d-flex align-items-center">
                  @if(!empty($offer->featured_image_path))
                    <img src="{{ $offer->featured_image_path }}" alt="" class="rounded me-2" style="width:40px;height:40px;object-fit:cover;">
                  @else
                    <div class="bg-light rounded d-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;">
                      <i class="fas fa-image text-muted small"></i>
                    </div>
                  @endif
                  @if(!empty($offer->slug))
                    <a href="{{ route('ahgmarketplace.listing', ['slug' => $offer->slug]) }}" class="text-decoration-none fw-semibold">{{ $offer->title ?? __('Listing') }}</a>
                  @else
                    <span class="fw-semibold">{{ $offer->title ?? __('Listing') }}</span>
                  @endif
                </div>
              </td>
              <td class="text-end fw-semibold">{{ $offer->currency ?? '' }} {{ number_format((float) ($offer->offer_amount ?? 0), 2) }}</td>
              <td class="text-end">
                @if(!empty($offer->counter_amount))
                  <span class="fw-semibold text-primary">{{ $offer->currency ?? '' }} {{ number_format((float) $offer->counter_amount, 2) }}</span>
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
              <td>
                <span class="badge bg-{{ $statusClass }}">{{ ucfirst($offer->status ?? '-') }}</span>
                @if(!empty($offer->expires_at) && in_array($offer->status ?? '', ['pending', 'countered']))
                  <br><span class="small text-muted">{{ __('Expires') }}: {{ date('d M Y', strtotime($offer->expires_at)) }}</span>
                @endif
              </td>
              <td class="text-end">
                @if(($offer->status ?? '') === 'countered')
                  <form method="POST" action="{{ route('ahgmarketplace.my-offers.post') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="form_action" value="accept_counter">
                    <input type="hidden" name="offer_id" value="{{ (int) $offer->id }}">
                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('{{ __('Accept counter-offer of :currency :amount?', ['currency' => $offer->currency ?? '', 'amount' => number_format((float) $offer->counter_amount, 2)]) }}');">
                      <i class="fas fa-check me-1"></i>{{ __('Accept Counter') }}
                    </button>
                  </form>
                @endif
                @if(in_array($offer->status ?? '', ['pending', 'countered']))
                  <form method="POST" action="{{ route('ahgmarketplace.my-offers.post') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="form_action" value="withdraw">
                    <input type="hidden" name="offer_id" value="{{ (int) $offer->id }}">
                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('{{ __('Withdraw this offer?') }}');">
                      <i class="fas fa-times me-1"></i>{{ __('Withdraw') }}
                    </button>
                  </form>
                @endif
                @if(!empty($offer->seller_response))
                  <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="tooltip" title="{{ $offer->seller_response }}">
                    <i class="fas fa-comment"></i>
                  </button>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
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

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
});
</script>
@endpush
@endsection
