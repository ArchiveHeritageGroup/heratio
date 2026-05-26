@extends('ahg-theme-b5::layout')

@section('title', __('Passkey verification'))

@section('content')
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="bi bi-key-fill"></i> {{ __('Passkey sign-in') }}</h4>
        </div>
        <div class="card-body">
          <div id="webauthnError" class="alert alert-danger d-none"></div>
          <div id="webauthnSuccess" class="alert alert-success d-none"></div>

          <p class="text-muted text-center">
            {{ __('Press the button below, then confirm on your device (hardware key, Touch ID, Windows Hello, etc.).') }}
          </p>

          <div class="d-grid gap-2">
            <button type="button" id="assertBtn" class="btn btn-primary btn-lg">
              <i class="bi bi-shield-lock"></i> {{ __('Sign in with passkey') }}
            </button>
            <a href="{{ route('security-clearance.two-factor', ['return' => $returnUrl]) }}" class="btn btn-link">
              {{ __('Use authenticator-app code instead') }}
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  const btn = document.getElementById('assertBtn');
  const errBox = document.getElementById('webauthnError');
  const okBox = document.getElementById('webauthnSuccess');
  const csrfToken = '{{ csrf_token() }}';

  function showError(msg) {
    errBox.textContent = msg;
    errBox.classList.remove('d-none');
    okBox.classList.add('d-none');
  }

  function showSuccess(msg) {
    okBox.textContent = msg;
    okBox.classList.remove('d-none');
    errBox.classList.add('d-none');
  }

  function b64urlToBuf(b64url) {
    const b64 = b64url.replace(/-/g, '+').replace(/_/g, '/');
    const padded = b64 + '='.repeat((4 - b64.length % 4) % 4);
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes.buffer;
  }

  function bufToB64url(buf) {
    const bytes = new Uint8Array(buf);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  async function startAssertion() {
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> {{ __("Waiting...") }}';

    try {
      const beginRes = await fetch('{{ route("security-clearance.webauthn.assert-begin") }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        }
      });
      if (!beginRes.ok) throw new Error('assert-begin HTTP ' + beginRes.status);
      const options = await beginRes.json();

      options.challenge = b64urlToBuf(options.challenge);
      if (Array.isArray(options.allowCredentials)) {
        options.allowCredentials = options.allowCredentials.map(c => ({
          ...c,
          id: b64urlToBuf(c.id)
        }));
      }

      btn.innerHTML = '<i class="bi bi-shield-check"></i> {{ __("Confirm on your device...") }}';

      const cred = await navigator.credentials.get({ publicKey: options });
      if (!cred) throw new Error('credentials.get returned null');

      const serialised = {
        id: cred.id,
        rawId: bufToB64url(cred.rawId),
        type: cred.type,
        response: {
          clientDataJSON: bufToB64url(cred.response.clientDataJSON),
          authenticatorData: bufToB64url(cred.response.authenticatorData),
          signature: bufToB64url(cred.response.signature),
          userHandle: cred.response.userHandle ? bufToB64url(cred.response.userHandle) : null
        }
      };

      const completeRes = await fetch('{{ route("security-clearance.webauthn.assert-complete") }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ credential: serialised })
      });
      const result = await completeRes.json();
      if (!completeRes.ok || !result.ok) {
        throw new Error(result.error || 'assert-complete HTTP ' + completeRes.status);
      }

      showSuccess('{{ __("Signed in. Redirecting...") }}');
      setTimeout(() => { window.location = result.redirect || '/'; }, 500);
    } catch (e) {
      showError(e.message || String(e));
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-shield-lock"></i> {{ __("Sign in with passkey") }}';
    }
  }

  btn.addEventListener('click', startAssertion);
})();
</script>
@endpush
@endsection
