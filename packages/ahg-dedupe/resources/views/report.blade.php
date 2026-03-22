@extends('theme::layouts.1col')

@section('title', 'Duplicate Detection Report')
@section('body-class', 'admin dedupe report')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-chart-bar me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Duplicate Detection Report</h1>
      <span class="small text-muted">Statistics and breakdown</span>
    </div>
    <div class="ms-auto">
      <a href="{{ route('dedupe.index') }}" class="btn atom-btn-white">
        <i class="fas fa-arrow-left me-1"></i> Dashboard
      </a>
    </div>
  </div>

  {{-- Efficiency Metrics --}}
  @if(isset($efficiency))
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <h2 class="text-primary">{{ number_format($efficiency['total_detected'] ?? 0) }}</h2>
            <p class="text-muted mb-0">Total Detected</p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <h2 class="text-success">{{ number_format($efficiency['total_merged'] ?? 0) }}</h2>
            <p class="text-muted mb-0">Merged</p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <h2 class="text-secondary">{{ number_format($efficiency['total_dismissed'] ?? 0) }}</h2>
            <p class="text-muted mb-0">Dismissed</p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <h2 class="text-warning">{{ $efficiency['false_positive_rate'] ?? 0 }}%</h2>
            <p class="text-muted mb-0">False Positive Rate</p>
          </div>
        </div>
      </div>
    </div>
  @endif

  <div class="row">
    {{-- Monthly Trend --}}
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong><i class="fas fa-chart-line me-2"></i>Monthly Detection Trend</strong></div>
        <div class="card-body p-0">
          @if($monthlyStats->isEmpty())
            <div class="p-3 text-muted text-center">No data available yet.</div>
          @else
            <div class="table-responsive">
              <table class="table table-bordered table-striped mb-0">
                <thead>
                  <tr>
                    <th>Month</th>
                    <th class="text-end">Detected</th>
                    <th class="text-end">Merged</th>
                    <th class="text-end">Dismissed</th>
                    <th>Resolution Rate</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($monthlyStats as $row)
                    @php
                      $resolved = ($row->merged ?? 0) + ($row->dismissed ?? 0);
                      $rate = $row->total > 0 ? round(($resolved / $row->total) * 100, 1) : 0;
                    @endphp
                    <tr>
                      <td>{{ $row->month }}</td>
                      <td class="text-end">{{ number_format($row->total) }}</td>
                      <td class="text-end"><span class="text-success">{{ number_format($row->merged ?? 0) }}</span></td>
                      <td class="text-end"><span class="text-secondary">{{ number_format($row->dismissed ?? 0) }}</span></td>
                      <td>
                        <div class="progress" style="height: 20px;">
                          <div class="progress-bar bg-success" role="progressbar" style="width: {{ $rate }}%">{{ $rate }}%</div>
                        </div>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Top Duplicate Clusters --}}
    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong><i class="fas fa-clone me-2"></i>Top Duplicate Clusters</strong></div>
        <div class="card-body p-0">
          @if(!isset($topClusters) || $topClusters->isEmpty())
            <div class="p-3 text-muted text-center">No pending duplicate clusters.</div>
          @else
            <ul class="list-group list-group-flush">
              @foreach($topClusters as $cluster)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <a href="{{ route('dedupe.browse', ['record' => $cluster->record_a_id]) }}">
                      {{ Str::limit($cluster->title ?? 'Untitled', 35) }}
                    </a>
                  </div>
                  <span class="badge bg-warning rounded-pill" title="Duplicate pairs">{{ $cluster->duplicate_count }}</span>
                </li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- By Detection Method --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong>By Detection Method</strong></div>
    <div class="card-body p-0">
      @if($methodBreakdown->isEmpty())
        <div class="p-3 text-muted">No data available.</div>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr>
                <th>Method</th>
                <th class="text-end">Total</th>
                <th class="text-end">Avg Score</th>
                <th class="text-end">Confirmed</th>
                <th class="text-end">Dismissed</th>
              </tr>
            </thead>
            <tbody>
              @foreach($methodBreakdown as $row)
                <tr>
                  <td><span class="badge bg-light text-dark">{{ $row->detection_method }}</span></td>
                  <td class="text-end">{{ number_format($row->total) }}</td>
                  <td class="text-end">{{ number_format($row->avg_score, 1) }}%</td>
                  <td class="text-end">{{ number_format($row->confirmed) }}</td>
                  <td class="text-end">{{ number_format($row->dismissed) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  {{-- Export Options --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong><i class="fas fa-download me-2"></i>Export Reports</strong></div>
    <div class="card-body">
      <p>Use the CLI to export detailed reports:</p>
      <div class="row">
        <div class="col-md-6">
          <h6>CSV Export</h6>
          <pre class="bg-light p-3 rounded">php artisan dedupe:report --format=csv --output=duplicates.csv</pre>
        </div>
        <div class="col-md-6">
          <h6>JSON Export</h6>
          <pre class="bg-light p-3 rounded">php artisan dedupe:report --format=json --output=duplicates.json</pre>
        </div>
      </div>
      <h6 class="mt-3">Filter Options</h6>
      <pre class="bg-light p-3 rounded">php artisan dedupe:report --status=pending --min-score=0.9 --limit=500</pre>
    </div>
  </div>
@endsection
