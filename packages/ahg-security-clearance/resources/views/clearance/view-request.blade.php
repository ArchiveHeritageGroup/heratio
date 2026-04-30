@extends('ahg-theme-b5::layout')

@section('title', 'Review Access Request')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.access-requests') }}">Access Requests</a></li>
    <li class="breadcrumb-item active">Review</li>
  </ol></nav>

  <h1><i class="fas fa-clipboard-check"></i> {{ __('Review Access Request') }}</h1>

<div class="row">
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">{{ __('Request Details') }}</h5></div>
        <div class="card-body">
          <table class="table table-borderless">
            <tr><th width="35%">{{ __('Requester') }}</th><td>{{ e($accessRequest->username ?? '') }}</td></tr>
            <tr><th>{{ __('Email') }}</th><td>{{ e($accessRequest->email ?? '') }}</td></tr>
            <tr><th>{{ __('Object') }}</th><td>{{ e($accessRequest->object_title ?? 'Object #' . ($accessRequest->object_id ?? '')) }}</td></tr>
            <tr><th>{{ __('Request Type') }}</th><td>{{ ucfirst(str_replace('_', ' ', $accessRequest->request_type ?? '')) }}</td></tr>
            <tr><th>{{ __('Priority') }}</th><td>
              <span class="badge bg-{{ ($accessRequest->priority ?? '') === 'immediate' ? 'danger' : (($accessRequest->priority ?? '') === 'urgent' ? 'warning' : 'secondary') }}">
                {{ ucfirst($accessRequest->priority ?? 'normal') }}
              </span>
            </td></tr>
            <tr><th>{{ __('Submitted') }}</th><td>{{ $accessRequest->created_at ?? '' }}</td></tr>
            <tr><th>{{ __('Status') }}</th><td>
              <span class="badge bg-{{ ($accessRequest->status ?? '') === 'approved' ? 'success' : (($accessRequest->status ?? '') === 'denied' ? 'danger' : 'warning') }}">
                {{ ucfirst($accessRequest->status ?? 'pending') }}
              </span>
            </td></tr>
          </table>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">{{ __('Justification') }}</h5></div>
        <div class="card-body">
          <p>{{ e($accessRequest->justification ?? 'No justification provided.') }}</p>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      {{-- User Clearance Info --}}
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">{{ __('User Clearance') }}</h5></div>
        <div class="card-body">
          @if(!empty($accessRequest->clearance_name))
            <p>Current: <span class="badge" style="background-color: {{ $accessRequest->clearance_color ?? '#666' }}">{{ e($accessRequest->clearance_name) }}</span></p>
          @else
            <p class="text-muted">No clearance assigned.</p>
          @endif
          @if(!empty($accessRequest->object_classification_name))
            <p>Required: <span class="badge" style="background-color: {{ $accessRequest->object_classification_color ?? '#666' }}">{{ e($accessRequest->object_classification_name) }}</span></p>
          @endif
        </div>
      </div>

      {{-- Decision --}}
      @if(($accessRequest->status ?? 'pending') === 'pending')
      <div class="card mb-3">
        <div class="card-header bg-success text-white"><h5 class="mb-0">{{ __('Approve') }}</h5></div>
        <div class="card-body">
          <form method="POST" action="{{ route('security-clearance.approve-request', ['id' => $accessRequest->id]) }}">
            @csrf
            <div class="mb-3">
              <label class="form-label">{{ __('Duration') }}</label>
              <select name="duration" class="form-select">
                <option value="24h">24 hours</option>
                <option value="7d">7 days</option>
                <option value="30d" selected>30 days</option>
                <option value="90d">90 days</option>
                <option value="permanent">{{ __('Permanent') }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Notes') }}</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('Approve') }}</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header bg-danger text-white"><h5 class="mb-0">{{ __('Deny') }}</h5></div>
        <div class="card-body">
          <form method="POST" action="{{ route('security-clearance.deny-request', ['id' => $accessRequest->id]) }}">
            @csrf
            <div class="mb-3">
              <label class="form-label">{{ __('Reason for Denial') }}</label>
              <textarea name="reason" class="form-control" rows="2" required></textarea>
            </div>
            <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> {{ __('Deny') }}</button>
          </form>
        </div>
      </div>
      @endif
    </div>
  </div>
</div>
@endsection
