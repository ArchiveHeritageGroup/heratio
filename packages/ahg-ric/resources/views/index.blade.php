@extends('theme::layouts.1col')

@section('title', 'RiC Dashboard')
@section('body-class', 'admin ric')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-project-diagram me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">RiC Dashboard</h1>
      <span class="small text-muted">Records in Contexts</span>
    </div>
  </div>

  {{-- Status Cards --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card text-center {{ !empty($fusekiSettings) ? 'border-success' : 'border-warning' }}">
        <div class="card-body py-2">
          @if(!empty($fusekiSettings))
            <div class="fs-3 fw-bold text-success"><i class="fas fa-check-circle"></i></div>
            <div class="small text-muted">Fuseki Connected</div>
          @else
            <div class="fs-3 fw-bold text-warning"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="small text-muted">Fuseki Not Configured</div>
          @endif
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center border-primary">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-primary">{{ number_format(($queueStatus['queued'] ?? 0) + ($queueStatus['processing'] ?? 0)) }}</div>
          <div class="small text-muted">Queue Count</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center border-danger">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold text-danger">{{ number_format($orphanCount) }}</div>
          <div class="small text-muted">Orphaned Triples</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center {{ ($syncSummary['synced'] ?? 0) > 0 ? 'border-success' : 'border-secondary' }}">
        <div class="card-body py-2">
          <div class="fs-3 fw-bold {{ ($syncSummary['synced'] ?? 0) > 0 ? 'text-success' : 'text-secondary' }}">
            {{ ($syncSummary['synced'] ?? 0) > 0 ? 'Enabled' : 'Inactive' }}
          </div>
          <div class="small text-muted">Sync Status</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    {{-- Entity Sync Status --}}
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <i class="fas fa-sync me-1"></i> Entity Sync Status
        </div>
        <div class="card-body p-0">
          @if($entitySync->count())
            <div class="table-responsive">
              <table class="table table-bordered table-striped mb-0">
                <thead>
                  <tr>
                    <th>Entity Type</th>
                    <th class="text-center">Synced</th>
                    <th class="text-center">Pending</th>
                    <th class="text-center">Failed</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($entitySync as $row)
                    <tr>
                      <td>{{ ucfirst(str_replace('_', ' ', $row->entity_type)) }}</td>
                      <td class="text-center"><span class="badge bg-success">{{ number_format($row->synced) }}</span></td>
                      <td class="text-center"><span class="badge bg-warning text-dark">{{ number_format($row->pending) }}</span></td>
                      <td class="text-center"><span class="badge bg-danger">{{ number_format($row->failed) }}</span></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="p-3 text-muted">No sync data available.</div>
          @endif
        </div>
      </div>
    </div>

    {{-- Quick Actions --}}
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <i class="fas fa-bolt me-1"></i> Quick Actions
        </div>
        <div class="list-group list-group-flush">
          <a href="{{ route('ric.sync-status') }}" class="list-group-item list-group-item-action d-flex align-items-center">
            <i class="fas fa-sync-alt me-2 text-primary"></i> Sync Status
            <span class="badge bg-primary ms-auto">{{ number_format(array_sum($syncSummary)) }}</span>
          </a>
          <a href="{{ route('ric.queue') }}" class="list-group-item list-group-item-action d-flex align-items-center">
            <i class="fas fa-list-ol me-2 text-info"></i> Sync Queue
            <span class="badge bg-info ms-auto">{{ number_format(array_sum($queueStatus)) }}</span>
          </a>
          <a href="{{ route('ric.orphans') }}" class="list-group-item list-group-item-action d-flex align-items-center">
            <i class="fas fa-unlink me-2 text-danger"></i> Orphaned Triples
            <span class="badge bg-danger ms-auto">{{ number_format($orphanCount) }}</span>
          </a>
          <a href="{{ route('ric.logs') }}" class="list-group-item list-group-item-action d-flex align-items-center">
            <i class="fas fa-history me-2 text-secondary"></i> Sync Logs
          </a>
          @if(Route::has('settings.ahg'))
            <a href="{{ route('settings.ahg', 'fuseki') }}" class="list-group-item list-group-item-action d-flex align-items-center">
              <i class="fas fa-cog me-2 text-muted"></i> Fuseki Settings
            </a>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Recent Operations --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-history me-1"></i> Recent Operations</span>
      <a href="{{ route('ric.logs') }}" class="btn btn-sm btn-outline-secondary">View All</a>
    </div>
    <div class="card-body p-0">
      @if($recentOps->count())
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr>
                <th>Time</th>
                <th>Operation</th>
                <th>Entity</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentOps as $op)
                <tr>
                  <td class="text-nowrap">{{ $op->created_at ? \Carbon\Carbon::parse($op->created_at)->format('Y-m-d H:i:s') : '' }}</td>
                  <td>{{ $op->operation }}</td>
                  <td>{{ ucfirst(str_replace('_', ' ', $op->entity_type ?? '')) }} #{{ $op->entity_id ?? '' }}</td>
                  <td>
                    @if($op->status === 'completed')
                      <span class="badge bg-success">{{ $op->status }}</span>
                    @elseif($op->status === 'failed')
                      <span class="badge bg-danger">{{ $op->status }}</span>
                    @elseif($op->status === 'processing')
                      <span class="badge bg-primary">{{ $op->status }}</span>
                    @else
                      <span class="badge bg-secondary">{{ $op->status }}</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="p-3 text-muted">No recent operations.</div>
      @endif
    </div>
  </div>

  {{-- 7-Day Sync Trend --}}
  @if($syncTrend->count())
    <div class="card mb-4">
      <div class="card-header">
        <i class="fas fa-chart-bar me-1"></i> 7-Day Sync Trend
      </div>
      <div class="card-body">
        <div class="d-flex align-items-end" style="height: 120px; gap: 4px;">
          @php
            $maxCount = $syncTrend->max('cnt') ?: 1;
          @endphp
          @foreach($syncTrend as $day)
            @php
              $pct = ($day->cnt / $maxCount) * 100;
            @endphp
            <div class="d-flex flex-column align-items-center flex-fill">
              <small class="text-muted mb-1">{{ $day->cnt }}</small>
              <div class="bg-primary rounded-top w-100" style="height: {{ max($pct, 5) }}%; min-height: 4px;"></div>
              <small class="text-muted mt-1">{{ \Carbon\Carbon::parse($day->log_date)->format('D') }}</small>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif
@endsection
