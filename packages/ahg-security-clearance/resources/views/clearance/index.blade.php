@extends('ahg-theme-b5::layout')

@section('title', 'Security Clearances')

@section('content')
<div class="container-fluid mt-3">
  <h1><i class="fas fa-user-shield"></i> Security Clearances</h1>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  {{-- Stats --}}
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card bg-primary text-white"><div class="card-body"><h5>Total Users</h5><h2>{{ $stats['total_users'] ?? 0 }}</h2></div></div>
    </div>
    <div class="col-md-4">
      <div class="card bg-success text-white"><div class="card-body"><h5>With Clearance</h5><h2>{{ $stats['with_clearance'] ?? 0 }}</h2></div></div>
    </div>
    <div class="col-md-4">
      <div class="card bg-danger text-white"><div class="card-body"><h5>Top Secret+</h5><h2>{{ $stats['top_secret'] ?? 0 }}</h2></div></div>
    </div>
  </div>

  {{-- Grant Clearance Modal --}}
  <div class="modal fade" id="grantModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="POST" action="{{ route('security-clearance.grant') }}">
        @csrf
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">Grant Clearance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <input type="hidden" name="user_id" id="grantUserId">
            <div class="mb-3">
              <label class="form-label">User</label>
              <input type="text" class="form-control" id="grantUserName" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Classification Level</label>
              <select name="classification_id" class="form-select" required>
                <option value="0">— Revoke —</option>
                @foreach($classifications ?? [] as $cl)
                  <option value="{{ $cl->id }}" style="color: {{ $cl->color ?? '#333' }}">{{ e($cl->name) }} (Level {{ $cl->level }})</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Expires At</label>
              <input type="date" name="expires_at" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Grant</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- Bulk Grant --}}
  <div class="mb-3">
    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#bulkGrantForm">
      <i class="fas fa-users-cog"></i> Bulk Grant
    </button>
    <a href="{{ route('security-clearance.dashboard') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
  </div>

  <div class="collapse mb-3" id="bulkGrantForm">
    <div class="card card-body">
      <form method="POST" action="{{ route('security-clearance.bulk-grant') }}">
        @csrf
        <div class="row">
          <div class="col-md-4">
            <label class="form-label">Classification Level</label>
            <select name="classification_id" class="form-select" required>
              @foreach($classifications ?? [] as $cl)
                <option value="{{ $cl->id }}">{{ e($cl->name) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Notes</label>
            <input type="text" name="notes" class="form-control" value="Bulk grant by administrator">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary">Apply to Selected</button>
          </div>
        </div>
        <div id="bulkUserIds"></div>
      </form>
    </div>
  </div>

  {{-- Users Table --}}
  <div class="card">
    <div class="card-body table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th><input type="checkbox" id="selectAll"></th>
            <th>User</th>
            <th>Email</th>
            <th>Clearance</th>
            <th>Granted By</th>
            <th>Expires</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($users ?? [] as $user)
          <tr>
            <td><input type="checkbox" class="bulk-check" value="{{ $user->id }}"></td>
            <td>
              <a href="{{ route('security-clearance.view', ['id' => $user->id]) }}">
                {{ e($user->authorized_form_of_name ?? $user->username ?? 'User #' . $user->id) }}
              </a>
            </td>
            <td>{{ e($user->email ?? '') }}</td>
            <td>
              @if(!empty($user->clearance_name))
                <span class="badge" style="background-color: {{ $user->clearance_color ?? '#666' }}">{{ e($user->clearance_name) }}</span>
              @else
                <span class="badge bg-secondary">None</span>
              @endif
            </td>
            <td>{{ e($user->granted_by_name ?? '') }}</td>
            <td>{{ $user->expires_at ?? '—' }}</td>
            <td>
              <button class="btn btn-sm btn-outline-primary grant-btn"
                      data-user-id="{{ $user->id }}"
                      data-user-name="{{ e($user->authorized_form_of_name ?? $user->username ?? '') }}"
                      data-bs-toggle="modal" data-bs-target="#grantModal">
                <i class="fas fa-edit"></i>
              </button>
              @if(!empty($user->clearance_name))
              <form method="POST" action="{{ route('security-clearance.revoke', ['id' => $user->id]) }}" class="d-inline"
                    onsubmit="return confirm('Revoke clearance for this user?')">
                @csrf
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-ban"></i></button>
              </form>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="7" class="text-muted">No users found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.grant-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('grantUserId').value = this.dataset.userId;
    document.getElementById('grantUserName').value = this.dataset.userName;
  });
});
document.getElementById('selectAll')?.addEventListener('change', function() {
  document.querySelectorAll('.bulk-check').forEach(cb => cb.checked = this.checked);
});
document.querySelector('#bulkGrantForm form')?.addEventListener('submit', function(e) {
  const container = document.getElementById('bulkUserIds');
  container.innerHTML = '';
  document.querySelectorAll('.bulk-check:checked').forEach(cb => {
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = 'user_ids[]'; input.value = cb.value;
    container.appendChild(input);
  });
  if (!container.children.length) { e.preventDefault(); alert('Select at least one user.'); }
});
</script>
@endpush
@endsection
