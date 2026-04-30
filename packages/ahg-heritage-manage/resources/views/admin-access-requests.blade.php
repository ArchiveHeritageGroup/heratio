@extends('theme::layouts.1col')
@section('title', 'Access Requests')
@section('body-class', 'admin heritage')

@php
$requests = $requestData['requests'] ?? [];
$total = $requestData['total'] ?? 0;
$page = $requestData['page'] ?? 1;
$pages = $requestData['pages'] ?? 1;
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-heritage-manage::partials._admin-sidebar')

    <div class="card border-0 shadow-sm mt-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h6 class="mb-0">{{ __('Statistics') }}</h6>
      </div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span>{{ __('Pending') }}</span>
          <span class="badge bg-warning">{{ $stats['pending'] ?? 0 }}</span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span>{{ __('This Month') }}</span>
          <span class="badge bg-info">{{ $stats['this_month'] ?? 0 }}</span>
        </div>
        <div class="d-flex justify-content-between">
          <span>{{ __('Approval Rate') }}</span>
          <span class="badge bg-success">{{ $stats['approval_rate'] ?? 0 }}%</span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-key me-2"></i>Access Requests</h1>
    </div>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <!-- Pending Requests -->
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">{{ __('Pending Requests') }}</h5>
        <span class="badge bg-warning text-dark">{{ number_format($total) }} pending</span>
      </div>
      <div class="card-body p-0">
        @if(empty($requests))
        <div class="text-center text-muted py-5">
          <i class="fas fa-check-circle fs-1 mb-3 d-block"></i>
          <p>No pending access requests.</p>
        </div>
        @else
        <div class="list-group list-group-flush">
          @foreach($requests as $request)
          <div class="list-group-item">
            <div class="row align-items-center">
              <div class="col-md-5">
                <h6 class="mb-1">{{ $request->object_title ?? 'Untitled' }}</h6>
                <small class="text-muted">
                  Requested by <strong>{{ $request->username }}</strong>
                  ({{ $request->email }})
                </small>
              </div>
              <div class="col-md-3">
                <small class="text-muted">{{ __('Purpose:') }}</small><br>
                {{ $request->purpose_name ?? $request->purpose_text ?? 'Not specified' }}
              </div>
              <div class="col-md-2">
                <small class="text-muted">{{ date('Y-m-d H:i', strtotime($request->created_at)) }}</small>
              </div>
              <div class="col-md-2 text-end">
                <button class="btn btn-sm atom-btn-outline-success me-1" data-bs-toggle="modal" data-bs-target="#approveModal"
                        data-request-id="{{ $request->id }}">
                  <i class="fas fa-check"></i>
                </button>
                <button class="btn btn-sm atom-btn-outline-danger" data-bs-toggle="modal" data-bs-target="#denyModal"
                        data-request-id="{{ $request->id }}">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>
            @if($request->justification)
            <div class="mt-2">
              <small><strong>{{ __('Justification:') }}</strong> {{ $request->justification }}</small>
            </div>
            @endif
          </div>
          @endforeach
        </div>
        @endif
      </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post">
            @csrf
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title">{{ __('Approve Request') }}</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="request_id" id="approve_request_id">
              <input type="hidden" name="decision" value="approve">
              <div class="mb-3">
                <label for="valid_until" class="form-label">Access Valid Until <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="date" class="form-control" name="valid_until" id="valid_until"
                       value="{{ date('Y-m-d', strtotime('+90 days')) }}">
              </div>
              <div class="mb-3">
                <label for="approve_notes" class="form-label">Notes <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <textarea class="form-control" name="notes" id="approve_notes" rows="2"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
              <button type="submit" class="btn atom-btn-outline-success">{{ __('Approve Access') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Deny Modal -->
    <div class="modal fade" id="denyModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post">
            @csrf
            <div class="modal-header bg-danger text-white">
              <h5 class="modal-title">{{ __('Deny Request') }}</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="request_id" id="deny_request_id">
              <input type="hidden" name="decision" value="deny">
              <div class="mb-3">
                <label for="deny_notes" class="form-label">Reason for Denial <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                <textarea class="form-control" name="notes" id="deny_notes" rows="3" required></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
              <button type="submit" class="btn atom-btn-outline-danger">{{ __('Deny Request') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
    document.getElementById('approveModal')?.addEventListener('show.bs.modal', function(event) {
      document.getElementById('approve_request_id').value = event.relatedTarget.getAttribute('data-request-id');
    });
    document.getElementById('denyModal')?.addEventListener('show.bs.modal', function(event) {
      document.getElementById('deny_request_id').value = event.relatedTarget.getAttribute('data-request-id');
    });
    </script>
  </div>
</div>
@endsection
