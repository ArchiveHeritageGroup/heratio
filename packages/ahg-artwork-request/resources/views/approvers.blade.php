{{-- Artwork placement requests - approver settings (#1459, admin only) --}}
@extends('theme::layouts.1col')

@section('title', 'Artwork request approvers')

@section('content')
<div class="container-fluid py-4">

  @include('ahg-artwork-request::_flash')

  <h1 class="h3 mb-3"><i class="fas fa-user-check me-2"></i>Artwork request approvers</h1>
  <p class="text-muted">Who is notified when a request comes in, and who may decide it. Leave the department blank
    for the general queue - those people see every request.</p>

  @if(!empty($formErrors))
    <div class="alert alert-danger"><ul class="mb-0">@foreach($formErrors as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <div class="card mb-4">
    <div class="card-header">Add an approver</div>
    <div class="card-body">
      <form method="post" action="{{ route('artwork-request.approvers') }}" class="row g-3 align-items-end">
        @csrf
        <input type="hidden" name="form_action" value="add">
        <div class="col-md-4"><label class="form-label" for="user_ref">User</label>
          <input type="text" class="form-control" id="user_ref" name="user_ref" list="userList" placeholder="username or email" required>
          <datalist id="userList">
            @foreach($candidates as $u)<option value="{{ $u->username }}">{{ $u->email }}</option>@endforeach
          </datalist></div>
        <div class="col-md-4"><label class="form-label" for="department">Department (optional)</label>
          <input type="text" class="form-control" id="department" name="department" list="deptList" placeholder="general queue if blank">
          <datalist id="deptList">@foreach($departments as $d)<option value="{{ $d }}">@endforeach</datalist></div>
        <div class="col-md-2 form-check mb-2 ms-2">
          <input type="checkbox" class="form-check-input" id="email_notifications" name="email_notifications" value="1" checked>
          <label class="form-check-label" for="email_notifications">Email them</label></div>
        <div class="col-md-1 d-grid"><button type="submit" class="btn btn-primary">Add</button></div>
      </form>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table align-middle">
      <thead class="table-light"><tr><th>User</th><th>Department</th><th>Email</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse($approvers as $a)
          <tr class="{{ $a->active ? '' : 'text-muted' }}">
            <td>{{ $a->username }}<span class="small text-muted d-block">{{ $a->email }}</span></td>
            <td>{{ $a->department ?: 'General queue' }}</td>
            <td>
              <form method="post" action="{{ route('artwork-request.approvers') }}" class="d-inline">
                @csrf
                <input type="hidden" name="form_action" value="notifications">
                <input type="hidden" name="approver_id" value="{{ $a->id }}">
                <input type="hidden" name="on" value="{{ $a->email_notifications ? 0 : 1 }}">
                <button type="submit" class="btn btn-sm btn-{{ $a->email_notifications ? 'success' : 'outline-secondary' }}">
                  {{ $a->email_notifications ? 'On' : 'Off' }}</button>
              </form>
            </td>
            <td><span class="badge bg-{{ $a->active ? 'success' : 'secondary' }}">{{ $a->active ? 'active' : 'disabled' }}</span></td>
            <td class="text-end">
              <form method="post" action="{{ route('artwork-request.approvers') }}" class="d-inline">
                @csrf
                <input type="hidden" name="form_action" value="{{ $a->active ? 'deactivate' : 'activate' }}">
                <input type="hidden" name="approver_id" value="{{ $a->id }}">
                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $a->active ? 'Disable' : 'Enable' }}</button>
              </form>
              <form method="post" action="{{ route('artwork-request.approvers') }}" class="d-inline"
                    onsubmit="return confirm('Remove this approver entirely?');">
                @csrf
                <input type="hidden" name="form_action" value="remove">
                <input type="hidden" name="approver_id" value="{{ $a->id }}">
                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-muted">No approvers yet. Add one above, or the general queue is empty and nobody is notified.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
