{{-- Two-Factor Authentication Verification - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/twoFactorSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Two-Factor Authentication Required')

@section('content')

<div class="row justify-content-center mt-5">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header bg-danger text-white">
        <h4 class="mb-0">
          <i class="fas fa-shield-alt"></i> {{ __('Two-Factor Authentication Required') }}
        </h4>
      </div>
      <div class="card-body">
        <div class="alert alert-warning">
          <i class="fas fa-exclamation-triangle"></i>
          {{ __('Your clearance level requires two-factor authentication to access classified materials.') }}
        </div>

        @if(isset($clearance) && $clearance)
        <p>
          <strong>{{ __('Your Clearance:') }}</strong>
          <span class="badge" style="background-color: {{ e($clearance->color ?? '#666') }}">
            {{ e($clearance->name ?? '') }}
          </span>
        </p>
        @endif

        <form action="{{ route('acl.verify-2fa') }}" method="post" id="2fa-form">
          @csrf
          <input type="hidden" name="return" value="{{ e($returnUrl ?? '/') }}">

          <div class="mb-4">
            <label class="form-label">{{ __('Enter Verification Code') }}</label>
            <input type="text" name="code" class="form-control form-control-lg text-center"
                   maxlength="6" pattern="\d{6}" placeholder="000000" required autofocus
                   autocomplete="one-time-code" inputmode="numeric"
                   style="font-size: 2rem; letter-spacing: 0.5rem;">
            <div class="form-text">
              Enter the 6-digit code from your authenticator app.
            </div>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="fas fa-check"></i> {{ __('Verify') }}
            </button>
          </div>
        </form>

        <hr>

        <div class="text-center">
          <p class="text-muted small">
            Don't have your authenticator app handy?
          </p>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-send-email">
            <i class="fas fa-envelope"></i> {{ __('Send code via email') }}
          </button>
          <div id="email-status" class="mt-2 small"></div>
        </div>
      </div>
    </div>

    <div class="text-center mt-3">
      <a href="{{ url('/') }}" class="text-muted">Return to Home</a>
    </div>
  </div>
</div>

<script>
document.getElementById('btn-send-email').addEventListener('click', function() {
  var btn = this;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

  fetch('{{ route("acl.verify-2fa") }}', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({ send_email: true })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    document.getElementById('email-status').innerHTML =
      '<span class="text-' + (data.success ? 'success' : 'danger') + '">' + data.message + '</span>';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-envelope"></i> Send code via email';
  })
  .catch(function() {
    document.getElementById('email-status').innerHTML =
      '<span class="text-danger">Failed to send. Use your authenticator app.</span>';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-envelope"></i> Send code via email';
  });
});
</script>

@endsection
