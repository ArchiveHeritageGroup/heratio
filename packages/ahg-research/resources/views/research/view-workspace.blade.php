@extends('theme::layouts.2col')
@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'workspaces'])
@endsection
@section('title', $workspace->name ?? 'Workspace')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
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

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h2">{{ e($workspace->name) }}</h1>
        @if($workspace->description)
            <p class="text-muted">{{ e($workspace->description) }}</p>
        @endif
        <span class="badge bg-{{ $workspace->visibility === 'private' ? 'dark' : ($workspace->visibility === 'members' ? 'info' : 'success') }}">
            <i class="fas fa-{{ $workspace->visibility === 'private' ? 'lock' : ($workspace->visibility === 'members' ? 'users' : 'globe') }} me-1"></i>{{ ucfirst($workspace->visibility) }}
        </span>
    </div>
    <div class="d-flex gap-2">
        @if(in_array($myRole, ['owner', 'admin']))
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editWorkspaceModal" title="{{ __('Edit workspace') }}"><i class="fas fa-pencil-alt"></i></button>
        @endif
        @if($myRole === 'owner')
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this workspace and all its resources, members, and discussions?')">
                @csrf
                <input type="hidden" name="form_action" value="delete_workspace">
                <button class="btn btn-sm btn-outline-danger" title="{{ __('Delete workspace') }}"><i class="fas fa-trash"></i></button>
            </form>
        @endif
    </div>
</div>

