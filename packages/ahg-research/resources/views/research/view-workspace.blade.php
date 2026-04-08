@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'workspaces'])
@endsection

@section('title', $workspace->name ?? 'Workspace')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.workspaces') }}">Workspaces</a></li>
        <li class="breadcrumb-item active">{{ e($workspace->name) }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1"><i class="fas fa-users-cog text-primary me-2"></i>{{ e($workspace->name) }}</h1>
        @if($workspace->description)
            <p class="text-muted mb-0">{{ e($workspace->description) }}</p>
        @endif
    </div>
    <div>
        <span class="badge bg-{{ $workspace->visibility === 'private' ? 'dark' : ($workspace->visibility === 'members' ? 'info' : 'success') }} me-2">
            <i class="fas fa-{{ $workspace->visibility === 'private' ? 'lock' : ($workspace->visibility === 'members' ? 'users' : 'globe') }} me-1"></i>{{ ucfirst($workspace->visibility) }}
        </span>
        <a href="{{ route('research.workspaces') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="row">
    {{-- Members --}}
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
                <span><i class="fas fa-users me-2"></i>Members</span>
                @if(in_array($myRole, ['owner', 'admin']))
                    <button class="btn btn-sm btn-light py-0" data-bs-toggle="modal" data-bs-target="#inviteMemberModal"><i class="fas fa-user-plus"></i></button>
                @endif
            </div>
            <ul class="list-group list-group-flush">
                @if($owner)
                <li class="list-group-item py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-crown text-warning me-1"></i>
                            <strong>{{ e($owner->name) }}</strong>
                            <br><small class="text-muted">{{ e($owner->institution ?? $owner->email) }}</small>
                        </div>
                        <span class="badge bg-warning text-dark">Owner</span>
                    </div>
                </li>
                @endif
                @foreach($members as $m)
                <li class="list-group-item py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-user me-1 text-muted"></i>
                            <strong>{{ e($m->name) }}</strong>
                            <br><small class="text-muted">{{ e($m->institution ?? $m->email) }}</small>
                        </div>
                        <div>
                            <span class="badge bg-secondary">{{ ucfirst($m->role) }}</span>
                            @if($myRole === 'owner')
                            <form method="POST" class="d-inline ms-1" onsubmit="return confirm('Remove this member?')">
                                @csrf
                                <input type="hidden" name="form_action" value="remove_member">
                                <input type="hidden" name="member_id" value="{{ $m->id }}">
                                <button class="btn btn-sm btn-outline-danger py-0 px-1"><i class="fas fa-times"></i></button>
                            </form>
                            @endif
                        </div>
                    </div>
                </li>
                @endforeach
                @if(empty($members))
                <li class="list-group-item text-muted small py-3 text-center">No additional members yet</li>
                @endif
            </ul>
        </div>
    </div>

    {{-- Resources --}}
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center py-2">
                <span><i class="fas fa-folder-open me-2"></i>Resources</span>
                @if(in_array($myRole, ['owner', 'admin', 'editor']))
                    <button class="btn btn-sm btn-light py-0" data-bs-toggle="modal" data-bs-target="#addResourceModal"><i class="fas fa-plus"></i></button>
                @endif
            </div>
            @if(!empty($resources))
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Added</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($resources as $res)
                        <tr>
                            <td>
                                @if($res->external_url)
                                    <a href="{{ $res->external_url }}" target="_blank">{{ e($res->title ?: 'Untitled') }} <i class="fas fa-external-link-alt ms-1 small"></i></a>
                                @elseif($res->resource_id)
                                    <a href="{{ url('/informationobject/browse?id=' . $res->resource_id) }}">{{ e($res->title ?: '#' . $res->resource_id) }}</a>
                                @else
                                    {{ e($res->title ?: 'Untitled') }}
                                @endif
                            </td>
                            <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $res->resource_type)) }}</span></td>
                            <td><small class="text-muted">{{ e(\Illuminate\Support\Str::limit($res->description ?? '', 60)) }}</small></td>
                            <td><small>{{ $res->added_at ? \Carbon\Carbon::parse($res->added_at)->format('M j, Y') : '' }}</small></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="card-body text-center text-muted py-4">
                <i class="fas fa-folder-open fa-2x mb-2 opacity-50"></i>
                <p class="mb-2">No resources yet</p>
                @if(in_array($myRole, ['owner', 'admin', 'editor']))
                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addResourceModal">Add first resource</button>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Invite Member Modal --}}
<div class="modal fade" id="inviteMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                @csrf
                <input type="hidden" name="form_action" value="invite">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Researcher Email *</label>
                        <input type="email" name="email" class="form-control" required placeholder="researcher@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="viewer">Viewer</option>
                            <option value="editor">Editor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i>Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add Resource Modal --}}
<div class="modal fade" id="addResourceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                @csrf
                <input type="hidden" name="form_action" value="add_resource">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="resource_type" class="form-select">
                            <option value="link">External Link</option>
                            <option value="archival_description">Archival Description</option>
                            <option value="document">Document</option>
                            <option value="note">Note</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL (for external links)</label>
                        <input type="url" name="external_url" class="form-control" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-plus me-1"></i>Add</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
