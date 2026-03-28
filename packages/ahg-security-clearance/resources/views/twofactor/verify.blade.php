@extends('ahg-theme-b5::layout')

@section('title', 'Two-Factor Verification')

@section('content')
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="fas fa-mobile-alt"></i> Two-Factor Authentication</h4>
        </div>
        <div class="card-body">
          @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
          @endif

          <p class="text-muted text-center">Enter the 6-digit code from your authenticator app.</p>

          @if($clearance)
            <div class="alert alert-info text-center">
              Clearance: <span class="badge" style="background-color: {{ $clearance->color ?? '#666' }}">{{ e($clearance->classification_name ?? '') }}</span>
            </div>
          @endif

          <form method="POST" action="{{ route('security-clearance.verify-2fa') }}">
            @csrf
            <input type="hidden" name="return" value="{{ $returnUrl }}">

            <div class="mb-3">
              <label class="form-label">Verification Code</label>
              <input type="text" name="code" class="form-control form-control-lg text-center" maxlength="6"
                     pattern="[0-9]{6}" placeholder="000000" autofocus required
                     style="letter-spacing: 0.5em; font-size: 1.5em;">
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg">Verify</button>
            </div>
          </form>

          <hr>

          <div class="text-center">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="sendEmailBtn">
              <i class="fas fa-envelope"></i> Send code via email
            </button>
            <div id="emailResult" class="mt-2 small"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.getElementById('sendEmailBtn')?.addEventListener('click', function() {
  const btn = this;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

  fetch('{{ route("security-clearance.send-email-code") }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}'
    }
  })
  .then(r => r.json())
  .then(data => {
    document.getElementById('emailResult').innerHTML =
      '<span class="text-' + (data.success ? 'success' : 'danger') + '">' + data.message + '</span>';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-envelope"></i> Send code via email';
  })
  .catch(() => {
    document.getElementById('emailResult').innerHTML = '<span class="text-danger">Request failed.</span>';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-envelope"></i> Send code via email';
  });
});
</script>
@endpush
@endsection
