@extends('ahg-theme-b5::layout')

@section('title', __('Add a passkey'))

@section('content')
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header text-center">
          <h4 class="mb-0"><i class="bi bi-key-fill"></i> {{ __('Add a passkey') }}</h4>
        </div>
        <div class="card-body">

          <div id="webauthnError" class="alert alert-danger d-none"></div>
          <div id="webauthnSuccess" class="alert alert-success d-none"></div>

          @if(!request()->isSecure() && request()->getHost() !== 'localhost' && request()->getHost() !== '127.0.0.1')
            <div class="alert alert-warning">
              <i class="bi bi-exclamation-triangle"></i>
              <strong>{{ __('Insecure connection.') }}</strong>
              {{ __('WebAuthn requires HTTPS (except on localhost). Your browser will refuse to enrol a passkey on this URL.') }}
            </div>
          @endif

          <p class="text-muted">
            {{ __('Give this passkey a short label so you can recognise it later, then press Enrol. Your browser will prompt you to use a hardware key, your device biometric (Touch ID, Windows Hello, etc.) or a platform passkey.') }}
          </p>

          <div class="mb-3">
            <label for="passkeyLabel" class="form-label">{{ __('Label') }}</label>
            <input type="text" id="passkeyLabel" class="form-control" maxlength="120"
                   value="{{ __('Passkey') }} {{ now()->format('Y-m-d') }}"
                   placeholder="{{ __('e.g. MacBook Touch ID') }}">
          </div>

          <div class="d-grid gap-2">
            <button type="button" id="enrollBtn" class="btn btn-primary btn-lg">
              <i class="bi bi-shield-plus"></i> {{ __('Enrol passkey') }}
            </button>
            <a href="{{ route('security-clearance.webauthn.list', ['return' => $returnUrl]) }}" class="btn btn-outline-secondary">
              {{ __('Cancel') }}
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
  const btn = document.getElementById('enrollBtn');
  const errBox = document.getElementById('webauthnError');
  const okBox = document.getElementById('webauthnSuccess');
  const labelInput = document.getElementById('passkeyLabel');
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

  btn.addEventListener('click', async function () {
    if (!window.PublicKeyCredential) {
      showError('{{ __("Your browser does not support WebAuthn. Try Chrome, Firefox, Safari or Edge.") }}');
      return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> {{ __("Starting...") }}';

    try {
      // 1. Begin
      const beginRes = await fetch('{{ route("security-clearance.webauthn.register-begin") }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        }
      });
      if (!beginRes.ok) throw new Error('register-begin HTTP ' + beginRes.status);
      const options = await beginRes.json();

      // 2. Convert challenge + user.id + excludeCredentials.id from b64url to ArrayBuffer
      options.challenge = b64urlToBuf(options.challenge);
      options.user.id = b64urlToBuf(options.user.id);
      if (Array.isArray(options.excludeCredentials)) {
        options.excludeCredentials = options.excludeCredentials.map(c => ({
          ...c,
          id: b64urlToBuf(c.id)
        }));
      }

      btn.innerHTML = '<i class="bi bi-shield-check"></i> {{ __("Confirm on your device...") }}';

      // 3. navigator.credentials.create()
      const cred = await navigator.credentials.create({ publicKey: options });
      if (!cred) throw new Error('credentials.create returned null');

      // 4. Serialise the response back to JSON-shape
      const serialised = {
        id: cred.id,
        rawId: bufToB64url(cred.rawId),
        type: cred.type,
        response: {
          clientDataJSON: bufToB64url(cred.response.clientDataJSON),
          attestationObject: bufToB64url(cred.response.attestationObject),
          transports: typeof cred.response.getTransports === 'function' ? cred.response.getTransports() : []
        }
      };

      // 5. Complete
      const completeRes = await fetch('{{ route("security-clearance.webauthn.register-complete") }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          credential: serialised,
          label: labelInput.value.trim()
        })
      });
      const result = await completeRes.json();
      if (!completeRes.ok || !result.ok) {
        throw new Error(result.error || 'register-complete HTTP ' + completeRes.status);
      }

      showSuccess('{{ __("Passkey enrolled. Redirecting...") }}');
      setTimeout(() => { window.location = result.redirect; }, 800);
    } catch (e) {
      showError(e.message || String(e));
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-shield-plus"></i> {{ __("Enrol passkey") }}';
    }
  });
})();
</script>
@endpush
@endsection
