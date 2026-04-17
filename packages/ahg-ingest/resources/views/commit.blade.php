{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Commit & Report')

@section('content')
@php
    $session = $session ?? null;
    $job = $job ?? null;

    $isRunning = false;
    $isCompleted = false;
    $isFailed = false;
    $pct = 0;
    $errorList = [];
    $elapsed = '';

    if ($job) {
        $isRunning = in_array($job->status ?? '', ['queued', 'running'], true);
        $isCompleted = ($job->status ?? '') === 'completed';
        $isFailed = ($job->status ?? '') === 'failed';
        $pct = !empty($job->total_rows) ? (int) round((($job->processed_rows ?? 0) / $job->total_rows) * 100) : 0;
        $errorList = json_decode($job->error_log ?? '[]', true) ?: [];
        if (!empty($job->started_at) && !empty($job->completed_at)) {
            $diff = strtotime($job->completed_at) - strtotime($job->started_at);
            $elapsed = $diff < 60 ? $diff . 's' : round($diff / 60, 1) . 'm';
        }
    }
@endphp

<h1>Commit &amp; Report</h1>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('ingest.index') }}">Ingestion Manager</a></li>
        <li class="breadcrumb-item">{{ $session->title ?? ('Session #' . ($session->id ?? '')) }}</li>
        <li class="breadcrumb-item active" aria-current="page">Commit</li>
    </ol>
</nav>

{{-- Wizard Progress --}}
<div class="mb-4">
    <div class="d-flex justify-content-between text-center">
        <div class="flex-fill"><span class="badge bg-success rounded-pill">1</span><br><small class="text-muted">Configure</small></div>
        <div class="flex-fill"><span class="badge bg-success rounded-pill">2</span><br><small class="text-muted">Upload</small></div>
        <div class="flex-fill"><span class="badge bg-success rounded-pill">3</span><br><small class="text-muted">Map</small></div>
        <div class="flex-fill"><span class="badge bg-success rounded-pill">4</span><br><small class="text-muted">Validate</small></div>
        <div class="flex-fill"><span class="badge bg-success rounded-pill">5</span><br><small class="text-muted">Preview</small></div>
        <div class="flex-fill"><span class="badge bg-primary rounded-pill">6</span><br><small class="fw-bold">Commit</small></div>
    </div>
    <div class="progress mt-2" style="height: 4px;">
        <div class="progress-bar" style="width: 100%"></div>
    </div>
</div>

