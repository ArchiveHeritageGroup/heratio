{{-- Invite Collaborator - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Invite Collaborator')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.projects') }}">Projects</a></li><li class="breadcrumb-item active">Invite Collaborator</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-user-plus text-primary me-2"></i>Invite Collaborator</h1>
<div class="row"><div class="col-md-8">
<div class="card"><div class="card-body">
    <form method="POST">@csrf
        <input type="hidden" name="project_id" value="{{ $project->id ?? 0 }}">
        <div class="mb-3"><label class="form-label">Email Address <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" required placeholder="researcher@example.com"></div>
        <div class="mb-3"><label class="form-label">Role</label><select name="role" class="form-select"><option value="viewer">Viewer</option><option value="contributor">Contributor</option><option value="editor">Editor</option><option value="admin">Admin</option></select></div>
        <div class="mb-3"><label class="form-label">Message (optional)</label><textarea name="message" class="form-control" rows="3" placeholder="Personal message to include in the invitation..."></textarea></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Send Invitation</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn atom-btn-white">Cancel</a>
    </form>
</div></div>
</div><div class="col-md-4">
<div class="card"><div class="card-header"><h6 class="mb-0">Pending Invitations</h6></div>
    <ul class="list-group list-group-flush">
        @forelse($pendingInvitations ?? [] as $inv)
        <li class="list-group-item d-flex justify-content-between"><span>{{ e($inv->email ?? '') }}<br><small class="text-muted">{{ ucfirst($inv->role ?? '') }}</small></span>
            <form method="POST" class="d-inline">@csrf @method('DELETE') <input type="hidden" name="invitation_id" value="{{ $inv->id }}"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button></form>
        </li>
        @empty
        <li class="list-group-item text-muted small">No pending invitations.</li>
        @endforelse
    </ul>
</div>
</div></div>
@endsection