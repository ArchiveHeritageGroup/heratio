{{--
  Seller Dashboard — Heratio Marketplace.
  Receives: $seller, $stats, $recentTransactions, $pendingOfferCount,
            $recentListings (top 10), $recentListingsTotal, $recentOffers (top 5).
--}}
@extends('theme::layouts.1col')

@section('title', 'Seller Dashboard')
@section('body-class', 'marketplace seller-dashboard')

@section('content')

  {{-- Header band --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
      <h1 class="mb-1">
        <i class="fas fa-store me-2 text-primary"></i>
        {{ $seller->display_name }}
      </h1>
      <div class="text-muted small">
        Seller #{{ $seller->id }} &middot;
        <span class="badge {{ $seller->is_active ? 'bg-success' : 'bg-secondary' }}">
          {{ $seller->is_active ? 'Active' : 'Inactive' }}
        </span>
        <span class="badge {{ $seller->verification_status === 'verified' ? 'bg-success' : 'bg-warning text-dark' }}">
          {{ ucfirst($seller->verification_status) }}
        </span>
        @if($seller->trust_level)
          <span class="badge bg-info text-dark">Trust: {{ $seller->trust_level }}</span>
        @endif
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('ahgmarketplace.seller-listing-create') }}" class="btn btn-success">
        <i class="fas fa-plus me-1"></i> New listing
      </a>
      <a href="{{ route('ahgmarketplace.seller-profile') }}" class="btn btn-outline-primary">
        <i class="fas fa-user me-1"></i> Edit profile
      </a>
      @if($seller->slug)
        <a href="{{ route('ahgmarketplace.seller', ['slug' => $seller->slug]) }}" class="btn btn-outline-secondary" target="_blank">
          <i class="fas fa-external-link-alt me-1"></i> Public profile
        </a>
      @endif
    </div>
  </div>

  {{-- Stats grid --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6 mb-1">{{ number_format($stats['total_listings']) }}</div>
          <div class="text-muted small">Total listings</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center border-success">
        <div class="card-body">
          <div class="display-6 mb-1 text-success">{{ number_format($stats['published_listings']) }}</div>
          <div class="text-muted small">Published</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6 mb-1">{{ number_format($stats['draft_listings']) }}</div>
          <div class="text-muted small">Drafts</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6 mb-1">{{ number_format($stats['sold_listings']) }}</div>
          <div class="text-muted small">
            Sold
            @if(($stats['demo_sold_listings'] ?? 0) > 0)
              <span class="badge bg-warning text-dark ms-1" title="{{ __('Includes demo-mode sales (e-commerce disabled)') }}">
                {{ number_format($stats['demo_sold_listings']) }} demo
              </span>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6 mb-1">{{ $seller->payout_currency ?: 'ZAR' }} {{ number_format($stats['total_revenue'], 2) }}</div>
          <div class="text-muted small">Total revenue</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center {{ $stats['pending_offers'] > 0 ? 'border-warning' : '' }}">
        <div class="card-body">
          <div class="display-6 mb-1 {{ $stats['pending_offers'] > 0 ? 'text-warning' : '' }}">
            {{ number_format($stats['pending_offers']) }}
          </div>
          <div class="text-muted small">Pending offers</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6 mb-1">{{ number_format($stats['total_views']) }}</div>
          <div class="text-muted small">Listing views</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100 text-center">
        <div class="card-body">
          <div class="display-6 mb-1">{{ number_format($stats['total_favourites']) }}</div>
          <div class="text-muted small">Favourites</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Quick actions --}}
  <div class="card mb-4">
    <div class="card-header fw-bold"><i class="fas fa-bolt me-1"></i> Quick actions</div>
    <div class="card-body">
      <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('ahgmarketplace.seller-listings') }}" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-list me-1"></i> All listings ({{ $stats['total_listings'] }})
        </a>
        <a href="{{ route('ahgmarketplace.seller-offers') }}" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-handshake me-1"></i> Offers ({{ $stats['pending_offers'] }} pending)
        </a>
        <a href="{{ route('ahgmarketplace.seller-transactions') }}" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-receipt me-1"></i> Transactions
        </a>
        <a href="{{ route('ahgmarketplace.seller-enquiries') }}" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-envelope me-1"></i> Enquiries ({{ $stats['total_enquiries'] }})
        </a>
        <a href="{{ route('ahgmarketplace.seller-collections') }}" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-layer-group me-1"></i> Collections
        </a>
        <a href="{{ route('ahgmarketplace.seller-artists') }}" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-palette me-1"></i> Artists
        </a>
        <a href="{{ route('ahgmarketplace.my-licences') }}" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-file-contract me-1"></i> My Licences
        </a>
        <a href="{{ route('ahgmarketplace.seller-payouts') }}" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-money-check-alt me-1"></i> Payouts
        </a>
        <a href="{{ route('ahgmarketplace.seller-analytics') }}" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-chart-line me-1"></i> Analytics
        </a>
        <a href="{{ route('ahgmarketplace.seller-reviews') }}" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-star me-1"></i> Reviews ({{ $seller->rating_count }})
        </a>
      </div>
    </div>
  </div>

  <div class="row g-4">

    {{-- Recent listings --}}
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="fw-bold"><i class="fas fa-list me-1"></i> Recent listings</span>
          <a href="{{ route('ahgmarketplace.seller-listings') }}" class="small">View all &raquo;</a>
        </div>
        @if(count($recentListings))
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>{{ __('Title') }}</th>
                  <th>{{ __('Sector') }}</th>
                  <th>{{ __('Status') }}</th>
                  <th class="text-end">{{ __('Price') }}</th>
                  <th class="text-center" style="width: 130px;">{{ __('Actions') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($recentListings as $l)
                  <tr>
                    <td>
                      <a href="{{ route('ahgmarketplace.seller-listing-edit', ['id' => $l->id]) }}" class="text-decoration-none">
                        {{ $l->title }}
                      </a>
                      <div class="small text-muted">{{ $l->listing_number }}</div>
                    </td>
                    <td><span class="badge bg-secondary text-uppercase">{{ $l->sector }}</span></td>
                    <td>
                      @php
                        $statusColors = [
                          'draft' => 'secondary',
                          'pending_review' => 'warning text-dark',
                          'published' => 'success',
                          'active' => 'success',
                          'sold' => 'primary',
                          'withdrawn' => 'dark',
                          'expired' => 'dark',
                        ];
                        $cls = $statusColors[$l->status] ?? 'secondary';
                      @endphp
                      <span class="badge bg-{{ $cls }}">{{ str_replace('_', ' ', $l->status) }}</span>
                    </td>
                    <td class="text-end">
                      @if($l->price_on_request)
                        <em class="text-muted small">On request</em>
                      @elseif($l->price !== null)
                        {{ $l->currency ?: 'ZAR' }} {{ number_format((float) $l->price, 2) }}
                      @else
                        <em class="text-muted small">Not set</em>
                      @endif
                    </td>
                    <td class="text-center">
                      <a href="{{ route('ahgmarketplace.seller-listing-edit', ['id' => $l->id]) }}"
                         class="btn btn-sm btn-outline-primary" title="{{ __('Edit') }}">
                        <i class="fas fa-edit"></i>
                      </a>
                      <a href="{{ route('ahgmarketplace.seller-listing-images', ['id' => $l->id]) }}"
                         class="btn btn-sm btn-outline-secondary" title="{{ __('Manage images') }}">
                        <i class="fas fa-images"></i>
                      </a>
                      @if($l->status === 'draft')
                        <form method="POST" action="{{ route('ahgmarketplace.seller-listing-publish') }}" class="d-inline">
                          @csrf
                          <input type="hidden" name="id" value="{{ $l->id }}">
                          <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Publish') }}">
                            <i class="fas fa-globe"></i>
                          </button>
                        </form>
                      @elseif(in_array($l->status, ['published', 'active']))
                        <a href="{{ route('ahgmarketplace.listing', ['slug' => $l->slug]) }}"
                           class="btn btn-sm btn-outline-info" title="{{ __('View public listing') }}" target="_blank">
                          <i class="fas fa-external-link-alt"></i>
                        </a>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="card-body text-center text-muted py-4">
            No listings yet.
            <a href="{{ route('ahgmarketplace.seller-listing-create') }}">Create your first listing</a>.
          </div>
        @endif
      </div>
    </div>

    {{-- Recent offers + transactions --}}
    <div class="col-lg-5">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="fw-bold"><i class="fas fa-handshake me-1"></i> Recent offers</span>
          <a href="{{ route('ahgmarketplace.seller-offers') }}" class="small">View all &raquo;</a>
        </div>
        @if(count($recentOffers))
          <ul class="list-group list-group-flush">
            @foreach($recentOffers as $o)
              <li class="list-group-item small d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-semibold">{{ $o->listing_title }}</div>
                  <div class="text-muted">
                    {{ $o->currency ?: 'ZAR' }} {{ number_format((float) $o->offer_amount, 2) }}
                    &middot;
                    {{ \Carbon\Carbon::parse($o->created_at)->diffForHumans() }}
                  </div>
                </div>
                <span class="badge bg-{{ $o->offer_status === 'pending' ? 'warning text-dark' : ($o->offer_status === 'accepted' ? 'success' : 'secondary') }}">
                  {{ $o->offer_status }}
                </span>
              </li>
            @endforeach
          </ul>
        @else
          <div class="card-body text-center text-muted py-3 small">No offers yet.</div>
        @endif
      </div>

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="fw-bold"><i class="fas fa-receipt me-1"></i> Recent transactions</span>
          <a href="{{ route('ahgmarketplace.seller-transactions') }}" class="small">View all &raquo;</a>
        </div>
        @if(count($recentTransactions))
          <ul class="list-group list-group-flush">
            @foreach($recentTransactions as $t)
              <li class="list-group-item small d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-semibold">{{ $t->transaction_number ?? '#' . $t->id }}</div>
                  <div class="text-muted">
                    {{ $t->currency ?? 'ZAR' }} {{ number_format((float) ($t->sale_price ?? 0), 2) }}
                    &middot;
                    {{ \Carbon\Carbon::parse($t->created_at)->diffForHumans() }}
                  </div>
                </div>
                <span class="badge bg-{{ ($t->payment_status ?? '') === 'paid' ? 'success' : 'secondary' }}">
                  {{ $t->payment_status ?? 'pending' }}
                </span>
              </li>
            @endforeach
          </ul>
        @else
          <div class="card-body text-center text-muted py-3 small">No transactions yet.</div>
        @endif
      </div>
    </div>
  </div>

  {{-- Profile summary --}}
  <div class="card mt-4">
    <div class="card-header fw-bold"><i class="fas fa-id-card me-1"></i> Profile</div>
    <div class="card-body">
      <div class="row g-3 small">
        <div class="col-md-4">
          <div class="text-muted">Display name</div>
          <div>{{ $seller->display_name }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted">Email</div>
          <div>{{ $seller->email ?: '—' }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted">Phone</div>
          <div>{{ $seller->phone ?: '—' }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted">Country / city</div>
          <div>{{ trim(($seller->city ?? '') . ($seller->country ? ', ' . $seller->country : ''), ', ') ?: '—' }}</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted">Commission rate</div>
          <div>{{ number_format((float) $seller->commission_rate, 2) }} %</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted">Payout method</div>
          <div>{{ $seller->payout_method ?: '—' }} ({{ $seller->payout_currency ?: 'ZAR' }})</div>
        </div>
        <div class="col-md-4">
          <div class="text-muted">Sectors</div>
          <div>
            @php
              $sectors = is_string($seller->sectors) ? (json_decode($seller->sectors, true) ?: []) : ($seller->sectors ?: []);
            @endphp
            @foreach($sectors as $s)
              <span class="badge bg-light text-dark border me-1">{{ $s }}</span>
            @endforeach
            @if(empty($sectors))<span class="text-muted">—</span>@endif
          </div>
        </div>
        <div class="col-md-8">
          <div class="text-muted">Bio</div>
          <div>{{ $seller->bio ?: '—' }}</div>
        </div>
      </div>
    </div>
  </div>

@endsection
