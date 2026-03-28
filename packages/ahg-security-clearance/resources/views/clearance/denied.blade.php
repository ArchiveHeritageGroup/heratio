@extends('ahg-theme-b5::layout')

@section('title', 'Access Denied')

@section('content')
<div class="container-fluid mt-3">
  <div class="row justify-content-center mt-5">
    <div class="col-md-6">
      <div class="card border-danger">
        <div class="card-header bg-danger text-white text-center">
          <h3 class="mb-0"><i class="fas fa-lock"></i> Access Denied</h3>
        </div>
        <div class="card-body text-center">
          <p class="lead">You do not have sufficient security clearance to access this resource.</p>

          @if(!empty($reason))
            <div class="alert alert-warning">
              <strong>Reason:</strong> {{ e($reason) }}
            </div>
          @endif

          @if(!empty($requiredLevel))
            <p><strong>Required clearance:</strong>
              <span class="badge" style="background-color: {{ $requiredColor ?? '#666' }}; font-size: 1em;">{{ e($requiredLevel) }}</span>
            </p>
          @endif

          @if(!empty($currentLevel))
            <p><strong>Your clearance:</strong>
              <span class="badge" style="background-color: {{ $currentColor ?? '#999' }}; font-size: 1em;">{{ e($currentLevel) }}</span>
            </p>
          @endif

          <hr>

          <div class="d-grid gap-2 d-md-flex justify-content-md-center">
            @auth
              <a href="{{ route('security-clearance.my-requests') }}" class="btn btn-primary">
                <i class="fas fa-file-alt"></i> Request Access
              </a>
            @endauth
            <a href="{{ url()->previous() }}" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i> Go Back
            </a>
            <a href="/" class="btn btn-outline-secondary">
              <i class="fas fa-home"></i> Home
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
