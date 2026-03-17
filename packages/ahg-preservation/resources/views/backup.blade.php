@extends('theme::layouts.1col')

@section('title', 'Backup & Replication - Preservation')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
    <div class="col-md-3">
        @include('ahg-preservation::_menu')
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h1 class="mb-0"><i class="fas fa-database"></i> Backup & Replication</h1>
        </div>
        <p class="text-muted mb-3">Replication targets, sync logs, and backup verifications</p>

        {{-- Replication Targets --}}
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-server"></i> Replication Targets</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Active</th>
                                <th>Sync Schedule</th>
                                <th>Last Sync</th>
                                <th>Last Status</th>
                                <th>Files</th>
                                <th>Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($targets as $target)
                            <tr>
                                <td>{{ $target->id }}</td>
                                <td class="fw-bold">{{ $target->name }}</td>
                                <td><small class="text-muted">{{ Str::limit($target->description, 40) }}</small></td>
                                <td><span class="badge bg-secondary">{{ $target->target_type }}</span></td>
                                <td>
                                    @if($target->is_active)
                                        <span class="badge bg-success"><i class="fas fa-check-circle"></i> Active</span>
                                    @else
                                        <span class="badge bg-secondary"><i class="fas fa-pause-circle"></i> Inactive</span>
                                    @endif
                                </td>
                                <td><code class="small">{{ $target->sync_schedule ?? '-' }}</code></td>
                                <td class="text-nowrap"><small>{{ $target->last_sync_at ?? 'Never' }}</small></td>
                                <td>
                                    @if($target->last_sync_status === 'success')
                                        <span class="badge bg-success">Success</span>
                                    @elseif($target->last_sync_status === 'failed')
                                        <span class="badge bg-danger">Failed</span>
                                    @elseif($target->last_sync_status)
                                        <span class="badge bg-secondary">{{ ucfirst($target->last_sync_status) }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><small>{{ $target->last_sync_files ? number_format($target->last_sync_files) : '-' }}</small></td>
                                <td><small>{{ $target->last_sync_bytes ? number_format($target->last_sync_bytes / 1048576, 1) . ' MB' : '-' }}</small></td>
                            </tr>
                            @empty
                            <tr><td colspan="10" class="text-center text-muted py-3">No replication targets configured</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Recent Replication Logs --}}
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-sync-alt"></i> Recent Replication Logs</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Target</th>
                                <th>Operation</th>
                                <th>Status</th>
                                <th>Files Total</th>
                                <th>Synced</th>
                                <th>Failed</th>
                                <th>Transferred</th>
                                <th>Started</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($replicationLogs as $log)
                            <tr>
                                <td>{{ $log->id }}</td>
                                <td>{{ $log->target_name ?? 'Target #' . $log->target_id }}</td>
                                <td><span class="badge bg-secondary">{{ $log->operation }}</span></td>
                                <td>
                                    @if($log->status === 'completed' || $log->status === 'success')
                                        <span class="badge bg-success">{{ ucfirst($log->status) }}</span>
                                    @elseif($log->status === 'failed')
                                        <span class="badge bg-danger">Failed</span>
                                    @elseif($log->status === 'running')
                                        <span class="badge bg-info">Running</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($log->status) }}</span>
                                    @endif
                                </td>
                                <td>{{ number_format($log->files_total) }}</td>
                                <td>{{ number_format($log->files_synced) }}</td>
                                <td>
                                    @if($log->files_failed > 0)
                                        <span class="text-danger fw-bold">{{ number_format($log->files_failed) }}</span>
                                    @else
                                        {{ $log->files_failed }}
                                    @endif
                                </td>
                                <td><small>{{ $log->bytes_transferred ? number_format($log->bytes_transferred / 1048576, 1) . ' MB' : '-' }}</small></td>
                                <td class="text-nowrap"><small>{{ $log->started_at }}</small></td>
                                <td><small>{{ $log->duration_ms ? number_format($log->duration_ms / 1000, 1) . 's' : '-' }}</small></td>
                            </tr>
                            @if($log->error_message)
                            <tr>
                                <td colspan="10" class="bg-danger bg-opacity-10 border-0 py-1 ps-4">
                                    <small class="text-danger"><i class="fas fa-exclamation-circle"></i> {{ $log->error_message }}</small>
                                </td>
                            </tr>
                            @endif
                            @empty
                            <tr><td colspan="10" class="text-center text-muted py-3">No replication logs</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Backup Verifications --}}
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-check-square"></i> Recent Backup Verifications</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Backup Type</th>
                                <th>Backup Path</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th>Method</th>
                                <th>Files Checked</th>
                                <th>Valid</th>
                                <th>Invalid</th>
                                <th>Missing</th>
                                <th>Verified At</th>
                                <th>Verified By</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($verifications as $v)
                            <tr>
                                <td>{{ $v->id }}</td>
                                <td><span class="badge bg-secondary">{{ $v->backup_type }}</span></td>
                                <td><code class="small">{{ Str::limit($v->backup_path, 30) }}</code></td>
                                <td><small>{{ $v->backup_size ? number_format($v->backup_size / 1048576, 1) . ' MB' : '-' }}</small></td>
                                <td>
                                    @if($v->status === 'valid' || $v->status === 'verified')
                                        <span class="badge bg-success"><i class="fas fa-check"></i> {{ ucfirst($v->status) }}</span>
                                    @elseif($v->status === 'invalid' || $v->status === 'failed')
                                        <span class="badge bg-danger"><i class="fas fa-times"></i> {{ ucfirst($v->status) }}</span>
                                    @elseif($v->status === 'partial')
                                        <span class="badge bg-warning text-dark"><i class="fas fa-exclamation"></i> Partial</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($v->status) }}</span>
                                    @endif
                                </td>
                                <td><small>{{ $v->verification_method ?? '-' }}</small></td>
                                <td>{{ number_format($v->files_checked) }}</td>
                                <td class="text-success">{{ number_format($v->files_valid) }}</td>
                                <td>
                                    @if($v->files_invalid > 0)
                                        <span class="text-danger fw-bold">{{ number_format($v->files_invalid) }}</span>
                                    @else
                                        {{ $v->files_invalid }}
                                    @endif
                                </td>
                                <td>
                                    @if($v->files_missing > 0)
                                        <span class="text-warning fw-bold">{{ number_format($v->files_missing) }}</span>
                                    @else
                                        {{ $v->files_missing }}
                                    @endif
                                </td>
                                <td class="text-nowrap"><small>{{ $v->verified_at }}</small></td>
                                <td><small>{{ $v->verified_by ?? '-' }}</small></td>
                                <td><small>{{ $v->duration_ms ? number_format($v->duration_ms / 1000, 1) . 's' : '-' }}</small></td>
                            </tr>
                            @if($v->error_message)
                            <tr>
                                <td colspan="13" class="bg-danger bg-opacity-10 border-0 py-1 ps-4">
                                    <small class="text-danger"><i class="fas fa-exclamation-circle"></i> {{ $v->error_message }}</small>
                                </td>
                            </tr>
                            @endif
                            @empty
                            <tr><td colspan="13" class="text-center text-muted py-3">No backup verifications recorded</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
