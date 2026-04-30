{{-- Trust Score - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'workspace'])@endsection
@section('title', 'Trust Score')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Trust Score</li></ol></nav>
@php
    $score = $score ?? 0;
    $scoreColor = $score >= 80 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
    $scoreLabel = $score >= 80 ? 'High Trust' : ($score >= 50 ? 'Moderate Trust' : 'Low Trust');
@endphp
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2">{{ __('Trust Score') }}</h1>
        @if($objectInfo ?? false)<p class="text-muted mb-0"><a href="/{{ e($objectInfo->slug ?? '') }}">{{ e($objectInfo->title ?? 'Object #' . ($objectId ?? '')) }}</a></p>@endif
    </div>
    <div class="text-center">
        <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1" style="width:80px;height:80px;border:4px solid var(--bs-{{ $scoreColor }});"><h2 class="mb-0 text-{{ $scoreColor }}">{{ $score }}</h2></div>
        <span class="badge bg-{{ $scoreColor }}">{{ $scoreLabel }}</span>
    </div>
</div>
<div class="row mb-4">
    <div class="col-md-4"><div class="card"><div class="card-body text-center"><h5>{{ __('Source Quality') }}</h5><div class="progress mb-2" style="height:8px;"><div class="progress-bar bg-info" style="width:{{ $dimensions['source'] ?? 0 }}%"></div></div><span class="small text-muted">{{ $dimensions['source'] ?? 0 }}/40</span></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body text-center"><h5>{{ __('Completeness') }}</h5><div class="progress mb-2" style="height:8px;"><div class="progress-bar bg-warning" style="width:{{ ($dimensions['completeness'] ?? 0) / 30 * 100 }}%"></div></div><span class="small text-muted">{{ $dimensions['completeness'] ?? 0 }}/30</span></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body text-center"><h5>{{ __('Verification') }}</h5><div class="progress mb-2" style="height:8px;"><div class="progress-bar bg-success" style="width:{{ ($dimensions['verification'] ?? 0) / 30 * 100 }}%"></div></div><span class="small text-muted">{{ $dimensions['verification'] ?? 0 }}/30</span></div></div></div>
</div>
@if(!empty($qualityMetrics))
<div class="card"><div class="card-header"><h5 class="mb-0">{{ __('Quality Metrics') }}</h5></div><div class="card-body p-0">
    <table class="table table-hover mb-0"><thead class="table-light"><tr><th>{{ __('Metric') }}</th><th>{{ __('Value') }}</th><th>{{ __('Weight') }}</th></tr></thead><tbody>
        @foreach($qualityMetrics as $m)<tr><td>{{ e($m->metric_name ?? '') }}</td><td>{{ number_format($m->metric_value ?? 0, 2) }}</td><td>{{ $m->weight ?? '-' }}</td></tr>@endforeach
    </tbody></table>
</div></div>
@endif
@endsection