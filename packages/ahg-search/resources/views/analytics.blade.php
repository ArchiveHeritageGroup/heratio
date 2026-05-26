{{--
  Search analytics dashboard - issue #650 Phase 3.

  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
--}}
@extends('theme::layouts.1col')

@section('title', 'Search analytics')
@section('body-class', 'search admin search-analytics')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="bi bi-graph-up me-3 fs-2" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Search analytics</h1>
      <small class="text-muted">
        Since {{ $since->format('Y-m-d H:i') }} ({{ $days }} {{ $days === 1 ? 'day' : 'days' }})
      </small>
    </div>
  </div>

  {{-- Range selector --}}
  <form method="get" action="{{ route('search.analytics') }}" class="card mb-4">
    <div class="card-body d-flex align-items-end gap-3">
      <div>
        <label for="days" class="form-label mb-1">Window (days)</label>
        <input type="number" id="days" name="days" min="1" max="365"
               class="form-control" style="width: 8rem;" value="{{ $days }}">
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Refresh
      </button>
    </div>
  </form>

  {{-- Totals strip --}}
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small">Total searches</div>
          <div class="fs-3 fw-semibold">{{ number_format($totals['total']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small">Unique queries</div>
          <div class="fs-3 fw-semibold">{{ number_format($totals['unique_queries']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small">Zero-result searches</div>
          <div class="fs-3 fw-semibold text-danger">{{ number_format($totals['zero']) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small">Overall CTR</div>
          <div class="fs-3 fw-semibold">{{ number_format($totals['ctr'] * 100, 1) }}%</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Top queries --}}
  <div class="card mb-4">
    <div class="card-header d-flex align-items-center">
      <i class="bi bi-bar-chart-fill me-2" aria-hidden="true"></i>
      <strong>Top queries</strong>
      <span class="badge bg-secondary ms-2">{{ count($top) }}</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>Query</th>
            <th class="text-end">Count</th>
            <th class="text-end">Clicks</th>
            <th class="text-end">CTR</th>
            <th class="text-end">Avg results</th>
            <th>Last seen</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
        @forelse($top as $row)
          <tr>
            <td><code>{{ $row['query'] }}</code></td>
            <td class="text-end">{{ number_format($row['count']) }}</td>
            <td class="text-end">{{ number_format($row['click_count']) }}</td>
            <td class="text-end">{{ number_format($row['ctr'] * 100, 1) }}%</td>
            <td class="text-end">{{ number_format($row['avg_results'], 1) }}</td>
            <td><small class="text-muted">{{ $row['last_seen'] }}</small></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary"
                 href="{{ route('search', ['q' => $row['query']]) }}"
                 title="Run this query">
                <i class="bi bi-search" aria-hidden="true"></i>
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-3">No data in this window.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Zero-result queries --}}
  <div class="card mb-4">
    <div class="card-header d-flex align-items-center">
      <i class="bi bi-exclamation-triangle-fill text-warning me-2" aria-hidden="true"></i>
      <strong>Zero-result queries</strong>
      <span class="badge bg-secondary ms-2">{{ count($zero) }}</span>
      <small class="text-muted ms-3">
        Signal for content gaps or missing synonym-dictionary entries.
      </small>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>Query</th>
            <th class="text-end">Count</th>
            <th>Last seen</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
        @forelse($zero as $row)
          <tr>
            <td><code>{{ $row['query'] }}</code></td>
            <td class="text-end">{{ number_format($row['count']) }}</td>
            <td><small class="text-muted">{{ $row['last_seen'] }}</small></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary"
                 href="{{ route('search', ['q' => $row['query']]) }}"
                 title="Re-run this query">
                <i class="bi bi-search" aria-hidden="true"></i>
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted py-3">No zero-result queries in this window.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <p class="text-muted small">
    Recorded via <code>ahg_search_query_log</code>. CTR =
    clicks / executions for a query string. See
    <a href="/help/search-analytics">/help/search-analytics</a> for the full schema and POPIA / GDPR notes.
  </p>
@endsection
