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
        </div>
        <p class="text-muted mb-3">Scheduled preservation workflows and their run history</p>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
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
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedules as $schedule)
                            <tr>
                                <td>{{ $schedule->id }}</td>
                                <td class="fw-bold">{{ $schedule->name }}</td>
                                <td><span class="badge bg-secondary">{{ $schedule->workflow_type }}</span></td>
                                <td>
                                    <code class="small">
                                        @if($schedule->schedule_type === 'cron')
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
                            </tr>
                            @if($schedule->description)
                            <tr>
                                <td colspan="12" class="bg-light border-0 py-1 ps-4">
                                    <small class="text-muted"><i class="fas fa-info-circle"></i> {{ $schedule->description }}</small>
                                </td>
                            </tr>
                            @endif
                            @empty
                            <tr><td colspan="12" class="text-center text-muted py-3">No workflow schedules configured</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
