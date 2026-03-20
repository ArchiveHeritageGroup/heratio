@extends('theme::layouts.1col')

@section('title', 'ACL Groups')

@section('content')
<div class="container-fluid py-4">

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Admin</a></li>
      <li class="breadcrumb-item active" aria-current="page">ACL Groups</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-users-cog me-2"></i> ACL Groups</h2>
    <div>
      <a href="{{ route('acl.classifications') }}" class="btn btn-outline-secondary me-1">
        <i class="fas fa-shield-alt me-1"></i> Classifications
      </a>
      <a href="{{ route('acl.clearances') }}" class="btn btn-outline-secondary me-1">
        <i class="fas fa-id-badge me-1"></i> Clearances
      </a>
      <a href="{{ route('acl.access-requests') }}" class="btn btn-outline-secondary me-1">
        <i class="fas fa-key me-1"></i> Access Requests
      </a>
      <a href="{{ route('acl.audit-log') }}" class="btn btn-outline-secondary">
        <i class="fas fa-clipboard-list me-1"></i> Audit Log
      </a>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i> Groups</h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th>Description</th>
              <th class="text-center">Members</th>
              <th class="text-center">Permissions</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($groups as $group)
              <tr>
                <td><strong>{{ $group->name ?? 'Unnamed' }}</strong></td>
                <td>{{ \Illuminate\Support\Str::limit($group->description ?? '', 80) }}</td>
                <td class="text-center">
                  <span class="badge bg-primary">{{ $group->member_count }}</span>
                </td>
                <td class="text-center">
                  <span class="badge bg-secondary">{{ $group->permissions_count ?? 0 }}</span>
                </td>
                <td class="text-end">
                  <a href="{{ route('acl.edit-group', ['id' => $group->id]) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-pencil-alt me-1"></i> Edit
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted py-4">No ACL groups found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
