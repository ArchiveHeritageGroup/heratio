@extends('theme::layouts.1col')

@section('title', 'Access Request Approvers')

@section('content')
<div class="container py-4">

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Admin</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.groups') }}">ACL</a></li>
      <li class="breadcrumb-item active" aria-current="page">Access Request Approvers</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user-check me-2"></i> Access Request Approvers</h2>
    <a href="{{ route('acl.groups') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i> Back to ACL
    </a>
  </div>

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ session('error') }}
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

  <div class="row">

    {{-- Left column: Current Approvers --}}
    <div class="col-lg-8 mb-4">
      <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-list me-2"></i> Current Approvers</h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover mb-0">
              <thead>
                <tr style="background:var(--ahg-primary);color:#fff">
                  <th>User</th>
                  <th>Clearance</th>
                  <th>Can Approve</th>
                  <th>Email Notify</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($approvers as $approver)
                  <tr>
                    <td>
                      <strong>{{ $approver->display_name ?? $approver->username }}</strong>
                      <br><small class="text-muted">{{ $approver->email ?? '' }}</small>
                    </td>
                    <td>
                      @if($approver->clearance_name)
                        <span class="badge" style="background-color:{{ $approver->clearance_color ?? '#6c757d' }};">
                          {{ $approver->clearance_name }}
                        </span>
                        <small class="text-muted ms-1">({{ $approver->clearance_code ?? '' }})</small>
                      @else
                        <span class="text-muted">None</span>
                      @endif
                    </td>
                    <td>
                      Level {{ $approver->min_classification_level }} &ndash; {{ $approver->max_classification_level }}
                    </td>
                    <td>
                      @if($approver->email_notifications)
                        <i class="fas fa-check text-success" title="Email notifications enabled"></i>
                      @else
                        <i class="fas fa-times text-danger" title="Email notifications disabled"></i>
                      @endif
                    </td>
                    <td>
                      <form action="{{ route('acl.remove-approver', ['id' => $approver->id]) }}" method="POST"
                            onsubmit="return confirm('Are you sure you want to remove this approver?');">
                        @csrf
                        <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="Remove approver">
                          <i class="fas fa-user-minus me-1"></i> Remove
                        </button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">No approvers configured.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- Right column: Add Approver --}}
    <div class="col-lg-4 mb-4">
      <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i> Add Approver</h5>
        </div>
        <div class="card-body">
          <form action="{{ route('acl.add-approver') }}" method="POST">
            @csrf

            <div class="mb-3">
              <label for="approver_user_id" class="form-label">User <span class="badge bg-danger ms-1">Required</span></label>
              <select name="user_id" id="approver_user_id" class="form-select" required>
                <option value="">-- Select User --</option>
                @foreach($availableUsers as $user)
                  <option value="{{ $user->id }}">{{ $user->display_name ?? $user->username }} ({{ $user->username }})</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="min_classification_level" class="form-label">Min Classification Level <span class="badge bg-danger ms-1">Required</span></label>
              <select name="min_classification_level" id="min_classification_level" class="form-select" required>
                @foreach($classifications as $cls)
                  <option value="{{ $cls->level }}">{{ $cls->name }} (Level {{ $cls->level }})</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="max_classification_level" class="form-label">Max Classification Level <span class="badge bg-danger ms-1">Required</span></label>
              <select name="max_classification_level" id="max_classification_level" class="form-select" required>
                @foreach($classifications as $cls)
                  <option value="{{ $cls->level }}" @if($loop->last) selected @endif>{{ $cls->name }} (Level {{ $cls->level }})</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3 form-check">
              <input type="hidden" name="email_notifications" value="0">
              <input type="checkbox" class="form-check-input" name="email_notifications" id="email_notifications" value="1" checked>
              <label class="form-check-label" for="email_notifications">Email Notifications</label>
            </div>

            <button type="submit" class="btn atom-btn-outline-success w-100">
              <i class="fas fa-user-plus me-1"></i> Add Approver
            </button>
          </form>
        </div>
      </div>
    </div>

  </div>

</div>
@endsection
