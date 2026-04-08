@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'adminStatistics'])@endsection
@section('title', 'Research Statistics')

@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Statistics</li></ol></nav>

<h1 class="h2 mb-4"><i class="fas fa-chart-bar text-primary me-2"></i>Research Statistics</h1>

{{-- Date Filter --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
            <div class="col-md-3"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Apply</button></div>
            <div class="col-md-4 text-end">
                <a href="?date_from={{ date('Y-m-01') }}&date_to={{ date('Y-m-d') }}" class="btn btn-outline-secondary btn-sm">This Month</a>
                <a href="?date_from={{ date('Y-01-01') }}&date_to={{ date('Y-m-d') }}" class="btn btn-outline-secondary btn-sm">This Year</a>
            </div>
        </form>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row mb-4">
    @php $cards = [
        ['Total Researchers', $stats['total_researchers'] ?? 0, 'users', 'primary'],
        ['Bookings', $stats['total_bookings'] ?? 0, 'calendar-check', 'success'],
        ['Item Views', $stats['total_views'] ?? 0, 'eye', 'info'],
        ['Citations', $stats['total_citations'] ?? 0, 'quote-right', 'warning'],
    ]; @endphp
    @foreach($cards as $c)
    <div class="col-md-3">
        <div class="card bg-{{ $c[3] }} text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div><h3 class="mb-0">{{ number_format($c[1]) }}</h3><small>{{ $c[0] }}</small></div>
                    <i class="fas fa-{{ $c[2] }} fa-2x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Charts --}}
<div class="row mb-4">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Registrations Over Time</h5></div>
            <div class="card-body"><canvas id="registrationsChart" height="250"></canvas></div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Bookings by Room</h5></div>
            <div class="card-body"><canvas id="bookingsChart" height="250"></canvas></div>
        </div>
    </div>
</div>

{{-- Most Active Researchers --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-users me-2"></i>Most Active Researchers</h5></div>
    <div class="card-body p-0">
        @if(!empty($activeResearchers))
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Researcher</th><th>Institution</th><th class="text-center">Bookings</th><th class="text-center">Evidence Sets</th></tr></thead>
            <tbody>
            @foreach($activeResearchers as $r)
                <tr>
                    <td><a href="{{ route('research.viewResearcher', $r->id) }}">{{ e($r->first_name . ' ' . $r->last_name) }}</a></td>
                    <td>{{ e($r->institution ?? '-') }}</td>
                    <td class="text-center">{{ number_format($r->booking_count ?? 0) }}</td>
                    <td class="text-center">{{ number_format($r->collection_count ?? 0) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @else
        <div class="text-center text-muted py-4">No data available</div>
        @endif
    </div>
</div>

{{-- Breakdown Cards --}}
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0">Researcher Types</h6></div>
            <ul class="list-group list-group-flush">
                @forelse($stats['by_type'] ?? [] as $type)
                    <li class="list-group-item d-flex justify-content-between">{{ e($type->name ?? 'Unspecified') }}<span class="badge bg-secondary">{{ $type->count }}</span></li>
                @empty
                    <li class="list-group-item text-muted">No data</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0">Projects by Status</h6></div>
            <ul class="list-group list-group-flush">
                @forelse($stats['projects_by_status'] ?? [] as $s)
                    <li class="list-group-item d-flex justify-content-between">{{ ucfirst($s->status) }}<span class="badge bg-secondary">{{ $s->count }}</span></li>
                @empty
                    <li class="list-group-item text-muted">No data</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0">Reproduction Requests</h6></div>
            <ul class="list-group list-group-flush">
                @forelse($stats['reproductions_by_status'] ?? [] as $s)
                    <li class="list-group-item d-flex justify-content-between">{{ ucfirst(str_replace('_', ' ', $s->status)) }}<span class="badge bg-secondary">{{ $s->count }}</span></li>
                @empty
                    <li class="list-group-item text-muted">No data</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('registrationsChart'), {
        type: 'line',
        data: { labels: {!! json_encode(array_map(fn($r) => $r->period ?? '', $regData ?? [])) !!}, datasets: [{ label: 'Registrations', data: {!! json_encode(array_map(fn($r) => (int)($r->count ?? 0), $regData ?? [])) !!}, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', fill: true, tension: 0.3 }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
    new Chart(document.getElementById('bookingsChart'), {
        type: 'bar',
        data: { labels: {!! json_encode(array_map(fn($r) => $r->room_name ?? '', $roomData ?? [])) !!}, datasets: [{ label: 'Bookings', data: {!! json_encode(array_map(fn($r) => (int)($r->count ?? 0), $roomData ?? [])) !!}, backgroundColor: ['#198754','#0dcaf0','#ffc107','#dc3545','#6f42c1','#fd7e14'] }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
});
</script>
@endpush
@endsection
