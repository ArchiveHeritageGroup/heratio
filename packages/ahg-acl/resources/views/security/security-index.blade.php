{{-- Security Clearance Index - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/indexSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'User Security Clearances')

@section('content')
<div class="container mt-4">
  <div class="row">
    <div class="col-12">
      <nav aria-label="{{ __('breadcrumb') }}">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
          <li class="breadcrumb-item"><a href="{{ route('acl.security-dashboard') }}">Security</a></li>
          <li class="breadcrumb-item active">Security Clearances</li>
        </ol>
      </nav>

      @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
          {{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
          {{ session('error') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      {{-- Stats Cards --}}
      <div class="row mb-4">
        <div class="col-md-4">
          <div class="card bg-primary text-white">
            <div class="card-body text-center">
              <h2 class="mb-0">{{ $stats['total_users'] ?? 0 }}</h2>
              <small>Total Users</small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card bg-success text-white">
            <div class="card-body text-center">
              <h2 class="mb-0">{{ $stats['with_clearance'] ?? 0 }}</h2>
              <small>With Clearance</small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card bg-danger text-white">
            <div class="card-body text-center">
              <h2 class="mb-0">{{ $stats['top_secret'] ?? 0 }}</h2>
              <small>Secret+ Level</small>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>User Security Clearances</h5>
          <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#bulkGrantModal">
            <i class="fas fa-users me-1"></i> Bulk Grant
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0" id="clearanceTable">
              <thead class="table-light">
                <tr>
                  <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                  <th>{{ __('User') }}</th>
                  <th>{{ __('Clearance Level') }}</th>
                  <th>{{ __('Granted By') }}</th>
                  <th>{{ __('Granted') }}</th>
                  <th>{{ __('Expires') }}</th>
                  <th>{{ __('Actions') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($users ?? [] as $user)
                  @php
                  $levelClass = 'secondary';
                  if ($user->classification_level !== null) {
                      if ($user->classification_level >= 4) $levelClass = 'danger';
                      elseif ($user->classification_level >= 2) $levelClass = 'warning';
                      else $levelClass = 'success';
                  }
                  @endphp
                  <tr>
                    <td>
                      <input type="checkbox" class="form-check-input user-select" value="{{ $user->id }}">
                    </td>
                    <td>
                      <strong>{{ e($user->username) }}</strong>
                      <br><small class="text-muted">{{ e($user->email) }}</small>
                      @if(!($user->active ?? true))
                        <span class="badge bg-secondary">Inactive</span>
                      @endif
                    </td>
                    <td>
                      @if($user->classification_name ?? null)
                        <span class="badge bg-{{ $levelClass }} fs-6">
                          {{ e($user->classification_name) }}
                        </span>
                        <br><small class="text-muted">Level {{ $user->classification_level }}</small>
                      @else
                        <span class="badge bg-secondary">No Clearance</span>
                      @endif
                    </td>
                    <td>
                      {{ $user->granted_by_name ? e($user->granted_by_name) : '-' }}
                    </td>
                    <td>
                      {{ $user->granted_at ? date('M j, Y', strtotime($user->granted_at)) : '-' }}
                    </td>
                    <td>
                      @if($user->expires_at ?? null)
                        @php $isExpired = strtotime($user->expires_at) < time(); @endphp
                        <span class="{{ $isExpired ? 'text-danger' : '' }}">
                          {{ date('M j, Y', strtotime($user->expires_at)) }}
                        </span>
                      @else
                        <span class="text-muted">Never</span>
                      @endif
                    </td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <a href="{{ route('acl.view-classification', ['id' => $user->id]) }}"
                           class="btn btn-outline-primary" title="{{ __('View Details') }}">
                          <i class="fas fa-eye"></i>
                        </a>
                        <button class="btn btn-outline-success"
                                data-bs-toggle="modal"
                                data-bs-target="#grantModal"
                                data-user-id="{{ $user->id }}"
                                data-username="{{ e($user->username) }}"
                                data-current="{{ $user->classification_id ?? 0 }}"
                                title="{{ __('Grant/Change Clearance') }}">
                          <i class="fas fa-key"></i>
                        </button>
                        @if($user->classification_id ?? null)
                          <a href="{{ route('acl.set-clearance') }}"
                             class="btn btn-outline-danger"
                             onclick="event.preventDefault(); if(confirm('Revoke clearance for {{ e($user->username) }}?')) { document.getElementById('revoke-form-{{ $user->id }}').submit(); }"
                             title="{{ __('Revoke Clearance') }}">
                            <i class="fas fa-ban"></i>
                          </a>
                          <form id="revoke-form-{{ $user->id }}" action="{{ route('acl.set-clearance') }}" method="POST" style="display:none;">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                            <input type="hidden" name="classification_id" value="0">
                            <input type="hidden" name="action_type" value="revoke">
                          </form>
                        @endif
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Grant Modal --}}
<div class="modal fade" id="grantModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="{{ route('acl.set-clearance') }}">
        @csrf
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="fas fa-key me-2"></i>Grant Security Clearance</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="user_id" id="grantUserId">
          <p>Granting clearance to: <strong id="grantUsername"></strong></p>

          <div class="mb-3">
            <label for="grantClassification" class="form-label">{{ __('Clearance Level') }}</label>
            <select class="form-select" name="classification_id" id="grantClassification" required>
              <option value="0">-- Revoke Clearance --</option>
              @foreach($classifications ?? [] as $c)
                <option value="{{ $c->id }}">
                  {{ e($c->name) }} (Level {{ $c->level }})
                </option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label for="grantExpires" class="form-label">{{ __('Expires (optional)') }}</label>
            <input type="date" class="form-control" name="expires_at" id="grantExpires">
          </div>

          <div class="mb-3">
            <label for="grantNotes" class="form-label">{{ __('Notes') }}</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="{{ __('Reason for granting clearance...') }}"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-check me-1"></i> Grant Clearance
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Bulk Grant Modal --}}
<div class="modal fade" id="bulkGrantModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="{{ route('acl.set-clearance') }}" id="bulkGrantForm">
        @csrf
        <input type="hidden" name="action_type" value="bulk_grant">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="fas fa-users me-2"></i>Bulk Grant Clearance</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-1"></i>
            Select users in the table, then choose a clearance level to grant to all selected users.
          </div>

          <p><strong id="selectedCount">0</strong> users selected</p>
          <div id="selectedUsersContainer"></div>

          <div class="mb-3">
            <label for="bulkClassification" class="form-label">{{ __('Clearance Level') }}</label>
            <select class="form-select" name="classification_id" id="bulkClassification" required>
              @foreach($classifications ?? [] as $c)
                <option value="{{ $c->id }}">
                  {{ e($c->name) }} (Level {{ $c->level }})
                </option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label for="bulkNotes" class="form-label">{{ __('Notes') }}</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="{{ __('Reason for bulk grant...') }}"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary" id="bulkGrantBtn" disabled>
            <i class="fas fa-check me-1"></i> Grant to Selected
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Grant modal - populate data
  var grantModal = document.getElementById('grantModal');
  grantModal.addEventListener('show.bs.modal', function(event) {
    var button = event.relatedTarget;
    document.getElementById('grantUserId').value = button.dataset.userId;
    document.getElementById('grantUsername').textContent = button.dataset.username;
    document.getElementById('grantClassification').value = button.dataset.current || 0;
  });

  // Select all checkbox
  document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.user-select').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
    updateSelectedCount();
  });

  // Individual checkboxes
  document.querySelectorAll('.user-select').forEach(function(cb) {
    cb.addEventListener('change', updateSelectedCount);
  });

  function updateSelectedCount() {
    var selected = document.querySelectorAll('.user-select:checked');
    document.getElementById('selectedCount').textContent = selected.length;
    document.getElementById('bulkGrantBtn').disabled = selected.length === 0;

    var container = document.getElementById('selectedUsersContainer');
    container.innerHTML = '';
    selected.forEach(function(cb) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'user_ids[]';
      input.value = cb.value;
      container.appendChild(input);
    });
  }
});
</script>
@endsection
