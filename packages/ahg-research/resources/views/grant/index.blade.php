{{-- Grant Engine - per-project draft list + template picker + calls summary (heratio#1239) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Grant Engine'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Grant Engine') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-hand-holding-usd text-primary me-2"></i>{{ __('Grant Engine') }}</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#startDraftForm"><i class="fas fa-plus me-1"></i>{{ __('New Grant Draft') }}</button>
        <a href="{{ route('research.grant.calls', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-bullhorn me-1"></i>{{ __('Tracked calls') }}</a>
        <a href="{{ route('research.grant.templates', ['project' => $project->id ?? 0]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-th-large me-1"></i>{{ __('Browse templates') }}</a>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<p class="text-muted">{{ __('A grant draft is assembled from material that already exists on this project - its mission, method protocol, question brief and claims. Choose a funder template and the sections are pre-filled from your own work, ready to edit. AI drafting per section is optional, labelled, and never submitted on your behalf.') }}</p>

{{-- Start-from-template form (collapsed) --}}
<div class="collapse mb-4" id="startDraftForm">
    <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ __('Start a Grant Draft') }}</h6></div>
        <div class="card-body">
            @if(empty($templates))
                <div class="alert alert-info mb-0">{{ __('No funder templates are available yet.') }}</div>
            @else
            <form method="POST" action="{{ route('research.grant.store', $project->id ?? 0) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">{{ __('Funder template') }} <span class="text-danger">*</span></label>
                        <select name="funder_template" class="form-select" required>
                            <option value="">{{ __('Choose a template...') }}</option>
                            @foreach($templates as $t)
                                <option value="{{ e($t['code']) }}">{{ e($t['name']) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">{{ __('Title') }}</label>
                        <input type="text" name="title" class="form-control" maxlength="255" placeholder="{{ __('Optional - defaults to the project title') }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check me-1"></i>{{ __('Create') }}</button>
                    </div>
                </div>
            </form>
            @endif
        </div>
    </div>
</div>

{{-- Draft list --}}
@if(empty($drafts))
    <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>{{ __('No grant drafts yet. Start one from a funder template to assemble a draft from your project material.') }}</div>
@else
<div class="table-responsive mb-4">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Template') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Updated') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($drafts as $d)
            <tr>
                <td><a href="{{ route('research.grant.show', [$project->id ?? 0, $d['id']]) }}">{{ e($d['title']) }}</a></td>
                <td><span class="text-muted small">{{ e($d['funder_template']) }}</span></td>
                <td>
                    @php
                        $st = $d['status'] ?? 'draft';
                        $badge = match($st) { 'submitted' => 'success', 'ready' => 'primary', 'in_review' => 'info', default => 'secondary' };
                    @endphp
                    <span class="badge bg-{{ $badge }}">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span>
                </td>
                <td class="text-muted small">{{ e($d['updated_at']) }}</td>
                <td class="text-end">
                    <a href="{{ route('research.grant.edit', [$project->id ?? 0, $d['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
                    <a href="{{ route('research.grant.show', [$project->id ?? 0, $d['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Tracked calls summary --}}
<h2 class="h5 mt-4"><i class="fas fa-bullhorn text-muted me-2"></i>{{ __('Tracked calls') }}</h2>
@if(empty($calls))
    <p class="text-muted small">{{ __('No grant calls tracked for this project yet.') }} <a href="{{ route('research.grant.calls', $project->id ?? 0) }}">{{ __('Track a call') }}</a>.</p>
@else
<ul class="list-group mb-3">
    @foreach(array_slice($calls, 0, 5) as $c)
    <li class="list-group-item d-flex justify-content-between align-items-center">
        <span>
            <strong>{{ e($c['funder']) }}</strong> - {{ e($c['title']) }}
            @if(!empty($c['deadline']))<span class="text-muted small ms-2"><i class="fas fa-calendar-day me-1"></i>{{ e($c['deadline']) }}</span>@endif
        </span>
        <span class="badge bg-secondary">{{ e(ucfirst(str_replace('_',' ',$c['status']))) }}</span>
    </li>
    @endforeach
</ul>
<a href="{{ route('research.grant.calls', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list me-1"></i>{{ __('Manage tracked calls') }}</a>
@endif
@endsection
