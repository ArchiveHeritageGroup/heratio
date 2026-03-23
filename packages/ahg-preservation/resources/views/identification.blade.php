@extends('theme::layouts.1col')
@section('title', 'Format Identification')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-preservation::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-fingerprint me-2"></i>Format Identification</h1>
    <p class="text-muted">Identify file formats using PRONOM registry and DROID/Siegfried.</p>

    {{-- Siegfried Status --}}
    @php
        $siegfriedAvailable = $siegfriedAvailable ?? false;
        $siegfriedVersion = $siegfriedVersion ?? [];
    @endphp
    <div class="card mb-4 {{ $siegfriedAvailable ? 'border-success' : 'border-danger' }}">
        <div class="card-header {{ $siegfriedAvailable ? 'bg-success' : 'bg-danger' }} text-white">
            <h5 class="mb-0">
                <i class="fas {{ $siegfriedAvailable ? 'fa-check-circle' : 'fa-times-circle' }}"></i>
                Siegfried - PRONOM Format Identification
            </h5>
        </div>
        <div class="card-body">
            @if($siegfriedAvailable)
                <div class="row">
                    <div class="col-md-4">
                        <strong>Status:</strong> <span class="badge bg-success">Available</span>
                    </div>
                    <div class="col-md-4">
                        <strong>Version:</strong> {{ $siegfriedVersion['version'] ?? 'Unknown' }}
                    </div>
                    <div class="col-md-4">
                        <strong>Signature Date:</strong> {{ $siegfriedVersion['signature_date'] ?? 'Unknown' }}
                    </div>
                </div>
            @else
                <div class="alert alert-danger mb-0">
                    <strong>Siegfried is not installed.</strong>
                    <p class="mb-0 mt-2">Install with:</p>
                    <code>curl -sL "https://github.com/richardlehane/siegfried/releases/download/v1.11.1/siegfried_1.11.1-1_amd64.deb" -o /tmp/sf.deb && sudo dpkg -i /tmp/sf.deb</code>
                </div>
            @endif
        </div>
    </div>

    {{-- Stats --}}
    <div class="row mb-4">
      <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body text-center"><h3>{{ number_format($stats['total'] ?? 0) }}</h3><small>Total Objects</small></div></div></div>
      <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h3>{{ number_format($stats['identified'] ?? 0) }}</h3><small>Identified</small></div></div></div>
      <div class="col-md-3"><div class="card bg-warning text-dark"><div class="card-body text-center"><h3>{{ number_format($stats['unidentified'] ?? 0) }}</h3><small>Unidentified</small></div></div></div>
      <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center">
          @php $coveragePct = ($stats['total'] ?? 0) > 0 ? round(($stats['identified'] ?? 0) / $stats['total'] * 100, 1) : 0; @endphp
          <h3>{{ $coveragePct }}%</h3><small>Coverage</small>
      </div></div></div>
    </div>

    <div class="row">
        {{-- Confidence Distribution --}}
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-1"></i> By Confidence</h6>
                </div>
                <div class="card-body">
                    @php $byConfidence = $byConfidence ?? []; @endphp
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-check-double text-success"></i> Certain</span>
                            <span class="badge bg-success">{{ $byConfidence['certain'] ?? 0 }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-check text-primary"></i> High</span>
                            <span class="badge bg-primary">{{ $byConfidence['high'] ?? 0 }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-minus text-warning"></i> Medium</span>
                            <span class="badge bg-warning text-dark">{{ $byConfidence['medium'] ?? 0 }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-question text-danger"></i> Low</span>
                            <span class="badge bg-danger">{{ $byConfidence['low'] ?? 0 }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Format Registry Risk --}}
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <h6 class="mb-0"><i class="fas fa-shield-alt me-1"></i> Registry by Risk</h6>
                </div>
                <div class="card-body">
                    @php $formatsByRisk = $formatsByRisk ?? []; @endphp
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-circle text-success"></i> Low Risk</span>
                            <span class="badge bg-success">{{ $formatsByRisk['low'] ?? 0 }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-circle text-warning"></i> Medium Risk</span>
                            <span class="badge bg-warning text-dark">{{ $formatsByRisk['medium'] ?? 0 }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-circle text-danger"></i> High Risk</span>
                            <span class="badge bg-danger">{{ $formatsByRisk['high'] ?? 0 }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-exclamation-triangle text-dark"></i> Critical Risk</span>
                            <span class="badge bg-dark">{{ $formatsByRisk['critical'] ?? 0 }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- With Warnings --}}
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-1"></i> With Warnings</h6>
                </div>
                <div class="card-body">
                    @php $withWarnings = $withWarnings ?? 0; @endphp
                    <div class="text-center">
                        <h2 class="{{ $withWarnings > 0 ? 'text-warning' : 'text-success' }}">
                            {{ $withWarnings }}
                        </h2>
                        <small class="text-muted">Objects with identification warnings</small>
                    </div>
                    @php $warningsList = $identificationsWithWarnings ?? []; @endphp
                    @if(!empty($warningsList))
                        <hr>
                        <small class="text-muted">Recent warnings:</small>
                        <ul class="list-unstyled small mt-2">
                            @foreach(array_slice((array)$warningsList, 0, 3) as $item)
                                <li class="text-truncate" title="{{ $item->warning ?? '' }}">
                                    <i class="fas fa-exclamation-circle text-warning"></i>
                                    {{ $item->object_name ?? '' }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Top 10 Identified Formats --}}
    <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h6 class="mb-0"><i class="fas fa-list-ol me-1"></i> Top 10 Identified Formats</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>PUID</th>
                            <th>Format Name</th>
                            <th class="text-end">Count</th>
                            <th>Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalIdentified = max($stats['identified'] ?? 1, 1);
                            $rank = 1;
                            $topFormats = $topFormats ?? [];
                        @endphp
                        @forelse($topFormats as $format)
                        @php $percentage = ($format->count / $totalIdentified) * 100; @endphp
                        <tr>
                            <td>{{ $rank++ }}</td>
                            <td>
                                @if($format->puid ?? null)
                                    <a href="https://www.nationalarchives.gov.uk/PRONOM/{{ $format->puid }}" target="_blank" class="text-decoration-none">
                                        <code>{{ $format->puid }}</code>
                                        <i class="fas fa-external-link-alt fa-xs"></i>
                                    </a>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>{{ $format->format_name ?? 'Unknown' }}</td>
                            <td class="text-end">{{ number_format($format->count) }}</td>
                            <td style="width: 200px;">
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" role="progressbar" style="width: {{ $percentage }}%; background:var(--ahg-primary);">
                                        {{ number_format($percentage, 1) }}%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No format identifications yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Recent Identifications --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-list me-2"></i>Recent Identifications</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead><tr>
              <th>File</th><th>PUID</th><th>Format</th><th>MIME</th><th>Confidence</th><th>Basis</th><th>Tool</th><th>Date</th>
            </tr></thead>
            <tbody>
              @forelse($identifications ?? [] as $id)
              <tr>
                <td>
                    @if($id->digital_object_id ?? null)
                        <a href="{{ route('preservation.object', $id->digital_object_id) }}">{{ Str::limit($id->filename ?? '', 30) }}</a>
                    @else
                        {{ Str::limit($id->filename ?? '', 40) }}
                    @endif
                </td>
                <td>
                    @if($id->puid ?? null)
                        <a href="https://www.nationalarchives.gov.uk/PRONOM/{{ $id->puid }}" target="_blank"><code>{{ $id->puid }}</code></a>
                    @else
                        <span class="text-muted">N/A</span>
                    @endif
                </td>
                <td>{{ $id->format_name ?? '-' }}</td>
                <td><small>{{ $id->mime_type ?? '-' }}</small></td>
                <td>
                    @php
                        $confBadge = [
                            'certain' => 'bg-success',
                            'high' => 'bg-primary',
                            'medium' => 'bg-warning text-dark',
                            'low' => 'bg-danger',
                        ];
                        $badge = $confBadge[$id->confidence ?? ''] ?? 'bg-secondary';
                    @endphp
                    <span class="badge {{ $badge }}">{{ $id->confidence ?? '-' }}</span>
                </td>
                <td><small class="text-muted">{{ Str::limit($id->basis ?? '-', 30) }}</small></td>
                <td><small>{{ $id->tool ?? '-' }}</small></td>
                <td><small class="text-muted">{{ $id->created_at ?? '' }}</small></td>
              </tr>
              @empty
              <tr><td colspan="8" class="text-center text-muted py-3">No identifications performed yet</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- CLI Commands --}}
    <div class="card border-info">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0"><i class="fas fa-terminal me-1"></i> CLI Commands</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Batch Identification</h6>
                    <pre class="bg-dark text-light p-3 rounded"><code># Check status
php artisan preservation:identify --status

# Identify unidentified objects
php artisan preservation:identify --limit=500

# Preview without identifying
php artisan preservation:identify --dry-run

# Re-identify all objects
php artisan preservation:identify --all --limit=1000</code></pre>
                </div>
                <div class="col-md-6">
                    <h6>Single Object</h6>
                    <pre class="bg-dark text-light p-3 rounded"><code># Identify specific object
php artisan preservation:identify --object-id=123

# Force re-identification
php artisan preservation:identify --object-id=123 --reidentify</code></pre>
                    <h6 class="mt-3">Cron Schedule</h6>
                    <pre class="bg-dark text-light p-3 rounded"><code># Daily identification at 1am
0 1 * * * cd /usr/share/nginx/heratio && \
  php artisan preservation:identify --limit=500</code></pre>
                </div>
            </div>
        </div>
    </div>
  </div>
</div>
@endsection
