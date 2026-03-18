{{-- Pending Access Requests - Migrated from AtoM: ahgAccessRequestPlugin/templates/pendingSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Pending Access Requests')

@section('content')
<div class="container-fluid py-4">

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
      <li class="breadcrumb-item active">Access Requests</li>
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
    <div class="col-md-3">
      <div class="card bg-warning text-dark">
        <div class="card-body text-center">
          <h2 class="mb-0">{{ $stats['pending'] }}</h2>
          <small>Pending</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-success text-white">
        <div class="card-body text-center">
          <h2 class="mb-0">{{ $stats['approved_today'] }}</h2>
          <small>Approved Today</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-danger text-white">
        <div class="card-body text-center">
          <h2 class="mb-0">{{ $stats['denied_today'] }}</h2>
          <small>Denied Today</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card bg-info text-white">
        <div class="card-body text-center">
          <h2 class="mb-0">{{ $stats['total_this_month'] }}</h2>
          <small>This Month</small>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Pending Access Requests</h5>
    </div>
    <div class="card-body p-0">
      @if($requests->isEmpty())
        <div class="p-4 text-center text-muted">
          <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
          <p>No pending requests. All caught up!</p>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>User</th>
                <th>Current &rarr; Requested</th>
                <th>Urgency</th>
                <th>Reason</th>
                <th>Submitted</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($requests as $req)
                @php
                  $urgency = strtolower($req->priority ?? $req->urgency ?? 'normal');
                  $rowClass = $urgency === 'critical' ? 'table-danger' : ($urgency === 'high' ? 'table-warning' : '');
                @endphp
                <tr class="{{ $rowClass }}">
                  <td>
                    <strong>{{ e($req->user_name ?? $req->username ?? '-') }}</strong>
                    <br><small class="text-muted">{{ e($req->email ?? '') }}</small>
                  </td>
                  <td>
                    <span class="badge bg-secondary">{{ $req->current_classification ?? 'None' }}</span>
                    <i class="fas fa-arrow-right mx-1"></i>
                    <span class="badge bg-primary">{{ e($req->classification_name ?? '-') }}</span>
                  </td>
                  <td>
                    @php
                      $urgencyColor = match($urgency) {
                        'critical' => 'danger', 'high' => 'warning text-dark', 'normal' => 'info', default => 'secondary'
                      };
                    @endphp
                    <span class="badge bg-{{ $urgencyColor }}">{{ ucfirst($urgency) }}</span>
                  </td>
                  <td>
                    <span title="{{ e($req->justification ?? $req->reason ?? '') }}">
                      {{ \Illuminate\Support\Str::limit($req->justification ?? $req->reason ?? '', 50) }}
                    </span>
                  </td>
                  <td>
                    {{ $req->created_at ? \Carbon\Carbon::parse($req->created_at)->format('M j, Y') : '-' }}
                    <br><small class="text-muted">{{ $req->created_at ? \Carbon\Carbon::parse($req->created_at)->format('H:i') : '' }}</small>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#approveModal{{ $req->id }}" title="Approve">
                        <i class="fas fa-check"></i>
                      </button>
                      <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#denyModal{{ $req->id }}" title="Deny">
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
                              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-start">
                              <p>Approve access request from <strong>{{ e($req->user_name ?? $req->username ?? '') }}</strong>?</p>
                              <div class="mb-3">
                                <label for="approve_notes_{{ $req->id }}" class="form-label">Notes (optional)</label>
                                <textarea name="notes" id="approve_notes_{{ $req->id }}" class="form-control" rows="3"></textarea>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i> Approve</button>
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
                              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-start">
                              <p>Deny access request from <strong>{{ e($req->user_name ?? $req->username ?? '') }}</strong>?</p>
                              <div class="mb-3">
                                <label for="deny_notes_{{ $req->id }}" class="form-label">Reason for denial</label>
                                <textarea name="notes" id="deny_notes_{{ $req->id }}" class="form-control" rows="3"></textarea>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn btn-danger"><i class="fas fa-times me-1"></i> Deny</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

</div>
@endsection
