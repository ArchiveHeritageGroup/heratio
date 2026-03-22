{{-- Share Project - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Share Project')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li><li class="breadcrumb-item active">Share</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-share-alt text-primary me-2"></i>Share Project</h1>
<div class="row"><div class="col-md-8">
<div class="card mb-4"><div class="card-header" style="background:var(--ahg-primary);color:#fff">Sharing Settings</div><div class="card-body">
    <form method="POST">@csrf
        <div class="mb-3"><label class="form-label">Visibility</label><select name="visibility" class="form-select">
            <option value="private" {{ ($project->visibility ?? 'private') === 'private' ? 'selected' : '' }}>Private (only you)</option>
            <option value="team" {{ ($project->visibility ?? '') === 'team' ? 'selected' : '' }}>Team (collaborators only)</option>
            <option value="public" {{ ($project->visibility ?? '') === 'public' ? 'selected' : '' }}>Public (anyone with link)</option>
        </select></div>
        <div class="mb-3"><label class="form-label">Share Link</label>
            <div class="input-group"><input type="text" class="form-control" id="shareLink" value="{{ url('/research/shared/' . ($project->share_token ?? '')) }}" readonly>
                <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('shareLink').value)"><i class="fas fa-copy"></i></button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Update Sharing</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn atom-btn-white">Cancel</a>
    </form>
</div></div>
</div><div class="col-md-4">
<div class="card"><div class="card-header"><h6 class="mb-0">Current Access</h6></div>
    <ul class="list-group list-group-flush">
        @forelse($collaborators ?? [] as $c)
        <li class="list-group-item d-flex justify-content-between"><span>{{ e(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) }}</span><span class="badge bg-secondary">{{ ucfirst($c->role ?? '') }}</span></li>
        @empty
        <li class="list-group-item text-muted small">No collaborators.</li>
        @endforelse
    </ul>
</div>
</div></div>
@endsection