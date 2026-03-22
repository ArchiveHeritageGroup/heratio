@extends('theme::layouts.1col')

@section('title', 'Integrity Check')
@section('body-class', 'admin integrity')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Integrity Check</h1>
      <span class="small text-muted">Digital object verification and integrity monitoring</span>
    </div>
  </div>

  @if(!$configured)
    <div class="alert alert-warning mb-4">
      <i class="fas fa-exclamation-triangle me-1"></i>
      <strong>Not configured.</strong>
      The integrity check system has not been set up yet. The required database tables
      (<code>integrity_run</code>, <code>integrity_dead_letter</code>) do not exist.
      Please run the integrity module migration to enable this feature.
    </div>
  @endif

  {{-- Stats cards --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg">
      <div class="card text-center">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold">{{ number_format($stats['master_objects']) }}</div>
          <div class="small text-muted">Master Objects</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg">
      <div class="card text-center border-primary">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-primary">{{ number_format($stats['total_verifications']) }}</div>
          <div class="small text-muted">Total Verifications</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg">
      <div class="card text-center border-success">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-success">{{ $stats['pass_rate'] }}%</div>
          <div class="small text-muted">Pass Rate</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg">
      <div class="card text-center {{ $stats['open_dead_letters'] > 0 ? 'border-danger' : 'border-secondary' }}">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold {{ $stats['open_dead_letters'] > 0 ? 'text-danger' : '' }}">{{ number_format($stats['open_dead_letters']) }}</div>
          <div class="small text-muted">Open Dead Letters</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg">
      <div class="card text-center border-warning">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-warning">{{ number_format($stats['never_verified']) }}</div>
          <div class="small text-muted">Never Verified</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg">
      <div class="card text-center border-info">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-info">{{ number_format($stats['throughput_7d']) }}</div>
          <div class="small text-muted">Throughput (7 days)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg">
      <div class="card text-center border-secondary">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-secondary">{{ number_format($stats['total_verifications']) }}</div>
          <div class="small text-muted">Storage Scanned</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Repository filter --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
      <label for="repository_filter" class="form-label mb-0 fw-bold">Filter by repository: <span class="badge bg-secondary ms-1">Optional</span></label>
      <select id="repository_filter" class="form-select form-select-sm" style="width: auto; min-width: 200px;">
        <option value="">All repositories</option>
        @foreach($repositories as $repo)
          <option value="{{ $repo->id }}">{{ $repo->name }}</option>
        @endforeach
      </select>
    </div>
  </div>

  {{-- Quick Actions --}}
  <div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ url('/integrity/schedules') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-clock me-1"></i>Schedules</a>
    <a href="{{ url('/integrity/ledger') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-clipboard-list me-1"></i>Ledger</a>
    <a href="{{ url('/integrity/dead-letters') }}" class="btn btn-sm atom-btn-white">
      <i class="fas fa-exclamation-circle me-1"></i>Dead Letters
      @if($stats['open_dead_letters'] > 0)
        <span class="badge bg-danger ms-1">{{ number_format($stats['open_dead_letters']) }}</span>
      @endif
    </a>
    <a href="{{ url('/integrity/policies') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-cogs me-1"></i>Policies</a>
    <a href="{{ url('/integrity/holds') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-lock me-1"></i>Holds</a>
    <a href="{{ url('/integrity/alerts') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-bell me-1"></i>Alerts</a>
    <a href="{{ url('/integrity/export') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-download me-1"></i>Export</a>
    <a href="{{ url('/integrity/report') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-chart-bar me-1"></i>Report</a>
  </div>

  {{-- Recent Verification Runs --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Verification Runs</h5>
    </div>
    <div class="card-body">
      @if(!$configured)
        <p class="text-muted mb-0">Integrity tables are not available. No verification runs to display.</p>
      @elseif($recentRuns->isEmpty())
        <p class="text-muted mb-0">No verification runs have been executed yet.</p>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr>
                <th>Schedule</th>
                <th>Status</th>
                <th>Progress</th>
                <th>Started</th>
                <th>Completed</th>
                <th>Duration</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentRuns as $run)
                <tr>
                  <td>{{ $run->schedule_name ?? $run->schedule_id ?? 'Manual' }}</td>
                  <td>
                    @if($run->status === 'passed')
                      <span class="badge bg-success">Passed</span>
                    @elseif($run->status === 'failed')
                      <span class="badge bg-danger">Failed</span>
                    @elseif($run->status === 'running')
                      <span class="badge bg-primary">Running</span>
                    @elseif($run->status === 'queued')
                      <span class="badge bg-warning text-dark">Queued</span>
                    @elseif($run->status === 'partial')
                      <span class="badge bg-info">Partial</span>
                    @else
                      <span class="badge bg-secondary">{{ ucfirst($run->status ?? 'Unknown') }}</span>
                    @endif
                  </td>
                  <td>
                    @if(isset($run->checked_count) && isset($run->total_count) && $run->total_count > 0)
                      <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height: 8px;">
                          <div class="progress-bar" role="progressbar"
                               style="width: {{ round(($run->checked_count / $run->total_count) * 100) }}%"
                               aria-valuenow="{{ $run->checked_count }}" aria-valuemin="0"
                               aria-valuemax="{{ $run->total_count }}"></div>
                        </div>
                        <span class="small text-muted text-nowrap">
                          {{ number_format($run->checked_count) }}/{{ number_format($run->total_count) }}
                        </span>
                      </div>
                    @else
                      &mdash;
                    @endif
                  </td>
                  <td>{{ $run->started_at ? \Carbon\Carbon::parse($run->started_at)->format('Y-m-d H:i') : '' }}</td>
                  <td>{{ $run->completed_at ? \Carbon\Carbon::parse($run->completed_at)->format('Y-m-d H:i') : '' }}</td>
                  <td>{{ $run->duration ?? '&mdash;' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  {{-- Daily Verification Trend --}}
  @if(!empty($dailyTrend))
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Daily Verification Trend (30 days)</h5></div>
    <div class="card-body">
      <canvas id="trendChart" height="200"></canvas>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
  <script>
  new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
      labels: {!! json_encode(array_column($dailyTrend, 'day')) !!},
      datasets: [{
        label: 'Verifications',
        data: {!! json_encode(array_column($dailyTrend, 'cnt')) !!},
        borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', fill: true, tension: 0.3
      }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } } }
  });
  </script>
  @endif

  {{-- Repository Breakdown --}}
  @if(!empty($repoBreakdown))
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-building me-2"></i>Repository Breakdown</h5></div>
    <div class="card-body p-0">
      <table class="table table-bordered mb-0">
        <thead>
        <tbody>
          @foreach($repoBreakdown as $rb)
            <tr><td>{{ $rb->name }}</td><td>{{ $rb->total }}</td><td class="text-success">{{ $rb->passed }}</td><td class="text-danger">{{ $rb->failed }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Failure Type Breakdown --}}
  @if(!empty($failureTypes))
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Failure Types</h5></div>
    <div class="card-body p-0">
      <table class="table table-bordered mb-0">
        <thead>
        <tbody>
          @foreach($failureTypes as $ft)
            <tr><td>{{ $ft->reason ?? 'Unknown' }}</td><td>{{ $ft->cnt }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif
@endsection
