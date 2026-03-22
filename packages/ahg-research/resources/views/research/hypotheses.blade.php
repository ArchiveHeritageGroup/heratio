{{-- Hypotheses - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Hypotheses')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li><li class="breadcrumb-item active">Hypotheses</li></ol></nav>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-lightbulb text-primary me-2"></i>Hypotheses</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHypothesisModal"><i class="fas fa-plus me-1"></i>New Hypothesis</button>
</div>
@if(!empty($hypotheses))
<div class="row">
    @foreach($hypotheses as $h)
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <span>{{ e($h->title ?? 'Untitled') }}</span>
                <span class="badge bg-{{ match($h->status ?? '') { 'confirmed' => 'success', 'refuted' => 'danger', 'testing' => 'info', default => 'warning' } }}">{{ ucfirst($h->status ?? 'proposed') }}</span>
            </div>
            <div class="card-body">
                <p>{{ e($h->description ?? '') }}</p>
                @if($h->evidence_count ?? 0)<small class="text-muted"><i class="fas fa-file-alt me-1"></i>{{ $h->evidence_count }} evidence items</small>@endif
            </div>
            <div class="card-footer">
                <a href="{{ route('research.dashboard', ['view_hypothesis' => $h->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i>View</a>
            </div>
        </div>
    </div>
    @endforeach
</div>
@else
<div class="alert alert-info">No hypotheses yet.</div>
@endif
<div class="modal fade" id="addHypothesisModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST">@csrf <input type="hidden" name="project_id" value="{{ $project->id ?? 0 }}">
    <div class="modal-header"><h5 class="modal-title">New Hypothesis</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Title <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
        <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="proposed">Proposed</option><option value="testing">Testing</option></select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
</form></div></div></div>
@endsection