{{-- RO-Crate Export - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'RO-Crate')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li><li class="breadcrumb-item active">RO-Crate</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-archive text-primary me-2"></i>RO-Crate Export</h1>
<div class="card mb-4"><div class="card-header"><h5 class="mb-0">RO-Crate Metadata</h5></div><div class="card-body">
    <dl class="row mb-0">
        <dt class="col-sm-3">Name</dt><dd class="col-sm-9">{{ e($project->title ?? '') }}</dd>
        <dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ e($project->description ?? 'N/A') }}</dd>
        <dt class="col-sm-3">Date Created</dt><dd class="col-sm-9">{{ $project->created_at ?? '' }}</dd>
        <dt class="col-sm-3">License</dt><dd class="col-sm-9">{{ e($project->license ?? 'Not specified') }}</dd>
    </dl>
</div></div>
<div class="card mb-4"><div class="card-header"><h5 class="mb-0">Included Parts</h5></div>
    <ul class="list-group list-group-flush">
        @forelse($parts ?? [] as $part)
        <li class="list-group-item d-flex justify-content-between">
            <span><i class="fas fa-{{ match($part->type ?? '') { 'file' => 'file', 'dataset' => 'database', default => 'cube' } }} me-2"></i>{{ e($part->name ?? '') }}</span>
            <span class="badge bg-secondary">{{ $part->type ?? '' }}</span>
        </li>
        @empty
        <li class="list-group-item text-muted">No parts included.</li>
        @endforelse
    </ul>
</div>
<div class="d-flex gap-2">
    <button class="btn btn-primary" id="downloadRoCrate"><i class="fas fa-download me-1"></i>Download RO-Crate (ZIP)</button>
    <button class="btn btn-outline-secondary" id="previewJson"><i class="fas fa-code me-1"></i>Preview JSON-LD</button>
    <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn atom-btn-white">Back to Project</a>
</div>
<div class="card mt-4 d-none" id="jsonPreview"><div class="card-header">ro-crate-metadata.json</div><div class="card-body"><pre class="mb-0"><code>{{ json_encode($roCrateMetadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre></div></div>
<script>document.getElementById('previewJson')?.addEventListener('click', function() { document.getElementById('jsonPreview')?.classList.toggle('d-none'); });</script>
@endsection