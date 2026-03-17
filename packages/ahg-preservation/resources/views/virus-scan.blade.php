@extends('theme::layouts.1col')

@section('title', 'Virus Scans - Preservation')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
    <div class="col-md-3">
        @include('ahg-preservation::_menu')
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h1 class="mb-0"><i class="fas fa-shield-virus"></i> Virus Scans</h1>
        </div>
        <p class="text-muted mb-3">Virus scanning results for digital objects</p>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>File</th>
                                <th>Engine</th>
                                <th>Status</th>
                                <th>Threat</th>
                                <th>File Size</th>
                                <th>Scanned At</th>
                                <th>Scanned By</th>
                                <th>Duration</th>
                                <th>Quarantined</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($scans as $scan)
                            <tr>
                                <td>{{ $scan->id }}</td>
                                <td><small>{{ $scan->file_name ?? basename($scan->file_path ?? 'Object #' . $scan->digital_object_id) }}</small></td>
                                <td><small>{{ $scan->scan_engine }} {{ $scan->engine_version ? 'v' . $scan->engine_version : '' }}</small></td>
                                <td>
                                    @if($scan->status === 'clean')
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Clean</span>
                                    @elseif($scan->status === 'infected')
                                        <span class="badge bg-danger"><i class="fas fa-virus"></i> Infected</span>
                                    @elseif($scan->status === 'error')
                                        <span class="badge bg-warning text-dark"><i class="fas fa-exclamation"></i> Error</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($scan->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($scan->threat_name)
                                        <span class="text-danger fw-bold"><i class="fas fa-bug"></i> {{ $scan->threat_name }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><small>{{ $scan->file_size ? number_format($scan->file_size / 1024, 1) . ' KB' : '-' }}</small></td>
                                <td class="text-nowrap"><small>{{ $scan->scanned_at }}</small></td>
                                <td><small>{{ $scan->scanned_by ?? '-' }}</small></td>
                                <td><small>{{ $scan->duration_ms ? $scan->duration_ms . 'ms' : '-' }}</small></td>
                                <td>
                                    @if($scan->quarantined)
                                        <span class="badge bg-danger"><i class="fas fa-lock"></i> Yes</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @if($scan->error_message)
                            <tr>
                                <td colspan="10" class="bg-danger bg-opacity-10 border-0 py-1 ps-4">
                                    <small class="text-danger"><i class="fas fa-exclamation-circle"></i> {{ $scan->error_message }}</small>
                                </td>
                            </tr>
                            @endif
                            @empty
                            <tr><td colspan="10" class="text-center text-muted py-3">No virus scans recorded</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
