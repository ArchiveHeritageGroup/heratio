@extends('theme::layouts.1col')

@section('title', 'Security Access Requests')

@section('content')
<div class="container py-4">

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Admin</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.groups') }}">ACL</a></li>
      <li class="breadcrumb-item active" aria-current="page">Access Requests</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-key me-2"></i> Security Access Requests</h2>
    <a href="{{ route('acl.groups') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i> Back to ACL
    </a>
  </div>

  {{-- Status filter tabs --}}
  <ul class="nav nav-tabs mb-4">
    <li class="nav-item">
      <a class="nav-link {{ $status === 'pending' ? 'active' : '' }}" href="{{ route('acl.access-requests', ['status' => 'pending']) }}">
        <i class="fas fa-clock me-1"></i> Pending
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $status === 'approved' ? 'active' : '' }}" href="{{ route('acl.access-requests', ['status' => 'approved']) }}">
        <i class="fas fa-check me-1"></i> Approved
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $status === 'denied' ? 'active' : '' }}" href="{{ route('acl.access-requests', ['status' => 'denied']) }}">
        <i class="fas fa-times me-1"></i> Denied
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $status === null || $status === '' ? 'active' : '' }}" href="{{ route('acl.access-requests', ['status' => '']) }}">
        <i class="fas fa-list me-1"></i> All
      </a>
    </li>
  </ul>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover mb-0">
          <thead>
            <tr>
              <th>User</th>
              <th>Classification</th>
              <th>Request Type</th>
              <th>Justification</th>
              <th class="text-center">Priority</th>
              <th class="text-center">Status</th>
              <th>Requested</th>
              @if($status === 'pending')
                <th class="text-end">Actions</th>
              @endif
            </tr>
          </thead>
          <tbody>
            @forelse($requests as $req)
              <tr>
                <td><strong>{{ $req->user_name ?? $req->username ?? '—' }}</strong></td>
                <td>
                  @if($req->classification_name)
                    <span class="badge" style="background-color:{{ $req->classification_color ?? '#6c757d' }};">
                      {{ $req->classification_name }}
                    </span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>{{ $req->request_type ?? '—' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($req->justification ?? '', 60) }}</td>
                <td class="text-center">
                  @php
                    $priority = strtolower($req->priority ?? 'normal');
                    $priorityClass = match($priority) {
                      'urgent', 'critical' => 'bg-danger',
                      'high' => 'bg-warning text-dark',
                      'low' => 'bg-info text-dark',
                      default => 'bg-secondary',
                    };
                  @endphp
                  <span class="badge {{ $priorityClass }}">{{ ucfirst($req->priority ?? 'Normal') }}</span>
                </td>
                <td class="text-center">
                  @php
                    $reqStatus = strtolower($req->status ?? 'pending');
                    $statusClass = match($reqStatus) {
                      'approved' => 'bg-success',
                      'denied' => 'bg-danger',
                      'pending' => 'bg-warning text-dark',
                      default => 'bg-secondary',
                    };
                  @endphp
                  <span class="badge {{ $statusClass }}">{{ ucfirst($req->status ?? 'Pending') }}</span>
                </td>
                <td>{{ $req->created_at ?? '—' }}</td>
                @if($status === 'pending')
                  <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                      <button type="button" class="btn atom-btn-outline-success" data-bs-toggle="modal" data-bs-target="#approveModal{{ $req->id }}" title="Approve">
                        <i class="fas fa-check"></i>
                      </button>
                      <button type="button" class="btn atom-btn-outline-danger" data-bs-toggle="modal" data-bs-target="#denyModal{{ $req->id }}" title="Deny">
                        <i class="fas fa-times"></i>
                      </button>
                    </div>

                    {{-- Approve Modal --}}
                    <div class="modal fade" id="approveModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form action="{{ route('acl.review-request', ['id' => $req->id]) }}" method="POST">
                            @csrf
                            <input type="hidden" name="decision" value="approved">
                            <div class="modal-header bg-success text-white">
                              <h5 class="modal-title">Approve Request</h5>
                              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-start">
                              <p>Approve access request from <strong>{{ $req->user_name ?? $req->username }}</strong>?</p>
                              <div class="mb-3">
                                <label for="approve_notes_{{ $req->id }}" class="form-label">Notes <span class="badge bg-secondary ms-1">Optional</span></label>
                                <textarea name="notes" id="approve_notes_{{ $req->id }}" class="form-control" rows="3"></textarea>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn atom-btn-outline-success">
                                <i class="fas fa-check me-1"></i> Approve
                              </button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    {{-- Deny Modal --}}
                    <div class="modal fade" id="denyModal{{ $req->id }}" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form action="{{ route('acl.review-request', ['id' => $req->id]) }}" method="POST">
                            @csrf
                            <input type="hidden" name="decision" value="denied">
                            <div class="modal-header bg-danger text-white">
                              <h5 class="modal-title">Deny Request</h5>
                              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-start">
                              <p>Deny access request from <strong>{{ $req->user_name ?? $req->username }}</strong>?</p>
                              <div class="mb-3">
                                <label for="deny_notes_{{ $req->id }}" class="form-label">Reason for denial <span class="badge bg-danger ms-1">Required</span></label>
                                <textarea name="notes" id="deny_notes_{{ $req->id }}" class="form-control" rows="3"></textarea>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn atom-btn-outline-danger">
                                <i class="fas fa-times me-1"></i> Deny
                              </button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  </td>
                @endif
              </tr>
            @empty
              <tr>
                <td colspan="{{ $status === 'pending' ? 8 : 7 }}" class="text-center text-muted py-4">No {{ $status ?? '' }} access requests found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
