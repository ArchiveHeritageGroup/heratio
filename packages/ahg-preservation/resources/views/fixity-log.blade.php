@extends('theme::layouts.1col')

@section('title', 'Fixity Log - Preservation')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
    <div class="col-md-3">
        @include('ahg-preservation::_menu')
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h1 class="mb-0"><i class="fas fa-check-double"></i> Fixity Log</h1>
        </div>
        <p class="text-muted mb-3">History of fixity verification checks</p>

        {{-- Status Filter --}}
        <div class="mb-3">
            <div class="btn-group" role="group">
                <a href="{{ route('preservation.fixity-log') }}" class="btn btn-sm {{ !$status ? 'atom-btn-outline-success' : 'atom-btn-white' }}">All</a>
                <a href="{{ route('preservation.fixity-log', ['status' => 'pass']) }}" class="btn btn-sm {{ $status === 'pass' ? 'atom-btn-outline-success' : 'atom-btn-white' }}">Pass</a>
                <a href="{{ route('preservation.fixity-log', ['status' => 'fail']) }}" class="btn btn-sm {{ $status === 'fail' ? 'atom-btn-outline-danger' : 'atom-btn-white' }}">Fail</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>File</th>
                                <th>Algorithm</th>
                                <th>Status</th>
                                <th>Expected</th>
                                <th>Actual</th>
                                <th>Checked At</th>
                                <th>Checked By</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr>
                                <td>{{ $log->id }}</td>
                                <td><small>{{ $log->file_name ?? 'Object #' . $log->digital_object_id }}</small></td>
                                <td><code>{{ $log->algorithm }}</code></td>
                                <td>
                                    @if($log->status === 'pass')
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Pass</span>
                                    @else
                                        <span class="badge bg-danger"><i class="fas fa-times"></i> Fail</span>
                                    @endif
                                </td>
                                <td><code class="small">{{ Str::limit($log->expected_value, 16) }}</code></td>
                                <td><code class="small">{{ $log->actual_value ? Str::limit($log->actual_value, 16) : '-' }}</code></td>
                                <td class="text-nowrap"><small>{{ $log->checked_at }}</small></td>
                                <td><small>{{ $log->checked_by ?? '-' }}</small></td>
                                <td><small>{{ $log->duration_ms ? $log->duration_ms . 'ms' : '-' }}</small></td>
                            </tr>
                            @if($log->error_message)
                            <tr>
                                <td colspan="9" class="bg-danger bg-opacity-10 border-0 py-1 ps-4">
                                    <small class="text-danger"><i class="fas fa-exclamation-circle"></i> {{ $log->error_message }}</small>
                                </td>
                            </tr>
                            @endif
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-3">No fixity checks recorded</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
