@extends('theme::layouts.1col')

@section('title', 'Format Registry - Preservation')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
    <div class="col-md-3">
        @include('ahg-preservation::_menu')
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h1 class="mb-0"><i class="fas fa-file-code"></i> Format Registry</h1>
        </div>
        <p class="text-muted mb-3">Known file formats, risk assessment, and preservation actions</p>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>PUID</th>
                                <th>Format Name</th>
                                <th>Version</th>
                                <th>MIME Type</th>
                                <th>Extension</th>
                                <th>Risk Level</th>
                                <th>Action</th>
                                <th>Preservation</th>
                                <th>Objects</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($formats as $format)
                            <tr>
                                <td>{{ $format->id }}</td>
                                <td><code>{{ $format->puid ?? '-' }}</code></td>
                                <td>{{ $format->format_name }}</td>
                                <td><small>{{ $format->format_version ?? '-' }}</small></td>
                                <td><small>{{ $format->mime_type ?? '-' }}</small></td>
                                <td><code>{{ $format->extension ?? '-' }}</code></td>
                                <td>
                                    @switch($format->risk_level)
                                        @case('critical')
                                            <span class="badge bg-danger">Critical</span>
                                            @break
                                        @case('high')
                                            <span class="badge bg-warning text-dark">High</span>
                                            @break
                                        @case('medium')
                                            <span class="badge bg-info">Medium</span>
                                            @break
                                        @case('low')
                                            <span class="badge bg-success">Low</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary">{{ ucfirst($format->risk_level ?? 'unknown') }}</span>
                                    @endswitch
                                </td>
                                <td><small>{{ $format->preservation_action ?? '-' }}</small></td>
                                <td>
                                    @if($format->is_preservation_format)
                                        <span class="badge bg-success"><i class="fas fa-check"></i></span>
                                    @else
                                        <span class="badge bg-secondary"><i class="fas fa-times"></i></span>
                                    @endif
                                </td>
                                <td><span class="badge bg-primary">{{ $format->object_count }}</span></td>
                            </tr>
                            @if($format->risk_notes)
                            <tr>
                                <td colspan="10" class="bg-light border-0 py-1 ps-4">
                                    <small class="text-muted"><i class="fas fa-info-circle"></i> {{ $format->risk_notes }}</small>
                                </td>
                            </tr>
                            @endif
                            @empty
                            <tr><td colspan="10" class="text-center text-muted py-3">No formats registered</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
