@extends('ahg-theme-b5::layout')

@section('title', 'User Clearance — ' . e($targetUser->authorized_form_of_name ?? $targetUser->username ?? ''))

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.index') }}">Security Clearances</a></li>
    <li class="breadcrumb-item active">{{ e($targetUser->authorized_form_of_name ?? $targetUser->username ?? 'User') }}</li>
  </ol></nav>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  <div class="row">
    {{-- User Info --}}
    <div class="col-md-4">
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-user"></i> User Information</h5></div>
        <div class="card-body">
          <p><strong>Name:</strong> {{ e($targetUser->authorized_form_of_name ?? $targetUser->username ?? '') }}</p>
          <p><strong>Email:</strong> {{ e($targetUser->email ?? '') }}</p>
          <p><strong>Active:</strong>
            @if(($targetUser->active ?? 1))
              <span class="badge bg-success">Yes</span>
            @else
              <span class="badge bg-danger">No</span>
            @endif
          </p>
        </div>
      </div>

      {{-- Current Clearance --}}
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-shield-alt"></i> Current Clearance</h5></div>
        <div class="card-body">
          @if($clearance)
            <p><strong>Level:</strong> <span class="badge" style="background-color: {{ $clearance->color ?? '#666' }}">{{ e($clearance->classification_name ?? 'Unknown') }}</span></p>
            <p><strong>Granted:</strong> {{ $clearance->granted_at ?? '' }}</p>
            <p><strong>Expires:</strong> {{ $clearance->expires_at ?? 'Never' }}</p>
            <p><strong>Granted By:</strong> {{ e($clearance->granted_by_name ?? '') }}</p>
            @if(!empty($clearance->notes))
              <p><strong>Notes:</strong> {{ e($clearance->notes) }}</p>
            @endif
          @else
            <p class="text-muted">No clearance granted.</p>
          @endif
        </div>
      </div>

      {{-- Grant / Update --}}
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-edit"></i> Update Clearance</h5></div>
        <div class="card-body">
          <form method="POST" action="{{ route('security-clearance.grant') }}">
            @csrf
            <input type="hidden" name="user_id" value="{{ $targetUser->id }}">
            <div class="mb-3">
              <label class="form-label">Classification Level</label>
              <select name="classification_id" class="form-select" required>
                <option value="0">— Revoke —</option>
                @foreach($classifications ?? [] as $cl)
                  <option value="{{ $cl->id }}" {{ ($clearance->classification_id ?? 0) == $cl->id ? 'selected' : '' }}
                          style="color: {{ $cl->color ?? '#333' }}">
                    {{ e($cl->name) }} (Level {{ $cl->level }})
                  </option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Expires At</label>
              <input type="date" name="expires_at" class="form-control" value="{{ $clearance->expires_at ?? '' }}">
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Update</button>
          </form>
        </div>
      </div>
    </div>

    {{-- Right column --}}
    <div class="col-md-8">
      {{-- Access Grants --}}
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-key"></i> Object Access Grants</h5></div>
        <div class="card-body table-responsive">
          <table class="table table-sm table-striped">
            <thead><tr><th>Object</th><th>Classification</th><th>Granted</th><th>Expires</th><th>Actions</th></tr></thead>
            <tbody>
              @forelse($accessGrants ?? [] as $grant)
              <tr>
                <td>{{ e($grant->object_title ?? 'Object #' . ($grant->object_id ?? '')) }}</td>
                <td><span class="badge" style="background-color: {{ $grant->color ?? '#666' }}">{{ e($grant->classification_name ?? '') }}</span></td>
                <td>{{ $grant->granted_at ?? '' }}</td>
                <td>{{ $grant->expires_at ?? 'Never' }}</td>
                <td>
                  <form method="POST" action="{{ route('security-clearance.revoke-access', ['id' => $grant->id ?? 0]) }}" class="d-inline"
                        onsubmit="return confirm('Revoke this access grant?')">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $targetUser->id }}">
                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i> Revoke</button>
                  </form>
                </td>
              </tr>
              @empty
              <tr><td colspan="5" class="text-muted">No object access grants.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      {{-- History --}}
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-history"></i> Clearance History</h5></div>
        <div class="card-body table-responsive">
          <table class="table table-sm table-striped">
            <thead><tr><th>Action</th><th>Level</th><th>By</th><th>Notes</th><th>Date</th></tr></thead>
            <tbody>
              @forelse($history ?? [] as $entry)
              <tr>
                <td>
                  <span class="badge bg-{{ ($entry->action ?? '') === 'grant' ? 'success' : (($entry->action ?? '') === 'revoke' ? 'danger' : 'info') }}">
                    {{ ucfirst($entry->action ?? '') }}
                  </span>
                </td>
                <td>{{ e($entry->classification_name ?? '') }}</td>
                <td>{{ e($entry->performed_by_name ?? '') }}</td>
                <td>{{ e($entry->notes ?? '') }}</td>
                <td>{{ $entry->created_at ?? '' }}</td>
              </tr>
              @empty
              <tr><td colspan="5" class="text-muted">No history records.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      {{-- Admin: 2FA --}}
      <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-mobile-alt"></i> Two-Factor Authentication</h5></div>
        <div class="card-body">
          <form method="POST" action="{{ route('security-clearance.remove-2fa', ['id' => $targetUser->id]) }}"
                onsubmit="return confirm('Remove 2FA for this user?')">
            @csrf
            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-times-circle"></i> Remove 2FA</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
