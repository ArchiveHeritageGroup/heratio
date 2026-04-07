{{-- Timeline Builder --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Timeline Builder')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Timeline Builder</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-stream text-primary me-2"></i>Timeline Builder</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addEventForm"><i class="fas fa-plus me-1"></i>Add Event</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

{{-- Add event form --}}
<div class="collapse mb-4" id="addEventForm">
    <div class="card">
        <div class="card-header"><h6 class="mb-0">Add Event</h6></div>
        <div class="card-body">
            <form method="POST">
                @csrf
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Event Date <span class="text-danger">*</span></label>
                        <input type="date" name="event_date" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Event Type</label>
                        <select name="event_type" class="form-select">
                            <option value="event">Event</option>
                            <option value="milestone">Milestone</option>
                            <option value="discovery">Discovery</option>
                            <option value="document">Document</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>Add Event</button>
            </form>
        </div>
    </div>
</div>

{{-- Timeline visualization --}}
<div class="card mb-4">
    <div class="card-body">
        @if(!empty($events) && count($events) > 0)
        <style>
            .timeline-item { position: relative; padding-left: 30px; margin-bottom: 24px; }
            .timeline-item::before { content: ''; position: absolute; left: 8px; top: 6px; width: 12px; height: 12px; background: var(--bs-primary, #0d6efd); border-radius: 50%; z-index: 1; }
            .timeline-item::after { content: ''; position: absolute; left: 13px; top: 18px; width: 2px; height: calc(100% + 12px); background: #dee2e6; }
            .timeline-item:last-child::after { display: none; }
        </style>
        @foreach($events as $event)
        <div class="timeline-item">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="mb-1">{{ e($event->title ?? '') }}</h6>
                    @if($event->description ?? false)
                        <p class="mb-1 small text-muted">{{ e($event->description) }}</p>
                    @endif
                    <span class="badge bg-{{ match($event->event_type ?? '') { 'milestone' => 'success', 'discovery' => 'info', 'document' => 'secondary', default => 'primary' } }}">{{ ucfirst($event->event_type ?? 'event') }}</span>
                </div>
                <span class="text-muted small">{{ e($event->event_date ?? '') }}</span>
            </div>
        </div>
        @endforeach
        @else
        <div class="text-center py-4 text-muted">
            <i class="fas fa-stream fa-2x mb-2 opacity-50"></i>
            <p>No events yet. Add events to build your timeline.</p>
        </div>
        @endif
    </div>
</div>
@endsection
