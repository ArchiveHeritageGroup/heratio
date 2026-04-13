{{--
 | Heratio — Marketplace Admin Reports
 |
 | @author    Johan Pieterse <johan@theahg.co.za>
 | @copyright 2026 Plain Sailing (Pty) Ltd t/a The Archive and Heritage Group
 | @license   AGPL-3.0-or-later
 |
 | Cloned from PSIS ahgMarketplacePlugin adminReportsSuccess.php for full parity.
--}}
@extends('theme::layouts.1col')

@section('title', 'Marketplace Reports - Marketplace Admin')

@section('content')

@php
    $totalRevenue     = (float) ($revenueStats->total_revenue ?? 0);
    $totalCommission  = (float) ($revenueStats->total_commission ?? 0);
    $netSellerPayouts = (float) ($revenueStats->net_seller_payouts ?? 0);
    $txnCount         = (int) ($revenueStats->transaction_count ?? 0);

    $maxRevenue = 0;
    if (!empty($monthlyRevenue)) {
        foreach ($monthlyRevenue as $m) {
            $rev = (float) ($m->revenue ?? 0);
            if ($rev > $maxRevenue) {
                $maxRevenue = $rev;
            }
        }
    }
@endphp

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.admin-dashboard') }}">Marketplace Admin</a></li>
    <li class="breadcrumb-item active">Reports</li>
  </ol>
</nav>

<h1 class="h3 mb-4">Marketplace Reports</h1>

{{-- Revenue overview cards --}}
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card h-100 text-center">
      <div class="card-body py-3">
        <i class="fas fa-coins text-success mb-1 d-block" style="font-size: 1.5rem;"></i>
        <div class="h4 mb-0">ZAR {{ number_format($totalRevenue, 2) }}</div>
        <small class="text-muted">Total Revenue</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card h-100 text-center">
      <div class="card-body py-3">
        <i class="fas fa-percentage text-primary mb-1 d-block" style="font-size: 1.5rem;"></i>
        <div class="h4 mb-0">ZAR {{ number_format($totalCommission, 2) }}</div>
        <small class="text-muted">Total Commission</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card h-100 text-center">
      <div class="card-body py-3">
        <i class="fas fa-wallet text-warning mb-1 d-block" style="font-size: 1.5rem;"></i>
        <div class="h4 mb-0">ZAR {{ number_format($netSellerPayouts, 2) }}</div>
        <small class="text-muted">Net Seller Payouts</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card h-100 text-center">
      <div class="card-body py-3">
        <i class="fas fa-receipt text-info mb-1 d-block" style="font-size: 1.5rem;"></i>
        <div class="h4 mb-0">{{ number_format($txnCount) }}</div>
        <small class="text-muted">Transaction Count</small>
      </div>
    </div>
  </div>
</div>

{{-- Monthly revenue table with progress bars --}}
<div class="card mb-4">
  <div class="card-header">
    <h5 class="card-title mb-0">Monthly Revenue</h5>
  </div>
  @if (!empty($monthlyRevenue))
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 120px;">Month</th>
            <th>Revenue</th>
            <th class="text-end" style="width: 140px;">Commission</th>
            <th class="text-end" style="width: 100px;">Sales</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($monthlyRevenue as $month)
            @php
                $rev = (float) ($month->revenue ?? 0);
                $pct = $maxRevenue > 0 ? round(($rev / $maxRevenue) * 100) : 0;
            @endphp
            <tr>
              <td class="fw-semibold">{{ $month->month ?? '-' }}</td>
              <td>
                <div class="d-flex align-items-center">
                  <div class="progress flex-grow-1 me-2" style="height: 20px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $pct }}%;" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                  <span class="text-nowrap small fw-semibold" style="width: 120px;">ZAR {{ number_format($rev, 2) }}</span>
                </div>
              </td>
              <td class="text-end small">ZAR {{ number_format((float) ($month->commission ?? 0), 2) }}</td>
              <td class="text-end">{{ number_format((int) ($month->sales_count ?? 0)) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="card-body text-center py-4">
      <p class="text-muted mb-0">No revenue data yet.</p>
    </div>
  @endif
</div>

<div class="row">

  {{-- Top 10 sellers --}}
  <div class="col-lg-6">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">Top 10 Sellers by Revenue</h5>
      </div>
      @if (!empty($topSellers))
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 40px;">#</th>
                <th>Seller</th>
                <th class="text-end">Sales</th>
                <th class="text-end">Revenue</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($topSellers as $idx => $ts)
                <tr>
                  <td class="small fw-semibold">{{ $idx + 1 }}</td>
                  <td>
                    <a href="{{ route('ahgmarketplace.seller', ['slug' => $ts->slug ?? '']) }}" class="text-decoration-none">
                      {{ $ts->display_name ?? '-' }}
                    </a>
                  </td>
                  <td class="text-end">{{ number_format((int) ($ts->sales_count ?? 0)) }}</td>
                  <td class="text-end fw-semibold">ZAR {{ number_format((float) ($ts->total_revenue ?? 0), 2) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="card-body text-center py-4">
          <p class="text-muted mb-0">No seller data yet.</p>
        </div>
      @endif
    </div>
  </div>

  {{-- Top 10 items --}}
  <div class="col-lg-6">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">Top 10 Items by Sales</h5>
      </div>
      @if (!empty($topItems))
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 40px;">#</th>
                <th>Item</th>
                <th>Sector</th>
                <th class="text-end">Revenue</th>
                <th class="text-end">Sales</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($topItems as $idx => $ti)
                <tr>
                  <td class="small fw-semibold">{{ $idx + 1 }}</td>
                  <td>
                    <a href="{{ route('ahgmarketplace.listing', ['slug' => $ti->slug ?? '']) }}" class="text-decoration-none">
                      {{ $ti->title ?? '-' }}
                    </a>
                  </td>
                  <td><span class="badge bg-info">{{ ucfirst($ti->sector ?? '-') }}</span></td>
                  <td class="text-end fw-semibold">ZAR {{ number_format((float) ($ti->total_revenue ?? 0), 2) }}</td>
                  <td class="text-end">{{ number_format((int) ($ti->sales_count ?? 0)) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="card-body text-center py-4">
          <p class="text-muted mb-0">No item data yet.</p>
        </div>
      @endif
    </div>
  </div>

</div>
@endsection
