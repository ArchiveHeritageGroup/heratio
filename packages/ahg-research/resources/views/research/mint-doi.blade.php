{{-- DOI Minting - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'DOI Minting')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li><li class="breadcrumb-item active">DOI Minting</li></ol></nav>
<h1 class="h2 mb-4">DOI Minting</h1>
<div class="card mb-4"><div class="card-header"><h5 class="mb-0">DOI Status</h5></div><div class="card-body">
    @if(!empty($currentDoi))
        <div class="alert alert-success mb-0"><strong>DOI Minted:</strong> <a href="https://doi.org/{{ e($currentDoi) }}" target="_blank" rel="noopener">{{ e($currentDoi) }}</a>
            @if(!empty($doiMintedAt))<br><small class="text-muted">Minted on: {{ e($doiMintedAt) }}</small>@endif
        </div>
    @else
        <p class="text-muted mb-0">No DOI has been minted for this project yet.</p>
    @endif
</div></div>
<div class="card mb-4"><div class="card-header"><h5 class="mb-0">Project Metadata for DOI</h5></div><div class="card-body">
    <form method="POST" id="doiForm">@csrf <input type="hidden" name="project_id" value="{{ $project->id ?? 0 }}">
        <div class="mb-3"><label class="form-label">Title <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" name="title" class="form-control" value="{{ e($project->title ?? '') }}"></div>
        <div class="mb-3"><label class="form-label">Creator(s) <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" name="creators" class="form-control" value="{{ e($creators ?? '') }}" placeholder="Comma-separated names"></div>
        <div class="row mb-3">
            <div class="col-md-6"><label class="form-label">Publisher <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" name="publisher" class="form-control" value="{{ e($publisher ?? '') }}"></div>
            <div class="col-md-3"><label class="form-label">Year <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" name="year" class="form-control" value="{{ date('Y') }}"></div>
            <div class="col-md-3"><label class="form-label">Type <span class="badge bg-secondary ms-1">Optional</span></label><select name="resource_type" class="form-select"><option value="Dataset">Dataset</option><option value="Collection">Collection</option><option value="Text">Text</option><option value="Other">Other</option></select></div>
        </div>
        <div class="mb-3"><label class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label><textarea name="description" class="form-control" rows="3">{{ e($project->description ?? '') }}</textarea></div>
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-stamp me-1"></i>Mint DOI</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn atom-btn-white">Cancel</a>
    </form>
</div></div>
@endsection