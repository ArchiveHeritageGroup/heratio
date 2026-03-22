{{-- Project Collaborators - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Project Collaborators')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.projects') }}">Projects</a></li><li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li><li class="breadcrumb-item active">Collaborators</li></ol></nav>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-users text-primary me-2"></i>Collaborators</h1>
    <a href="{{ route('research.dashboard', ['invite_collaborator' => $project->id ?? 0]) }}" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i>Invite</a>
</div>
<div class="card">
    <div class="card-body p-0">
        @if(!empty($collaborators))
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach($collaborators as $c)
                <tr>
                    <td><strong>{{ e(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) }}</strong></td>
                    <td>{{ e($c->email ?? '') }}</td>
                    <td><span class="badge bg-{{ $c->role === 'admin' ? 'danger' : ($c->role === 'editor' ? 'warning' : 'secondary') }}">{{ ucfirst($c->role ?? 'viewer') }}</span></td>
                    <td class="small">{{ $c->joined_at ?? '' }}</td>
                    <td><span class="badge bg-{{ ($c->status ?? '') === 'active' ? 'success' : 'warning' }}">{{ ucfirst($c->status ?? 'pending') }}</span></td>
                    <td>
                        @if(($c->researcher_id ?? 0) != ($researcher->id ?? -1))
                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove this collaborator?')">@csrf @method('DELETE') <input type="hidden" name="collaborator_id" value="{{ $c->id }}"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button></form>
                        @else
                        <span class="badge bg-primary">Owner</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="text-center py-4 text-muted">No collaborators yet.</div>
        @endif
    </div>
</div>
@endsection