@extends('ahg-theme-b5::layout')

@section('title', __('Passkeys'))

@section('content')
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h4 class="mb-0"><i class="bi bi-key-fill"></i> {{ __('Passkeys (WebAuthn / FIDO2)') }}</h4>
          <a href="{{ route('security-clearance.webauthn.add', ['return' => $returnUrl]) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> {{ __('Add passkey') }}
          </a>
        </div>
        <div class="card-body">

          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif

          <p class="text-muted">
            {{ __('Passkeys let you sign in with a hardware key (e.g. YubiKey) or your device biometric (Touch ID, Windows Hello). They sit alongside your authenticator-app code — either factor can satisfy two-factor sign-in.') }}
          </p>

          @if(empty($credentials))
            <div class="alert alert-info text-center">
              {{ __('You have no enrolled passkeys yet.') }}
            </div>
          @else
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>{{ __('Label') }}</th>
                    <th>{{ __('Transports') }}</th>
                    <th>{{ __('Enrolled') }}</th>
                    <th>{{ __('Last used') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                  </tr>
                </thead>
                <tbody>
                @foreach($credentials as $cred)
                  <tr>
                    <td>
                      <i class="bi bi-shield-lock"></i>
                      <strong>{{ $cred->label ?: __('Passkey') }}</strong>
                      @if($cred->aaguid)
                        <div class="small text-muted">AAGUID: <code>{{ $cred->aaguid }}</code></div>
                      @endif
                    </td>
                    <td>
                      @php
                        $transports = $cred->transports ? json_decode($cred->transports, true) : [];
                      @endphp
                      @if(!empty($transports))
                        @foreach($transports as $t)
                          <span class="badge bg-secondary">{{ $t }}</span>
                        @endforeach
                      @else
                        <span class="text-muted small">{{ __('unknown') }}</span>
                      @endif
                    </td>
                    <td><span class="small">{{ \Carbon\Carbon::parse($cred->created_at)->diffForHumans() }}</span></td>
                    <td><span class="small">{{ $cred->last_used_at ? \Carbon\Carbon::parse($cred->last_used_at)->diffForHumans() : __('never') }}</span></td>
                    <td class="text-end">
                      <form method="POST" action="{{ route('security-clearance.webauthn.delete', $cred->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('{{ __('Delete this passkey? You will no longer be able to sign in with it.') }}')">
                          <i class="bi bi-trash"></i> {{ __('Delete') }}
                        </button>
                      </form>
                    </td>
                  </tr>
                @endforeach
                </tbody>
              </table>
            </div>
          @endif

          <hr>
          <a href="{{ $returnUrl }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