@if($job)

    @if($isRunning)
        {{-- Progress (polling) --}}
        <div class="card mb-4" id="progress-card">
            <div class="card-header">
                <h5 class="mb-0" id="progress-header">
                    <i class="fas fa-spinner fa-spin me-2"></i>
                    <span id="progress-label">Committing...</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="progress mb-3" style="height: 24px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="commit-progress"
                         style="width: {{ $pct }}%">
                        {{ $pct }}%
                    </div>
                </div>
                <div id="post-processing-info" class="alert alert-info mb-3" style="display: none;">
                    <i class="fas fa-cogs me-2"></i>
                    Records created. Running post-processing (derivatives, packages, indexing)...
                </div>
                <div class="row text-center">
                    <div class="col">
                        <strong id="stat-processed">{{ $job->processed_rows ?? 0 }}</strong> / <span id="stat-total">{{ $job->total_rows ?? 0 }}</span>
                        <br><small class="text-muted">Processed</small>
                    </div>
                    <div class="col">
                        <strong id="stat-records" class="text-success">{{ $job->created_records ?? 0 }}</strong>
                        <br><small class="text-muted">Records</small>
                    </div>
                    <div class="col">
                        <strong id="stat-dos" class="text-info">{{ $job->created_dos ?? 0 }}</strong>
                        <br><small class="text-muted">Digital Objects</small>
                    </div>
                    <div class="col">
                        <strong id="stat-errors" class="text-danger">{{ $job->error_count ?? 0 }}</strong>
                        <br><small class="text-muted">Errors</small>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($isCompleted || $isFailed)
        {{-- Completion Report --}}
        <div class="card mb-4">
            <div class="card-header bg-{{ $isCompleted ? 'success' : 'danger' }} text-white">
                <h5 class="mb-0">
                    <i class="fas fa-{{ $isCompleted ? 'check-circle' : 'exclamation-circle' }} me-2"></i>
                    {{ $isCompleted ? 'Ingest Completed' : 'Ingest Completed with Errors' }}
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center mb-4">
                    <div class="col-md-3">
                        <div class="card border-0">
                            <div class="card-body">
                                <h2 class="mb-0 text-success">{{ $job->created_records ?? 0 }}</h2>
                                <small class="text-muted">Records Created</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0">
                            <div class="card-body">
                                <h2 class="mb-0 text-info">{{ $job->created_dos ?? 0 }}</h2>
                                <small class="text-muted">Digital Objects</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0">
                            <div class="card-body">
                                <h2 class="mb-0 text-danger">{{ $job->error_count ?? 0 }}</h2>
                                <small class="text-muted">Errors</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0">
                            <div class="card-body">
                                <h2 class="mb-0">{{ $elapsed ?: '—' }}</h2>
                                <small class="text-muted">Duration</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="d-flex flex-wrap gap-2 justify-content-center mb-3">
                    @if(!empty($job->created_records) && $job->created_records > 0)
                        <a href="/glam/browse" class="btn btn-outline-success">
                            <i class="fas fa-list me-1"></i>Browse Records
                        </a>
                    @endif
                </div>

                @if(!empty($job->sip_package_id))
                    <div class="alert alert-info mb-2">
                        <i class="fas fa-box me-1"></i>SIP (Submission Information Package) generated
                    </div>
                @endif

                @if(!empty($job->aip_package_id))
                    <div class="alert alert-success mb-2">
                        <i class="fas fa-archive me-1"></i>AIP (Archival Information Package) generated
                    </div>
                @endif

                @if(!empty($job->dip_package_id))
                    <div class="alert alert-info mb-2">
                        <i class="fas fa-box-open me-1"></i>DIP (Dissemination Information Package) generated
                    </div>
                @endif
            </div>
        </div>

        @if(!empty($errorList))
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Error Log</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Row / Stage</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($errorList as $err)
                                    <tr>
                                        <td>
                                            @if(isset($err['row']))
                                                Row #{{ $err['row'] }}
                                            @elseif(isset($err['stage']))
                                                {{ ucfirst($err['stage']) }}
                                            @endif
                                        </td>
                                        <td><code>{{ $err['error'] ?? '' }}</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    @endif

@else
    {{-- No job yet — show start button --}}
    <div class="card mb-4">
        <div class="card-body text-center py-5">
            <i class="fas fa-rocket fa-3x text-primary mb-3"></i>
            <h5>Ready to commit</h5>
            <p class="text-muted">This will create records in Heratio based on your validated data.</p>
            <form method="post" action="{{ route('ingest.commit', ['id' => $session->id ?? 0]) }}">
                @csrf
                <button type="submit" class="btn btn-lg btn-success"
                        onclick="return confirm('Start committing records to Heratio?')">
                    <i class="fas fa-play me-1"></i>Start Commit
                </button>
            </form>
        </div>
    </div>
@endif

<div class="text-center">
    <a href="{{ route('ingest.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

@if($job && $isRunning)
<script>
document.addEventListener('DOMContentLoaded', function() {
    var totalRows = {{ (int) ($job->total_rows ?? 0) }};
    var pollInterval = setInterval(function() {
        fetch('{{ route('ingest.commit', ['id' => $session->id ?? 0]) }}?job_id={{ $job->id ?? 0 }}', {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || data.error) return;
            var pct = data.total_rows > 0 ? Math.round((data.processed_rows / data.total_rows) * 100) : 0;
            var bar = document.getElementById('commit-progress');
            if (bar) {
                bar.style.width = pct + '%';
                bar.textContent = pct + '%';
            }
            var el;
            el = document.getElementById('stat-processed');
            if (el) el.textContent = data.processed_rows;
            el = document.getElementById('stat-records');
            if (el) el.textContent = data.created_records;
            el = document.getElementById('stat-dos');
            if (el) el.textContent = data.created_dos;
            el = document.getElementById('stat-errors');
            if (el) el.textContent = data.error_count;
            if (data.processed_rows >= totalRows && data.status === 'running') {
                var ppInfo = document.getElementById('post-processing-info');
                if (ppInfo) ppInfo.style.display = 'block';
                var lbl = document.getElementById('progress-label');
                if (lbl) lbl.textContent = 'Post-processing...';
            }
            if (data.status === 'completed' || data.status === 'failed') {
                clearInterval(pollInterval);
                location.reload();
            }
        })
        .catch(function() {});
    }, 2000);
});
</script>
@endif
@endsection
