{{-- Research Milestones & Deliverables tracker - per-project list + summary (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Milestones & Deliverables'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Milestones & Deliverables') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-flag-checkered text-primary me-2"></i>{{ __('Milestones & Deliverables') }}</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('research.milestones.create', $project->id ?? 0) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Milestone') }}</a>
        @if(($summary['total'] ?? 0) > 0)
            <a href="{{ route('research.milestones.export', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-code me-1"></i>{{ __('Export JSON') }}</a>
        @endif
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('The plan for this project - the milestones and deliverables it intends to reach, each with a due date, a status and a progress percentage. This documents the intended schedule of the work alongside the project Data Management Plan, outputs, ethics, funding and team. A milestone is a planned point in the work; a deliverable is a tangible output the plan commits to producing.') }}</p>

{{-- Overdue warning banner --}}
@if(($summary['overdue'] ?? 0) > 0)
<div class="alert alert-danger d-flex align-items-center mb-3">
    <i class="fas fa-triangle-exclamation me-2"></i>
    <div><strong>{{ $summary['overdue'] }}</strong> {{ trans_choice('milestone is|milestones are', $summary['overdue']) }} {{ __('overdue (past the due date and not yet completed or cancelled).') }}</div>
</div>
@endif

{{-- Per-project summary --}}
@if(($summary['total'] ?? 0) > 0)
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">{{ __('Summary') }}</h6>
        <span class="badge bg-primary rounded-pill">{{ $summary['total'] }} {{ __('total') }}</span>
    </div>
    <div class="card-body">
        {{-- Overall progress --}}
        <div class="mb-3">
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span class="text-uppercase">{{ __('Overall progress') }}</span>
                <span><strong>{{ $summary['progress_pct'] }}%</strong> &middot; {{ $summary['completed'] }} / {{ $summary['total'] }} {{ __('completed') }}</span>
            </div>
            <div class="progress" style="height: 8px;" role="progressbar" aria-valuenow="{{ $summary['progress_pct'] }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-success" style="width: {{ $summary['progress_pct'] }}%"></div>
            </div>
        </div>

        {{-- Next upcoming milestone --}}
        @if(! empty($summary['next']))
        @php $nx = $summary['next']; @endphp
        <div class="alert {{ $nx['is_overdue'] ? 'alert-danger' : ($nx['is_due_soon'] ? 'alert-warning' : 'alert-info') }} d-flex align-items-center py-2 mb-3">
            <i class="fas fa-arrow-right me-2"></i>
            <div class="small">
                {{ __('Next upcoming:') }}
                <a href="{{ route('research.milestones.show', [$project->id ?? 0, $nx['id']]) }}" class="alert-link">{{ e($nx['title']) }}</a>
                <span class="text-muted">&middot; {{ __('due') }} {{ e($nx['due_date']) }}</span>
                @if($nx['is_overdue'])<span class="badge bg-danger ms-1">{{ __('Overdue') }}</span>
                @elseif($nx['is_due_soon'])<span class="badge bg-warning text-dark ms-1">{{ __('Due soon') }}</span>@endif
            </div>
        </div>
        @endif

        <div class="row">
            <div class="col-md-6">
                <div class="text-muted small text-uppercase mb-2">{{ __('By status') }}</div>
                @foreach($summary['by_status'] as $s)
                    <span class="badge bg-light text-dark border me-1 mb-1">{{ e($s['label']) }} <span class="badge bg-secondary ms-1">{{ $s['count'] }}</span></span>
                @endforeach
            </div>
            <div class="col-md-6">
                <div class="text-muted small text-uppercase mb-2">{{ __('By type') }}</div>
                @foreach($summary['by_type'] as $t)
                    <span class="badge bg-light text-dark border me-1 mb-1">{{ e($t['label']) }} <span class="badge bg-secondary ms-1">{{ $t['count'] }}</span></span>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

{{-- Milestone list (ordered by due date) --}}
@if(empty($milestones))
    <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>{{ __('No milestones yet. Record this project\'s planned milestones and deliverables - each with a due date, a status and a progress percentage.') }}</div>
@else
<div class="table-responsive mb-4">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>{{ __('Milestone / Deliverable') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Due') }}</th>
                <th>{{ __('Status') }}</th>
                <th style="min-width: 140px;">{{ __('Progress') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($milestones as $m)
            @php
                $st = $m['status'] ?? 'planned';
                $badge = match($st) {
                    'completed' => 'success', 'in_progress' => 'primary', 'delayed' => 'warning',
                    'cancelled' => 'dark', 'planned' => 'secondary', default => 'secondary',
                };
            @endphp
            <tr @class(['table-danger' => ! empty($m['is_overdue'])])>
                <td>
                    <a href="{{ route('research.milestones.show', [$project->id ?? 0, $m['id']]) }}">{{ e($m['title']) }}</a>
                    @if(! empty($m['is_overdue']))<span class="badge bg-danger ms-1"><i class="fas fa-triangle-exclamation me-1"></i>{{ __('Overdue') }}</span>
                    @elseif(! empty($m['is_due_soon']))<span class="badge bg-warning text-dark ms-1"><i class="fas fa-clock me-1"></i>{{ __('Due soon') }}</span>@endif
                    @if($m['deliverable'] !== '')<div class="text-muted small"><i class="fas fa-box me-1"></i>{{ e($m['deliverable']) }}</div>@endif
                </td>
                <td><span class="text-muted small">{{ e($typeOptions[$m['milestone_type']] ?? ucfirst(str_replace('_',' ',$m['milestone_type']))) }}</span></td>
                <td class="small">{{ $m['due_date'] !== '' ? e($m['due_date']) : '-' }}</td>
                <td><span class="badge bg-{{ $badge }}">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span></td>
                <td>
                    <div class="progress" style="height: 6px;" role="progressbar" aria-valuenow="{{ $m['progress_pct'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar {{ $st === 'completed' ? 'bg-success' : 'bg-primary' }}" style="width: {{ $m['progress_pct'] }}%"></div>
                    </div>
                    <span class="text-muted small">{{ $m['progress_pct'] }}%</span>
                </td>
                <td class="text-end">
                    <a href="{{ route('research.milestones.edit', [$project->id ?? 0, $m['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
                    <a href="{{ route('research.milestones.show', [$project->id ?? 0, $m['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
