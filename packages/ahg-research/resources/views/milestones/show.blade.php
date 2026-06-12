{{-- Research Milestones & Deliverables tracker - read-only detail (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Milestone'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.milestones.index', $project->id ?? 0) }}">{{ __('Milestones & Deliverables') }}</a></li>
        <li class="breadcrumb-item active">{{ e($milestone['title']) }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@php
    $st = $milestone['status'] ?? 'planned';
    $badge = match($st) {
        'completed' => 'success', 'in_progress' => 'primary', 'delayed' => 'warning',
        'cancelled' => 'dark', 'planned' => 'secondary', default => 'secondary',
    };
@endphp

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-flag-checkered text-primary me-2"></i>{{ e($milestone['title']) }}</h1>
        <div class="text-muted small">
            <span class="badge bg-info text-dark">{{ e($typeOptions[$milestone['milestone_type']] ?? ucfirst(str_replace('_',' ',$milestone['milestone_type']))) }}</span>
            <span class="badge bg-{{ $badge }} ms-1">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span>
            @if(! empty($milestone['is_overdue']))<span class="badge bg-danger ms-1"><i class="fas fa-triangle-exclamation me-1"></i>{{ __('Overdue') }}</span>
            @elseif(! empty($milestone['is_due_soon']))<span class="badge bg-warning text-dark ms-1"><i class="fas fa-clock me-1"></i>{{ __('Due soon') }}</span>@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('research.milestones.edit', [$project->id ?? 0, $milestone['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
        <a href="{{ route('research.milestones.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

@if(! empty($milestone['is_overdue']))
<div class="alert alert-danger d-flex align-items-center py-2">
    <i class="fas fa-triangle-exclamation me-2"></i>
    <div class="small">{{ __('This milestone is overdue - its due date has passed and it is not completed or cancelled.') }}</div>
</div>
@endif

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Progress') }}</h6></div>
    <div class="card-body">
        <div class="d-flex justify-content-between small text-muted mb-1">
            <span>{{ __('Completion') }}</span>
            <span><strong>{{ $milestone['progress_pct'] }}%</strong></span>
        </div>
        <div class="progress" style="height: 10px;" role="progressbar" aria-valuenow="{{ $milestone['progress_pct'] }}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar {{ $st === 'completed' ? 'bg-success' : 'bg-primary' }}" style="width: {{ $milestone['progress_pct'] }}%"></div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Schedule') }}</h6></div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('Due date') }}</dt>
            <dd class="col-sm-9">{{ e($milestone['due_date'] !== '' ? $milestone['due_date'] : '-') }}</dd>
            <dt class="col-sm-3">{{ __('Completed date') }}</dt>
            <dd class="col-sm-9">{{ e($milestone['completed_date'] !== '' ? $milestone['completed_date'] : '-') }}</dd>
            <dt class="col-sm-3">{{ __('Deliverable') }}</dt>
            <dd class="col-sm-9">{{ $milestone['deliverable'] !== '' ? e($milestone['deliverable']) : '-' }}</dd>
        </dl>
    </div>
</div>

@if(trim($milestone['description']) !== '')
<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">{{ __('Description') }}</h6></div>
    <div class="card-body">
        <div style="white-space: pre-wrap;">{{ $milestone['description'] }}</div>
    </div>
</div>
@endif

<form method="POST" action="{{ route('research.milestones.destroy', [$project->id ?? 0, $milestone['id']]) }}" onsubmit="return confirm('{{ __('Remove this milestone?') }}');" class="mb-5">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>{{ __('Remove milestone') }}</button>
</form>
@endsection
