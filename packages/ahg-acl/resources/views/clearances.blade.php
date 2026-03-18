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
      <i class="fas fa-arrow-left me-1"></i>Back to ACL
    </a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#grantModal">
      <i class="fas fa-plus me-1"></i>Grant New Clearance
    </button>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

@if($errors->any())
  <div class="alert alert-danger alert-dismissible fade show">
    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

{{-- Clearances Table --}}
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped table-hover mb-0">
        <thead>
          <tr>
            <th>User</th>
            <th>Clearance Level</th>
            <th>Granted</th>
            <th>Expires</th>
            <th class="text-center">2FA</th>
            <th class="text-center">Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($clearances as $clr)
            <tr>
              <td>
                <strong>{{ $clr->user_display_name ?? $clr->username ?? '—' }}</strong><br>
                <small class="text-muted">{{ $clr->email ?? '' }}</small>
              </td>
              <td>
                <span class="badge" style="background-color:{{ $clr->classification_color ?? '#6c757d' }};">
                  {{ $clr->classification_name ?? '—' }}
                </span>
                <br><small>Level {{ $clr->classification_level ?? $clr->classification_code ?? '' }}</small>
              </td>
              <td>{{ $clr->granted_at ?? '—' }}</td>
              <td>
                @if($clr->expires_at)
                  @php
                    $daysLeft = \Carbon\Carbon::parse($clr->expires_at)->diffInDays(now(), false) * -1;
                    $expiryClass = $daysLeft <= 7 ? 'text-danger fw-bold' : ($daysLeft <= 30 ? 'text-warning' : '');
                  @endphp
                  <span class="{{ $expiryClass }}">
                    {{ $clr->expires_at }}
                    @if($daysLeft <= 0)
                      <i class="fas fa-exclamation-triangle ms-1" title="Expired"></i>
                    @endif
                  </span>
                @else
                  <span class="text-muted">No expiry</span>
                @endif
              </td>
              <td class="text-center">
                @if($clr->two_factor_verified ?? false)
                  <span class="badge bg-success"><i class="fas fa-check"></i></span>
                @else
                  <span class="badge bg-secondary"><i class="fas fa-times"></i></span>
                @endif
              </td>
              <td class="text-center">
                @if(($clr->renewal_status ?? '') === 'pending')
                  <span class="badge bg-warning">Renewal Pending</span>
                @else
                  <span class="badge bg-success">Active</span>
                @endif
              </td>
              <td class="text-end">
                <form action="{{ route('acl.set-clearance') }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Revoke clearance for {{ $clr->user_display_name ?? $clr->username }}?');">
                  @csrf
                  <input type="hidden" name="user_id" value="{{ $clr->user_id ?? '' }}">
                  <input type="hidden" name="classification_id" value="0">
                  <input type="hidden" name="_revoke" value="1">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Revoke">
                    <i class="fas fa-ban"></i>
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">No user clearances assigned.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Grant Modal --}}
<div class="modal fade" id="grantModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form action="{{ route('acl.set-clearance') }}" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Grant Security Clearance</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">User <span class="text-danger">*</span></label>
              <select name="user_id" class="form-select" required>
                <option value="">-- Select User --</option>
                @foreach($users as $user)
                  <option value="{{ $user->id }}">{{ $user->display_name ?? $user->username }} ({{ $user->email ?? $user->username }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Clearance Level <span class="text-danger">*</span></label>
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
              <label class="form-label">Granted Date</label>
              <input type="date" name="granted_date" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Expiry Date</label>
              <input type="date" name="expiry_date" class="form-control">
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Vetting Reference</label>
              <input type="text" name="vetting_reference" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Vetting Date</label>
              <input type="date" name="vetting_date" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Vetting Authority</label>
              <input type="text" name="vetting_authority" class="form-control">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Grant Clearance</button>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection
