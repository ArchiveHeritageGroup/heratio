{{-- Timeline Builder - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Timeline Builder')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Timeline Builder</li></ol></nav>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-stream text-primary me-2"></i>Timeline Builder</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal"><i class="fas fa-plus me-1"></i>Add Event</button>
</div>
<div class="card mb-4"><div class="card-body">
    @if(!empty($events))
    <div class="timeline">
        @foreach($events as $event)
        <div class="d-flex mb-4">
            <div class="flex-shrink-0 text-end me-3" style="width:120px;">
                <strong class="small">{{ e($event->event_date ?? '') }}</strong>
            </div>
            <div class="flex-shrink-0 me-3 position-relative">
                <div class="bg-primary rounded-circle" style="width:12px;height:12px;margin-top:4px;"></div>
                @if(!$loop->last)<div style="position:absolute;left:5px;top:16px;bottom:-16px;width:2px;background:#dee2e6;"></div>@endif
            </div>
            <div class="flex-grow-1">
                <div class="card">
                    <div class="card-body py-2 px-3">
                        <h6 class="mb-1">{{ e($event->title ?? '') }}</h6>
                        @if($event->description ?? false)<p class="mb-0 small text-muted">{{ e($event->description) }}</p>@endif
                        @if($event->source_title ?? false)<small class="text-muted"><i class="fas fa-link me-1"></i>{{ e($event->source_title) }}</small>@endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-4 text-muted"><i class="fas fa-stream fa-2x mb-2 opacity-50"></i><p>No events yet. Add events to build your timeline.</p></div>
    @endif
</div></div>
<div class="modal fade" id="addEventModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST">@csrf
    <div class="modal-header"><h5 class="modal-title">Add Timeline Event</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Title <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" name="event_date" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
        <div class="mb-3"><label class="form-label">Category</label><select name="category" class="form-select"><option value="event">Event</option><option value="milestone">Milestone</option><option value="discovery">Discovery</option><option value="document">Document</option></select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add Event</button></div>
</form></div></div></div>
@endsection