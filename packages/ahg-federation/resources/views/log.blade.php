@extends('theme::layout')

@section('title', 'Federation Harvest Logs')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('federation.index') }}">Federation</a></li>
                    <li class="breadcrumb-item active">Harvest Logs</li>
                </ol>
            </nav>
            <h4 class="mb-0"><i class="bi bi-journal-text me-2"></i>Harvest Logs</h4>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-auto">
                    <label for="peer_id" class="form-label">Filter by Peer <span class="badge bg-secondary ms-1">Optional</span></label>
                    <select class="form-select" id="peer_id" name="peer_id">
                        <option value="">All peers</option>
                        @foreach($peers as $peer)
                            <option value="{{ $peer->id }}" {{ ($peerId ?? '') == $peer->id ? 'selected' : '' }}>{{ $peer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="atom-btn-white">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if($logs->isEmpty())
                <div class="p-4 text-center text-muted">No harvest logs found.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Peer</th>
                                <th>Started</th>
                                <th>Completed</th>
                                <th>Status</th>
                                <th>Records</th>
                                <th>Errors</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                                <tr>
                                    <td>{{ $log->id }}</td>
                                    <td>{{ $log->peer_name ?? 'Unknown' }}</td>
                                    <td>{{ $log->started_at ?? '' }}</td>
                                    <td>{{ $log->completed_at ?? 'In progress' }}</td>
                                    <td>
                                        @if(($log->status ?? '') === 'completed')
                                            <span class="badge bg-success">Completed</span>
                                        @elseif(($log->status ?? '') === 'failed')
                                            <span class="badge bg-danger">Failed</span>
                                        @else
                                            <span class="badge bg-info">{{ $log->status ?? 'Unknown' }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $log->record_count ?? 0 }}</td>
                                    <td>{{ $log->error_count ?? 0 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