{{-- Shared Collections --}}
@if(!empty($sharedCollections))
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>{{ __('Shared Collections') }}</h5></div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>{{ __('Collection') }}</th><th>{{ __('Items') }}</th><th>{{ __('Owner') }}</th></tr></thead>
            <tbody>
            @foreach($sharedCollections as $c)
                <tr>
                    <td><a href="{{ route('research.viewCollection') }}?id={{ $c->id }}">{{ e($c->name ?? '') }}</a></td>
                    <td>{{ (int) ($c->item_count ?? 0) }}</td>
                    <td>{{ e($c->owner_name ?? '') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="row">
    {{-- Left: Discussions + Resources --}}
    <div class="col-md-8">
        {{-- Discussions --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-comments me-2"></i>{{ __('Discussions') }}</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newDiscussionModal">
                    <i class="fas fa-plus me-1"></i> {{ __('New Discussion') }}
                </button>
            </div>
            <div class="card-body p-0">
                @if(!empty($discussions))
                    <div class="list-group list-group-flush">
                        @foreach($discussions as $disc)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ e($disc->subject ?? $disc->title ?? '') }}</h6>
                                        <p class="mb-1 small">{{ e(\Illuminate\Support\Str::limit($disc->content, 150)) }}</p>
                                        <small class="text-muted">
                                            by {{ e($disc->author_name ?? 'Unknown') }} -
                                            {{ date('M j, Y H:i', strtotime($disc->created_at)) }}
                                            @if($disc->reply_count ?? 0)
                                                <span class="badge bg-secondary ms-2">{{ $disc->reply_count }} replies</span>
                                            @endif
                                        </small>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        @if($disc->is_resolved ?? false)
                                            <span class="badge bg-success">{{ __('Resolved') }}</span>
                                        @endif
                                        <button class="btn btn-sm btn-outline-secondary edit-disc-btn"
                                            data-id="{{ (int) $disc->id }}"
                                            data-title="{{ e($disc->subject ?? $disc->title ?? '') }}"
                                            data-content="{{ e($disc->content ?? '') }}"
                                            title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this discussion?');">
                                            @csrf
                                            <input type="hidden" name="form_action" value="delete_discussion">
                                            <input type="hidden" name="discussion_id" value="{{ (int) $disc->id }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-comments fa-2x mb-2"></i>
                        <p>No discussions yet</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Resources --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i>{{ __('Shared Resources') }}</h5>
                @if(in_array($myRole, ['owner', 'admin', 'editor']))
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addResourceModal">
                    <i class="fas fa-plus me-1"></i> {{ __('Add Resource') }}
                </button>
                @endif
            </div>
            <div class="card-body">
                @if(!empty($resources))
                    <div class="row">
                        @foreach($resources as $res)
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3 position-relative">
                                    @if(in_array($myRole, ['owner', 'admin', 'editor']))
                                    <div class="position-absolute top-0 end-0 m-2 d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-secondary edit-resource-btn" title="{{ __('Edit') }}"
                                            data-id="{{ (int) $res->id }}"
                                            data-title="{{ e($res->title ?? '') }}"
                                            data-resource_type="{{ $res->resource_type }}"
                                            data-external_url="{{ e($res->external_url ?? '') }}"
                                            data-description="{{ e($res->description ?? '') }}">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this resource?');">
                                            @csrf
                                            <input type="hidden" name="form_action" value="remove_resource">
                                            <input type="hidden" name="resource_id" value="{{ (int) $res->id }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Remove') }}"><i class="fas fa-times"></i></button>
                                        </form>
                                    </div>
                                    @endif
                                    <span class="badge bg-secondary mb-2">{{ ucfirst(str_replace('_', ' ', $res->resource_type)) }}</span>
                                    <h6 class="mb-1">
                                        @if($res->external_url)
                                            <a href="{{ $res->external_url }}" target="_blank">{{ e($res->title ?: 'Untitled') }} <i class="fas fa-external-link-alt ms-1 small"></i></a>
                                        @else
                                            {{ e($res->title ?: 'Untitled') }}
                                        @endif
                                    </h6>
                                    @if($res->description)
                                        <small class="text-muted">{{ e($res->description) }}</small>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No resources shared yet</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Right: Members + About --}}
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-users me-2"></i>{{ __('Members') }}</h6>
                @if(in_array($myRole, ['owner', 'admin']))
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#inviteModal"><i class="fas fa-user-plus"></i></button>
                @endif
            </div>
            <ul class="list-group list-group-flush">
                @if($owner)
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>{{ e($owner->name) }} <i class="fas fa-crown text-warning ms-1" title="{{ __('Owner') }}"></i></div>
                        <span class="badge bg-warning text-dark">{{ __('Owner') }}</span>
                    </div>
                </li>
                @endif
                @foreach($members as $member)
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>{{ e($member->name) }}</div>
                        <span class="badge bg-{{ $member->status === 'accepted' ? 'success' : 'warning' }}">{{ ucfirst($member->role) }}</span>
                    </div>
                    @if(in_array($myRole, ['owner', 'admin']))
                    <div class="mt-1 d-flex gap-1 justify-content-end">
                        <form method="post" class="d-inline">
                            @csrf
                            <input type="hidden" name="form_action" value="change_role">
                            <input type="hidden" name="member_id" value="{{ (int) $member->id }}">
                            <select name="role" class="form-select form-select-sm d-inline-block" style="width:auto;" data-csp-auto-submit>
                                @foreach(['viewer', 'member', 'editor', 'admin'] as $r)
                                <option value="{{ $r }}" {{ ($member->role === $r) ? 'selected' : '' }}>{{ ucfirst($r) }}</option>
                                @endforeach
                            </select>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this member?');">
                            @csrf
                            <input type="hidden" name="form_action" value="remove_member">
                            <input type="hidden" name="member_id" value="{{ (int) $member->id }}">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Remove') }}"><i class="fas fa-times"></i></button>
                        </form>
                    </div>
                    @endif
                </li>
                @endforeach
                @if(empty($members))
                <li class="list-group-item text-muted">No members yet</li>
                @endif
            </ul>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('About') }}</h6></div>
            <div class="card-body">
                <p class="mb-2"><strong>{{ __('Created:') }}</strong> {{ date('M j, Y', strtotime($workspace->created_at)) }}</p>
                <p class="mb-0"><strong>{{ __('Your role:') }}</strong> {{ ucfirst($myRole) }}</p>
            </div>
        </div>
    </div>
</div>

{{-- New Discussion Modal --}}
<div class="modal fade" id="newDiscussionModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            @csrf
            <input type="hidden" name="form_action" value="create_discussion">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('New Discussion') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">{{ __('Title *') }}</label><input type="text" name="title" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">{{ __('Content *') }}</label><textarea name="content" class="form-control" rows="4" required></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ __('Create Discussion') }}</button></div>
            </div>
        </form>
    </div>
