@extends('theme::layouts.1col')

@section('title', 'DOI Management')
@section('body-class', 'admin doi dashboard')

@section('content')
  @if(!($tablesExist ?? false))
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle me-2"></i>
      The DOI management tables (<code>ahg_doi</code>, <code>ahg_doi_queue</code>, <code>ahg_doi_log</code>) have not been created yet.
      Please run the database migration to set up DOI management.
    </div>
  @else
    {{-- Header --}}
    <div class="multiline-header d-flex align-items-center mb-3">
      <i class="fas fa-3x fa-fingerprint me-3" aria-hidden="true"></i>
      <div class="d-flex flex-column flex-grow-1">
        <h1 class="mb-0">{{ __('DOI Management') }}</h1>
        <span class="small text-muted">DataCite Integration Dashboard</span>
      </div>
      <div class="d-flex gap-2">
        <div class="btn-group">
          <a href="{{ route('doi.report') }}?format=csv" class="btn btn-outline-secondary btn-sm" title="{{ __('Export CSV') }}">
            <i class="fas fa-file-csv me-1"></i> Export
          </a>
          <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
            <span class="visually-hidden">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="{{ route('doi.report') }}?format=csv"><i class="fas fa-file-csv me-2"></i>Export as CSV</a></li>
            <li><a class="dropdown-item" href="{{ route('doi.report') }}?format=json"><i class="fas fa-file-code me-2"></i>Export as JSON</a></li>
          </ul>
        </div>
        <form action="{{ route('doi.sync') }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-sync me-1"></i> Bulk Sync
          </button>
        </form>
        <a href="{{ route('doi.batch-mint') }}" class="btn btn-primary btn-sm">
          <i class="fas fa-plus me-1"></i> Batch Mint
        </a>
        <a href="{{ route('doi.config') }}" class="btn btn-outline-secondary btn-sm">
          <i class="fas fa-cog me-1"></i> Configuration
        </a>
      </div>
    </div>

    {{-- Stat cards -- row 1 --}}
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-4">
        <div class="card text-center border-primary">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-primary">{{ number_format($stats['total']) }}</div>
            <div class="small text-muted">Total DOIs</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="card text-center border-success">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-success">{{ number_format($stats['findable']) }}</div>
            <div class="small text-muted">Findable</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="card text-center border-info">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-info">{{ number_format($stats['registered']) }}</div>
            <div class="small text-muted">Registered</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Stat cards -- row 2 --}}
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-4">
        <div class="card text-center border-secondary">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-secondary">{{ number_format($stats['draft']) }}</div>
            <div class="small text-muted">Draft</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="card text-center border-warning">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-warning">{{ number_format($stats['pending']) }}</div>
            <div class="small text-muted">Queue Pending</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="card text-center border-danger">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-danger">{{ number_format($stats['failed']) }}</div>
            <div class="small text-muted">Failed</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Quick Links --}}
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <a href="{{ route('doi.browse') }}" class="card text-decoration-none h-100">
          <div class="card-body text-center">
            <i class="fas fa-2x fa-list text-primary mb-2"></i>
            <div class="fw-bold">Browse DOIs</div>
            <p class="small text-muted mb-0">View and manage all minted DOIs</p>
          </div>
        </a>
      </div>
      <div class="col-6 col-md-3">
        <a href="{{ route('doi.queue') }}" class="card text-decoration-none h-100">
          <div class="card-body text-center">
            <i class="fas fa-2x fa-tasks text-warning mb-2"></i>
            <div class="fw-bold">Queue</div>
            <p class="small text-muted mb-0">View pending minting operations</p>
          </div>
        </a>
      </div>
      <div class="col-6 col-md-3">
        <a href="{{ route('doi.report') }}" class="card text-decoration-none h-100">
          <div class="card-body text-center">
            <i class="fas fa-2x fa-chart-bar text-info mb-2"></i>
            <div class="fw-bold">Reports</div>
            <p class="small text-muted mb-0">DOI statistics and reports</p>
          </div>
        </a>
      </div>
      <div class="col-6 col-md-3">
        <a href="{{ route('doi.config') }}" class="card text-decoration-none h-100">
          <div class="card-body text-center">
            <i class="fas fa-2x fa-cog text-secondary mb-2"></i>
            <div class="fw-bold">Configuration</div>
            <p class="small text-muted mb-0">DataCite API settings</p>
          </div>
        </a>
      </div>
    </div>

    {{-- Recent DOIs --}}
    <h3 class="mb-3">{{ __('Recently Minted DOIs') }}</h3>
    @if(count($recentDois))
      <div class="table-responsive mb-3">
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr>
              <th>{{ __('DOI') }}</th>
              <th>{{ __('Record Title') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Minted') }}</th>
              <th class="text-end">{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($recentDois as $doi)
              <tr>
                <td>
                  <a href="https://doi.org/{{ $doi['doi'] }}" target="_blank" class="text-monospace text-decoration-none">
                    <code>{{ $doi['doi'] }}</code>
                    <i class="fas fa-external-link-alt fa-xs ms-1"></i>
                  </a>
                </td>
                <td>{{ $doi['record_title'] ?: '[Untitled]' }}</td>
                <td>
                  @if($doi['status'] === 'findable')
                    <span class="badge bg-success">Findable</span>
                  @elseif($doi['status'] === 'registered')
                    <span class="badge bg-info">Registered</span>
                  @elseif($doi['status'] === 'failed')
                    <span class="badge bg-danger">Failed</span>
                  @elseif($doi['status'] === 'deleted')
                    <span class="badge bg-danger">Deleted</span>
                  @else
                    <span class="badge bg-secondary">Draft</span>
                  @endif
                </td>
                <td>{{ $doi['minted_at'] ? \Carbon\Carbon::parse($doi['minted_at'])->format('Y-m-d H:i') : '-' }}</td>
                <td class="text-end">
                  <a href="{{ route('doi.view', $doi['id']) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-eye"></i> View
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="text-end">
        <a href="{{ route('doi.browse') }}" class="btn btn-sm btn-outline-secondary">View All</a>
      </div>
    @else
      <div class="text-center text-muted py-4">
        <i class="fas fa-link fa-3x mb-3"></i>
        <p>No DOIs minted yet.</p>
        <a href="{{ route('doi.queue') }}?batch=1" class="btn btn-outline-secondary">Mint Your First DOI</a>
      </div>
    @endif
  @endif
@endsection
