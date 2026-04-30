{{-- Access Denied - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/deniedSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Access Denied')

@section('content')

<div class="container">
  <div class="row justify-content-center mt-5">
    <div class="col-md-6">
      <div class="card border-danger">
        <div class="card-header bg-danger text-white">
          <h4 class="mb-0"><i class="fas fa-ban"></i> Access Denied</h4>
        </div>
        <div class="card-body text-center">
          <i class="fas fa-lock fa-5x text-danger mb-4"></i>

          <h5>{{ __('You do not have permission to access this resource.') }}</h5>

          @if($classification ?? null)
          <p class="mt-3">
            <strong>{{ __('Required Classification:') }}</strong>
            <span class="badge" style="background-color: {{ $classification->color ?? '#666' }}">
              {{ e($classification->name ?? '') }}
            </span>
          </p>
          @endif

          @if(!empty($accessResult['reason']))
          <p class="alert alert-warning mt-3">
            {{ e($accessResult['reason']) }}
          </p>
          @endif

          <hr>

          <div class="d-grid gap-2">
            @if(!empty($accessResult['requires_2fa']))
            <a href="{{ route('acl.two-factor', ['return' => urlencode(request()->getRequestUri())]) }}" class="btn btn-warning">
              <i class="fas fa-shield-alt"></i> {{ __('Verify 2FA') }}
            </a>
            @endif

            @if(!empty($accessResult['requires_request']))
            <a href="{{ route('acl.access-request', ['id' => $resource->id ?? 0]) }}" class="btn btn-primary">
              <i class="fas fa-key"></i> {{ __('Request Access') }}
            </a>
            @endif

            <a href="javascript:history.back()" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i> {{ __('Go Back') }}
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
