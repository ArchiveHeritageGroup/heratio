@extends('theme::layouts.1col')

@section('title', 'Heritage Analytics Dashboard')
@section('body-class', 'admin heritage analytics')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="mb-0"><i class="fas fa-chart-line"></i> Analytics Dashboard</h1>
  </div>
  <p class="text-muted mb-4">Usage statistics, search performance, and access control metrics</p>

  <div class="row">
    {{-- Sidebar --}}
    <div class="col-md-3">
      @include('ahg-heritage-manage::partials._admin-sidebar')
    </div>

    {{-- Main content --}}
    <div class="col-md-9">
      {{-- Time period selector --}}
      <div class="btn-group mb-4" role="group" aria-label="Time period">
        <a href="{{ route('heritage.analytics', ['days' => 7]) }}"
           class="btn {{ $days === 7 ? 'atom-btn-outline-success' : 'atom-btn-white' }}">
          7 Days
        </a>
        <a href="{{ route('heritage.analytics', ['days' => 30]) }}"
           class="btn {{ $days === 30 ? 'atom-btn-outline-success' : 'atom-btn-white' }}">
          30 Days
        </a>
        <a href="{{ route('heritage.analytics', ['days' => 90]) }}"
           class="btn {{ $days === 90 ? 'atom-btn-outline-success' : 'atom-btn-white' }}">
          90 Days
        </a>
      </div>

      {{-- Overview Stats --}}
      <div class="row mb-4">
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-primary h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-eye fa-2x text-primary"></i></div>
              <h3 class="mb-1">{{ number_format($pageViews) }}</h3>
              <small class="text-muted">Page Views</small>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-info h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-search fa-2x text-info"></i></div>
              <h3 class="mb-1">{{ number_format($searches) }}</h3>
              <small class="text-muted">Searches</small>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-success h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-download fa-2x text-success"></i></div>
              <h3 class="mb-1">{{ number_format($downloads) }}</h3>
              <small class="text-muted">Downloads</small>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
          <div class="card shadow-sm border-secondary h-100">
            <div class="card-body text-center">
              <div class="mb-2"><i class="fas fa-user fa-2x text-secondary"></i></div>
              <h3 class="mb-1">{{ number_format($uniqueVisitors) }}</h3>
              <small class="text-muted">Unique Visitors</small>
            </div>
          </div>
        </div>
      </div>

      {{-- Search Performance --}}
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h5 class="mb-0"><i class="fas fa-search-plus"></i> Search Performance</h5>
        </div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col-md-4 mb-3">
              <div class="border rounded p-3">
                <h4 class="mb-1 text-primary">{{ $avgResults }}</h4>
                <small class="text-muted">Avg Results per Search</small>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <div class="border rounded p-3">
                <h4 class="mb-1 {{ $zeroResultRate > 20 ? 'text-danger' : 'text-success' }}">{{ $zeroResultRate }}%</h4>
                <small class="text-muted">Zero Results Rate</small>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <div class="border rounded p-3">
                <h4 class="mb-1 text-info">{{ $clickThroughRate }}%</h4>
                <small class="text-muted">Click-through Rate</small>
              </div>
            </div>
          </div>
          <hr>
          <a href="{{ route('heritage.analytics-search') }}" class="btn btn-outline-primary w-100">
            View Search Insights
          </a>
        </div>
      </div>

      {{-- Heritage Daily Metrics --}}
      @if(isset($metricTotals) && !empty($metricTotals))
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h5 class="mb-0"><i class="fas fa-calendar-day"></i> Daily Tracked Metrics</h5>
        </div>
        <div class="card-body">
          <div class="row text-center">
            @foreach($metricTotals as $metricType => $metricTotal)
            <div class="col-md-3 mb-3">
              <div class="border rounded p-3">
                <h4 class="mb-1 text-primary">{{ number_format($metricTotal, 0) }}</h4>
                <small class="text-muted">{{ ucwords(str_replace('_', ' ', $metricType)) }}</small>
              </div>
            </div>
            @endforeach
          </div>
        </div>
      </div>
      @endif

      {{-- Access Control --}}
      <div class="card shadow-sm">
        <div class="card-header bg-white">
          <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Access Control</h5>
        </div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col-md-4 mb-3">
              <div class="border rounded p-3">
                <h4 class="mb-1 {{ $pendingRequests > 0 ? 'text-warning' : 'text-success' }}">{{ number_format($pendingRequests) }}</h4>
                <small class="text-muted">Pending Requests</small>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <div class="border rounded p-3">
                <h4 class="mb-1 text-success">{{ $approvalRate }}%</h4>
                <small class="text-muted">Approval Rate</small>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <div class="border rounded p-3">
                <h4 class="mb-1 {{ $popiaFlags > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($popiaFlags) }}</h4>
                <small class="text-muted">POPIA Flags</small>
              </div>
            </div>
          </div>
          <hr>
          <div class="d-flex gap-2">
            <a href="{{ route('heritage.admin-access-requests') }}" class="btn btn-outline-primary flex-fill">
              Requests
            </a>
            <a href="{{ route('heritage.admin-popia') }}" class="btn btn-outline-primary flex-fill">
              POPIA Flags
            </a>
          </div>
        </div>
      </div>

      {{-- Trends Chart --}}
      <div class="card shadow-sm mt-4">
        <div class="card-header bg-white">
          <h5 class="mb-0"><i class="fas fa-chart-area"></i> Search & Click Trends</h5>
        </div>
        <div class="card-body">
          @if(!empty($trendSearches) && count($trendSearches) > 0)
          <div style="height: 250px;">
            <canvas id="trendsChart"></canvas>
          </div>
          <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
          <script>
          new Chart(document.getElementById('trendsChart'), {
              type: 'line',
              data: {
                  labels: {!! json_encode(array_keys($trendSearches)) !!},
                  datasets: [{
                      label: 'Searches',
                      data: {!! json_encode(array_values($trendSearches)) !!},
                      borderColor: 'rgb(13, 110, 253)',
                      backgroundColor: 'rgba(13, 110, 253, 0.1)',
                      tension: 0.3,
                      fill: true
                  }, {
                      label: 'Clicks',
                      data: {!! json_encode(array_values($trendClicks)) !!},
                      borderColor: 'rgb(25, 135, 84)',
                      backgroundColor: 'rgba(25, 135, 84, 0.1)',
                      tension: 0.3,
                      fill: true
                  }]
              },
              options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: { legend: { position: 'top' } },
                  scales: { y: { beginAtZero: true } }
              }
          });
          </script>
          @else
          <p class="text-muted text-center py-4">No trend data available for this period.</p>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
