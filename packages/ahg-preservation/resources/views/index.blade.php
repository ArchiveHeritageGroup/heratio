@extends('theme::layouts.1col')

@section('title', 'Digital Preservation')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
    <div class="col-md-3">
        @include('ahg-preservation::_menu')
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h1 class="mb-0"><i class="fas fa-shield-alt"></i> Digital Preservation</h1>
        </div>
        <p class="text-muted mb-4">Preservation dashboard: checksums, fixity, formats, and virus scanning</p>

        {{-- Stats Cards --}}
        <div class="row mb-4">
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card border-primary h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-file fa-2x text-primary mb-2"></i>
                        <h3 class="mb-0">{{ number_format($stats->total_objects) }}</h3>
                        <small class="text-muted">Total Digital Objects</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card border-success h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-fingerprint fa-2x text-success mb-2"></i>
                        <h3 class="mb-0">{{ number_format($stats->objects_with_checksums) }}</h3>
                        <small class="text-muted">Objects with Checksums</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card border-info h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-check-double fa-2x text-info mb-2"></i>
                        <h3 class="mb-0">{{ number_format($stats->fixity_checks_run) }}</h3>
                        <small class="text-muted">Fixity Checks Run</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card border-danger h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                        <h3 class="mb-0">{{ number_format($stats->fixity_failures) }}</h3>
                        <small class="text-muted">Fixity Failures</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card border-warning h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-file-code fa-2x text-warning mb-2"></i>
                        <h3 class="mb-0">{{ number_format($stats->at_risk_formats) }}</h3>
                        <small class="text-muted">At-Risk Formats</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card border-secondary h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-shield-virus fa-2x text-secondary mb-2"></i>
                        <h3 class="mb-0">{{ number_format($stats->virus_scans) }}</h3>
                        <small class="text-muted">Virus Scans ({{ number_format($stats->clean_scans) }} clean)</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent PREMIS Events --}}
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-history"></i> Recent PREMIS Events
                <a href="{{ route('preservation.events') }}" class="btn btn-sm btn-outline-primary float-end">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date/Time</th>
                                <th>Type</th>
                                <th>File</th>
                                <th>Outcome</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentEvents as $event)
                            <tr>
                                <td class="text-nowrap"><small>{{ $event->event_datetime }}</small></td>
                                <td><span class="badge bg-secondary">{{ $event->event_type }}</span></td>
                                <td><small>{{ $event->file_name ?? 'N/A' }}</small></td>
                                <td>
                                    @if($event->event_outcome === 'success')
                                        <span class="badge bg-success">Success</span>
                                    @elseif($event->event_outcome === 'failure')
                                        <span class="badge bg-danger">Failure</span>
                                    @else
                                        <span class="badge bg-warning text-dark">{{ ucfirst($event->event_outcome ?? 'unknown') }}</span>
                                    @endif
                                </td>
                                <td><small class="text-muted">{{ Str::limit($event->event_detail, 60) }}</small></td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No events recorded yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- At-Risk Formats --}}
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-exclamation-triangle text-warning"></i> At-Risk Formats
                <a href="{{ route('preservation.formats') }}" class="btn btn-sm btn-outline-primary float-end">View All Formats</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Format</th>
                                <th>PUID</th>
                                <th>MIME Type</th>
                                <th>Risk Level</th>
                                <th>Action</th>
                                <th>Objects</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($atRiskFormats as $format)
                            <tr>
                                <td>{{ $format->format_name }}</td>
                                <td><code>{{ $format->puid ?? '-' }}</code></td>
                                <td><small>{{ $format->mime_type ?? '-' }}</small></td>
                                <td>
                                    @if($format->risk_level === 'critical')
                                        <span class="badge bg-danger">Critical</span>
                                    @else
                                        <span class="badge bg-warning text-dark">High</span>
                                    @endif
                                </td>
                                <td><small>{{ $format->preservation_action ?? '-' }}</small></td>
                                <td><span class="badge bg-primary">{{ $format->object_count }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No at-risk formats identified</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
