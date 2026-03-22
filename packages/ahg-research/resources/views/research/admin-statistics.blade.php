@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-chart-bar me-2"></i>Research Statistics</h1>@endsection
@section('content')
<div class="card mb-3">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">Date Range Filter</h5></div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" class="form-control form-control-sm" name="date_from" value="{{ $dateFrom }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" class="form-control form-control-sm" name="date_to" value="{{ $dateTo }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn atom-btn-outline-light btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card border-primary h-100">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                <h3 class="fw-bold">{{ $stats['total_researchers'] ?? 0 }}</h3>
                <p class="text-muted mb-0">Total Researchers</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-success h-100">
            <div class="card-body text-center">
                <i class="fas fa-user-check fa-2x text-success mb-2"></i>
                <h3 class="fw-bold">{{ $stats['approved_researchers'] ?? 0 }}</h3>
                <p class="text-muted mb-0">Approved Researchers</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-info h-100">
            <div class="card-body text-center">
                <i class="fas fa-calendar-check fa-2x text-info mb-2"></i>
                <h3 class="fw-bold">{{ $stats['total_bookings'] ?? 0 }}</h3>
                <p class="text-muted mb-0">Total Bookings (Period)</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-warning h-100">
            <div class="card-body text-center">
                <i class="fas fa-check-double fa-2x text-warning mb-2"></i>
                <h3 class="fw-bold">{{ $stats['completed_bookings'] ?? 0 }}</h3>
                <p class="text-muted mb-0">Completed Bookings (Period)</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-secondary h-100">
            <div class="card-body text-center">
                <i class="fas fa-folder-open fa-2x text-secondary mb-2"></i>
                <h3 class="fw-bold">{{ $stats['total_collections'] ?? 0 }}</h3>
                <p class="text-muted mb-0">Total Collections</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card border-dark h-100">
            <div class="card-body text-center">
                <i class="fas fa-sticky-note fa-2x text-dark mb-2"></i>
                <h3 class="fw-bold">{{ $stats['total_annotations'] ?? 0 }}</h3>
                <p class="text-muted mb-0">Total Annotations</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Visits Over Time</h5></div>
            <div class="card-body">
                <div id="chart-visits-over-time" class="d-flex align-items-center justify-content-center" style="min-height: 250px;">
                    <span class="text-muted"><i class="fas fa-chart-line fa-3x mb-2 d-block"></i>Chart placeholder - integrate Chart.js or similar</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Researcher Types Breakdown</h5></div>
            <div class="card-body">
                <div id="chart-researcher-types" class="d-flex align-items-center justify-content-center" style="min-height: 250px;">
                    <span class="text-muted"><i class="fas fa-chart-pie fa-3x mb-2 d-block"></i>Chart placeholder - integrate Chart.js or similar</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
