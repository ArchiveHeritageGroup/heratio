{{-- Security Compliance Dashboard - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/securityComplianceSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Security Compliance Dashboard')

@section('content')

<div class="row">
    <div class="col-md-12">
        <h1 class="h3 mb-4">
            <i class="fas fa-shield-alt me-2"></i>
            Security Compliance Dashboard
        </h1>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary h-100">
            <div class="card-body">
                <h4 class="mb-0">{{ $stats['classified_objects'] ?? 0 }}</h4>
                <small>Classified Objects</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning h-100">
            <div class="card-body">
                <h4 class="mb-0">{{ $stats['pending_reviews'] ?? 0 }}</h4>
                <small>Pending Reviews</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success h-100">
            <div class="card-body">
                <h4 class="mb-0">{{ $stats['cleared_users'] ?? 0 }}</h4>
                <small>Cleared Users</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info h-100">
            <div class="card-body">
                <h4 class="mb-0">{{ $stats['access_logs_today'] ?? 0 }}</h4>
                <small>Access Logs Today</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Recent Audit Logs</h5>
            </div>
            <div class="card-body">
                @if(!empty($recentLogs))
                    <table class="table table-sm">
                        <thead><tr><th>Action</th><th>User</th><th>Time</th></tr></thead>
                        <tbody>
                        @foreach($recentLogs as $log)
                            <tr>
                                <td>{{ e($log->action ?? '') }}</td>
                                <td>{{ e($log->username ?? '') }}</td>
                                <td><small>{{ e($log->created_at ?? '') }}</small></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted text-center">No recent logs</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Retention Schedules (NARSSA)</h5>
            </div>
            <div class="card-body">
                @if(!empty($retentionSchedules))
                    <table class="table table-sm">
                        <thead><tr><th>Ref</th><th>Type</th><th>Period</th></tr></thead>
                        <tbody>
                        @foreach($retentionSchedules as $s)
                            <tr>
                                <td><code>{{ e($s->narssa_ref ?? '') }}</code></td>
                                <td>{{ e($s->record_type ?? '') }}</td>
                                <td>{{ e($s->retention_period ?? '') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted text-center">No retention schedules</p>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
