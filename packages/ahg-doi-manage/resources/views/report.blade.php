@extends('theme::layouts.1col')

@section('title', 'DOI Reports')
@section('body-class', 'admin doi report')

@section('content')
  @if(!($tablesExist ?? false))
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle me-2"></i>
      The DOI management tables have not been created yet. Please run the database migration to set up DOI management.
    </div>
  @else
    <div class="multiline-header d-flex align-items-center mb-3">
      <i class="fas fa-3x fa-chart-bar me-3" aria-hidden="true"></i>
      <div class="d-flex flex-column flex-grow-1">
        <h1 class="mb-0">DOI Reports</h1>
        <span class="small text-muted">Statistics and Analytics</span>
      </div>
      <div class="d-flex gap-2">
        <a href="{{ route('doi.index') }}" class="btn btn-sm atom-btn-white">
          <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
        <div class="btn-group">
          <a href="{{ route('doi.report') }}?format=csv" class="btn btn-sm atom-btn-white">
            <i class="fas fa-file-csv me-1"></i> Export CSV
          </a>
          <a href="{{ route('doi.report') }}?format=json" class="btn btn-sm atom-btn-white">
            <i class="fas fa-file-code me-1"></i> Export JSON
          </a>
        </div>
      </div>
    </div>

    {{-- Summary Stats --}}
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-2">
        <div class="card text-center border-primary">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-primary">{{ number_format($stats['total'] ?? 0) }}</div>
            <div class="small text-muted">Total DOIs</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="card text-center border-success">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-success">{{ number_format($stats['findable'] ?? 0) }}</div>
            <div class="small text-muted">Findable</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="card text-center border-info">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-info">{{ number_format($stats['registered'] ?? 0) }}</div>
            <div class="small text-muted">Registered</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="card text-center border-secondary">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-secondary">{{ number_format($stats['draft'] ?? 0) }}</div>
            <div class="small text-muted">Draft</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="card text-center border-warning">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-warning">{{ number_format($stats['pending'] ?? 0) }}</div>
            <div class="small text-muted">Queue Pending</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="card text-center border-danger">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-danger">{{ number_format($stats['failed'] ?? 0) }}</div>
            <div class="small text-muted">Failed</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-6">
        {{-- Monthly Stats --}}
        <div class="card mb-4">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
            <i class="fas fa-calendar me-2"></i>Monthly Minting
          </div>
          <div class="card-body">
            @if(count($monthlyStats))
              <table class="table table-sm mb-0">
                <thead>
                  <tr>
                    <th>Month</th>
                    <th class="text-end">Minted</th>
                    <th class="text-end">Updated</th>
                    <th style="width: 40%"></th>
                  </tr>
                </thead>
                <tbody>
                  @php
                    $maxCount = collect($monthlyStats)->max('minted_count') ?: 1;
                  @endphp
                  @foreach($monthlyStats as $row)
                    @php
                      $percentage = ($row['minted_count'] / $maxCount) * 100;
                    @endphp
                    <tr>
                      <td>{{ $row['month'] }}</td>
                      <td class="text-end">{{ number_format($row['minted_count']) }}</td>
                      <td class="text-end">{{ number_format($row['updated_count']) }}</td>
                      <td>
                        <div class="progress" style="height: 20px;">
                          <div class="progress-bar bg-primary" style="width: {{ $percentage }}%"></div>
                        </div>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            @else
              <p class="text-muted text-center mb-0">No monthly statistics available yet.</p>
            @endif
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        {{-- By Repository --}}
        <div class="card mb-4">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
            <i class="fas fa-building me-2"></i>DOIs by Repository
          </div>
          <div class="card-body">
            @if(count($byRepository))
              <table class="table table-sm mb-0">
                <thead>
                  <tr>
                    <th>Repository</th>
                    <th class="text-end">Count</th>
                    <th style="width: 40%"></th>
                  </tr>
                </thead>
                <tbody>
                  @php
                    $maxRepoCount = collect($byRepository)->max('doi_count') ?: 1;
                  @endphp
                  @foreach($byRepository as $row)
                    @php
                      $percentage = ($row['doi_count'] / $maxRepoCount) * 100;
                    @endphp
                    <tr>
                      <td>{{ $row['repository_name'] ?: 'No repository' }}</td>
                      <td class="text-end">{{ number_format($row['doi_count']) }}</td>
                      <td>
                        <div class="progress" style="height: 20px;">
                          <div class="progress-bar bg-success" style="width: {{ $percentage }}%"></div>
                        </div>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            @else
              <p class="text-muted text-center mb-0">No repository breakdown available yet.</p>
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- Export Options --}}
    <div class="card">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-download me-2"></i>Export Options
      </div>
      <div class="card-body">
        <form method="get" action="{{ route('doi.report') }}" class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Format <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="format" class="form-select">
              <option value="csv">CSV</option>
              <option value="json">JSON</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Status <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="status" class="form-select">
              <option value="">All Statuses</option>
              <option value="findable">Findable</option>
              <option value="registered">Registered</option>
              <option value="draft">Draft</option>
              <option value="deleted">Deleted</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">From Date <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" name="from_date" class="form-control">
          </div>
          <div class="col-md-2">
            <label class="form-label">To Date <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" name="to_date" class="form-control">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn atom-btn-outline-success w-100">
              <i class="fas fa-download me-1"></i> Export
            </button>
          </div>
        </form>
      </div>
    </div>
  @endif
@endsection
