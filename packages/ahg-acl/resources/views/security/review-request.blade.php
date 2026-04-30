{{-- Review Access Request - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/reviewRequestSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Review Access Request')

@section('content')

<h1><i class="fas fa-search"></i> Review Access Request #{{ $accessRequest->id ?? '' }}</h1>

<div class="mb-3">
  <a href="{{ route('security.pending-requests') }}" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left"></i> {{ __('Back to Pending Requests') }}
  </a>
</div>

<div class="row">
  <div class="col-md-8">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Request Details') }}</h5>
      </div>
      <div class="card-body">
        <dl class="row">
          <dt class="col-sm-3">Requester</dt>
          <dd class="col-sm-9">
            <strong>{{ e($accessRequest->username ?? '') }}</strong><br>
            <small>{{ e($accessRequest->email ?? '') }}</small>
          </dd>

          <dt class="col-sm-3">Resource</dt>
          <dd class="col-sm-9">
            {{ e($accessRequest->object_title ?? 'N/A') }}
          </dd>

          <dt class="col-sm-3">Request Type</dt>
          <dd class="col-sm-9">
            {{ ucfirst(str_replace('_', ' ', $accessRequest->request_type ?? '')) }}
          </dd>

          <dt class="col-sm-3">Priority</dt>
          <dd class="col-sm-9">
            <span class="badge bg-{{ ($accessRequest->priority ?? '') === 'immediate' ? 'danger' : (($accessRequest->priority ?? '') === 'urgent' ? 'warning' : 'secondary') }}">
              {{ ucfirst($accessRequest->priority ?? 'normal') }}
            </span>
          </dd>

          <dt class="col-sm-3">Duration Requested</dt>
          <dd class="col-sm-9">{{ $accessRequest->duration_hours ?? '' }} hours</dd>

          <dt class="col-sm-3">Submitted</dt>
          <dd class="col-sm-9">{{ ($accessRequest->created_at ?? null) ? date('Y-m-d H:i:s', strtotime($accessRequest->created_at)) : '' }}</dd>
        </dl>

        <hr>

        <h6>{{ __('Justification') }}</h6>
        <div class="bg-light p-3 rounded">
          {!! nl2br(e($accessRequest->justification ?? $accessRequest->reason ?? '')) !!}
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0">{{ __('Review Decision') }}</h5>
      </div>
      <div class="card-body">
        <form method="post" action="{{ route('acl.review-request', ['id' => $accessRequest->id ?? 0]) }}">
          @csrf
          <div class="mb-3">
            <label class="form-label">{{ __('Access Duration (hours)') }}</label>
            <input type="number" name="duration_hours" class="form-control"
                   value="{{ $accessRequest->duration_hours ?? 24 }}" min="1" max="720">
          </div>

          <div class="mb-3">
            <label class="form-label">{{ __('Review Notes') }}</label>
            <textarea name="notes" class="form-control" rows="3"></textarea>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" name="decision" value="approved" class="btn btn-success btn-lg">
              <i class="fas fa-check"></i> {{ __('Approve') }}
            </button>
            <button type="submit" name="decision" value="denied" class="btn btn-danger">
              <i class="fas fa-times"></i> {{ __('Deny') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    {{-- User Clearance Info --}}
    <div class="card mt-3">
      <div class="card-header">
        <h6 class="mb-0">{{ __('User Clearance Info') }}</h6>
      </div>
      <div class="card-body">
        <a href="{{ route('acl.view-classification', ['id' => $accessRequest->user_id ?? 0]) }}" class="btn btn-sm btn-outline-primary">
          View User Clearance
        </a>
        <a href="{{ route('acl.user-security', ['id' => $accessRequest->user_id ?? 0]) }}" class="btn btn-sm btn-outline-secondary">
          View Access History
        </a>
      </div>
    </div>
  </div>
</div>

@endsection
