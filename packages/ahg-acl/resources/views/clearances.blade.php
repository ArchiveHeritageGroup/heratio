@extends('theme::layouts.1col')

@section('title', 'User Security Clearances')

@section('content')
<h1><i class="fas fa-users me-2"></i>User Security Clearances</h1>

<div class="row mb-4">
  <div class="col-md-8">
    <p class="text-muted">Manage security clearances for all users.</p>
  </div>
  <div class="col-md-4 text-end">
    <a href="{{ route('acl.groups') }}" class="btn btn-outline-secondary me-1">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to ACL') }}
    </a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#grantModal">
      <i class="fas fa-plus me-1"></i>{{ __('Grant New Clearance') }}
    </button>
  </div>
</div>

{{-- Stats cards (matches AtoM) --}}
<div class="row mb-4">
  <div class="col-md-4">
    <div class="card bg-primary text-white">
      <div class="card-body text-center">
        <h2 class="mb-0">{{ $stats['total_users'] }}</h2>
        <small>{{ __('Total Users') }}</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card bg-success text-white">
      <div class="card-body text-center">
        <h2 class="mb-0">{{ $stats['with_clearance'] }}</h2>
        <small>{{ __('With Clearance') }}</small>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card bg-danger text-white">
      <div class="card-body text-center">
        <h2 class="mb-0">{{ $stats['top_secret'] }}</h2>
        <small>{{ __('Secret+ Level') }}</small>
      </div>
    </div>
  </div>
</div>

@if($errors->any())
  <div class="alert alert-danger alert-dismissible fade show">
    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

