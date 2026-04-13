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

  {{-- ═══════════════════════════════════════════════════════════════
       PSIS history audit log (additive clone from
       ahgAccessRequestPlugin/.../historySuccess.php)
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="mt-5">
    <div class="multiline-header d-flex flex-column mb-3">
      <h3 class="mb-0"><i class="fas fa-history me-2"></i> Access Request History</h3>
      <span class="small text-muted">Full audit trail of all access request actions</span>
    </div>

    {{-- Stats --}}
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <h2 class="text-primary mb-0">{{ number_format($stats['total_requests'] ?? 0) }}</h2>
            <small class="text-muted">Total Requests</small>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <h2 class="text-warning mb-0">{{ number_format($stats['pending'] ?? 0) }}</h2>
            <small class="text-muted">Pending</small>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <h2 class="text-success mb-0">{{ number_format($stats['approved'] ?? 0) }}</h2>
            <small class="text-muted">Approved</small>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <h2 class="text-danger mb-0">{{ number_format($stats['denied'] ?? 0) }}</h2>
            <small class="text-muted">Denied</small>
          </div>
        </div>
      </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
      <div class="card-body">
        <form method="get" action="{{ route('acl.access-requests') }}" class="row g-2">
          <input type="hidden" name="status" value="{{ $status }}">
          <div class="col-md-4">
            <label class="form-label small mb-1">Filter by request status</label>
            <select name="status_filter" class="form-select form-select-sm">
              <option value="">All statuses</option>
              @foreach (['pending', 'approved', 'denied', 'cancelled', 'expired'] as $s)
                <option value="{{ $s }}" {{ ($statusFilter ?? '') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-1">Filter by action</label>
            <select name="action_filter" class="form-select form-select-sm">
              <option value="">All actions</option>
              @foreach (['created', 'approved', 'denied', 'cancelled', 'expired', 'reviewed'] as $a)
                <option value="{{ $a }}" {{ ($actionFilter ?? '') === $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-sm btn-primary me-2"><i class="fas fa-filter me-1"></i> Apply</button>
            <a href="{{ route('acl.access-requests', ['status' => $status]) }}" class="btn btn-sm atom-btn-white">Reset</a>
          </div>
        </form>
      </div>
    </div>

    {{-- Log table --}}
    <div class="card">
      <div class="card-header bg-primary text-white">
        <i class="fas fa-list me-2"></i> Audit Log
        <span class="badge bg-light text-dark ms-2">{{ number_format($total ?? 0) }} entries</span>
      </div>
      @if (empty($logs) || $logs->isEmpty())
        <div class="card-body text-center text-muted py-5">
          <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
          No audit log entries found.
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>When</th>
                <th>Action</th>
                <th>Request #</th>
                <th>Status</th>
                <th>Actor</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($logs as $log)
                @php
                  $actionColors = ['created' => 'info', 'approved' => 'success', 'denied' => 'danger', 'cancelled' => 'secondary', 'expired' => 'warning'];
                  $color = $actionColors[$log->action] ?? 'secondary';
                  $reqStatusColor = match(strtolower($log->request_status ?? '')) {
                    'approved' => 'success',
                    'denied' => 'danger',
                    'pending' => 'warning',
                    default => 'secondary',
                  };
                @endphp
                <tr>
                  <td class="text-nowrap"><small>{{ $log->created_at ? \Illuminate\Support\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') : '—' }}</small></td>
                  <td><span class="badge bg-{{ $color }}">{{ ucfirst($log->action) }}</span></td>
                  <td>#{{ $log->request_id }}</td>
                  <td>
                    @if ($log->request_status)
                      <span class="badge bg-{{ $reqStatusColor }}">{{ ucfirst($log->request_status) }}</span>
                    @else
                      —
                    @endif
                  </td>
                  <td><small>{{ e($log->actor_username ?: '—') }}</small></td>
                  <td><small class="text-muted">{{ e($log->details ?: '') }}</small></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @if (($totalPages ?? 1) > 1)
          <div class="card-footer">
            <nav>
              <ul class="pagination pagination-sm justify-content-center mb-0">
                @php
                  $qs = [
                    'status' => $status,
                    'status_filter' => $statusFilter,
                    'action_filter' => $actionFilter,
                  ];
                @endphp
                @if ($page > 1)
                  <li class="page-item"><a class="page-link" href="{{ route('acl.access-requests', array_merge($qs, ['page' => $page - 1])) }}">&laquo;</a></li>
                @endif
                <li class="page-item active"><span class="page-link">Page {{ $page }} of {{ $totalPages }}</span></li>
                @if ($page < $totalPages)
                  <li class="page-item"><a class="page-link" href="{{ route('acl.access-requests', array_merge($qs, ['page' => $page + 1])) }}">&raquo;</a></li>
                @endif
              </ul>
            </nav>
          </div>
        @endif
      @endif
    </div>
  </div>

</div>
@endsection