</div>

{{-- Edit Discussion Modal --}}
<div class="modal fade" id="editDiscussionModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            @csrf
            <input type="hidden" name="form_action" value="edit_discussion">
            <input type="hidden" name="discussion_id" id="editDiscId">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('Edit Discussion') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">{{ __('Title *') }}</label><input type="text" name="title" id="editDiscTitle" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">{{ __('Content *') }}</label><textarea name="content" id="editDiscContent" class="form-control" rows="4" required></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button></div>
            </div>
        </form>
    </div>
</div>

{{-- Edit Workspace Modal --}}
<div class="modal fade" id="editWorkspaceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            @csrf
            <input type="hidden" name="form_action" value="edit_workspace">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('Edit Workspace') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">{{ __('Name *') }}</label><input type="text" name="name" class="form-control" value="{{ e($workspace->name) }}" required></div>
                    <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea name="description" class="form-control" rows="3">{{ e($workspace->description ?? '') }}</textarea></div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Visibility') }}</label>
                        <select name="visibility" class="form-select">
                            <option value="private" {{ ($workspace->visibility ?? '') === 'private' ? 'selected' : '' }}>Private</option>
                            <option value="members" {{ ($workspace->visibility ?? '') === 'members' ? 'selected' : '' }}>Members Only</option>
                            <option value="public" {{ ($workspace->visibility ?? '') === 'public' ? 'selected' : '' }}>Public</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button></div>
            </div>
        </form>
    </div>
</div>

{{-- Invite Modal --}}
@push('css')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush
<div class="modal fade" id="inviteModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            @csrf
            <input type="hidden" name="form_action" value="invite">
            <input type="hidden" name="email" id="inviteEmailHidden" value="">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('Invite Member') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Search Researcher *') }}</label>
                        <select id="inviteResearcherSearch"></select>
                        <small class="text-muted">{{ __('Type name or email of a registered researcher') }}</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Role') }}</label>
                        <select name="role" class="form-select">
                            <option value="member">{{ __('Member - Can view and comment') }}</option>
                            <option value="editor">{{ __('Editor - Can add resources') }}</option>
                            <option value="admin">{{ __('Admin - Can manage members') }}</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ __('Send Invitation') }}</button></div>
            </div>
        </form>
    </div>
</div>

{{-- Add Resource Modal --}}
<div class="modal fade" id="addResourceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            @csrf
            <input type="hidden" name="form_action" value="add_resource">
            <input type="hidden" name="resource_id" id="resourceIdHidden" value="">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('Add Resource') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Resource Type *') }}</label>
                        <select name="resource_type" id="resourceTypeSelect" class="form-select" required>
                            <option value="collection">{{ __('Collection') }}</option>
                            <option value="saved_search">{{ __('Saved Search') }}</option>
                            <option value="bibliography">{{ __('Bibliography') }}</option>
                            <option value="object">{{ __('Archive Object') }}</option>
                            <option value="external_link">{{ __('External Link') }}</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">{{ __('Title *') }}</label><input type="text" name="title" id="resourceTitleInput" class="form-control" required></div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('External URL') }}</label>
                        <input type="url" name="external_url" id="externalUrlInput" class="form-control" placeholder="{{ __('https://') }}">
                    </div>
                    <div class="mb-3" id="resourceSearchGroup">
                        <label class="form-label">{{ __('Or search existing resource') }}</label>
                        <select id="resourceSearch"></select>
                    </div>
                    <div class="mb-3"><label class="form-label">{{ __('Notes') }}</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ __('Add Resource') }}</button></div>
            </div>
        </form>
    </div>
</div>

