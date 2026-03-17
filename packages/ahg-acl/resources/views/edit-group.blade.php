@extends('ahg-theme-b5::layouts.app')

@section('title', 'Edit Group — ' . ($group->name ?? 'Unnamed'))

@section('content')
<div class="container-fluid py-4">

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Admin</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.groups') }}">ACL Groups</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $group->name ?? 'Unnamed' }}</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-users-cog me-2"></i> {{ $group->name ?? 'Unnamed' }}</h2>
    <a href="{{ route('acl.groups') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i> Back to Groups
    </a>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if($group->description)
    <p class="text-muted">{{ $group->description }}</p>
  @endif

  <div class="row">
    {{-- Members Section --}}
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-users me-2"></i> Members ({{ $group->members->count() }})</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>User</th>
                  <th>Username</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($group->members as $member)
                  <tr>
                    <td>{{ $member->display_name ?? $member->username }}</td>
                    <td><code>{{ $member->username }}</code></td>
                    <td class="text-end">
                      <form action="{{ route('acl.remove-member', ['groupId' => $group->id, 'userId' => $member->user_id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this member from the group?');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove member">
                          <i class="fas fa-user-minus"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-muted py-3">No members in this group.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer">
          <form action="{{ route('acl.add-member', ['groupId' => $group->id]) }}" method="POST" class="row g-2 align-items-end">
            @csrf
            <div class="col">
              <label for="user_id" class="form-label form-label-sm">Add Member</label>
              <select name="user_id" id="user_id" class="form-select form-select-sm" required>
                <option value="">-- Select User --</option>
                @foreach($allUsers as $user)
                  <option value="{{ $user->id }}">{{ $user->display_name ?? $user->username }} ({{ $user->username }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-auto">
              <button type="submit" class="btn btn-sm btn-success">
                <i class="fas fa-user-plus me-1"></i> Add
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- Permissions Section --}}
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-lock me-2"></i> Permissions ({{ $group->permissions->count() }})</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Action</th>
                  <th>Object ID</th>
                  <th class="text-center">Grant / Deny</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($group->permissions as $perm)
                  <tr>
                    <td><code>{{ $perm->action }}</code></td>
                    <td>{{ $perm->object_id ?? '<em class="text-muted">All</em>' }}</td>
                    <td class="text-center">
                      @if($perm->grant_deny == 1)
                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Grant</span>
                      @else
                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Deny</span>
                      @endif
                    </td>
                    <td class="text-end">
                      <form action="{{ route('acl.edit-group', ['id' => $group->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this permission?');">
                        @csrf
                        <input type="hidden" name="_action" value="delete_permission">
                        <input type="hidden" name="permission_id" value="{{ $perm->id }}">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove permission">
                          <i class="fas fa-trash-alt"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="text-center text-muted py-3">No permissions configured.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer">
          <form action="{{ route('acl.edit-group', ['id' => $group->id]) }}" method="POST" class="row g-2 align-items-end">
            @csrf
            <input type="hidden" name="_action" value="add_permission">
            <div class="col">
              <label for="perm_action" class="form-label form-label-sm">Action</label>
              <input type="text" name="action" id="perm_action" class="form-control form-control-sm" placeholder="e.g. read, create, update, delete" required>
            </div>
            <div class="col-3">
              <label for="perm_object_id" class="form-label form-label-sm">Object ID</label>
              <input type="number" name="object_id" id="perm_object_id" class="form-control form-control-sm" placeholder="All">
            </div>
            <div class="col-3">
              <label for="perm_grant_deny" class="form-label form-label-sm">Grant/Deny</label>
              <select name="grant_deny" id="perm_grant_deny" class="form-select form-select-sm" required>
                <option value="1">Grant</option>
                <option value="0">Deny</option>
              </select>
            </div>
            <div class="col-auto">
              <button type="submit" class="btn btn-sm btn-success">
                <i class="fas fa-plus me-1"></i> Add
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
