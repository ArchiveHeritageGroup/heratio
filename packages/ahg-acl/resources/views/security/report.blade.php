{{-- Security Reports - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/reportSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Security Reports')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-chart-bar me-2"></i>{{ __('Security Reports') }}</h1>
    <div>
        <form method="get" class="d-inline">
            <select name="period" class="form-select form-select-sm d-inline-block" style="width: auto;" data-csp-auto-submit>
                <option value="7 days" {{ ($period ?? '') === '7 days' ? 'selected' : '' }}>{{ __('Last 7 Days') }}</option>
                <option value="30 days" {{ ($period ?? '30 days') === '30 days' ? 'selected' : '' }}>{{ __('Last 30 Days') }}</option>
                <option value="90 days" {{ ($period ?? '') === '90 days' ? 'selected' : '' }}>{{ __('Last 90 Days') }}</option>
            </select>
        </form>
        <a href="{{ route('acl.security-dashboard') }}" class="btn btn-sm btn-primary ms-2">
            <i class="fas fa-tachometer-alt me-1"></i>{{ __('Dashboard') }}
        </a>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center">
                <h2>{{ $clearanceStats['total_users'] ?? 0 }}</h2>
                <p class="mb-0">Active Users</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center">
                <h2>{{ $clearanceStats['with_clearance'] ?? 0 }}</h2>
                <p class="mb-0">With Clearance</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-secondary text-white h-100">
            <div class="card-body text-center">
                <h2>{{ $clearanceStats['without_clearance'] ?? 0 }}</h2>
                <p class="mb-0">Without Clearance</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Clearances by Level --}}
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>{{ __('User Clearances by Level') }}</h5>
            </div>
            <div class="card-body">
                <canvas id="clearancesChart" height="200"></canvas>
                <table class="table table-sm mt-3">
                    <thead>
                        <tr><th>{{ __('Level') }}</th><th class="text-end">{{ __('Users') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach($clearancesByLevel ?? [] as $level)
                        <tr>
                            <td>
                                <span class="badge" style="background-color: {{ $level->color ?? '#666' }}">{{ e($level->name ?? '') }}</span>
                            </td>
                            <td class="text-end">{{ $level->count ?? 0 }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Objects by Level --}}
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i>{{ __('Classified Objects by Level') }}</h5>
            </div>
            <div class="card-body">
                <canvas id="objectsChart" height="200"></canvas>
                <table class="table table-sm mt-3">
                    <thead>
                        <tr><th>{{ __('Level') }}</th><th class="text-end">{{ __('Objects') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach($objectsByLevel ?? [] as $level)
                        <tr>
                            <td>
                                <span class="badge" style="background-color: {{ $level->color ?? '#666' }}">{{ e($level->name ?? '') }}</span>
                            </td>
                            <td class="text-end">{{ $level->count ?? 0 }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Access Requests --}}
<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="fas fa-key me-2"></i>{{ __('Access Requests') }}</h5>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-4">
                <h3 class="text-warning">{{ $requestStats['pending'] ?? 0 }}</h3>
                <p>Pending</p>
            </div>
            <div class="col-md-4">
                <h3 class="text-success">{{ $requestStats['approved'] ?? 0 }}</h3>
                <p>Approved</p>
            </div>
            <div class="col-md-4">
                <h3 class="text-danger">{{ $requestStats['denied'] ?? 0 }}</h3>
                <p>Denied</p>
            </div>
        </div>
    </div>
</div>

{{-- Recent Security Activity --}}
<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="fas fa-history me-2"></i>{{ __('Recent Security Activity') }}</h5>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover table-striped mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('User') }}</th>
                    <th>{{ __('Action') }}</th>
                    <th>{{ __('Object') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentActivity ?? [] as $activity)
                <tr>
                    <td><small>{{ date('M j, H:i', strtotime($activity->action_date ?? $activity->created_at ?? '')) }}</small></td>
                    <td>{{ e($activity->user_name ?? $activity->username ?? '') }}</td>
                    <td><span class="badge bg-secondary">{{ e($activity->action ?? '') }}</span></td>
                    <td>{{ e($activity->object_title ?? '-') }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-3">No recent activity</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@php
    $clLabels = collect($clearancesByLevel ?? [])->pluck('name')->toArray();
    $clData = collect($clearancesByLevel ?? [])->pluck('count')->toArray();
    $clColors = collect($clearancesByLevel ?? [])->pluck('color')->toArray();
    $objLabels = collect($objectsByLevel ?? [])->pluck('name')->toArray();
    $objData = collect($objectsByLevel ?? [])->pluck('count')->toArray();
    $objColors = collect($objectsByLevel ?? [])->pluck('color')->toArray();
@endphp

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var clearancesEl = document.getElementById('clearancesChart');
    if (clearancesEl) {
        new Chart(clearancesEl, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($clLabels) !!},
                datasets: [{ data: {!! json_encode($clData) !!}, backgroundColor: {!! json_encode($clColors) !!} }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }

    var objectsEl = document.getElementById('objectsChart');
    if (objectsEl) {
        new Chart(objectsEl, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($objLabels) !!},
                datasets: [{ data: {!! json_encode($objData) !!}, backgroundColor: {!! json_encode($objColors) !!} }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }
});
</script>

@endsection
