{{-- Reproducibility Pack --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Reproducibility Pack')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Reproducibility Pack</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-box-open text-primary me-2"></i>Reproducibility Pack</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" disabled><i class="fas fa-download me-1"></i>Download Pack</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

{{-- Summary cards --}}
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="mb-0">{{ count($milestones ?? []) }}</h3>
                <small class="text-muted">Milestones</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="mb-0">{{ count($resources ?? []) }}</h3>
                <small class="text-muted">Resources</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="mb-0">{{ count($assertions ?? []) }}</h3>
                <small class="text-muted">Assertions</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="mb-0">{{ count($hypotheses ?? []) }}</h3>
                <small class="text-muted">Hypotheses</small>
            </div>
        </div>
    </div>
</div>

{{-- Milestones --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Milestones</h6></div>
    <ul class="list-group list-group-flush">
        @forelse($milestones ?? [] as $m)
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>{{ e($m->title ?? '') }}</span>
            <span class="badge bg-{{ match($m->status ?? '') { 'completed' => 'success', 'in_progress' => 'primary', default => 'secondary' } }}">{{ ucfirst(str_replace('_', ' ', $m->status ?? 'pending')) }}</span>
        </li>
        @empty
        <li class="list-group-item text-muted small">No milestones.</li>
        @endforelse
    </ul>
</div>

{{-- Resources --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Resources</h6></div>
    <ul class="list-group list-group-flush">
        @forelse($resources ?? [] as $r)
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><i class="fas fa-file me-2"></i>{{ e($r->name ?? $r->title ?? '') }}</span>
            <span class="badge bg-secondary">{{ $r->type ?? '' }}</span>
        </li>
        @empty
        <li class="list-group-item text-muted small">No resources.</li>
        @endforelse
    </ul>
</div>

{{-- Assertions --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Assertions</h6></div>
    <ul class="list-group list-group-flush">
        @forelse($assertions ?? [] as $a)
        <li class="list-group-item">
            <strong>{{ e($a->subject_label ?? '') }}</strong> {{ e($a->predicate ?? '') }} <strong>{{ e($a->object_label ?? $a->object_value ?? '') }}</strong>
            <span class="badge bg-{{ match($a->status ?? '') { 'verified' => 'success', 'disputed' => 'danger', default => 'warning' } }} ms-2">{{ ucfirst($a->status ?? 'proposed') }}</span>
        </li>
        @empty
        <li class="list-group-item text-muted small">No assertions.</li>
        @endforelse
    </ul>
</div>

{{-- Hypotheses --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Hypotheses</h6></div>
    <ul class="list-group list-group-flush">
        @forelse($hypotheses ?? [] as $h)
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>{{ e($h->title ?? '') }}</span>
            <span class="badge bg-{{ match($h->status ?? '') { 'supported' => 'success', 'refuted' => 'danger', 'testing' => 'info', default => 'warning' } }}">{{ ucfirst($h->status ?? 'proposed') }}</span>
        </li>
        @empty
        <li class="list-group-item text-muted small">No hypotheses.</li>
        @endforelse
    </ul>
</div>
@endsection
