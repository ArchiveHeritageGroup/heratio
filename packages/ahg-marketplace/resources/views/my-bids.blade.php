{{--
  My Bids — listing of the user's bids and any auctions they've won.
  Receives: $bids (collection), $total, $page, $limit, $wonAuctions (collection).
--}}
@extends('theme::layouts.1col')

@section('title', 'My Bids')
@section('body-class', 'marketplace my-bids')

@section('content')

  <h1 class="mb-4"><i class="fas fa-gavel me-2 text-primary"></i> My Bids &amp; Wins</h1>

  {{-- Auctions you've won — pay-now CTA --}}
  @if(count($wonAuctions))
    <div class="card mb-4 border-success">
      <div class="card-header bg-success bg-opacity-10 fw-bold">
        <i class="fas fa-trophy me-1 text-success"></i> Auctions you've won ({{ count($wonAuctions) }})
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>{{ __('Lot') }}</th>
              <th class="text-end">{{ __('Winning bid') }}</th>
              <th>{{ __('Auction ended') }}</th>
              <th>{{ __('Payment') }}</th>
              <th class="text-center" style="width:160px;">{{ __('Action') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($wonAuctions as $w)
              <tr>
                <td>
                  <a href="{{ url('/marketplace/listing?slug=' . $w->slug) }}">{{ $w->title }}</a>
                </td>
                <td class="text-end fw-semibold">{{ $w->currency ?: 'ZAR' }} {{ number_format((float) $w->winning_bid, 2) }}</td>
                <td class="small text-muted">{{ \Carbon\Carbon::parse($w->end_time)->format('Y-m-d H:i') }}</td>
                <td>
                  @if($w->payment_status === 'paid')
                    <span class="badge bg-success">paid</span>
                  @elseif(in_array($w->payment_status, ['pending', 'pending_payment']))
                    <span class="badge bg-warning text-dark">awaiting payment</span>
                  @elseif($w->payment_status === 'cancelled')
                    <span class="badge bg-secondary">cancelled</span>
                  @else
                    <span class="badge bg-light text-dark">not started</span>
                  @endif
                </td>
                <td class="text-center">
                  @if($w->payment_status === 'paid')
                    <a href="{{ route('ahgmarketplace.my-purchases') }}" class="btn btn-sm btn-outline-success">
                      <i class="fas fa-receipt me-1"></i> {{ __('View receipt') }}
                    </a>
                  @else
                    <form method="POST" action="{{ route('ahgmarketplace.checkout-win', ['auctionId' => $w->auction_id]) }}" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-success">
                        <i class="fas fa-credit-card me-1"></i> {{ __('Pay now') }}
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
  @endif

  {{-- All bids placed --}}
  <div class="card">
    <div class="card-header fw-bold"><i class="fas fa-list me-1"></i> Bids you've placed ({{ $total }})</div>
    @if(count($bids))
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>{{ __('Lot') }}</th>
              <th class="text-end">{{ __('Your bid') }}</th>
              <th class="text-end">{{ __('Current top bid') }}</th>
              <th>{{ __('Auction ends') }}</th>
              <th>{{ __('Status') }}</th>
              <th class="text-center">{{ __('Listing') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($bids as $b)
              <tr>
                <td>{{ $b->title }}</td>
                <td class="text-end">ZAR {{ number_format((float) $b->bid_amount, 2) }}</td>
                <td class="text-end small text-muted">ZAR {{ number_format((float) ($b->current_bid ?? 0), 2) }}</td>
                <td class="small text-muted">{{ \Carbon\Carbon::parse($b->end_time)->format('Y-m-d H:i') }}</td>
                <td><span class="badge bg-secondary">{{ $b->auction_status }}</span></td>
                <td class="text-center">
                  <a href="{{ url('/marketplace/listing?slug=' . $b->slug) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-external-link-alt"></i>
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="card-body text-center text-muted py-4">
        You haven't placed any bids yet.
        <a href="{{ url('/marketplace/auction-browse') }}">Browse auctions</a>.
      </div>
    @endif
  </div>

@endsection
