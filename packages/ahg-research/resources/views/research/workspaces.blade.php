@extends('theme::layouts.2col')

@section('sidebar')
    @include('ahg-research::research._sidebar', ['sidebarActive' => 'workspaces'])
@endsection

@section('title', 'Team Workspaces')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Workspaces</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-users-cog text-primary me-2"></i>Research Workspaces</h1>
    <button type="button" class="btn atom-btn-outline-success" data-bs-toggle="modal" data-bs-target="#createWorkspaceModal">
        <i class="fas fa-plus me-1"></i> New Workspace
    </button>
</div>

@if(!empty($workspaces))
    <div class="row">
        @foreach($workspaces as $ws)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
                        <span class="badge bg-{{ $ws->visibility === 'private' ? 'dark' : ($ws->visibility === 'members' ? 'info' : 'success') }}">
                            <i class="fas fa-{{ $ws->visibility === 'private' ? 'lock' : ($ws->visibility === 'members' ? 'users' : 'globe') }} me-1"></i>
                            {{ ucfirst($ws->visibility) }}
                        </span>
                        @if(($ws->my_role ?? $ws->role ?? '') === 'owner')
                            <i class="fas fa-crown text-warning" title="Owner"></i>
                        @endif
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">
                            <a href="{{ url('/research/viewWorkspace?id=' . $ws->id) }}" class="text-decoration-none">
                                {{ $ws->name }}
                            </a>
                        </h5>
                        @if($ws->description)
                            <p class="card-text text-muted small">{{ Str::limit($ws->description, 100) }}</p>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent d-flex justify-content-between">
                        <small class="text-muted">
                            <i class="fas fa-users me-1"></i>{{ $ws->member_count ?? 1 }} members
                        </small>
                        <small class="text-muted">
                            {{ \Carbon\Carbon::parse($ws->created_at)->format('M j, Y') }}
                        </small>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-users-cog fa-3x text-muted mb-3"></i>
            <h5>No Workspaces Yet</h5>
            <p class="text-muted">Create a private workspace to collaborate with other researchers.</p>
            <button type="button" class="btn atom-btn-outline-success" data-bs-toggle="modal" data-bs-target="#createWorkspaceModal">
                <i class="fas fa-plus me-1"></i> Create Workspace
            </button>
        </div>
    </div>
@endif

<!-- Create Workspace Modal -->
<div class="modal fade" id="createWorkspaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ url('/research/workspaces') }}">
                @csrf
                <input type="hidden" name="form_action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Create Workspace</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g., Thesis Research Group">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Visibility</label>
                        <select name="visibility" class="form-select">
                            <option value="private">Private</option>
                            <option value="members">Members Only</option>
                            <option value="public">Public</option>
                        </select>
                        <small class="text-muted">Private is recommended for research collaboration</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-plus me-1"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
