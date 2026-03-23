@extends('theme::layouts.1col')

@section('title', 'Scheduler - Preservation')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
    <div class="col-md-3">
        @include('ahg-preservation::_menu')
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h1 class="mb-0"><i class="fas fa-clock"></i> Workflow Scheduler</h1>
            <a href="{{ route('preservation.schedule-edit', 0) }}" class="btn btn-sm atom-btn-white">
                <i class="fas fa-plus me-1"></i>New Schedule
            </a>
        </div>
        <p class="text-muted mb-3">Scheduled preservation workflows and their run history</p>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Statistics Cards --}}
        @php
            $enabledCount = 0;
            $totalRuns24h = 0;
            $failedRuns24h = 0;
            foreach ($schedules as $s) {
                if ($s->is_enabled ?? false) $enabledCount++;
            }
        @endphp
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3>{{ count($schedules) }}</h3>
                        <small>Total Schedules</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3>{{ $enabledCount }}</h3>
                        <small>Enabled</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        @php $totalAllRuns = $schedules->sum('total_runs'); @endphp
                        <h3>{{ number_format($totalAllRuns) }}</h3>
                        <small>Total Runs</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body text-center">
                        @php $totalAllProcessed = $schedules->sum('total_processed'); @endphp
                        <h3>{{ number_format($totalAllProcessed) }}</h3>
                        <small>Total Processed</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- Schedules List --}}
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <h6 class="mb-0"><i class="fas fa-list me-1"></i> Configured Schedules</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Workflow Type</th>
                                        <th>Schedule</th>
                                        <th>Batch</th>
                                        <th>Timeout</th>
                                        <th>Enabled</th>
                                        <th>Last Run</th>
                                        <th>Last Status</th>
                                        <th>Next Run</th>
                                        <th>Total Runs</th>
                                        <th>Total Processed</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($schedules as $schedule)
                                    <tr id="schedule-row-{{ $schedule->id }}">
                                        <td>{{ $schedule->id }}</td>
                                        <td class="fw-bold">{{ $schedule->name }}</td>
                                        <td><span class="badge bg-secondary">{{ $schedule->workflow_type }}</span></td>
                                        <td>
                                            <code class="small">
                                                @if(($schedule->schedule_type ?? 'cron') === 'cron')
                                                    {{ $schedule->cron_expression ?? '-' }}
                                                @else
                                                    Every {{ $schedule->interval_hours }}h
                                                @endif
                                            </code>
                                        </td>
                                        <td><small>{{ $schedule->batch_limit ?? '-' }}</small></td>
                                        <td><small>{{ $schedule->timeout_minutes ? $schedule->timeout_minutes . 'min' : '-' }}</small></td>
                                        <td>
                                            @if($schedule->is_enabled)
                                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Enabled</span>
                                            @else
                                                <span class="badge bg-secondary"><i class="fas fa-pause-circle"></i> Disabled</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap"><small>{{ $schedule->last_run_at ?? 'Never' }}</small></td>
                                        <td>
                                            @if($schedule->last_run_status === 'completed')
                                                <span class="badge bg-success">Completed</span>
                                            @elseif($schedule->last_run_status === 'failed')
                                                <span class="badge bg-danger">Failed</span>
                                            @elseif($schedule->last_run_status === 'running')
                                                <span class="badge bg-info">Running</span>
                                            @elseif($schedule->last_run_status)
                                                <span class="badge bg-secondary">{{ ucfirst($schedule->last_run_status) }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap"><small>{{ $schedule->next_run_at ?? '-' }}</small></td>
                                        <td><span class="badge bg-primary">{{ number_format($schedule->total_runs) }}</span></td>
                                        <td><small>{{ number_format($schedule->total_processed) }}</small></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('preservation.schedule-edit', $schedule->id) }}"
                                                   class="btn btn-sm atom-btn-white" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    @if($schedule->description)
                                    <tr>
                                        <td colspan="13" class="bg-light border-0 py-1 ps-4">
                                            <small class="text-muted"><i class="fas fa-info-circle"></i> {{ $schedule->description }}</small>
                                        </td>
                                    </tr>
                                    @endif
                                    @empty
                                    <tr><td colspan="13" class="text-center text-muted py-3">No workflow schedules configured</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Recent Runs --}}
                <div class="card">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <h6 class="mb-0"><i class="fas fa-history me-1"></i> Recent Runs</h6>
                    </div>
                    <div class="card-body p-0">
                        @php $recentRuns = $recentRuns ?? collect(); @endphp
                        @if($recentRuns->isEmpty())
                            <div class="p-4 text-center text-muted">No workflow runs yet.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Schedule</th>
                                            <th>Started</th>
                                            <th>Duration</th>
                                            <th>Processed</th>
                                            <th>Status</th>
                                            <th>Triggered By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentRuns as $run)
                                        <tr>
                                            <td>{{ $run->schedule_name ?? '' }}</td>
                                            <td><small>{{ $run->started_at }}</small></td>
                                            <td>
                                                @if($run->duration_ms ?? null)
                                                    <small>{{ number_format($run->duration_ms / 1000, 1) }}s</small>
                                                @elseif(($run->status ?? '') === 'running')
                                                    <small class="text-warning"><i class="fas fa-spinner fa-spin"></i> Running</small>
                                                @else
                                                    <small>-</small>
                                                @endif
                                            </td>
                                            <td>
                                                <small>
                                                    <span class="text-success">{{ $run->objects_succeeded ?? 0 }}</span> /
                                                    <span class="text-danger">{{ $run->objects_failed ?? 0 }}</span> /
                                                    {{ $run->objects_processed ?? 0 }}
                                                </small>
                                            </td>
                                            <td>
                                                @php
                                                    $runBadge = [
                                                        'completed' => 'bg-success', 'running' => 'bg-info',
                                                        'partial' => 'bg-warning', 'failed' => 'bg-danger',
                                                        'timeout' => 'bg-danger', 'cancelled' => 'bg-secondary',
                                                    ];
                                                @endphp
                                                <span class="badge {{ $runBadge[$run->status ?? ''] ?? 'bg-secondary' }}">{{ $run->status ?? '' }}</span>
                                            </td>
                                            <td><small>{{ $run->triggered_by ?? '' }}{{ ($run->triggered_by_user ?? null) ? " ({$run->triggered_by_user})" : '' }}</small></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
                {{-- Upcoming Schedules --}}
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-calendar-alt me-1"></i> Upcoming Runs</h6>
                    </div>
                    <div class="card-body">
                        @php
                            $upcoming = $schedules->filter(fn($s) => $s->is_enabled && $s->next_run_at)->sortBy('next_run_at')->take(5);
                        @endphp
                        @if($upcoming->isEmpty())
                            <p class="text-muted mb-0">No upcoming scheduled runs.</p>
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach($upcoming as $up)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <strong>{{ $up->name }}</strong>
                                        <br><small class="text-muted">{{ $up->next_run_at }}</small>
                                    </div>
                                </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                {{-- CLI Command Reference --}}
                <div class="card border-secondary">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fas fa-terminal me-1"></i> CLI Command</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">Run the scheduler via cron to execute due workflows:</p>
                        <pre class="bg-dark text-light p-3 rounded small"><code># Run every minute (recommended)
* * * * * cd /usr/share/nginx/heratio && \
  php artisan schedule:run >> \
  /var/log/heratio/scheduler.log 2>&1</code></pre>
                        <p class="small text-muted mb-0">Or run individual workflows:</p>
                        <pre class="bg-dark text-light p-3 rounded small mb-0"><code>php artisan preservation:identify --limit=500
php artisan preservation:fixity --limit=500
php artisan preservation:virus-scan --limit=200</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
