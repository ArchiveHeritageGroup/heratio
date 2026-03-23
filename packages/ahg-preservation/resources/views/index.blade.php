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
            <h1 class="mb-0"><i class="fas fa-shield-alt"></i> Digital Preservation Dashboard</h1>
        </div>
        <p class="text-muted mb-4">Preservation dashboard: checksums, fixity, formats, and virus scanning</p>

        {{-- Statistics Cards --}}
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-white-50">Digital Objects</h6>
                                <h2 class="mb-0">{{ number_format($stats->total_objects) }}</h2>
                                @if(isset($stats->total_size_formatted))
                                    <small>{{ $stats->total_size_formatted }}</small>
                                @endif
                            </div>
                            <i class="fas fa-file fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-white-50">Checksum Coverage</h6>
                                @php
                                    $coverage = $stats->total_objects > 0
                                        ? round(($stats->objects_with_checksums / $stats->total_objects) * 100, 1)
                                        : 0;
                                @endphp
                                <h2 class="mb-0">{{ $coverage }}%</h2>
                                <small>{{ number_format($stats->objects_with_checksums) }} objects</small>
                            </div>
                            <i class="fas fa-fingerprint fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card {{ $stats->fixity_failures > 0 ? 'bg-danger' : 'bg-info' }} text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-white-50">Fixity Checks</h6>
                                <h2 class="mb-0">{{ number_format($stats->fixity_checks_run) }}</h2>
                                <small>{{ number_format($stats->fixity_failures) }} failures</small>
                            </div>
                            <i class="fas fa-check-double fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card {{ $stats->at_risk_formats > 0 ? 'bg-warning' : 'bg-secondary' }} text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-white-50">At-Risk Formats</h6>
                                <h2 class="mb-0">{{ number_format($stats->at_risk_formats) }}</h2>
                                <small>{{ number_format($stats->virus_scans) }} virus scans ({{ number_format($stats->clean_scans) }} clean)</small>
                            </div>
                            <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div class="d-flex flex-wrap gap-1">
                <a href="{{ route('preservation.identification') }}" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-fingerprint me-1"></i>Format ID
                </a>
                <a href="{{ route('preservation.fixity-log') }}" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-check-double me-1"></i>Fixity Log
                </a>
                <a href="{{ route('preservation.events') }}" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-history me-1"></i>Events
                </a>
                <a href="{{ route('preservation.formats') }}" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-file-code me-1"></i>Format Registry
                </a>
                <a href="{{ route('preservation.scheduler') }}" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-clock me-1"></i>Scheduler
                </a>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <a href="{{ route('preservation.packages') }}" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-box me-1"></i>OAIS Packages
                </a>
                <a href="{{ route('preservation.virus-scan') }}" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-shield-virus me-1"></i>Virus Scan
                </a>
                <a href="{{ route('preservation.conversion') }}" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-sync-alt me-1"></i>Conversion
                </a>
                <a href="{{ route('preservation.backup') }}" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-database me-1"></i>Backup
                </a>
            </div>
        </div>

        <div class="row">
            {{-- Recent Fixity Checks --}}
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
                        <span><i class="fas fa-fingerprint me-2"></i>Recent Fixity Checks</span>
                        <a href="{{ route('preservation.fixity-log') }}" class="btn btn-sm atom-btn-white">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>File</th>
                                        <th>Status</th>
                                        <th>Checked</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        // Use fixity logs if provided, otherwise empty
                                        $recentFixity = $recentFixity ?? collect();
                                    @endphp
                                    @forelse($recentFixity as $check)
                                    <tr>
                                        <td>
                                            <a href="{{ route('preservation.object', $check->digital_object_id) }}">
                                                {{ Str::limit($check->file_name ?? 'Unknown', 30) }}
                                            </a>
                                        </td>
                                        <td>
                                            @if($check->status === 'pass')
                                                <span class="badge bg-success">Pass</span>
                                            @elseif($check->status === 'fail')
                                                <span class="badge bg-danger">Fail</span>
                                            @else
                                                <span class="badge bg-warning text-dark">{{ ucfirst($check->status) }}</span>
                                            @endif
                                        </td>
                                        <td><small class="text-muted">{{ $check->checked_at }}</small></td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="3" class="text-center text-muted py-3">No fixity checks yet</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent PREMIS Events --}}
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
                        <span><i class="fas fa-history me-2"></i>Recent PREMIS Events</span>
                        <a href="{{ route('preservation.events') }}" class="btn btn-sm atom-btn-white">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-striped mb-0">
                                <thead>
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
            </div>
        </div>

        {{-- At-Risk Formats --}}
        <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <i class="fas fa-exclamation-triangle text-warning me-2"></i>At-Risk Formats
                <a href="{{ route('preservation.formats') }}" class="btn btn-sm atom-btn-white float-end">View All Formats</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped mb-0">
                        <thead>
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
