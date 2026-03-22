@extends('theme::layouts.1col')

@section('title', 'Loan Management')
@section('body-class', 'browse loan')

@section('content')

  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-handshake me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Loan Management</h1>
      <span class="small text-muted">
        @if($loans->total())
          Showing {{ number_format($loans->total()) }} loan{{ $loans->total() !== 1 ? 's' : '' }}
        @else
          No loans found
        @endif
      </span>
    </div>
    <a href="{{ route('loan.create') }}" class="btn atom-btn-outline-success ms-auto">
      <i class="fas fa-plus me-1"></i> New Loan
    </a>
  </div>

  {{-- Statistics cards --}}
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="card border-success h-100">
        <div class="card-body text-center">
          <div class="fs-2 fw-bold text-success">{{ $stats['active'] }}</div>
          <div class="text-muted small">Active Loans</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card border-danger h-100">
        <div class="card-body text-center">
          <div class="fs-2 fw-bold text-danger">{{ $stats['overdue'] }}</div>
          <div class="text-muted small">Overdue</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card border-warning h-100">
        <div class="card-body text-center">
          <div class="fs-2 fw-bold text-warning">{{ $stats['due_soon'] }}</div>
          <div class="text-muted small">Due Soon (14 days)</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card border-primary h-100">
        <div class="card-body text-center">
          <div class="fs-2 fw-bold text-primary">{{ $stats['total'] }}</div>
          <div class="text-muted small">Total Loans</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Filters --}}
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" action="{{ route('loan.index') }}" class="row g-2 align-items-end">
        <div class="col-md-2">
          <label for="filter-type" class="form-label small">Type <span class="badge bg-warning ms-1">Recommended</span></label>
          <select name="type" id="filter-type" class="form-select form-select-sm">
            <option value="">All types</option>
            <option value="out" {{ ($params['type'] ?? '') === 'out' ? 'selected' : '' }}>Outgoing</option>
            <option value="in" {{ ($params['type'] ?? '') === 'in' ? 'selected' : '' }}>Incoming</option>
          </select>
        </div>
        <div class="col-md-2">
          <label for="filter-status" class="form-label small">Status <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="status" id="filter-status" class="form-select form-select-sm">
            <option value="">All statuses</option>
            @foreach(['draft','submitted','under_review','approved','rejected','preparing','dispatched','in_transit','received','on_loan','return_requested','returned','closed','cancelled'] as $s)
              <option value="{{ $s }}" {{ ($params['status'] ?? '') === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label for="filter-search" class="form-label small">Search <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="text" name="search" id="filter-search" class="form-control form-control-sm"
                 placeholder="Loan #, title, partner..." value="{{ $params['search'] ?? '' }}">
        </div>
        <div class="col-md-2">
          <label for="filter-sector" class="form-label small">Sector <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="sector" id="filter-sector" class="form-select form-select-sm">
            <option value="">All sectors</option>
            @foreach(['museum','archive','library','gallery'] as $sec)
              <option value="{{ $sec }}" {{ ($params['sector'] ?? '') === $sec ? 'selected' : '' }}>{{ ucfirst($sec) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-1">
          <div class="form-check">
            <input type="checkbox" name="overdue" value="1" id="filter-overdue" class="form-check-input"
                   {{ !empty($params['overdue']) ? 'checked' : '' }}>
            <label for="filter-overdue" class="form-check-label small">Overdue <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
        </div>
        <div class="col-md-2 d-flex gap-1">
          <button type="submit" class="btn atom-btn-outline-light btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
          <a href="{{ route('loan.index') }}" class="btn btn-sm atom-btn-white">Clear</a>
        </div>
      </form>
    </div>
  </div>

  {{-- Loans table --}}
  @if($loans->total())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped table-hover mb-0">
        <thead>
          <tr>
            <th>Loan #</th>
            <th>Title</th>
            <th>Partner Institution</th>
            <th>Type</th>
            <th>Status</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th class="text-center">Objects</th>
          </tr>
        </thead>
        <tbody>
          @foreach($loans as $loan)
            @php
              $isOverdue = $loan->end_date && $loan->end_date < now()->toDateString()
                           && in_array($loan->status, ['on_loan','dispatched','in_transit','received']);
            @endphp
            <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
              <td>
                <a href="{{ route('loan.show', $loan->id) }}" class="fw-bold text-decoration-none">
                  {{ $loan->loan_number }}
                </a>
              </td>
              <td>{{ $loan->title ?: '-' }}</td>
              <td>{{ $loan->partner_institution }}</td>
              <td>
                @if($loan->loan_type === 'out')
                  <span class="badge bg-info"><i class="fas fa-arrow-right me-1"></i>Outgoing</span>
                @else
                  <span class="badge bg-warning text-dark"><i class="fas fa-arrow-left me-1"></i>Incoming</span>
                @endif
              </td>
              <td>
                <span class="badge bg-{{ \AhgLoan\Services\LoanService::getStatusColour($loan->status) }}">
                  {{ ucwords(str_replace('_', ' ', $loan->status)) }}
                </span>
                @if($isOverdue)
                  <span class="badge bg-danger ms-1"><i class="fas fa-exclamation-triangle"></i> Overdue</span>
                @endif
              </td>
              <td>{{ $loan->start_date ? \Carbon\Carbon::parse($loan->start_date)->format('Y-m-d') : '-' }}</td>
              <td>{{ $loan->end_date ? \Carbon\Carbon::parse($loan->end_date)->format('Y-m-d') : '-' }}</td>
              <td class="text-center">{{ $loan->objects_count }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Pagination --}}
    <div class="d-flex justify-content-center">
      {{ $loans->withQueryString()->links() }}
    </div>
  @else
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-2"></i>No loans found matching your criteria.
    </div>
  @endif

@endsection
