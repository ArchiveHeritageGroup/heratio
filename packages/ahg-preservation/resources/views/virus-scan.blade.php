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

        {{-- ClamAV Status --}}
        @php
            $clamAvAvailable = $clamAvAvailable ?? false;
            $clamAvVersion = $clamAvVersion ?? null;
        @endphp
        <div class="alert {{ $clamAvAvailable ? 'alert-success' : 'alert-warning' }} mb-4">
            <div class="d-flex align-items-center">
                <i class="fas {{ $clamAvAvailable ? 'fa-check-circle' : 'fa-exclamation-triangle' }} fa-2x me-3"></i>
                <div class="flex-grow-1">
                    @if($clamAvAvailable)
                        <strong>ClamAV is installed and available</strong>
                        @if($clamAvVersion)
                        <br><small class="text-muted">
                            Scanner: {{ $clamAvVersion['scanner'] ?? '' }} |
                            Version: {{ $clamAvVersion['version'] ?? '' }} |
                            Database: {{ $clamAvVersion['database'] ?? '' }}
                        </small>
                        @endif
                    @else
                        <strong>ClamAV is not installed</strong>
                        <br><small>Install with: <code>sudo apt install clamav clamav-daemon && sudo freshclam</code></small>
                    @endif
                </div>
            </div>
        </div>

        {{-- Statistics Cards --}}
        @php
            $scanStats = $scanStats ?? ['clean' => 0, 'infected' => 0, 'error' => 0];
            $unscannedObjects = $unscannedObjects ?? 0;
        @endphp
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-white-50">Clean</h6>
                                <h2 class="mb-0">{{ number_format($scanStats['clean'] ?? 0) }}</h2>
                            </div>
                            <i class="fas fa-check-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-white-50">Infected</h6>
                                <h2 class="mb-0">{{ number_format($scanStats['infected'] ?? 0) }}</h2>
                            </div>
                            <i class="fas fa-virus fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Errors</h6>
                                <h2 class="mb-0">{{ number_format($scanStats['error'] ?? 0) }}</h2>
                            </div>
                            <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-white-50">Not Scanned</h6>
                                <h2 class="mb-0">{{ number_format($unscannedObjects) }}</h2>
                            </div>
                            <i class="fas fa-question-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- CLI Commands --}}
        <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <i class="fas fa-terminal me-2"></i>CLI Commands
            </div>
            <div class="card-body">
                <p class="mb-2">Run virus scans from the command line:</p>
                <pre class="bg-dark text-light p-3 rounded mb-0"><code># Show ClamAV status
php artisan preservation:virus-scan --status

# Scan up to 100 new objects
php artisan preservation:virus-scan

# Scan specific object
php artisan preservation:virus-scan --object-id=123

# Scan 500 objects
php artisan preservation:virus-scan --limit=500</code></pre>
            </div>
        </div>

        {{-- Recent Scans --}}
        <div class="card">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <i class="fas fa-list me-2"></i>Recent Virus Scans
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped mb-0">
                        <thead>
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
                                <td><small>{{ $scan->scan_engine ?? '' }} {{ ($scan->engine_version ?? null) ? 'v' . $scan->engine_version : '' }}</small></td>
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
                                    @if($scan->threat_name ?? null)
                                        <span class="text-danger fw-bold"><i class="fas fa-bug"></i> {{ $scan->threat_name }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><small>{{ ($scan->file_size ?? null) ? number_format($scan->file_size / 1024, 1) . ' KB' : '-' }}</small></td>
                                <td class="text-nowrap"><small>{{ $scan->scanned_at ?? '' }}</small></td>
                                <td><small>{{ $scan->scanned_by ?? '-' }}</small></td>
                                <td><small>{{ ($scan->duration_ms ?? null) ? $scan->duration_ms . 'ms' : '-' }}</small></td>
                                <td>
                                    @if($scan->quarantined ?? false)
                                        <span class="badge bg-danger"><i class="fas fa-lock"></i> Yes</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @if($scan->error_message ?? null)
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