{{-- Edit Resource Modal --}}
<div class="modal fade" id="editResourceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post">
            @csrf
            <input type="hidden" name="form_action" value="edit_resource">
            <input type="hidden" name="resource_id" id="editResId">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('Edit Resource') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">{{ __('Title *') }}</label><input type="text" name="title" id="editResTitle" class="form-control" required></div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Type') }}</label>
                        <select name="resource_type" id="editResType" class="form-select">
                            <option value="collection">{{ __('Collection') }}</option>
                            <option value="saved_search">{{ __('Saved Search') }}</option>
                            <option value="bibliography">{{ __('Bibliography') }}</option>
                            <option value="object">{{ __('Archive Object') }}</option>
                            <option value="external_link">{{ __('External Link') }}</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">{{ __('URL (for external links)') }}</label><input type="url" name="external_url" id="editResUrl" class="form-control" placeholder="{{ __('https://') }}"></div>
                    <div class="mb-3"><label class="form-label">{{ __('Notes') }}</label><textarea name="notes" id="editResNotes" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button></div>
            </div>
        </form>
    </div>
</div>

@push('js')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Invite researcher TomSelect
    new TomSelect('#inviteResearcherSearch', {
        valueField: 'email', labelField: 'name', searchField: ['name', 'email'],
        placeholder: 'Search by name or email...', loadThrottle: 300,
        load: function(query, callback) {
            if (query.length < 2) return callback();
            fetch('/research/researcher-autocomplete?query=' + encodeURIComponent(query))
                .then(function(r) { return r.json(); })
                .then(function(data) { callback(data); })
                .catch(function() { callback(); });
        },
        render: {
            option: function(item) { return '<div><strong>' + item.name + '</strong><br><small class="text-muted">' + (item.email || '') + '</small></div>'; },
            item: function(item) { return '<div>' + item.name + ' <small>(' + (item.email || '') + ')</small></div>'; }
        },
        onChange: function(value) { document.getElementById('inviteEmailHidden').value = value; }
    });

    // Validate invite form
    document.querySelector('#inviteModal form').addEventListener('submit', function(e) {
        if (!document.getElementById('inviteEmailHidden').value) {
            e.preventDefault();
            alert('Please select a researcher first.');
        }
    });

    // Resource search TomSelect
    var resourceSelect = new TomSelect('#resourceSearch', {
        valueField: 'id', labelField: 'name', searchField: ['name'],
        placeholder: 'Type to search...', loadThrottle: 300,
        load: function(query, callback) {
            if (query.length < 2) return callback();
            var resType = document.getElementById('resourceTypeSelect').value;
            var searchType = {collection: 'collection', saved_search: 'project', bibliography: 'project', object: 'archival_description'}[resType] || 'archival_description';
            fetch('/research/target-autocomplete?type=' + encodeURIComponent(searchType) + '&query=' + encodeURIComponent(query))
                .then(function(r) { return r.json(); })
                .then(function(data) { callback(data); })
                .catch(function() { callback(); });
        },
        render: {
            option: function(item) { return '<div><strong>' + (item.name || '[Untitled]') + '</strong> <small class="text-muted">#' + item.id + '</small></div>'; },
            item: function(item) { return '<div>' + (item.name || '[Untitled]') + '</div>'; }
        },
        onChange: function(value) {
            document.getElementById('resourceIdHidden').value = value;
            var sel = this.options[value];
            if (sel && sel.name) document.getElementById('resourceTitleInput').value = sel.name;
        }
    });

    document.getElementById('resourceTypeSelect').addEventListener('change', function() {
        resourceSelect.clear(); resourceSelect.clearOptions();
    });

    // Edit discussion
    document.querySelectorAll('.edit-disc-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('editDiscId').value = this.dataset.id;
            document.getElementById('editDiscTitle').value = this.dataset.title;
            document.getElementById('editDiscContent').value = this.dataset.content;
            new bootstrap.Modal(document.getElementById('editDiscussionModal')).show();
        });
    });

    // Edit resource
    document.querySelectorAll('.edit-resource-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('editResId').value = this.dataset.id;
            document.getElementById('editResTitle').value = this.dataset.title;
            document.getElementById('editResType').value = this.dataset.resource_type;
            document.getElementById('editResUrl').value = this.dataset.external_url;
            document.getElementById('editResNotes').value = this.dataset.description;
            new bootstrap.Modal(document.getElementById('editResourceModal')).show();
        });
    });
});
</script>
@endpush
@endsection