{{-- Clearances Table --}}
<div class="card">
  <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
    <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>User Security Clearances</h5>
    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#bulkGrantModal">
      <i class="fas fa-users me-1"></i> {{ __('Bulk Grant') }}
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
            <th class="text-center">{{ __('2FA') }}</th>
            <th class="text-center">{{ __('Status') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $clr)
            <tr>
              <td>
                <input type="checkbox" class="form-check-input user-select" value="{{ $clr->user_id }}">
              </td>
              <td>
                <strong>{{ $clr->user_display_name ?? $clr->username ?? '—' }}</strong><br>
                <small class="text-muted">{{ $clr->email ?? '' }}</small>
                @if(!$clr->active)
                  <span class="badge bg-secondary ms-1">{{ __('Inactive') }}</span>
                @endif
              </td>
              <td>
                @if($clr->classification_name)
                  <span class="badge fs-6" style="background-color:{{ $clr->classification_color ?? '#6c757d' }};">
                    {{ $clr->classification_name }}
                  </span>
                  <br><small class="text-muted">Level {{ $clr->classification_level ?? '' }}</small>
                @else
                  <span class="badge bg-secondary">{{ __('No Clearance') }}</span>
                @endif
              </td>
              <td>{{ $clr->granted_by_name ?? '—' }}</td>
              <td>{{ $clr->granted_at ? \Carbon\Carbon::parse($clr->granted_at)->format('M j, Y') : '—' }}</td>
              <td>
                @if($clr->expires_at)
                  @php
                    $daysLeft = \Carbon\Carbon::parse($clr->expires_at)->diffInDays(now(), false) * -1;
                    $expiryClass = $daysLeft <= 7 ? 'text-danger fw-bold' : ($daysLeft <= 30 ? 'text-warning' : '');
                  @endphp
                  <span class="{{ $expiryClass }}">
                    {{ \Carbon\Carbon::parse($clr->expires_at)->format('M j, Y') }}
                    @if($daysLeft <= 0)
                      <i class="fas fa-exclamation-triangle ms-1" title="{{ __('Expired') }}"></i>
                    @endif
                  </span>
                @else
                  <span class="text-muted">{{ __('Never') }}</span>
                @endif
              </td>
              <td class="text-center">
                @if($clr->two_factor_verified)
                  <span class="badge bg-success"><i class="fas fa-check"></i></span>
                @else
                  <span class="badge bg-secondary"><i class="fas fa-times"></i></span>
                @endif
              </td>
              <td class="text-center">
                @if(!$clr->classification_id)
                  <span class="badge bg-secondary">—</span>
                @elseif(($clr->renewal_status ?? '') === 'pending')
                  <span class="badge bg-warning">{{ __('Renewal Pending') }}</span>
                @else
                  <span class="badge bg-success">{{ __('Active') }}</span>
                @endif
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a href="{{ route('security-clearance.user', $clr->username) }}" class="btn btn-outline-primary" title="{{ __('View Details') }}">
                    <i class="fas fa-eye"></i>
                  </a>
                  <button type="button" class="btn btn-outline-success btn-row-grant"
                          data-bs-toggle="modal" data-bs-target="#grantModal"
                          data-user-id="{{ $clr->user_id }}"
                          data-username="{{ $clr->user_display_name ?? $clr->username }}"
                          data-current="{{ $clr->classification_id ?? 0 }}"
                          title="{{ __('Grant/Change Clearance') }}">
                    <i class="fas fa-key"></i>
                  </button>
                  @if($clr->classification_id)
                    <form action="{{ route('acl.set-clearance') }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Revoke clearance for {{ $clr->user_display_name ?? $clr->username }}?');">
                      @csrf
                      <input type="hidden" name="user_id" value="{{ $clr->user_id }}">
                      <input type="hidden" name="classification_id" value="0">
                      <input type="hidden" name="_revoke" value="1">
                      <button type="submit" class="btn btn-outline-danger" title="{{ __('Revoke Clearance') }}">
                        <i class="fas fa-ban"></i>
                      </button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-center text-muted py-4">No users found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Bulk Grant Modal --}}
<div class="modal fade" id="bulkGrantModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="{{ route('acl.bulk-grant-clearance') }}" id="bulkGrantForm">
        @csrf
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
              @foreach($classifications as $c)
                <option value="{{ $c->id }}">{{ $c->name }} (Level {{ $c->level ?? '' }})</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Notes') }}</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="{{ __('Reason for bulk grant...') }}"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary" id="bulkGrantBtn" disabled>
            <i class="fas fa-check me-1"></i> {{ __('Grant to Selected') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Per-row Grant button — pre-populates the existing grantModal user/classification
  document.getElementById('grantModal').addEventListener('show.bs.modal', function(event) {
    var btn = event.relatedTarget;
    if (!btn || !btn.classList.contains('btn-row-grant')) return;
    var userSelect = this.querySelector('select[name="user_id"]');
    var classSelect = this.querySelector('select[name="classification_id"]');
    if (userSelect) userSelect.value = btn.dataset.userId;
    if (classSelect) classSelect.value = btn.dataset.current || '';
  });

  // Select all
  var selectAll = document.getElementById('selectAll');
  if (selectAll) {
    selectAll.addEventListener('change', function() {
      document.querySelectorAll('.user-select').forEach(function(cb) { cb.checked = selectAll.checked; });
      updateSelectedCount();
    });
  }
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

{{-- Grant Modal --}}
<div class="modal fade" id="grantModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form action="{{ route('acl.set-clearance') }}" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ __('Grant Security Clearance') }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">User <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <select name="user_id" class="form-select" required>
                <option value="">-- Select User --</option>
                @foreach($users as $user)
                  <option value="{{ $user->id }}">{{ $user->display_name ?? $user->username }} ({{ $user->email ?? $user->username }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Clearance Level <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <select name="classification_id" class="form-select" required>
                <option value="">-- Select Level --</option>
                @foreach($classifications as $cls)
                  <option value="{{ $cls->id }}">{{ $cls->name }} ({{ $cls->code ?? '' }}, level {{ $cls->level ?? '' }})</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Granted Date <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="date" name="granted_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Expiry Date <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="date" name="expiry_date" class="form-control">
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Vetting Reference <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="text" name="vetting_reference" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Vetting Date <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="date" name="vetting_date" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Vetting Authority <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="text" name="vetting_authority" class="form-control">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Grant Clearance') }}</button>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
