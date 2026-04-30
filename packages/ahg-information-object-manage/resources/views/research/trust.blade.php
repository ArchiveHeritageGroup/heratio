@extends('theme::layouts.2col')
@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'workspace'])
@endsection
@section('title', 'Trust Score — ' . ($io->title ?? ''))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Trust Score</li>
    </ol>
</nav>

@php
    $scoreColor = $score >= 80 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
    $scoreLabel = $score >= 80 ? 'High Trust' : ($score >= 50 ? 'Moderate Trust' : 'Low Trust');
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2">{{ __('Trust Score') }}</h1>
        <p class="text-muted mb-0">
            <a href="{{ url('/' . $io->slug) }}">{{ e($io->title ?? 'Untitled') }}</a>
            @if($io->identifier ?? null)
                <small class="ms-2">({{ e($io->identifier) }})</small>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('io.research.assessment', $io->slug) }}" class="btn btn-outline-primary"><i class="fas fa-clipboard-check me-1"></i>{{ __('Assess Source') }}</a>
        <a href="{{ route('research.evidence-viewer', ['object_id' => $io->id]) }}" class="btn btn-outline-secondary"><i class="fas fa-search me-1"></i>{{ __('Evidence Viewer') }}</a>
    </div>
</div>

{{-- Score Overview --}}
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-{{ $scoreColor }}">
            <div class="card-body text-center">
                <div class="position-relative d-inline-block mb-3" style="width:140px;height:140px;">
                    <svg viewBox="0 0 36 36" style="width:140px;height:140px;transform:rotate(-90deg);">
                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e9ecef" stroke-width="3"/>
                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                              fill="none" stroke="var(--bs-{{ $scoreColor }})" stroke-width="3"
                              stroke-dasharray="{{ $score }}, 100"/>
                    </svg>
                    <div class="position-absolute top-50 start-50 translate-middle">
                        <span class="display-4 fw-bold text-{{ $scoreColor }}">{{ $score }}</span>
                    </div>
                </div>
                <h5 class="text-{{ $scoreColor }}">{{ $scoreLabel }}</h5>
                <small class="text-muted">{{ __('Composite score out of 100') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">{{ __('Score Breakdown') }}</h5></div>
            <div class="card-body">
                {{-- Source Type --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="fas fa-archive me-1 text-primary"></i>Source Type
                            @if($assessment)
                                <span class="badge bg-light text-dark ms-1">{{ ucfirst($assessment->source_type) }}</span>
                            @endif
                        </span>
                        <span class="fw-bold">{{ $sourceWeight }}/40</span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-primary" style="width:{{ $sourceWeight > 0 ? round($sourceWeight / 40 * 100) : 0 }}%"></div>
                    </div>
                </div>
                {{-- Completeness --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="fas fa-puzzle-piece me-1 text-info"></i>Completeness
                            @if($assessment)
                                <span class="badge bg-light text-dark ms-1">{{ ucfirst(str_replace('_', ' ', $assessment->completeness ?? '')) }}</span>
                            @endif
                        </span>
                        <span class="fw-bold">{{ $completenessWeight }}/30</span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-info" style="width:{{ $completenessWeight > 0 ? round($completenessWeight / 30 * 100) : 0 }}%"></div>
                    </div>
                </div>
                {{-- Quality Metrics --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="fas fa-star me-1 text-warning"></i>Quality Metrics
                            <small class="text-muted">({{ $qualityCount }} metrics)</small>
                        </span>
                        <span class="fw-bold">{{ $qualityScore }}/30</span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-warning" style="width:{{ $qualityScore > 0 ? round($qualityScore / 30 * 100) : 0 }}%"></div>
                    </div>
                </div>
                @if(!$assessment)
                    <div class="alert alert-info py-2 mb-0"><i class="fas fa-info-circle me-1"></i>No source assessment yet. <a href="{{ route('io.research.assessment', $io->slug) }}">Submit one</a> to get a meaningful score.</div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($assessment)
{{-- Latest Assessment --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Latest Assessment</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <strong>{{ __('Source Type') }}</strong><br>
                @php
                    $typeBadge = match($assessment->source_type) { 'primary' => 'success', 'secondary' => 'info', 'tertiary' => 'secondary', default => 'dark' };
                @endphp
                <span class="badge bg-{{ $typeBadge }} fs-6">{{ ucfirst($assessment->source_type) }}</span>
            </div>
            <div class="col-md-3">
                <strong>{{ __('Form') }}</strong><br>
                {{ ucfirst(str_replace('_', ' ', $assessment->source_form ?? 'original')) }}
            </div>
            <div class="col-md-3">
                <strong>{{ __('Completeness') }}</strong><br>
                {{ ucfirst(str_replace('_', ' ', $assessment->completeness ?? 'unknown')) }}
            </div>
            <div class="col-md-3">
                <strong>{{ __('Assessed by') }}</strong><br>
                {{ e(($assessment->assessor_first_name ?? '') . ' ' . ($assessment->assessor_last_name ?? '')) }}
                <br><small class="text-muted">{{ $assessment->assessed_at ? date('M j, Y H:i', strtotime($assessment->assessed_at)) : '' }}</small>
            </div>
        </div>
        @if($assessment->rationale)
            <div class="mt-3">
                <strong>{{ __('Rationale') }}</strong>
                <p class="mb-0">{!! nl2br(e($assessment->rationale)) !!}</p>
            </div>
        @endif
        @if($assessment->bias_context)
            <div class="mt-2">
                <strong>{{ __('Bias Context') }}</strong>
                <p class="text-muted mb-0">{!! nl2br(e($assessment->bias_context)) !!}</p>
            </div>
        @endif
    </div>
</div>
@endif

@if(!empty($qualityMetrics))
{{-- Quality Metrics --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-star me-2"></i>Quality Metrics</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Metric') }}</th><th>{{ __('Value') }}</th><th style="width:40%">{{ __('Score') }}</th><th>{{ __('Service') }}</th><th>{{ __('Date') }}</th></tr></thead>
                <tbody>
                @foreach($qualityMetrics as $m)
                    @php
                        $pct = round((float) $m->metric_value * 100, 1);
                        $barColor = $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
                        $metricIcon = match($m->metric_type) {
                            'ocr_confidence' => 'font',
                            'image_quality' => 'image',
                            'digitisation_completeness' => 'check-double',
                            'fixity_status' => 'shield-alt',
                            'colour_accuracy' => 'palette',
                            'resolution_dpi' => 'expand-arrows-alt',
                            'file_integrity' => 'file-alt',
                            'metadata_completeness' => 'tags',
                            'legibility' => 'glasses',
                            default => 'circle'
                        };
                    @endphp
                    <tr>
                        <td><i class="fas fa-{{ $metricIcon }} me-1 text-muted"></i>{{ ucwords(str_replace('_', ' ', $m->metric_type)) }}</td>
                        <td class="fw-bold">{{ $pct }}%</td>
                        <td>
                            <div class="progress" style="height:6px;">
                                <div class="progress-bar bg-{{ $barColor }}" style="width:{{ $pct }}%"></div>
                            </div>
                        </td>
                        <td><small class="text-muted">{{ e($m->source_service ?? '-') }}</small></td>
                        <td><small class="text-muted">{{ $m->created_at ? date('M j, Y', strtotime($m->created_at)) : '' }}</small></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@if(count($assessmentHistory) > 1)
{{-- Assessment History --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-history me-2"></i>Assessment History</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Assessor') }}</th><th>{{ __('Source Type') }}</th><th>{{ __('Completeness') }}</th><th>{{ __('Manual Score') }}</th><th>{{ __('Date') }}</th></tr></thead>
                <tbody>
                @foreach($assessmentHistory as $h)
                    @php
                        $hBadge = match($h->source_type) { 'primary' => 'success', 'secondary' => 'info', 'tertiary' => 'secondary', default => 'dark' };
                    @endphp
                    <tr>
                        <td>{{ e(($h->assessor_first_name ?? '') . ' ' . ($h->assessor_last_name ?? '')) }}</td>
                        <td><span class="badge bg-{{ $hBadge }}">{{ ucfirst($h->source_type) }}</span></td>
                        <td>{{ ucfirst(str_replace('_', ' ', $h->completeness)) }}</td>
                        <td>{{ $h->trust_score !== null ? $h->trust_score . '/100' : '—' }}</td>
                        <td>{{ $h->assessed_at ? date('M j, Y H:i', strtotime($h->assessed_at)) : '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
