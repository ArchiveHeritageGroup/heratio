{{-- Access Request Form - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/accessRequestSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Request Access')

@section('content')

<div class="row justify-content-center">
  <div class="col-md-8">
    <h1><i class="fas fa-hand-paper"></i> {{ __('Request Access') }}</h1>

    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Requested Resource') }}</h5>
      </div>
      <div class="card-body">
        <p>
          <strong>{{ __('Title:') }}</strong>
          {{ e($object->title ?? 'Untitled') }}
        </p>
        @if($object->identifier ?? null)
        <p>
          <strong>{{ __('Identifier:') }}</strong>
          {{ e($object->identifier) }}
        </p>
        @endif

        @if($classification ?? null)
        <p>
          <strong>{{ __('Classification:') }}</strong>
          <span class="badge" style="background-color: {{ $classification->color ?? '#666' }}">
            <i class="fas {{ $classification->icon ?? 'fa-lock' }}"></i>
            {{ e($classification->name ?? '') }}
          </span>
        </p>
        @endif
      </div>
    </div>

    @if($userClearance ?? null)
    <div class="alert alert-info">
      <strong>{{ __('Your Current Clearance:') }}</strong>
      <span class="badge" style="background-color: {{ $userClearance->color ?? '#666' }}">
        {{ e($userClearance->name ?? '') }}
      </span>
      (Level {{ $userClearance->level ?? 0 }})
    </div>
    @else
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle"></i>
      {{ __('You do not currently have a security clearance. Your request will need to be approved by a security officer.') }}
    </div>
    @endif

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Access Request Details') }}</h5>
      </div>
      <div class="card-body">
        <form action="{{ route('acl.submit-access-request') }}" method="post">
          @csrf
          <input type="hidden" name="object_id" value="{{ $object->id ?? '' }}">
          @if($classification ?? null)
          <input type="hidden" name="classification_id" value="{{ $classification->classification_id ?? $classification->id ?? '' }}">
          @endif

          <div class="mb-3">
            <label class="form-label">{{ __('Type of Access *') }}</label>
            <select name="request_type" class="form-select" required>
              <option value="view">{{ __('View Only') }}</option>
              <option value="download">{{ __('Download') }}</option>
              <option value="print">{{ __('Print') }}</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">{{ __('Priority') }}</label>
            <select name="priority" class="form-select">
              <option value="normal">{{ __('Normal') }}</option>
              <option value="urgent">{{ __('Urgent') }}</option>
              <option value="immediate">{{ __('Immediate') }}</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">{{ __('Duration Required (hours)') }}</label>
            <select name="duration_hours" class="form-select">
              <option value="1">1 hour</option>
              <option value="4">4 hours</option>
              <option value="8">8 hours (1 day)</option>
              <option value="24" selected>24 hours</option>
              <option value="72">72 hours (3 days)</option>
              <option value="168">168 hours (1 week)</option>
              <option value="720">720 hours (30 days)</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">{{ __('Justification *') }}</label>
            <textarea name="justification" class="form-control" rows="5" required
                      minlength="20" placeholder="{{ __('Please provide a detailed justification for your access request. Include the purpose, project name, and any relevant authorization.') }}"></textarea>
            <div class="form-text">Minimum 20 characters required.</div>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="fas fa-paper-plane"></i> {{ __('Submit Request') }}
            </button>
            <a href="{{ route('security.my-requests') }}" class="btn btn-outline-secondary">
              View My Requests
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
